<?php
// modules/passive_devices/api/GetData/get_passive_ports.php
// Порты пассивного устройства с расшифровкой направления.
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAuth();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

$deviceId = (int)($_GET['device_id'] ?? 0);
if (!$deviceId) {
    echo json_encode(['error' => 'Не указано устройство']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               b.Name_Building AS building_name,
               n.KY_number,
               e.hostname      AS equipment_hostname,
               l.workshop, l.floor, l.room
        FROM passive_device_ports p
        LEFT JOIN Buildings b ON p.destination_building_id  = b.Id
        LEFT JOIN nodes     n ON p.destination_node_id      = n.id_node
        LEFT JOIN equipment e ON p.destination_equipment_id = e.id
        LEFT JOIN locations l ON p.destination_location_id  = l.id_location
        WHERE p.device_id = ?
        ORDER BY p.port_number
    ");
    $stmt->execute([$deviceId]);
    $ports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Собираем человекочитаемое направление: «КУ-34, АБЧ-1, ком. 226»
    foreach ($ports as &$p) {
        $p['id']          = (int)$p['id'];
        $p['port_number'] = (int)$p['port_number'];
        $p['is_connected'] = (int)$p['is_connected'];

        $parts = [];
        if (!empty($p['KY_number']))          $parts[] = 'КУ-' . $p['KY_number'];
        if (!empty($p['building_name']))      $parts[] = $p['building_name'];
        if (!empty($p['room']))               $parts[] = 'ком. ' . $p['room'];
        if (!empty($p['equipment_hostname'])) $parts[] = $p['equipment_hostname'];
        $p['destination_display'] = implode(', ', $parts);
    }
    unset($p);

    echo json_encode(['success' => true, 'data' => $ports, 'total' => count($ports)]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
