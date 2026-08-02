<?php
// modules/nodes_page/api/GetData/get_node_racks.php – шкафы, привязанные к узлу
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

$nodeId = (int)($_GET['node_id'] ?? 0);
if (!$nodeId) {
    echo json_encode([]);
    exit;
}

// Шкафы привязываются к узлу напрямую через racks.id_node
$stmt = $pdo->prepare("
    SELECT r.id_rack,
           r.name,
           r.status,
           r.notes,
           r.model_id,
           r.location_id,
           rm.model_name,
           rm.height_u,
           rm.width_mm,
           rm.depth_mm,
           rm.form_factor,
           rm.door_type,
           rm.ip_rating,
           rm.max_load_kg,
           v.name AS vendor_name,
           b.Name_Building AS building_name,
           l.workshop,
           l.floor,
           l.room
    FROM racks r
    LEFT JOIN rack_models rm ON r.model_id = rm.id
    LEFT JOIN vendors v ON rm.vendor_id = v.id_vendor
    LEFT JOIN locations l ON r.location_id = l.id_location
    LEFT JOIN Buildings b ON l.building = b.Id
    WHERE r.id_node = ?
    ORDER BY r.name
");
$stmt->execute([$nodeId]);
$racks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Человекочитаемое расположение шкафа
foreach ($racks as &$rack) {
    $parts = [];
    if (!empty($rack['building_name'])) $parts[] = $rack['building_name'];
    if (!empty($rack['workshop']))      $parts[] = 'цех ' . $rack['workshop'];
    if (!empty($rack['floor']))         $parts[] = $rack['floor'] . ' этаж';
    if (!empty($rack['room']))          $parts[] = 'ком. ' . $rack['room'];
    $rack['location_display'] = implode(', ', $parts);
}
unset($rack);

echo json_encode($racks);
