<?php
// modules/passive_devices/api/UpdateData/update_passive_device.php
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
require_once dirname(__FILE__, 5) . '/modules/passive_devices/api/passive_helpers.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Не указан идентификатор устройства']);
    exit;
}

$data = passiveValidateInput($_POST);

try {
    $stmt = $pdo->prepare("SELECT ports_count FROM passive_devices WHERE id = ?");
    $stmt->execute([$id]);
    $oldPorts = $stmt->fetchColumn();
    if ($oldPorts === false) {
        echo json_encode(['error' => 'Устройство не найдено']);
        exit;
    }
    $oldPorts = (int)$oldPorts;

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE passive_devices SET
            type = ?, name = ?, vendor_id = ?, model = ?, ports_count = ?,
            port_type = ?, port_rows = ?, rack_id = ?, unit_position = ?,
            warehouse_id = ?, node_id = ?, status = ?, serial_number = ?, notes = ?
        WHERE id = ?
    ")->execute([
        $data['type'], $data['name'], $data['vendor_id'], $data['model'],
        $data['ports_count'], $data['port_type'], $data['port_rows'],
        $data['rack_id'], $data['unit_position'], $data['warehouse_id'],
        $data['node_id'], $data['status'], $data['serial_number'], $data['notes'],
        $id,
    ]);

    // Количество портов изменилось — досоздаём или убираем лишние.
    // Уже назначенные направления у оставшихся портов не трогаем.
    if ($data['ports_count'] > $oldPorts) {
        $ins = $pdo->prepare("INSERT IGNORE INTO passive_device_ports (device_id, port_number) VALUES (?, ?)");
        for ($i = $oldPorts + 1; $i <= $data['ports_count']; $i++) {
            $ins->execute([$id, $i]);
        }
    } elseif ($data['ports_count'] < $oldPorts) {
        $pdo->prepare("DELETE FROM passive_device_ports WHERE device_id = ? AND port_number > ?")
            ->execute([$id, $data['ports_count']]);
    }

    $pdo->commit();

    require_once dirname(__FILE__, 5) . '/includes/logger.php';
    logAction($pdo, 'edit_passive_device', 'passive_device', $id, $data['name'],
        ['type' => $data['type'], 'ports' => $data['ports_count']]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
