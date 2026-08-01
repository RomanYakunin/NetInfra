<?php
// api/AddData/add_warehouse.php – добавляет новый склад
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$buildingId = $_POST['building_id'] ?? null;
$name = trim($_POST['name'] ?? '');

if (empty($buildingId) || empty($name)) {
    echo json_encode(['error' => 'Здание и помещение обязательны']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO warehouses (building, name) VALUES (?, ?)");
    $stmt->execute([$buildingId, $name]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка добавления: ' . $e->getMessage()]);
}