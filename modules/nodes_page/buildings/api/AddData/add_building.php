<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 6) . '/includes/acl.php';
requireAdmin();
// api/AddData/add_builder.php – добавление нового здания
require_once dirname(__FILE__, 6) . '/config/db.php';
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
    // Проверяем, нет ли уже здания с таким именем
    $check = $pdo->prepare("SELECT COUNT(*) FROM Buildings WHERE Name_Building = ?");
    $check->execute([$name]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['error' => 'duplicate', 'message' => 'Такое здание уже есть']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO Buildings (Name_Building) VALUES (?)");
    $stmt->execute([$name]);
    $newId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'db', 'message' => 'Ошибка добавления: ' . $e->getMessage()]);
}