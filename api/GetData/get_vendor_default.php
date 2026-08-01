<?php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$vendorId = (int)($_GET['vendor_id'] ?? 0);
$service  = $_GET['service'] ?? 'local_admin';

if (!$vendorId) {
    echo json_encode(['error' => 'vendor_id обязателен']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT default_login, default_password FROM vendor_defaults WHERE vendor_id = ? AND service_name = ?");
    $stmt->execute([$vendorId, $service]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'login'    => $row['default_login'],
            'password' => $row['default_password']
        ]);
    } else {
        echo json_encode(['login' => null, 'password' => null]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}