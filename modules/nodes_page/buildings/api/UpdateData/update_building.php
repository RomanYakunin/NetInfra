<?php
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id   = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');

if (!$id || $name === '') {
    echo json_encode(['error' => 'ID и название обязательны']);
    exit;
}

try {
    // Получаем старое имя до обновления
    $stmt = $pdo->prepare("SELECT Name_Building FROM Buildings WHERE Id = ?");
    $stmt->execute([$id]);
    $oldName = $stmt->fetchColumn();

    // Проверка дубликата
    $dup = $pdo->prepare("SELECT COUNT(*) FROM Buildings WHERE Name_Building = ? AND Id != ?");
    $dup->execute([$name, $id]);
    if ($dup->fetchColumn() > 0) {
        echo json_encode(['error' => 'duplicate', 'message' => "Здание «{$name}» уже существует"]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE Buildings SET Name_Building = ? WHERE Id = ?");
    $stmt->execute([$name, $id]);
    echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'old_name' => $oldName]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'db', 'message' => 'Ошибка обновления: ' . $e->getMessage()]);
}