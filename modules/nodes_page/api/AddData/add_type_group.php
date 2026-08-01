<?php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    echo json_encode(['error' => 'Название обязательно']);
    exit;
}

// Проверка дубликата – замените type_name на реальное имя столбца
$stmt = $pdo->prepare("SELECT id FROM Type_group WHERE Type_group = ?");
$stmt->execute([$name]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'Такой тип группы уже существует']);
    exit;
}

// Вставка – замените type_name на реальное имя столбца
$stmt = $pdo->prepare("INSERT INTO Type_group (Type_group) VALUES (?)");
$stmt->execute([$name]);
$newId = $pdo->lastInsertId();

echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);