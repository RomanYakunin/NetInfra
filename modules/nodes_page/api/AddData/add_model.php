<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
// api/add_model.php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__, 5) . '/config/db.php';

$vendorId = $_POST['vendor_id'] ?? null;
$modelName = trim($_POST['model_name'] ?? '');

if (!$vendorId || $modelName === '') {
    echo json_encode(['success' => false, 'error' => 'Заполните все поля']);
    exit;
}

// Проверяем, нет ли уже такой модели у этого производителя
$stmt = $pdo->prepare("SELECT id FROM device_models WHERE Vendor = ? AND name = ?");
$stmt->execute([$vendorId, $modelName]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Модель с таким названием уже существует']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO device_models (Vendor, name) VALUES (?, ?)");
$stmt->execute([$vendorId, $modelName]);
$newId = $pdo->lastInsertId();

echo json_encode(['success' => true, 'id' => $newId, 'name' => $modelName]);