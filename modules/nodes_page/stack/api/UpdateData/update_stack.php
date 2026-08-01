<?php
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$groupId   = (int)($_POST['group_id'] ?? 0);
$ipValue   = $_POST['ip_address'] ?? '';
$hostname  = trim($_POST['hostname'] ?? '');
$vendorId  = (int)($_POST['vendor_id'] ?? 0);
$annotation = $_POST['annotation'] ?? '';

if (!$groupId) {
    echo json_encode(['error' => 'group_id обязателен']);
    exit;
}

// Приведение IP к ID
$ipId = null;
if ($ipValue !== '') {
    if (is_numeric($ipValue)) {
        $ipId = (int)$ipValue;
    } else {
        $stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
        $stmt->execute([$ipValue]);
        $ipId = $stmt->fetchColumn();
        if (!$ipId) {
            $stmt = $pdo->prepare("INSERT INTO ip_address (ip_address) VALUES (?)");
            $stmt->execute([$ipValue]);
            $ipId = $pdo->lastInsertId();
        }
    }
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE equipment_groups SET hostname = ?, ip_address_id = ?, annotation = ? WHERE id = ?");
    $stmt->execute([$hostname, $ipId, $annotation, $groupId]);

    if ($ipId || $hostname !== '') {
        $updateFields = [];
        $params = [];
        if ($ipId) {
            $updateFields[] = "ip_address = ?";
            $params[] = $ipId;
        }
        if ($hostname !== '') {
            $updateFields[] = "hostname = ?";
            $params[] = $hostname;
        }
        if (!empty($updateFields)) {
            $params[] = $groupId;
            $pdo->prepare("UPDATE equipment SET " . implode(', ', $updateFields) . " WHERE group_id = ?")->execute($params);
        }
    }

    if ($vendorId > 0) {
        $pdo->prepare("UPDATE equipment SET vendor_id = ? WHERE group_id = ?")->execute([$vendorId, $groupId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}