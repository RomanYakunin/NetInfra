<?php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$service  = $_GET['service'] ?? '';
$vendorId = (int)($_GET['vendor_id'] ?? 0);
if (!$service || !$vendorId) {
    echo json_encode(['error' => 'service и vendor_id обязательны']);
    exit;
}

$stmt = $pdo->prepare("SELECT instruction_text, image_path FROM service_instructions WHERE service_name = ? AND vendor_id = ?");
$stmt->execute([$service, $vendorId]);
$row = $stmt->fetch();
if ($row) {
    echo json_encode(['text' => $row['instruction_text'], 'image' => $row['image_path']]);
} else {
    echo json_encode(['text' => null, 'image' => null]);
}