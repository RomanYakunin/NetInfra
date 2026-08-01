<?php
// api/GetData/get_building_item.php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['error' => 'ID не указан']);
    exit;
}

try {
    $building = $pdo->query("SELECT Id, Name_Building FROM Buildings WHERE Id = " . (int)$id)->fetch();
    if (!$building) {
        echo json_encode(['error' => 'Здание не найдено']);
        exit;
    }
    echo json_encode($building);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}