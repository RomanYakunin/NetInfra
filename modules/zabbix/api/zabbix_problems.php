<?php
// modules/zabbix/api/zabbix_problems.php
// Активные проблемы из Zabbix для страницы «Панель».
// Узлы Zabbix сопоставляются с нашим оборудованием по IP: так из аварии
// можно сразу перейти в досье устройства.
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
        'error'    => 'Интеграция с Zabbix выключена. Укажите адрес и учётную запись в config/zabbix.php и включите enabled.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Фильтры страницы
$severities = [];
if (isset($_GET['severities']) && $_GET['severities'] !== '') {
    foreach (explode(',', $_GET['severities']) as $s) {
        if ($s !== '' && is_numeric($s)) $severities[] = (int)$s;
    }
}
$ack = null;
if (isset($_GET['unack']) && $_GET['unack'] === '1') {
    $ack = false;   // только неподтверждённые
}

$problems = $client->getProblemsWithHosts([
    'severities'   => $severities,
    'acknowledged' => $ack,
    'search'       => $_GET['search'] ?? '',
]);

if ($problems === null) {
    echo json_encode([
        'success' => false,
        'error'   => $client->getError() ?: 'Не удалось получить данные из Zabbix',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- Сопоставление с нашим оборудованием ----------
// Узел Zabbix ищем по IP его интерфейса: имена хостов у нас и в
// мониторинге совпадают не всегда, а адрес — надёжный признак.
$equipmentByHostId = [];
try {
    $hosts = $client->getHosts();
    $ipByHostId = [];
    foreach ((array)$hosts as $h) {
        foreach (($h['interfaces'] ?? []) as $iface) {
            if (!empty($iface['ip'])) { $ipByHostId[$h['hostid']] = $iface['ip']; break; }
        }
    }

    if ($ipByHostId) {
        $uniqueIps = array_values(array_unique($ipByHostId));
        $ph = implode(',', array_fill(0, count($uniqueIps), '?'));
        $stmt = $pdo->prepare("
            SELECT e.id, e.hostname, e.id_node, ip.ip_address, n.KY_number
            FROM equipment e
            JOIN ip_address ip ON e.ip_address = ip.Id
            LEFT JOIN nodes n ON e.id_node = n.id_node
            WHERE ip.ip_address IN ($ph)
        ");
        $stmt->execute($uniqueIps);

        $byIp = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byIp[$row['ip_address']] = $row;
        }
        foreach ($ipByHostId as $hostid => $ip) {
            if (isset($byIp[$ip])) $equipmentByHostId[$hostid] = $byIp[$ip];
        }
    }
} catch (PDOException $e) {
    // Без сопоставления страница всё равно полезна — показываем проблемы как есть
    $equipmentByHostId = [];
}

foreach ($problems as &$p) {
    $eq = $equipmentByHostId[$p['hostid']] ?? null;
    $p['equipment_id'] = $eq['id'] ?? null;
    $p['ky_number']    = $eq['KY_number'] ?? null;
    $p['node_id']      = $eq['id_node'] ?? null;
    $p['ip_address']   = $eq['ip_address'] ?? null;
}
unset($p);

// Сводка по уровням важности для плиток наверху
$counts = array_fill(0, 6, 0);
foreach ($problems as $p) {
    if (isset($counts[$p['severity']])) $counts[$p['severity']]++;
}

echo json_encode([
    'success'   => true,
    'data'      => $problems,
    'total'     => count($problems),
    'counts'    => $counts,
    'matched'   => count(array_filter($problems, function ($p) { return $p['equipment_id'] !== null; })),
    'version'   => $client->getVersion(),
    'fetched_at'=> date('c'),
], JSON_UNESCAPED_UNICODE);
