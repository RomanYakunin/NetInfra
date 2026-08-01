<?php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$groupId = $_POST['group_id'] ?? null;
$hostname = trim($_POST['hostname'] ?? '');
$ipId = (int)($_POST['ip_address_id'] ?? 0);
$annotation = $_POST['annotation'] ?? '';
$vendorId = (int)($_POST['vendor_id'] ?? 0);

if (!$hostname || !$ipId) {
    echo json_encode(['error' => 'hostname и ip_address_id обязательны']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($groupId) {
        // Обновляем группу
        $stmt = $pdo->prepare("UPDATE equipment_groups SET hostname = ?, ip_address_id = ?, annotation = ? WHERE id = ?");
        $stmt->execute([$hostname, $ipId, $annotation, $groupId]);
        // Удаляем старые связи (на случай, если какие-то устройства были удалены из стека, но group_id остался)
        // Не удаляем, оставляем как есть, только обновим существующие
        // Перепривязываем все устройства с таким hostname и ip к этому group_id
        $pdo->prepare("UPDATE equipment SET group_id = ? WHERE hostname = ? AND ip_address = ?")->execute([$groupId, $hostname, $ipId]);
    } else {
        // Создаём группу
        $stmt = $pdo->prepare("INSERT INTO equipment_groups (hostname, ip_address_id, group_type, annotation) VALUES (?, ?, 'stack', ?)");
        $stmt->execute([$hostname, $ipId, $annotation]);
        $newGroupId = $pdo->lastInsertId();
        // Назначаем group_id всем устройствам с таким hostname и ip
        $pdo->prepare("UPDATE equipment SET group_id = ? WHERE hostname = ? AND ip_address = ?")->execute([$newGroupId, $hostname, $ipId]);
        $groupId = $newGroupId;
    }

    // Опционально: обновить vendor_id у всех устройств стека
    if ($vendorId) {
        $pdo->prepare("UPDATE equipment SET vendor_id = ? WHERE group_id = ?")->execute([$vendorId, $groupId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'group_id' => $groupId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}