<?php
// api/AddData/move_equipment.php – перемещение оборудования на склад
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$equipmentId = $_POST['equipment_id'] ?? 0;
$targetType  = $_POST['target_type'] ?? '';
$warehouseId = $_POST['warehouse_id'] ?? null;

if (!$equipmentId) {
    echo json_encode(['error' => 'Не указан ID оборудования']);
    exit;
}

if ($targetType !== 'warehouse' || !$warehouseId) {
    echo json_encode(['error' => 'Неверные параметры']);
    exit;
}

try {
    // Проверяем существование склада
    $warehouseExists = $pdo->prepare("SELECT id FROM warehouses WHERE id = ?");
    $warehouseExists->execute([$warehouseId]);
    if (!$warehouseExists->fetch()) {
        echo json_encode(['error' => 'Склад не найден']);
        exit;
    }

    // Обновляем оборудование
    $stmt = $pdo->prepare("UPDATE equipment SET id_node = NULL, location_type = 'warehouse', warehouse_id = ? WHERE id = ?");
    $stmt->execute([$warehouseId, $equipmentId]);

    // Обновляем счётчик на складе
    $pdo->prepare("UPDATE warehouses SET device_count = (SELECT COUNT(*) FROM equipment WHERE warehouse_id = ?) WHERE id = ?")
        ->execute([$warehouseId, $warehouseId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка перемещения: ' . $e->getMessage()]);
}