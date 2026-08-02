<?php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$equipId = (int)($_GET['equipment_id'] ?? 0);
if (!$equipId) {
    echo json_encode(['error' => 'Не указан ID оборудования']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT e.id_rack, e.unit_position, e.group_id, e.device_type_id,
           dt.name AS device_type_name
    FROM equipment e
    LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
    WHERE e.id = ?
");
$stmt->execute([$equipId]);
$eq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eq || !$eq['id_rack']) {
    echo json_encode(['error' => 'У этого оборудования Шкаф пока не заведён в БД или его нет совсем']);
    exit;
}

// ... остальной код без изменений (запросы rack, units, racks)

$rackId = $eq['id_rack'];
$nodeId = null;

// Определяем узел, в котором находится оборудование (для получения всех стоек узла)
$stmtNode = $pdo->prepare("SELECT id_node FROM equipment WHERE id = ?");
$stmtNode->execute([$equipId]);
$nodeId = $stmtNode->fetchColumn();

// Информация о текущей стойке
$stmtRack = $pdo->prepare("
    SELECT r.name AS rack_name, rm.height_u AS rack_height
    FROM racks r
    LEFT JOIN rack_models rm ON r.model_id = rm.id
    WHERE r.id_rack = ?
");
$stmtRack->execute([$rackId]);
$rack = $stmtRack->fetch(PDO::FETCH_ASSOC);
if (!$rack) {
    echo json_encode(['error' => 'Стойка не найдена']);
    exit;
}

// Все устройства в этой стойке с их типом и стеком
$stmtUnits = $pdo->prepare("
    SELECT e.id, e.hostname, e.unit_position, e.status, ip.ip_address,
           dt.name AS device_type_name,
           e.group_id,
           eg.hostname AS stack_hostname
    FROM equipment e
    LEFT JOIN ip_address ip ON e.ip_address = ip.Id
    LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
    LEFT JOIN equipment_groups eg ON e.group_id = eg.id
    WHERE e.id_rack = ? AND e.unit_position IS NOT NULL
    ORDER BY e.unit_position ASC
");
$stmtUnits->execute([$rackId]);
$units = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);

// Список всех стоек в том же узле (для вкладок)
$racks = [];
if ($nodeId) {
    $stmtCabs = $pdo->prepare("
        SELECT DISTINCT r.id_rack, r.name AS rack_name
        FROM racks r
        JOIN equipment e ON e.id_rack = r.id_rack
        WHERE e.id_node = ?
    ");
    $stmtCabs->execute([$nodeId]);
    $racks = $stmtCabs->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode([
    'rack_name'   => $rack['rack_name'] ?? 'Стойка',
    'rack_height' => (int)$rack['rack_height'],
    'units'          => $units,
    'racks'       => $racks
]);