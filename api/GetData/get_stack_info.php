<?php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$groupId = (int)($_GET['group_id'] ?? 0);
if (!$groupId) {
    echo json_encode(['error' => 'group_id обязателен']);
    exit;
}

$stmt = $pdo->prepare("SELECT hostname, ip_address_id, annotation FROM equipment_groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if ($group) {
    echo json_encode([
        'success' => true,
        'hostname'      => $group['hostname'],
        'ip_address_id' => $group['ip_address_id'],
        'annotation'    => $group['annotation']
    ]);
} else {
    echo json_encode(['error' => 'Стек не найден']);
}