<?php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$equipId  = (int)($_POST['equip_id'] ?? 0);
$newSize  = (int)($_POST['unit_size'] ?? 1);

if (!$equipId || $newSize < 1) {
    echo json_encode(['error' => 'Неверные параметры']);
    exit;
}

// Проверка, что новый размер не выходит за пределы стойки и не накладывается на другие устройства
$stmt = $pdo->prepare("SELECT id_rack, unit_position FROM equipment WHERE id = ?");
$stmt->execute([$equipId]);
$eq = $stmt->fetch();
if (!$eq || !$eq['id_rack']) {
    echo json_encode(['error' => 'Оборудование не в стойке']);
    exit;
}

$rackId = $eq['id_rack'];

// Проверяем, что от unit_position до unit_position + newSize - 1 нет других устройств (кроме себя)
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE id_rack = ? AND id != ? AND unit_position BETWEEN ? AND ?");
$startUnit = $eq['unit_position'];
$endUnit   = $startUnit + $newSize - 1;
$stmtCheck->execute([$rackId, $equipId, $startUnit, $endUnit]);
if ($stmtCheck->fetchColumn() > 0) {
    echo json_encode(['error' => 'Невозможно изменить размер: юниты заняты']);
    exit;
}

// Проверяем высоту стойки
$stmtRack = $pdo->prepare("SELECT rh.height FROM racks r JOIN rack_heights rh ON r.height_id = rh.id WHERE r.id_rack = ?");
$stmtRack->execute([$rackId]);
$cabHeight = $stmtRack->fetchColumn();
if ($cabHeight && $endUnit > $cabHeight) {
    echo json_encode(['error' => 'Размер превышает высоту стойки']);
    exit;
}

$pdo->prepare("UPDATE equipment SET unit_size = ? WHERE id = ?")->execute([$newSize, $equipId]);

echo json_encode(['success' => true]);