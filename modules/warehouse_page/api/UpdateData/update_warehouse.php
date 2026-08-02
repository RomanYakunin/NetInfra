<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
header('Content-Type: application/json; charset=utf-8');
$id = (int)$_POST['id'];
$buildingId = (int)$_POST['building_id'];
$name = trim($_POST['name'] ?? '');
if (!$id || $buildingId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
    exit;
}
if ($buildingId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Здание обязательно']);
    exit;
}
try {
    // Проверка дубликата
    $stmt = $pdo->prepare("SELECT id FROM warehouses WHERE building=? AND name=? AND id!=?");
    $stmt->execute([$buildingId, $name, $id]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => "Склад «{$name}» уже существует"]);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE warehouses SET building=?, name=? WHERE id=?");
    $stmt->execute([$buildingId, $name, $id]);

    // Получить building_name для ответа
    $bStmt = $pdo->prepare("SELECT Name_Building FROM Buildings WHERE Id=?");
    $bStmt->execute([$buildingId]);
    $buildingName = $bStmt->fetchColumn();

    echo json_encode(['success' => true, 'new_name' => $name, 'building_name' => $buildingName]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД']);
}