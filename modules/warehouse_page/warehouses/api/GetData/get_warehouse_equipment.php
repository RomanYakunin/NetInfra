<?php
// api/GetData/get_warehouse_equipment.php – оборудование склада по зданию и вкладке
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$buildingId = $_GET['building_id'] ?? 0;
$type = $_GET['type'] ?? 'all';

try {
    $sql = "SELECT e.*,
                   dt.name AS device_type_name,
                   v.name AS vendor_name,
                   dm.name AS model_name,
                   f.name AS firmware_name
            FROM equipment e
            LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
            LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
            LEFT JOIN device_models dm ON e.model_id = dm.id
            LEFT JOIN firmwares f ON e.firmwares = f.id_firmware
            WHERE e.location_type = 'warehouse'
              AND e.id_node IS NULL
              AND e.id IN (
                  SELECT e2.id
                  FROM equipment e2
                  JOIN warehouses w ON e2.id_cabinet = w.id
                  JOIN locations l ON w.location = l.id_location
                  WHERE l.building = ?
              )";

    if ($type === 'active') {
        $sql .= " AND e.status = 'active'";
    } elseif ($type === 'inactive') {
        $sql .= " AND e.status != 'active'";
    }

    $sql .= " ORDER BY e.hostname";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$buildingId]);
    $equipment = $stmt->fetchAll();
    echo json_encode($equipment);
} catch (PDOException $e) {
    echo json_encode([]);
}