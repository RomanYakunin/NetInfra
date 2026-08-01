<?php
require_once dirname(__FILE__, 5) . '/config/db.php';
$type = $_GET['type'] ?? '';
$sql = '';
switch ($type) {
    case 'nodes': $sql = "SELECT * FROM nodes"; break;
    case 'equipment': $sql = "SELECT * FROM equipment"; break;
    case 'ups': $sql = "SELECT * FROM equipment WHERE device_type_id = 5"; break;
    case 'phones': $sql = "SELECT * FROM phones"; break;
    case 'printers': $sql = "SELECT * FROM equipment WHERE device_type_id = (SELECT id_type_device FROM device_types WHERE name='Принтер')"; break;
    default: echo 'Invalid type'; exit;
}
$rows = $pdo->query($sql)->fetchAll();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $type . '.csv');
$output = fopen('php://output', 'w');
if (!empty($rows)) {
    fputcsv($output, array_keys($rows[0]));
    foreach ($rows as $row) fputcsv($output, $row);
}
fclose($output);
exit;