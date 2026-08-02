<?php
// modules/nodes_page/api/GetData/get_node_racks.php – шкафы, привязанные к локации узла
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

$nodeId = (int)($_GET['node_id'] ?? 0);
if (!$nodeId) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id_location FROM nodes WHERE id_node = ?");
$stmt->execute([$nodeId]);
$locationId = $stmt->fetchColumn();
if (!$locationId) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.id_rack AS id, r.name, rm.model_name, rm.height_u, rm.width_mm, rm.depth_mm, v.name AS vendor_name
    FROM racks r
    LEFT JOIN rack_models rm ON r.model_id = rm.id
    LEFT JOIN vendors v ON rm.vendor_id = v.id_vendor
    WHERE r.location_id = ?
    ORDER BY r.name
");
$stmt->execute([$locationId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
