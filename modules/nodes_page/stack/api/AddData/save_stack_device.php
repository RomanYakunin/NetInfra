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

$groupId   = $_POST['group_id'] ?? null;
$ipAddress = $_POST['ip_address'] ?? '';
$hostname  = trim($_POST['hostname'] ?? '');
$vendorId  = $_POST['vendor_id'] ?? null;
$slot      = (int)($_POST['Slot'] ?? 0);
$nodeId    = $_POST['id_node'] ?? null;

if (!$hostname || !$ipAddress) {
    echo json_encode(['error' => 'IP-адрес и имя хоста обязательны']);
    exit;
}

// Приведение IP к ID
if (!is_numeric($ipAddress)) {
    $stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
    $stmt->execute([$ipAddress]);
    $ipId = $stmt->fetchColumn();
    if (!$ipId) {
        $stmt = $pdo->prepare("INSERT INTO ip_address (ip_address) VALUES (?)");
        $stmt->execute([$ipAddress]);
        $ipId = $pdo->lastInsertId();
    }
    $ipAddressId = $ipId;
} else {
    $ipAddressId = (int)$ipAddress;
}

// Сбор полей устройства
$data = [
    'ip_address'    => $ipAddressId,
    'hostname'      => $hostname,
    'vendor_id'     => $vendorId ?: null,
    'device_type_id' => $_POST['device_type_id'] ?? null,
    'model_id'      => $_POST['model_id'] ?? null,
    'serial_number' => $_POST['serial_number'] ?? null,
    'mac_address'   => $_POST['mac_address'] ?? null,
    'firmwares'     => $_POST['firmwares'] ?? null,
    'id_cabinet'    => $_POST['id_cabinet'] ?? null,
    'unit_position' => $_POST['unit_position'] ?? null,
    'Slot'          => $slot,
    'Annotation'    => $_POST['Annotation'] ?? null,
    'id_node'       => $nodeId ?: null,
    'warehouse_id'  => null,
    'group_id'      => $groupId ?: null,
    'status'        => 'inactive'
];

$editId = $_POST['id'] ?? null;
try {
    $pdo->beginTransaction();

    if ($editId) {
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = "`$col` = ?";
            $params[] = $val;
        }
        $params[] = $editId;
        $sql = "UPDATE equipment SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $equipId = $editId;
    } else {
        $columns = '`' . implode('`, `', array_keys($data)) . '`';
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO equipment ($columns) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $equipId = $pdo->lastInsertId();
    }

    // Если передан group_id, но устройство ещё не в группе – добавить
    if ($groupId && !$editId) {
        $pdo->prepare("UPDATE equipment SET group_id = ? WHERE id = ?")->execute([$groupId, $equipId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $equipId, 'group_id' => $groupId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}