<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 6) . '/includes/acl.php';
requireAdmin();
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$ipAddress = $_POST['ip_address'] ?? '';
$hostname  = trim($_POST['hostname'] ?? '');
$vendorId  = $_POST['vendor_id'] ?? '';
$annotation = $_POST['annotation'] ?? '';
$devices   = json_decode($_POST['devices'] ?? '[]', true);
$nodeId    = $_POST['id_node'] ?? null;

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
    $stmt = $pdo->prepare("INSERT INTO equipment_groups (hostname, ip_address_id, group_type, annotation) VALUES (?, ?, 'stack', ?)");
    $stmt->execute([$hostname, $ipId, $annotation]);
    $groupId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("UPDATE equipment SET group_id = ?, id_node = ? WHERE id = ?");
    foreach ($devices as $device) {
        $deviceId = $device['id'] ?? null;
        if ($deviceId) {
            $stmt->execute([$groupId, $nodeId, $deviceId]);
        }
    }

    $pdo->commit();
    require_once dirname(__FILE__, 6) . "/includes/logger.php";
    logAction($pdo, "add_stack", "stack", $groupId, $hostname ?? "", ["node_id" => $nodeId ?? null]);

    echo json_encode(['success' => true, 'group_id' => $groupId, 'id_node' => $nodeId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка создания стека: ' . $e->getMessage()]);
}
