<?php
// modules/passive_devices/api/UpdateData/update_passive_port.php
// Назначение направления для порта (куда идёт кабель/волокно).
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$portId   = (int)($_POST['port_id'] ?? 0);
$deviceId = (int)($_POST['device_id'] ?? 0);
$portNum  = (int)($_POST['port_number'] ?? 0);

// Порт можно указать либо напрямую по id, либо парой устройство+номер
if (!$portId && !($deviceId && $portNum)) {
    echo json_encode(['error' => 'Не указан порт']);
    exit;
}

$label      = trim($_POST['label'] ?? '') ?: null;
$buildingId = !empty($_POST['destination_building_id'])  ? (int)$_POST['destination_building_id']  : null;
$locationId = !empty($_POST['destination_location_id'])  ? (int)$_POST['destination_location_id']  : null;
$nodeId     = !empty($_POST['destination_node_id'])      ? (int)$_POST['destination_node_id']      : null;
$equipId    = !empty($_POST['destination_equipment_id']) ? (int)$_POST['destination_equipment_id'] : null;
$notes      = trim($_POST['notes'] ?? '') ?: null;

$fiberType = trim($_POST['fiber_type'] ?? '');
if (!in_array($fiberType, ['одномод', 'многомод'], true)) $fiberType = null;

// Порт считается занятым, если указано хоть какое-то направление
$isConnected = ($label || $buildingId || $locationId || $nodeId || $equipId) ? 1 : 0;

try {
    if (!$portId) {
        // Порта может ещё не быть в таблице (например, у старой записи) — создаём
        $stmt = $pdo->prepare("SELECT id FROM passive_device_ports WHERE device_id = ? AND port_number = ?");
        $stmt->execute([$deviceId, $portNum]);
        $portId = (int)$stmt->fetchColumn();

        if (!$portId) {
            $pdo->prepare("INSERT INTO passive_device_ports (device_id, port_number) VALUES (?, ?)")
                ->execute([$deviceId, $portNum]);
            $portId = (int)$pdo->lastInsertId();
        }
    }

    $pdo->prepare("
        UPDATE passive_device_ports SET
            label = ?, destination_building_id = ?, destination_location_id = ?,
            destination_node_id = ?, destination_equipment_id = ?,
            fiber_type = ?, notes = ?, is_connected = ?
        WHERE id = ?
    ")->execute([$label, $buildingId, $locationId, $nodeId, $equipId,
                 $fiberType, $notes, $isConnected, $portId]);

    // Журналируем в разрезе устройства — так история читается осмысленно
    $stmt = $pdo->prepare("
        SELECT p.device_id, p.port_number, d.name
        FROM passive_device_ports p
        JOIN passive_devices d ON p.device_id = d.id
        WHERE p.id = ?
    ");
    $stmt->execute([$portId]);
    if ($info = $stmt->fetch(PDO::FETCH_ASSOC)) {
        require_once dirname(__FILE__, 5) . '/includes/logger.php';
        logAction($pdo, 'edit_passive_port', 'passive_device', (int)$info['device_id'], $info['name'],
            'Порт ' . $info['port_number'] . ': ' . ($isConnected ? ($label ?: 'направление задано') : 'освобождён'));
    }

    echo json_encode(['success' => true, 'port_id' => $portId, 'is_connected' => $isConnected]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
