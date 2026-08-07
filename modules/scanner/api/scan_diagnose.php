<?php
/**
 * modules/scanner/api/scan_diagnose.php  (маршрут scan_diagnose)
 *
 * Отвечает на вопрос «почему приложение не видит мой принтер».
 * Показывает всё, что сервер знает об устройствах: расширение COM,
 * службу WIA, найденные WIA-сканеры, наличие NAPS2 и TWAIN-источники,
 * установленные принтеры и имя машины.
 *
 * Нужен именно на сервере: PHP видит устройства той машины, где
 * работает NetInfra, а не той, где открыт браузер.
 */
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
require_once dirname(__FILE__, 2) . '/ScannerWia.php';
require_once dirname(__FILE__, 2) . '/ScannerNaps2.php';
header('Content-Type: application/json; charset=utf-8');

/** Короткий запуск PowerShell для сведений, которых нет в PHP. */
function diagPowerShell($script, $timeout = 15)
{
    if (!function_exists('proc_open')) return null;
    $cmd = 'powershell -NoProfile -NonInteractive -Command "' . str_replace('"', '\"', $script) . '"';
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $p = @proc_open($cmd, $desc, $pipes);
    if (!is_resource($p)) return null;

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = '';
    $deadline = microtime(true) + $timeout;
    while (true) {
        $st = proc_get_status($p);
        $out .= (string)stream_get_contents($pipes[1]);
        if (!$st['running']) break;
        if (microtime(true) > $deadline) { proc_terminate($p, 9); break; }
        usleep(100000);
    }
    fclose($pipes[1]); fclose($pipes[2]); proc_close($p);

    if ($out !== '' && !mb_check_encoding($out, 'UTF-8')) {
        $out = mb_convert_encoding($out, 'UTF-8', 'CP866,Windows-1251');
    }
    return trim($out);
}

$result = [
    'server_host' => php_uname('n'),
    'php_sapi'    => PHP_SAPI,
    'com'         => [
        'loaded' => extension_loaded('com_dotnet'),
        'reason' => ScannerWia::available() ? '' : ScannerWia::unavailableReason(),
    ],
    'wia'   => ['devices' => [], 'error' => ''],
    'naps2' => [
        'path'      => ScannerNaps2::consolePath(),
        'available' => ScannerNaps2::available(),
        'reason'    => ScannerNaps2::available() ? '' : ScannerNaps2::unavailableReason(),
        'devices'   => [],
        'error'     => '',
    ],
];

// ---------- WIA ----------
if (ScannerWia::available()) {
    $wia = new ScannerWia();
    $devices = $wia->devices();
    if ($devices === null) $result['wia']['error'] = $wia->getError();
    else                   $result['wia']['devices'] = $devices;
}

// ---------- TWAIN через NAPS2 ----------
if (ScannerNaps2::available()) {
    $naps = new ScannerNaps2();
    $devices = $naps->devices('twain');
    if ($devices === null) $result['naps2']['error'] = $naps->getError();
    else                   $result['naps2']['devices'] = $devices;
}

// ---------- Что видит сама Windows ----------
$result['windows'] = [
    'wia_service' => diagPowerShell(
        "(Get-Service stisvc -ErrorAction SilentlyContinue).Status"),
    'printers' => array_values(array_filter(array_map('trim', preg_split('/\R/',
        (string)diagPowerShell(
            "Get-CimInstance Win32_Printer -ErrorAction SilentlyContinue | " .
            "ForEach-Object { \$_.Name + ' | ' + \$_.DriverName + ' | ' + \$_.PortName }")
    )), 'strlen')),
    'image_devices' => array_values(array_filter(array_map('trim', preg_split('/\R/',
        (string)diagPowerShell(
            "Get-PnpDevice -Class Image -ErrorAction SilentlyContinue | " .
            "ForEach-Object { \$_.Status + ' | ' + \$_.FriendlyName }")
    )), 'strlen')),
    'twain_sources' => array_values(array_filter(array_map('trim', preg_split('/\R/',
        (string)diagPowerShell(
            "Get-ChildItem \$env:WINDIR\\twain_32 -Recurse -Include *.ds -ErrorAction SilentlyContinue | " .
            "ForEach-Object { \$_.Name }")
    )), 'strlen')),
];

// ---------- Итог понятным языком ----------
$wiaCount   = count($result['wia']['devices']);
$twainCount = count($result['naps2']['devices']);
$verdict = [];

if (!$result['com']['loaded']) {
    $verdict[] = 'Расширение com_dotnet выключено — WIA недоступно совсем.';
} elseif ($wiaCount === 0) {
    $verdict[] = 'WIA-сканеров нет. Так бывает, когда у МФУ установлен только '
               . 'TWAIN-драйвер: WIA такие устройства не видит в принципе.';
} else {
    $verdict[] = 'Найдено WIA-сканеров: ' . $wiaCount . '.';
}

if (!$result['naps2']['available']) {
    $verdict[] = 'TWAIN не проверялся: нет NAPS2.Console.exe в tools/naps2/.';
} else {
    $verdict[] = 'TWAIN-источников найдено: ' . $twainCount . '.';
}

// PowerShell из-под веб-сервера может быть недоступен: тогда пустой
// список принтеров означает «не смогли посмотреть», а не «их нет».
// Разница принципиальная — иначе вывод отправит искать несуществующую
// проблему.
$psWorks = $result['windows']['wia_service'] !== null
        && $result['windows']['wia_service'] !== '';
$result['windows']['powershell_ok'] = $psWorks;

if (!$psWorks) {
    $verdict[] = 'Список принтеров получить не удалось: PowerShell не отвечает из-под '
               . 'веб-сервера. Сведения ниже — только от PHP.';
} else {
    // Виртуальные принтеры не считаем — они не про сканирование
    $realPrinters = array_values(array_filter($result['windows']['printers'], function ($p) {
        return !preg_match('/Microsoft (Print to PDF|XPS)|OneNote|Fax|PDFCreator/i', $p);
    }));
    $verdict[] = 'Физических принтеров на сервере ' . php_uname('n') . ': ' . count($realPrinters) . '.';
}
$verdict[] = 'Важно: приложение видит устройства только той машины, где оно запущено. '
           . 'Сканер, подключённый к компьютеру пользователя, отсюда не виден.';

$result['verdict'] = $verdict;
$result['can_scan'] = ($wiaCount + $twainCount) > 0;

echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
