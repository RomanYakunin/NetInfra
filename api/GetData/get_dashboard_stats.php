<?php
require_once '../../config/db.php';
$type = $_GET['type'] ?? '';
$response = ['count'=>0];
switch ($type) {
    case 'nodes': $response['count'] = $pdo->query("SELECT COUNT(*) FROM nodes")->fetchColumn(); break;
    case 'equipment': $response['count'] = $pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn(); break;
    case 'ups': $response['count'] = $pdo->query("SELECT COUNT(*) FROM equipment WHERE device_type_id = 5")->fetchColumn(); break;
    case 'phones': $response['count'] = $pdo->query("SELECT COUNT(*) FROM phones")->fetchColumn(); break;
    case 'printers': $response['count'] = $pdo->query("SELECT COUNT(*) FROM equipment WHERE device_type_id = (SELECT id_type_device FROM device_types WHERE name='Принтер')")->fetchColumn(); break;
}
echo json_encode($response);