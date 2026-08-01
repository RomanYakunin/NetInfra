<?php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$ip = trim($_POST['name'] ?? '');
if ($ip === '') {
    echo json_encode(['error' => 'IP-адрес обязателен']);
    exit;
}
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(['error' => 'Некорректный формат IP']);
    exit;
}

// Проверка дубликата
$stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
$stmt->execute([$ip]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'Такой IP-адрес уже существует']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO ip_address (ip_address) VALUES (?)");
$stmt->execute([$ip]);
$newId = $pdo->lastInsertId();

echo json_encode(['success' => true, 'id' => $newId, 'name' => $ip]);