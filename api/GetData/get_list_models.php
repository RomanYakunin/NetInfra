<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__, 3) . '/config/db.php';

$vendorId = (int)($_GET['vendor_id'] ?? 0);
if (!$vendorId) {
    echo json_encode(['data' => []]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM device_models WHERE Vendor = ? ORDER BY name");
$stmt->execute([$vendorId]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['data' => $data]);