<?php
// api/GetData/get_nodes_list.php – возвращает JSON со списком узлов
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

error_reporting(0);
ini_set('display_errors', 0);

$buildingId = isset($_GET['building_id']) ? (int)$_GET['building_id'] : 0;
$search     = trim($_GET['search'] ?? '');

try {
    // ================== Основной запрос узлов ==================
    $sql = "SELECT n.id_node,
               n.status,
               n.KY_number,
               n.id_location AS building_id,
               n.node_type_id AS node_type_id,
               (SELECT COUNT(*) FROM equipment WHERE id_node = n.id_node) AS device_count,
               CONCAT_WS(' ',
                   COALESCE(b.Name_Building, ''),
                   NULLIF(l.workshop, ''),
                   IF(l.floor IS NOT NULL AND l.floor != '', CONCAT('этаж ', l.floor), NULL),
                   IF(l.room IS NOT NULL AND l.room != '', CONCAT('каб. ', l.room), NULL)
               ) AS location_display,
               nt.name_node_type AS node_type_name
        FROM nodes n
        LEFT JOIN locations l ON n.id_location = l.id_location
        LEFT JOIN Buildings b ON l.building = b.Id
        LEFT JOIN node_types nt ON n.node_type_id = nt.id_node_type";

    $where = [];
    $params = [];

    if ($buildingId > 0) {
        $where[] = "l.building = ?";
        $params[] = $buildingId;
    }

    if (!empty($search)) {
        $like = '%' . $search . '%';
        $conditions = [
            "n.KY_number LIKE ?",
            "n.status LIKE ?",
            "b.Name_Building LIKE ?",
            "l.workshop LIKE ?",
            "l.floor LIKE ?",
            "l.room LIKE ?",
            "nt.name_node_type LIKE ?"
        ];

        $conditionsNum = [
            "CAST(e.unit_position AS CHAR) LIKE ?",
            "CAST(c.id_rack AS CHAR) LIKE ?"
        ];

        $needEquipmentJoin = false;
        $equipmentFields = [
            "e.hostname"         => "e.hostname LIKE ?",
            "e.serial_number"    => "e.serial_number LIKE ?",
            "e.mac_address"      => "e.mac_address LIKE ?",
            "ip.ip_address"      => "ip.ip_address LIKE ?",
            "dt.name"            => "dt.name LIKE ?",
            "v.name"             => "v.name LIKE ?",
            "dm.name"            => "dm.name LIKE ?",
            "fw.name"            => "fw.name LIKE ?",
            "e.Annotation"       => "e.Annotation LIKE ?"
        ];

        $allSearchConditions = array_merge($conditions, array_values($equipmentFields), $conditionsNum);
        $where[] = "(" . implode(" OR ", $allSearchConditions) . ")";

        for ($i = 0; $i < count($conditions); $i++) {
            $params[] = $like;
        }
        if (!empty($equipmentFields)) {
            $needEquipmentJoin = true;
            for ($i = 0; $i < count($equipmentFields); $i++) {
                $params[] = $like;
            }
        }
        for ($i = 0; $i < count($conditionsNum); $i++) {
            $params[] = $like;
        }

        if ($needEquipmentJoin) {
            $sql .= " LEFT JOIN equipment e ON n.id_node = e.id_node
                      LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
                      LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
                      LEFT JOIN device_models dm ON e.model_id = dm.id
                      LEFT JOIN ip_address ip ON e.ip_address = ip.Id
                      LEFT JOIN firmwares fw ON e.firmwares = fw.id_firmware
                      LEFT JOIN racks c ON e.id_rack = c.id_rack";
        }
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " GROUP BY n.id_node ORDER BY n.KY_number ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    require_once __DIR__ . '/../../modules/nodes_page/data_checks/data_checks.php';

foreach ($nodes as &$node) {
    $node['missing_fields'] = getNodeMissingFields($node);
    $node['has_missing']    = !empty($node['missing_fields']);
}
unset($node);

    foreach ($nodes as &$node) {
        $node['device_count'] = (int)$node['device_count'];
    }
    unset($node);

    // ================== Поиск по складу ==================
    $warehouseMatch = null;
    if (!empty($search)) {
        // Ищем оборудование на складе, подходящее под поисковый запрос
        $sqlWh = "SELECT e.serial_number, e.mac_address, e.hostname,
                         w.name AS warehouse_name,
                         CONCAT_WS(' ', b.Name_Building, w.name) AS warehouse_display
                  FROM equipment e
                  JOIN warehouses w ON e.warehouse_id = w.id
                  LEFT JOIN Buildings b ON w.building = b.Id
                  WHERE e.warehouse_id IS NOT NULL
                    AND (e.serial_number LIKE ? OR e.mac_address LIKE ? OR e.hostname LIKE ?)
                  LIMIT 1";
        $stmtWh = $pdo->prepare($sqlWh);
        $stmtWh->execute([$like, $like, $like]);
        $whRow = $stmtWh->fetch(PDO::FETCH_ASSOC);
        if ($whRow) {
            $warehouseMatch = [
                'serial_number' => $whRow['serial_number'],
                'mac_address'   => $whRow['mac_address'],
                'hostname'      => $whRow['hostname'],
                'warehouse'     => $whRow['warehouse_display'] ?: $whRow['warehouse_name']
            ];
        }
    }

    // Формируем ответ
    if (!empty($search)) {
        // При поиске возвращаем объект с nodes и warehouse_match
        echo json_encode([
            'nodes' => $nodes,
            'warehouse_match' => $warehouseMatch
        ]);
    } else {
        // Без поиска возвращаем просто массив узлов (обратная совместимость)
        echo json_encode($nodes);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}