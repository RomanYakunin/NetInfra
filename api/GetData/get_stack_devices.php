<?php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$hostname = $_GET['hostname'] ?? '';
$ipId     = (int)($_GET['ip_address_id'] ?? 0);

if ($hostname === '' || $ipId === 0) {
    echo json_encode(['success' => false, 'error' => 'hostname и ip_address_id обязательны']);
    exit;
}

// Ищем группу по hostname и ip_address_id
$stmt = $pdo->prepare("SELECT id FROM equipment_groups WHERE hostname = ? AND ip_address_id = ?");
$stmt->execute([$hostname, $ipId]);
$groupId = $stmt->fetchColumn();

if (!$groupId) {
    echo json_encode(['success' => true, 'devices' => []]);
    exit;
}

// Получаем устройства стека с сортировкой по слоту
$stmt = $pdo->prepare("
    SELECT e.id, e.Slot, dm.name AS model_name, e.serial_number, e.mac_address
    FROM equipment e
    LEFT JOIN device_models dm ON e.model_id = dm.id
    WHERE e.group_id = ?
    ORDER BY e.Slot ASC
");
$stmt->execute([$groupId]);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'devices' => $devices]);