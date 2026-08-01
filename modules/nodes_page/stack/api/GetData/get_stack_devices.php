<?php
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$groupId = (int)($_GET['group_id'] ?? 0);
if (!$groupId) {
    echo json_encode(['success' => false, 'error' => 'group_id required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT e.id, e.Slot, e.serial_number, e.mac_address, e.hostname,
               dm.name AS model_name
        FROM equipment e
        LEFT JOIN device_models dm ON e.model_id = dm.id
        WHERE e.group_id = ?
        ORDER BY e.Slot ASC
    ");
    $stmt->execute([$groupId]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'devices' => $devices]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД']);
}