<?php
// modules/nodes_page/api/GetData/get_equipment_lldp.php
// Сохранённые LLDP-соседи оборудования — для подстановки в форму при редактировании.
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAuth();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Не указан ID оборудования']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT l.id,
               l.local_port        AS local_interface,
               l.remote_port       AS neighbor_interface,
               l.remote_device_id  AS neighbor_hostname,
               l.remote_device_type,
               l.remote_equipment_id,
               e.hostname          AS remote_known_hostname,
               e.id_node           AS remote_node_id
        FROM lldp_neighbors l
        LEFT JOIN equipment e ON l.remote_equipment_id = e.id
        WHERE l.local_equipment_id = ?
        ORDER BY l.local_port
    ");
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
