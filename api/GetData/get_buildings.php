<?php
// api/GetData/get_buildings.php – возвращает список зданий
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $buildings = $pdo->query("SELECT Id AS id, Name_Building AS name FROM Buildings ORDER BY Name_Building")->fetchAll();
} catch (PDOException $e) {
    $buildings = [];
}
echo json_encode($buildings);