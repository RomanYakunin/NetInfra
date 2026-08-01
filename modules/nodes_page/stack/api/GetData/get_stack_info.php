<?php
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$groupId = (int)$_GET['group_id'];
if (!$groupId) {
    echo json_encode(['success' => false, 'error' => 'group_id required']);
    exit;
}

try {
    // 1. Получаем основные поля группы (без vendor_id)
    $stmt = $pdo->prepare("SELECT hostname, ip_address_id, annotation FROM equipment_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        echo json_encode(['success' => false, 'error' => 'Группа не найдена']);
        exit;
    }

    // 2. vendor_id берём из первого устройства стека (если есть)
    $vendorId = null;
    $stmtV = $pdo->prepare("SELECT vendor_id FROM equipment WHERE group_id = ? ORDER BY Slot LIMIT 1");
    $stmtV->execute([$groupId]);
    $vendorId = $stmtV->fetchColumn();

    echo json_encode([
        'success'        => true,
        'hostname'       => $group['hostname'],
        'ip_address_id'  => $group['ip_address_id'],
        'annotation'     => $group['annotation'],
        'vendor_id'      => $vendorId ?: null
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД']);
}