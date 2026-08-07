<?php
/**
 * modules/scanner/ScannerNaps2.php
 *
 * Сканирование через TWAIN с помощью консольной утилиты NAPS2.
 *
 * ЗАЧЕМ ОТДЕЛЬНЫЙ ДВИЖОК. Из PHP напрямую доступен только WIA: у него
 * есть COM-интерфейс. TWAIN — это библиотека на C, которой нужен цикл
 * сообщений и дескриптор окна; ни привязки к PHP, ни COM у неё нет.
 * Поэтому МФУ, у которого производитель дал только TWAIN-драйвер, через
 * WIA не виден вообще — сколько ни перечисляй устройства.
 *
 * NAPS2.Console.exe умеет и TWAIN, и WIA, работает без установки и
 * запускается одной командой. Кладётся в tools/naps2/ рядом с проектом:
 * в изолированной сети скачать его на месте не получится, поэтому папку
 * переносят вместе с приложением — так же, как поступили с net-snmp.
 *
 * Если утилиты нет, класс просто сообщает об этом, а приложение
 * продолжает работать на WIA.
 */

class ScannerNaps2
{
    private $error = '';

    public function getError() { return $this->error; }

    /**
     * Путь к NAPS2.Console.exe. Сначала ищем в проекте, потом среди
     * обычных мест установки.
     */
    public static function consolePath()
    {
        $candidates = [
            dirname(__FILE__, 3) . '/tools/naps2/NAPS2.Console.exe',
            dirname(__FILE__, 3) . '/tools/naps2/App/NAPS2.Console.exe',
            'C:/Program Files/NAPS2/NAPS2.Console.exe',
            'C:/Program Files (x86)/NAPS2/NAPS2.Console.exe',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }
        return null;
    }

    public static function available()
    {
        return self::consolePath() !== null && function_exists('proc_open');
    }

    public static function unavailableReason()
    {
        if (!function_exists('proc_open')) {
            return 'На сервере запрещена функция proc_open — запустить утилиту сканирования нельзя.';
        }
        return 'Утилита NAPS2.Console.exe не найдена. Положите портативную сборку NAPS2 '
             . 'в tools/naps2/ — она умеет TWAIN, который из PHP иначе недоступен.';
    }

    /**
     * Запускает утилиту и возвращает [код возврата, stdout, stderr].
     * proc_open, а не exec: нужен отдельный stderr и контроль таймаута —
     * драйвер TWAIN может показать своё окно и подвесить процесс.
     */
    private function run(array $args, $timeout = 120)
    {
        $exe = self::consolePath();
        if ($exe === null) {
            $this->error = self::unavailableReason();
            return null;
        }

        $cmd = '"' . str_replace('/', '\\', $exe) . '"';
        foreach ($args as $a) {
            $cmd .= ' ' . (preg_match('/^-/', $a) ? $a : '"' . str_replace('"', '""', $a) . '"');
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open('cmd /c ' . $cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            $this->error = 'Не удалось запустить NAPS2.Console.exe';
            return null;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $out = '';
        $err = '';
        $deadline = microtime(true) + $timeout;

        while (true) {
            $status = proc_get_status($proc);
            $out .= (string)stream_get_contents($pipes[1]);
            $err .= (string)stream_get_contents($pipes[2]);

            if (!$status['running']) break;

            if (microtime(true) > $deadline) {
                proc_terminate($proc, 9);
                $this->error = 'Сканирование не завершилось за ' . $timeout . ' с. '
                             . 'Возможно, драйвер показал своё окно на рабочем столе сервера.';
                fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
                return null;
            }
            usleep(120000);
        }

        $code = proc_get_status($proc)['exitcode'];
        fclose($pipes[1]); fclose($pipes[2]);
        proc_close($proc);

        // Утилита пишет в кодировке консоли — приводим к UTF-8
        $out = $this->toUtf8($out);
        $err = $this->toUtf8($err);

        return [$code, $out, $err];
    }

    private function toUtf8($s)
    {
        if ($s === '' || mb_check_encoding($s, 'UTF-8')) return $s;
        return mb_convert_encoding($s, 'UTF-8', 'CP866,Windows-1251');
    }

    /**
     * Список TWAIN-источников.
     *
     * @param string $driver twain | wia
     * @return array|null
     */
    public function devices($driver = 'twain')
    {
        $this->error = '';
        if (!self::available()) {
            $this->error = self::unavailableReason();
            return null;
        }

        $res = $this->run(['--driver', $driver, '--listdevices'], 40);
        if ($res === null) return null;

        [$code, $out, $err] = $res;
        if ($code !== 0 && trim($out) === '') {
            $this->error = trim($err) !== ''
                ? 'NAPS2: ' . mb_substr(trim($err), 0, 300)
                : 'NAPS2 завершился с кодом ' . $code;
            return null;
        }

        $devices = [];
        foreach (preg_split('/\R/', $out) as $line) {
            $line = trim($line);
            // Пустые строки и служебные сообщения утилиты пропускаем
            if ($line === '' || preg_match('/^(NAPS2|Copyright|Usage|No devices)/i', $line)) continue;
            $devices[] = [
                'id'          => $line,   // NAPS2 адресует источник по имени
                'name'        => $line,
                'description' => 'TWAIN',
                'driver'      => $driver,
                'backend'     => 'naps2',
            ];
        }
        return $devices;
    }

    /**
     * Сканирует страницу в файл.
     *
     * @param string $deviceName имя источника из devices()
     * @param string $targetPath куда сохранить
     * @param array  $opts dpi, color (color|gray|bw), driver
     */
    public function scanToFile($deviceName, $targetPath, array $opts = [])
    {
        $this->error = '';
        if (!self::available()) {
            $this->error = self::unavailableReason();
            return false;
        }

        $dpi   = max(75, min(1200, (int)($opts['dpi'] ?? 300)));
        $color = strtolower($opts['color'] ?? 'color');
        $driver = strtolower($opts['driver'] ?? 'twain');

        $bitDepth = ['color' => 'color', 'gray' => 'gray', 'bw' => 'bw'][$color] ?? 'color';

        $args = [
            '--driver', $driver,
            '--output', str_replace('/', '\\', $targetPath),
            '--dpi', (string)$dpi,
            '--bitdepth', $bitDepth,
            '--pagesize', 'a4',
            '--force',            // перезаписать, если файл уже есть
            '--noprofile',
        ];
        if ($deviceName !== '') { $args[] = '--device'; $args[] = $deviceName; }

        $res = $this->run($args, 180);
        if ($res === null) return false;

        [$code, $out, $err] = $res;

        if (!is_file($targetPath) || filesize($targetPath) === 0) {
            $text = trim($err) !== '' ? $err : $out;
            $this->error = trim($text) !== ''
                ? 'NAPS2: ' . mb_substr(trim($text), 0, 300)
                : 'Файл не создан, код возврата ' . $code;
            return false;
        }
        return true;
    }
}
