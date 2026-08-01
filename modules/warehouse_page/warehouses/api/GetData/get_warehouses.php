<?php
header('Content-Type: application/json; charset=utf-8');
try {
    $stmt = $pdo->query("
        SELECT w.id, 
               COALESCE(w.name, '') AS name,
               COALESCE(b.Name_Building, '') AS building_name,
               w.building AS building_id,
               CONCAT(
                   COALESCE(b.Name_Building, ''),
                   IF(w.name IS NOT NULL AND w.name != '', CONCAT(' (', w.name, ')'), '')
               ) AS display
        FROM warehouses w
        LEFT JOIN Buildings b ON w.building = b.Id
        ORDER BY b.Name_Building, w.name
    ");
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($warehouses);
} catch (PDOException $e) {
    echo json_encode([]);
}