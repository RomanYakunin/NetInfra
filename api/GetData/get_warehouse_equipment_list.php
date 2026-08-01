<?php
// api/GetData/get_warehouse_equipment_list.php – оборудование склада
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$buildingId = $_GET['building_id'] ?? 0;
$search = $_GET['search'] ?? '';

try {
    $sql = "SELECT e.*,
                   dt.name AS device_type_name,
                   v.name AS vendor_name,
                   dm.name AS model_name,
                   f.name AS firmware_name,
                   b.Name_Building AS building_name
            FROM equipment e
            LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
            LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
            LEFT JOIN device_models dm ON e.model_id = dm.id
            LEFT JOIN firmwares f ON e.firmwares = f.id_firmware
            JOIN warehouses w ON e.id_rack = w.id
            JOIN locations l ON w.location = l.id_location
            JOIN Buildings b ON l.building = b.Id
            WHERE e.location_type = 'warehouse'
              AND e.id_node IS NULL";

    if ($buildingId) {
        $sql .= " AND l.building = ?";
    }
    if ($search) {
        $sql .= " AND (e.hostname LIKE ? OR e.serial_number LIKE ? OR e.mac_address LIKE ?)";
    }

    $sql .= " ORDER BY e.hostname";

    $stmt = $pdo->prepare($sql);
    $params = [];
    if ($buildingId) $params[] = $buildingId;
    if ($search) {
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    $stmt->execute($params);
    $equipment = $stmt->fetchAll();
    echo json_encode($equipment);
} catch (PDOException $e) {
    echo json_encode([]);
}