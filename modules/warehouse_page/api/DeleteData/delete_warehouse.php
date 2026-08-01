<?php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');
$id = (int)$_POST['id'];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE warehouse_id=?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'На складе находится оборудование']);
        exit;
    }
    $pdo->prepare("DELETE FROM warehouses WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД']);
}