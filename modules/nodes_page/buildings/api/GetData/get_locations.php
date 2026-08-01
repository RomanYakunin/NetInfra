<?php
// api/GetData/get_locations.php – возвращает JSON с локациями
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT l.id_location AS id,
                   CONCAT_WS(' ',
                       COALESCE(b.Name_Building, ''),
                       NULLIF(l.workshop, ''),
                       CONCAT('этаж ', NULLIF(l.floor, '')),
                       IF(l.room REGEXP '^[0-9]+$', CONCAT('каб. ', l.room), l.room)
                   ) AS display
            FROM locations l
            LEFT JOIN Buildings b ON l.building = b.Id
            ORDER BY display";
    $locations = $pdo->query($sql)->fetchAll();
    echo json_encode($locations);
} catch (PDOException $e) {
    echo json_encode([]);
}