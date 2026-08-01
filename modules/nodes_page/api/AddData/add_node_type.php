<?php
// api/AddData/add_node_type.php – добавляет новый тип узла
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    echo json_encode(['error' => 'empty', 'message' => 'Название обязательно']);
    exit;
}

try {
    $check = $pdo->prepare("SELECT COUNT(*) FROM node_types WHERE name_node_type = ?");
    $check->execute([$name]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['error' => 'duplicate', 'message' => 'Такой тип уже существует']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO node_types (name_node_type) VALUES (?)");
    $stmt->execute([$name]);
    $newId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['error' => 'duplicate', 'message' => 'Такой тип уже существует']);
    } else {
        echo json_encode(['error' => 'db', 'message' => 'Ошибка: ' . $e->getMessage()]);
    }
}