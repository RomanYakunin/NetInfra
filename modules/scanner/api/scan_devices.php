<?php
/**
 * modules/scanner/api/scan_devices.php  (маршрут scan_devices)
 *
 * Список сканирующих устройств, видимых машине с NetInfra.
 *
 * Опрашиваются оба движка: WIA через COM и TWAIN через NAPS2.Console.
 * Второй нужен потому, что из PHP TWAIN недоступен напрямую — у него нет
 * ни привязки, ни COM-интерфейса, и МФУ с одним лишь TWAIN-драйвером
 * через WIA не виден вообще.
 */
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAuth();
require_once dirname(__FILE__, 3) . '/scanner/ScannerWia.php';
require_once dirname(__FILE__, 3) . '/scanner/ScannerNaps2.php';
require_once dirname(__FILE__, 3) . '/storage/storage_common.php';
header('Content-Type: application/json; charset=utf-8');

$cfg = storageConfig();
if (empty($cfg['scan_enabled'])) {
    echo json_encode([
        'success'  => false,
        'disabled' => true,
        'error'    => 'Сканирование выключено в настройках хранилища',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$devices  = [];
$problems = [];

// ---------- WIA ----------
if (ScannerWia::available()) {
    $wia = new ScannerWia();
    $found = $wia->devices();
    if ($found === null) {
        $problems[] = 'WIA: ' . $wia->getError();
    } else {
        foreach ($found as $d) {
            $d['backend'] = 'wia';
            $d['driver']  = 'wia';
            $devices[] = $d;
        }
    }
} else {
    $problems[] = 'WIA: ' . ScannerWia::unavailableReason();
}

// ---------- TWAIN через NAPS2 ----------
if (ScannerNaps2::available()) {
    $naps = new ScannerNaps2();
    $found = $naps->devices('twain');
    if ($found === null) {
        $problems[] = 'TWAIN: ' . $naps->getError();
    } else {
        // Одно и то же устройство может отвечать по обоим протоколам —
        // показываем оба варианта, но помечаем протокол
        foreach ($found as $d) $devices[] = $d;
    }
} else {
    $problems[] = 'TWAIN: ' . ScannerNaps2::unavailableReason();
}

// Список пуст — объясняем, куда смотреть, а не просто «ничего не найдено»
$hint = '';
if (!$devices) {
    $hint = 'Устройств не найдено. Приложение видит только те сканеры, что подключены '
          . 'к компьютеру ' . php_uname('n') . ', где работает NetInfra, — сканер на '
          . 'рабочем месте пользователя отсюда не виден. '
          . 'Если МФУ подключено именно к этой машине, проверьте диагностику: '
          . 'у TWAIN-драйверов нет доступа из PHP без утилиты NAPS2 в tools/naps2/.';
}

echo json_encode([
    'success'  => true,
    'devices'  => $devices,
    'problems' => $problems,
    'defaults' => [
        'dpi'    => (int)$cfg['scan_dpi'],
        'format' => $cfg['scan_format'],
        'color'  => $cfg['scan_color'],
    ],
    'hint' => $hint,
], JSON_UNESCAPED_UNICODE);
