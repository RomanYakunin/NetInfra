<?php
// modules/passive_devices/api/AddData/add_passive_device.php
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

$data = passiveValidateInput($_POST);   // при ошибке сам отдаёт JSON и выходит

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO passive_devices
            (type, name, vendor_id, model, ports_count, port_type, port_rows,
             rack_id, unit_position, warehouse_id, node_id, status, serial_number, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $data['type'], $data['name'], $data['vendor_id'], $data['model'],
        $data['ports_count'], $data['port_type'], $data['port_rows'],
        $data['rack_id'], $data['unit_position'], $data['warehouse_id'],
        $data['node_id'], $data['status'], $data['serial_number'], $data['notes'],
    ]);
    $newId = (int)$pdo->lastInsertId();

    // Сразу заводим порты: панель шкафа рисует их по этой таблице,
    // а пользователь потом только назначает направления
    if ($data['ports_count'] > 0) {
        $insPort = $pdo->prepare("INSERT INTO passive_device_ports (device_id, port_number) VALUES (?, ?)");
        for ($i = 1; $i <= $data['ports_count']; $i++) {
            $insPort->execute([$newId, $i]);
        }
    }

    $pdo->commit();

    require_once dirname(__FILE__, 5) . '/includes/logger.php';
    logAction($pdo, 'add_passive_device', 'passive_device', $newId, $data['name'],
        ['type' => $data['type'], 'ports' => $data['ports_count']]);

    echo json_encode(['success' => true, 'id' => $newId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
