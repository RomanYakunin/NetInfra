<?php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$mac = $_GET['mac'] ?? '';
if ($mac === '') {
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT e.id, e.id_node, e.warehouse_id, n.KY_number,
                           w.name AS warehouse_name,
                           CONCAT_WS(' ', b.Name_Building, w.name) AS warehouse_display
                    FROM equipment e
                    LEFT JOIN nodes n ON e.id_node = n.id_node
                    LEFT JOIN warehouses w ON e.warehouse_id = w.id
                    LEFT JOIN Buildings b ON w.building = b.Id
                    WHERE e.mac_address = ? LIMIT 1");
$stmt->execute([$mac]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    if ($row['KY_number']) {
        $message = 'Оборудование с таким MAC-адресом находится в КУ-' . $row['KY_number'];
    } elseif ($row['warehouse_id']) {
        $whDisplay = $row['warehouse_display'] ?: ('склад ' . $row['warehouse_id']);
        $message = 'Данное оборудование находится на Складе ' . $whDisplay;
    } else {
        $message = 'Оборудование с таким MAC-адресом уже существует (без привязки)';
    }
    echo json_encode(['exists' => true, 'message' => $message]);
} else {
    echo json_encode(['exists' => false]);
}