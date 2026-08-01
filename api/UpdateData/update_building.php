<?php
// api/UpdateData/update_builder.php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = $_POST['id'] ?? 0;
$name = trim($_POST['name'] ?? '');

if (!$id || $name === '') {
    echo json_encode(['error' => 'ID и название обязательны']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE Buildings SET Name_Building = ? WHERE Id = ?");
    $stmt->execute([$name, $id]);
    echo json_encode(['success' => true, 'name' => $name]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка обновления: ' . $e->getMessage()]);
}