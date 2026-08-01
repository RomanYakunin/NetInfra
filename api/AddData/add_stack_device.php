<?php
// api/AddData/add_stack.php – создание стека и привязка устройств
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$stackData = json_decode($_POST['stack_data'] ?? '{}', true);
if (!$stackData) {
    echo json_encode(['error' => 'Нет данных стека']);
    exit;
}

$ipAddress   = $stackData['ip_address'] ?? '';
$hostname    = $stackData['hostname'] ?? '';
$vendorId    = $stackData['vendor_id'] ?? '';
$annotation  = $stackData['annotation'] ?? '';
$devices     = $stackData['devices'] ?? [];
$nodeId      = $_POST['id_node'] ?? null;

if (empty($hostname) || empty($ipAddress) || empty($devices)) {
    echo json_encode(['error' => 'Необходимы IP, hostname и хотя бы одно устройство']);
    exit;
}

// Получаем или создаём IP-адрес стека
$ipId = null;
if (!empty($ipAddress)) {
    $stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
    $stmt->execute([$ipAddress]);
    $ipId = $stmt->fetchColumn();
    if (!$ipId) {
        $stmt = $pdo->prepare("INSERT INTO ip_address (ip_address) VALUES (?)");
        $stmt->execute([$ipAddress]);
        $ipId = $pdo->lastInsertId();
    }
}

$pdo->beginTransaction();
try {
    // Создаём группу оборудования (стек)
    $stmt = $pdo->prepare("INSERT INTO equipment_groups (hostname, ip_address_id, group_type, annotation) VALUES (?, ?, 'stack', ?)");
    $stmt->execute([$hostname, $ipId, $annotation]);
    $groupId = $pdo->lastInsertId();

    // Привязываем устройства к группе и к узлу
    $stmt = $pdo->prepare("UPDATE equipment SET group_id = ?, id_node = ? WHERE id = ?");
    foreach ($devices as $device) {
        $deviceId = $device['id'] ?? null;
        if ($deviceId) {
            $stmt->execute([$groupId, $nodeId, $deviceId]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'group_id' => $groupId, 'id_node' => $nodeId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка создания стека: ' . $e->getMessage()]);
}