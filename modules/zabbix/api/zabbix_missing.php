<?php
/**
 * modules/zabbix/api/zabbix_missing.php  (маршрут zabbix_missing)
 *
 * Сверяет наше оборудование с узлами Zabbix и возвращает то, чего в
 * мониторинге нет. По этому списку на странице «Чек-лист» появляется
 * задача «Подключить Zabbix».
 *
 * Сверяем по двум признакам: IP-адрес интерфейса узла Zabbix и имя
 * хоста. Адрес надёжнее, но у части узлов в Zabbix он задан через DNS,
 * поэтому имя проверяем тоже.
 *
 * Оборудование со склада и демонтированное в расчёт не берём: его
 * мониторить незачем, и в списке оно было бы шумом.
 */
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAuth();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
require_once dirname(__FILE__, 3) . '/zabbix/ZabbixClient.php';
header('Content-Type: application/json; charset=utf-8');

$client = new ZabbixClient();

if (!$client->isEnabled()) {
    echo json_encode([
        'success'  => false,
        'disabled' => true,
        'error'    => 'Интеграция с Zabbix выключена',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$hosts = $client->getHosts();
if ($hosts === null) {
    echo json_encode([
        'success' => false,
        'error'   => $client->getError() ?: 'Не удалось получить список узлов Zabbix',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- Что знает Zabbix ----------
$zbxIps   = [];
$zbxNames = [];
foreach ((array)$hosts as $h) {
    foreach (($h['interfaces'] ?? []) as $iface) {
        if (!empty($iface['ip']))  $zbxIps[$iface['ip']] = true;
        if (!empty($iface['dns'])) $zbxNames[mb_strtolower($iface['dns'])] = true;
    }
    if (!empty($h['host'])) $zbxNames[mb_strtolower($h['host'])] = true;
    if (!empty($h['name'])) $zbxNames[mb_strtolower($h['name'])] = true;
}

// ---------- Что знаем мы ----------
try {
    $stmt = $pdo->query("
        SELECT e.id, e.hostname, e.status, e.serial_number,
               ip.ip_address,
               dt.name AS device_type_name,
               v.name  AS vendor_name,
               dm.name AS model_name,
               n.id_node, n.KY_number,
               b.Name_Building AS building_name,
               l.room
        FROM equipment e
        LEFT JOIN ip_address ip ON e.ip_address = ip.Id
        LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
        LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
        LEFT JOIN device_models dm ON e.model_id = dm.id
        LEFT JOIN nodes n ON e.id_node = n.id_node
        LEFT JOIN locations l ON n.id_location = l.id_location
        LEFT JOIN Buildings b ON l.building = b.Id
        WHERE e.warehouse_id IS NULL
          AND e.id_node IS NOT NULL
        ORDER BY CAST(n.KY_number AS UNSIGNED), e.hostname
    ");
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$missing  = [];
$covered  = 0;
$noIp     = 0;

foreach ($equipment as $eq) {
    $ip   = $eq['ip_address'] ?? '';
    $name = mb_strtolower((string)($eq['hostname'] ?? ''));

    $inZabbix = ($ip !== '' && isset($zbxIps[$ip]))
             || ($name !== '' && isset($zbxNames[$name]));

    if ($inZabbix) { $covered++; continue; }

    // Без адреса и без имени сопоставлять не с чем — отмечаем отдельно,
    // это дефект учёта, а не отсутствие мониторинга
    if ($ip === '' && $name === '') { $noIp++; continue; }

    $missing[] = [
        'id'          => (int)$eq['id'],
        'hostname'    => $eq['hostname'],
        'ip_address'  => $ip ?: null,
        'device_type' => $eq['device_type_name'],
        'model'       => trim(($eq['vendor_name'] ?? '') . ' ' . ($eq['model_name'] ?? '')) ?: null,
        'status'      => $eq['status'],
        'node_id'     => $eq['id_node'] !== null ? (int)$eq['id_node'] : null,
        'ky_number'   => $eq['KY_number'],
        'location'    => trim(implode(', ', array_filter([
            $eq['building_name'],
            $eq['room'] ? 'ком. ' . $eq['room'] : null,
        ]))) ?: null,
    ];
}

// Узлы Zabbix, которых нет у нас, — обратная сторона той же сверки
$ourIps = [];
foreach ($equipment as $eq) {
    if (!empty($eq['ip_address'])) $ourIps[$eq['ip_address']] = true;
}
$unknownHosts = [];
foreach ((array)$hosts as $h) {
    $ip = '';
    foreach (($h['interfaces'] ?? []) as $iface) {
        if (!empty($iface['ip'])) { $ip = $iface['ip']; break; }
    }
    if ($ip !== '' && !isset($ourIps[$ip])) {
        $unknownHosts[] = ['hostid' => $h['hostid'], 'name' => $h['name'] ?? $h['host'], 'ip' => $ip];
    }
}

echo json_encode([
    'success'        => true,
    'missing'        => $missing,
    'missing_count'  => count($missing),
    'covered'        => $covered,
    'without_ip'     => $noIp,
    'zabbix_hosts'   => count((array)$hosts),
    'unknown_hosts'  => $unknownHosts,
    'checked_at'     => date('c'),
], JSON_UNESCAPED_UNICODE);
