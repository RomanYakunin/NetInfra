<?php
// api/ping_worker.php – фоновый пинг без блокировки основного потока
set_time_limit(0);
ignore_user_abort(true);
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$ips = $input['ips'] ?? [];

if (empty($ips)) {
    echo json_encode(['error' => 'No IPs provided']);
    exit;
}

function ping($ip, $timeout = 200) { // 200 мс таймаут
    $os = strtoupper(substr(PHP_OS, 0, 3));
    if ($os === 'WIN') {
        $cmd = "ping -n 1 -w " . $timeout . " " . escapeshellarg($ip);
    } else {
        $cmd = "ping -c 1 -W " . round($timeout / 1000, 1) . " " . escapeshellarg($ip);
    }
    exec($cmd, $output, $return_var);
    return $return_var === 0;
}

$results = [];
foreach ($ips as $ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $results[] = ['ip' => $ip, 'alive' => false, 'time' => 'Invalid IP'];
        continue;
    }
    $start = microtime(true);
    $alive = ping($ip);
    $time = $alive ? round((microtime(true) - $start) * 1000, 2) . ' мс' : '-';
    $results[] = [
        'ip' => $ip,
        'alive' => $alive,
        'time' => $time
    ];

    // Обновляем статус оборудования в БД
    $stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $ipId = $stmt->fetchColumn();
    if ($ipId) {
        $newStatus = $alive ? 'active' : 'inactive';
        $pdo->prepare("UPDATE equipment SET status = ? WHERE ip_address = ?")
            ->execute([$newStatus, $ipId]);
    }
}

// Обновлённая статистика
$activeDevices = (int)$pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'active'")->fetchColumn();
$inactiveDevices = (int)$pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'inactive'")->fetchColumn();
$activeNodes = (int)$pdo->query("SELECT COUNT(*) FROM nodes WHERE status = 'active'")->fetchColumn();
$inactiveNodes = (int)$pdo->query("SELECT COUNT(*) FROM nodes WHERE status = 'inactive'")->fetchColumn();

echo json_encode([
    'success' => true,
    'results' => $results,
    'stats' => [
        'activeNodes' => $activeNodes,
        'inactiveNodes' => $inactiveNodes,
        'activeDevices' => $activeDevices,
        'inactiveDevices' => $inactiveDevices
    ]
]);