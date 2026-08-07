<?php
// Тестовые данные для проверки поиска дубликатов.
require_once dirname(__DIR__) . '/config/db.php';
$act = $_GET['act'] ?? '';
$serial = 'DUPTEST-SN-0001';

if ($act === 'create') {
    $pdo->prepare("DELETE FROM phones WHERE serial_number = ?")->execute([$serial]);
    $pdo->prepare("INSERT INTO phones (serial_number, mac_address, phone_number, user_fio, status)
                   VALUES (?, 'dead.beef.0001', '77777', 'Тестовый Пользователь', 'в эксплуатации')")
        ->execute([$serial]);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'тестовый телефон создан';
    exit;
}

if ($act === 'info') {
    header('Content-Type: application/json; charset=utf-8');
    $st = $pdo->prepare("SELECT id, serial_number, mac_address, phone_number FROM phones WHERE serial_number = ?");
    $st->execute([$serial]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    echo json_encode([
        'id'     => (int)($r['id'] ?? 0),
        'serial' => $r['serial_number'] ?? '',
        'mac'    => $r['mac_address'] ?? '',
        'number' => $r['phone_number'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($act === 'equip') {
    header('Content-Type: application/json; charset=utf-8');
    $r = $pdo->query("
        SELECT e.hostname, e.mac_address, ip.ip_address
        FROM equipment e
        LEFT JOIN ip_address ip ON e.ip_address = ip.Id
        WHERE e.mac_address IS NOT NULL AND e.mac_address <> ''
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: [];
    echo json_encode([
        'hostname' => $r['hostname'] ?? '',
        'mac'      => $r['mac_address'] ?? '',
        'ip'       => $r['ip_address'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($act === 'drop') {
    header('Content-Type: text/plain; charset=utf-8');
    $pdo->prepare("DELETE FROM phones WHERE serial_number = ?")->execute([$serial]);
    echo 'тестовый телефон удалён';
    exit;
}

echo 'act=create|info|equip|drop';
