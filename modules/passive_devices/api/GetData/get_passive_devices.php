<?php
// modules/passive_devices/api/GetData/get_passive_devices.php
// Список пассивного оборудования с фильтрами по типу, шкафу, складу, узлу.
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAuth();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

$type        = trim($_GET['type'] ?? '');
$rackId      = isset($_GET['rack_id']) ? (int)$_GET['rack_id'] : 0;
$warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$nodeId      = isset($_GET['node_id']) ? (int)$_GET['node_id'] : 0;
$search      = trim($_GET['search'] ?? '');

// Тип сверяем с белым списком: он подставляется как значение, но лишний
// контроль отсекает мусорные запросы
$allowedTypes = ['patch_panel', 'optical_panel', 'sfp_module', 'psu_module', 'terminal', 'other'];

try {
    $where  = [];
    $params = [];

    if ($type !== '') {
        if (!in_array($type, $allowedTypes, true)) {
            echo json_encode(['error' => 'Недопустимый тип устройства']);
            exit;
        }
        $where[] = 'pd.type = ?';
        $params[] = $type;
    }
    if ($rackId)      { $where[] = 'pd.rack_id = ?';      $params[] = $rackId; }
    if ($warehouseId) { $where[] = 'pd.warehouse_id = ?'; $params[] = $warehouseId; }
    if ($nodeId)      { $where[] = 'pd.node_id = ?';      $params[] = $nodeId; }
    if ($search !== '') {
        $where[] = '(pd.name LIKE ? OR pd.model LIKE ? OR pd.serial_number LIKE ? OR v.name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT pd.*,
                   v.name  AS vendor_name,
                   r.name  AS rack_name,
                   w.name  AS warehouse_name,
                   n.KY_number,
                   (SELECT COUNT(*) FROM passive_device_ports p
                     WHERE p.device_id = pd.id AND p.is_connected = 1) AS ports_connected
            FROM passive_devices pd
            LEFT JOIN vendors    v ON pd.vendor_id    = v.id_vendor
            LEFT JOIN racks      r ON pd.rack_id      = r.id_rack
            LEFT JOIN warehouses w ON pd.warehouse_id = w.id
            LEFT JOIN nodes      n ON pd.node_id      = n.id_node
            $whereSql
            ORDER BY pd.type, pd.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id']              = (int)$r['id'];
        $r['ports_count']     = (int)$r['ports_count'];
        $r['port_rows']       = (int)$r['port_rows'];
        $r['ports_connected'] = (int)$r['ports_connected'];
    }
    unset($r);

    echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
