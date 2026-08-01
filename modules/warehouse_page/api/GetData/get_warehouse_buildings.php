<?php
// api/GetData/get_warehouse_buildings.php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT b.Id AS id, b.Name_Building AS name,
                   COUNT(e.id) AS device_count
            FROM warehouses w
            JOIN locations l ON w.location = l.id_location
            JOIN Buildings b ON l.building = b.Id
            LEFT JOIN equipment e ON e.warehouses = 'warehouse' AND e.id_node IS NULL
            GROUP BY b.Id, b.Name_Building
            ORDER BY b.Name_Building";
    $buildings = $pdo->query($sql)->fetchAll();
    echo json_encode($buildings);
} catch (PDOException $e) {
    echo json_encode([]);
}