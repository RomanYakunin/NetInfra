<?php
/**
 * modules/scanner/ScannerWia.php
 *
 * Сканирование через WIA (Windows Image Acquisition).
 *
 * ВАЖНО ПРО РАЗМЕЩЕНИЕ. PHP выполняется на сервере, поэтому видит
 * сканеры той машины, где работает NetInfra, а не той, где открыт
 * браузер. Это годится, когда:
 *   — приложение стоит на том же компьютере, за которым сидит оператор;
 *   — либо МФУ установлено как WIA-устройство на машине с NetInfra
 *     (сетевые МФУ так и ставятся драйвером производителя).
 * Если операторы работают с других компьютеров, их локальные сканеры
 * отсюда не видны — для такого случая остаётся приём сканов из сетевой
 * папки, куда МФУ складывает файлы.
 *
 * Расширение com_dotnet входит в поставку OSPanel (php_com_dotnet.dll),
 * его достаточно раскомментировать в php.ini и перезапустить сервер.
 */

class ScannerWia
{
    /** Тип устройства WIA: 1 — сканер, 2 — камера, 3 — видео. */
    const DEVICE_TYPE_SCANNER = 1;

    /** Идентификаторы форматов WIA. */
    const FORMATS = [
        'bmp'  => '{B96B3CAB-0728-11D3-9D7B-0000F81EF32E}',
        'png'  => '{B96B3CAF-0728-11D3-9D7B-0000F81EF32E}',
        'jpeg' => '{B96B3CAE-0728-11D3-9D7B-0000F81EF32E}',
        'tiff' => '{B96B3CB1-0728-11D3-9D7B-0000F81EF32E}',
    ];

    /** Свойства элемента сканирования (WIA Item Properties). */
    const PROP_HORIZONTAL_DPI  = 6147;
    const PROP_VERTICAL_DPI    = 6148;
    const PROP_HORIZONTAL_EXT  = 6151;
    const PROP_VERTICAL_EXT    = 6152;
    const PROP_DATA_TYPE       = 4103;   // 0 — ч/б, 2 — оттенки серого, 3 — цвет
    const PROP_BRIGHTNESS      = 6154;
    const PROP_CONTRAST        = 6155;

    const DATA_TYPE = ['bw' => 0, 'gray' => 2, 'color' => 3];

    private $error = '';

    public function getError() { return $this->error; }

    /** Доступно ли сканирование на этом сервере. */
    public static function available()
    {
        return extension_loaded('com_dotnet') && class_exists('COM');
    }

    /** Почему недоступно — текстом для интерфейса. */
    public static function unavailableReason()
    {
        if (!extension_loaded('com_dotnet')) {
            return 'В php.ini не включено расширение com_dotnet. '
                 . 'Файл php_com_dotnet.dll входит в поставку OSPanel: '
                 . 'раскомментируйте строку «extension = com_dotnet» и перезапустите сервер.';
        }
        if (!class_exists('COM')) {
            return 'Расширение com_dotnet загружено, но класс COM недоступен.';
        }
        return '';
    }

    /**
     * Список сканеров, видимых машине с NetInfra.
     *
     * @return array|null массив ['id' => .., 'name' => .., 'description' => ..]
     */
    public function devices()
    {
        $this->error = '';
        if (!self::available()) {
            $this->error = self::unavailableReason();
            return null;
        }

        try {
            $manager = new COM('WIA.DeviceManager');
            $infos = $manager->DeviceInfos;
            $out = [];

            for ($i = 1; $i <= $infos->Count; $i++) {
                $info = $infos->Item($i);
                if ((int)$info->Type !== self::DEVICE_TYPE_SCANNER) continue;

                $props = [];
                foreach ($info->Properties as $p) {
                    $props[$p->Name] = (string)$p->Value;
                }

                $out[] = [
                    'id'          => (string)$info->DeviceID,
                    'name'        => $props['Name'] ?? ('Сканер ' . $i),
                    'description' => $props['Description'] ?? '',
                    'port'        => $props['Port'] ?? '',
                ];
            }
            return $out;
        } catch (Throwable $e) {
            $this->error = 'Служба WIA недоступна: ' . $e->getMessage();
            return null;
        }
    }

