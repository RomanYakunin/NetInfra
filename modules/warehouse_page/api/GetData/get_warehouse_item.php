<?php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');
$id = (int)$_GET['id'];
$stmt = $pdo->prepare("
    SELECT w.id, w.name, w.building, b.Name_Building AS building_name
    FROM warehouses w
    LEFT JOIN Buildings b ON w.building = b.Id
    WHERE w.id = ?
");
$stmt->execute([$id]);
$warehouse = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($warehouse ?: ['error' => 'Склад не найден']);