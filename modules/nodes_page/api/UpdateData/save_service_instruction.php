<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

// Проверка прав администратора
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещён']);
    exit;
}

$service  = $_POST['service'] ?? '';
$vendorId = (int)($_POST['vendor_id'] ?? 0);
$text     = $_POST['instruction_text'] ?? '';
$image    = null;

// Загрузка изображения
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/instructions/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('instr_') . '.' . $ext;
    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename);
    $image = $uploadDir . $filename;
}

try {
    $stmt = $pdo->prepare("INSERT INTO service_instructions (service_name, vendor_id, instruction_text, image_path)
                           VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE instruction_text = VALUES(instruction_text), image_path = VALUES(image_path)");
    $stmt->execute([$service, $vendorId, $text, $image]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}