<?php
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$groupId   = $_POST['group_id'] ?? null;
$hostname  = trim($_POST['hostname'] ?? '');
$ipId      = (int)($_POST['ip_address_id'] ?? 0);
$annotation = $_POST['annotation'] ?? '';
$vendorId  = (int)($_POST['vendor_id'] ?? 0);

if (!$hostname || !$ipId) {
    echo json_encode(['error' => 'hostname и ip_address_id обязательны']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($groupId) {
        $stmt = $pdo->prepare("UPDATE equipment_groups SET hostname = ?, ip_address_id = ?, annotation = ? WHERE id = ?");
        $stmt->execute([$hostname, $ipId, $annotation, $groupId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO equipment_groups (hostname, ip_address_id, group_type, annotation) VALUES (?, ?, 'stack', ?)");
        $stmt->execute([$hostname, $ipId, $annotation]);
        $newGroupId = $pdo->lastInsertId();
        $groupId = $newGroupId;
    }

    // Обновляем все устройства стека с таким же хостом/ip (если есть)
    $pdo->prepare("UPDATE equipment SET group_id = ? WHERE hostname = ? AND ip_address = ?")->execute([$groupId, $hostname, $ipId]);

    if ($vendorId) {
        $pdo->prepare("UPDATE equipment SET vendor_id = ? WHERE group_id = ?")->execute([$vendorId, $groupId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'group_id' => $groupId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}