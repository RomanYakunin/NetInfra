<?php
// api/GetData/get_node_types.php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $types = $pdo->query("SELECT id_node_type AS id, name_node_type AS name FROM node_types ORDER BY name_node_type")->fetchAll();
} catch (PDOException $e) {
    $types = [];
}
echo json_encode($types);