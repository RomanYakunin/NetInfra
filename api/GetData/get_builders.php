<?php
// api/GetData/get_builders.php – возвращает список зданий
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $buildings = $pdo->query("SELECT Id AS id, Name_Builder AS name FROM Builders ORDER BY Name_Builder")->fetchAll();
} catch (PDOException $e) {
    $buildings = [];
}
echo json_encode($buildings);