<?php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Не указан ID']);
    exit;
}

$pdo->prepare("DELETE FROM equipment WHERE id = ?")->execute([$id]);
echo json_encode(['success' => true]);