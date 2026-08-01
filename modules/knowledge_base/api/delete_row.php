<?php
require_once dirname(__FILE__, 4) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$table = $_POST['table'] ?? '';
$id = $_POST['id'] ?? '';
$pk = $_POST['pk'] ?? 'id';

if (!$table || !$id) {
    echo json_encode(['error' => 'Параметры обязательны']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
$stmt->execute([$id]);
echo json_encode(['success' => true]);