    /**
     * Сканирует страницу и сохраняет её в указанный файл.
     *
     * @param string $deviceId идентификатор из devices(); пустой — первый найденный
     * @param string $targetPath куда сохранить (каталог должен существовать)
     * @param array  $opts dpi, format (jpeg|png|tiff|bmp), color (color|gray|bw)
     * @return bool
     */
    public function scanToFile($deviceId, $targetPath, array $opts = [])
    {
        $this->error = '';
        if (!self::available()) {
            $this->error = self::unavailableReason();
            return false;
        }

        $dpi    = max(75, min(1200, (int)($opts['dpi'] ?? 300)));
        $format = strtolower($opts['format'] ?? 'jpeg');
        $color  = strtolower($opts['color'] ?? 'color');

        if (!isset(self::FORMATS[$format])) $format = 'jpeg';
        $dataType = self::DATA_TYPE[$color] ?? self::DATA_TYPE['color'];

        try {
            $manager = new COM('WIA.DeviceManager');
            $infos = $manager->DeviceInfos;

            $target = null;
            for ($i = 1; $i <= $infos->Count; $i++) {
                $info = $infos->Item($i);
                if ((int)$info->Type !== self::DEVICE_TYPE_SCANNER) continue;
                if ($deviceId === '' || (string)$info->DeviceID === $deviceId) { $target = $info; break; }
            }
            if ($target === null) {
                $this->error = 'Сканер не найден. Проверьте, что он включён и виден машине с NetInfra.';
                return false;
            }

            $device = $target->Connect();
            $item   = $device->Items->Item(1);

            // Размер области зависит от разрешения, поэтому её задаём
            // после DPI — иначе драйвер пересчитает её по-своему
            $this->setProp($item, self::PROP_HORIZONTAL_DPI, $dpi);
            $this->setProp($item, self::PROP_VERTICAL_DPI, $dpi);
            $this->setProp($item, self::PROP_DATA_TYPE, $dataType);
            // A4 при заданном разрешении: 8.27 × 11.69 дюйма
            $this->setProp($item, self::PROP_HORIZONTAL_EXT, (int)round(8.27 * $dpi));
            $this->setProp($item, self::PROP_VERTICAL_EXT,   (int)round(11.69 * $dpi));

            $image = $item->Transfer(self::FORMATS[$format]);

            if (is_file($targetPath)) @unlink($targetPath);   // SaveFile не перезаписывает
            $image->SaveFile($targetPath);

            if (!is_file($targetPath) || filesize($targetPath) === 0) {
                $this->error = 'Сканирование завершилось, но файл не создан';
                return false;
            }
            return true;
        } catch (Throwable $e) {
            $this->error = $this->explain($e->getMessage());
            return false;
        }
    }

    /**
     * Свойства поддерживают не все драйверы: недоступное просто
     * пропускаем, иначе сканирование падало бы на мелочи.
     */
    private function setProp($item, $propId, $value)
    {
        try {
            foreach ($item->Properties as $p) {
                if ((int)$p->PropertyID === $propId) {
                    if (!$p->IsReadOnly) $p->Value = $value;
                    return;
                }
            }
        } catch (Throwable $e) {
            // Драйвер не дал изменить свойство — сканируем с его значением
        }
    }

    /** Коды WIA в тексте ошибки заменяем понятным объяснением. */
    private function explain($message)
    {
        $map = [
            '0x80210006' => 'Сканер занят другой задачей',
            '0x80210064' => 'Сканирование отменено на устройстве',
            '0x80210015' => 'Сканер не найден или выключен',
            '0x80210067' => 'В лотке нет бумаги',
            '0x8021000C' => 'Открыта крышка сканера',
            '0x80070005' => 'Отказано в доступе к устройству. Веб-сервер работает под '
                          . 'своей учётной записью — у неё должен быть доступ к сканеру.',
        ];
        foreach ($map as $code => $text) {
            if (stripos($message, $code) !== false) return $text;
        }
        return 'Ошибка сканирования: ' . $message;
    }
}
