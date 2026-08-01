<?php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    echo json_encode(['error' => 'Название обязательно']);
    exit;
}
$stmt = $pdo->prepare("INSERT INTO vendors (name) VALUES (?)");
$stmt->execute([$name]);
echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);