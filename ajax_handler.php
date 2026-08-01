<?php
// ajax_handler.php – централизованная обработка всех AJAX-запросов

// Доступ к базе данных (если ещё не подключён)
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

// ======================== СПРАВОЧНИКИ ========================
// 1. Фильтрованный список моделей (только для выбранного производителя)
if ($_GET['ajax'] === 'get_list_models' && isset($_GET['vendor_id'])) {
    $vendorId = (int)$_GET['vendor_id'];
    $stmt = $pdo->prepare("SELECT id, name FROM device_models WHERE Vendor = ? ORDER BY name");
    $stmt->execute([$vendorId]);
    $data = $stmt->fetchAll();
    echo json_encode(['data' => $data]);
    exit;
}

// 2. Универсальный список для любых справочников (вендоры, типы устройств и т.д.)
if ($_GET['ajax'] === 'get_list' && isset($_GET['list'])) {
    $list = $_GET['list'];
    $allowed = [
        'device_types'   => ['table' => 'device_types',   'id' => 'id_type_device', 'name' => 'name'],
        'vendors'        => ['table' => 'vendors',        'id' => 'id_vendor',      'name' => 'name'],
        'device_models'  => ['table' => 'device_models',  'id' => 'id',             'name' => 'name', 'extra' => 'Vendor'],
        'cabinets'       => ['table' => 'cabinets',       'id' => 'id_cabinet',     'name' => 'id_cabinet'],
        'cabinet_heights'=> ['table' => 'cabinet_heights','id' => 'id',             'name' => 'height'],
        'Type_group'     => ['table' => 'Type_group',     'id' => 'Id',             'name' => 'Type_group'],
        'firmwares'      => ['table' => 'firmwares',      'id' => 'id_firmware',    'name' => 'name'],
        'ip_address'     => ['table' => 'ip_address',     'id' => 'Id',             'name' => 'ip_address'],
        'node_types'     => ['table' => 'node_types',     'id' => 'id_node_type',   'name' => 'name_node_type'],
    ];

    if (!isset($allowed[$list])) {
        echo json_encode(['error' => 'Invalid list', 'data' => []]);
        exit;
    }

    $config = $allowed[$list];
    $sql = "SELECT {$config['id']} AS id, {$config['name']} AS name FROM {$config['table']}";

    // Фильтрация моделей по производителю (если передан vendor_id)
    $params = [];
    if ($list === 'device_models' && !empty($_GET['vendor_id'])) {
        $sql .= " WHERE Vendor = ?";
        $params[] = (int)$_GET['vendor_id'];
    }

    $sql .= " ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    echo json_encode(['data' => $data, 'list_name' => $list]);
    exit;
}


// 3. Добавление записей в справочники (все, включая модели, вендоров и т.д.)
if ($_GET['ajax'] === 'add_meta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $list   = $input['list'] ?? '';
    $name   = trim($input['name'] ?? '');
    $vendorId = isset($input['vendor_id']) ? (int)$input['vendor_id'] : null;

    // Разрешённые справочники с указанием таблицы и столбца, куда вставлять name
    $allowed = [
        'vendors'         => ['table' => 'vendors',         'column' => 'name'],
        'device_types'    => ['table' => 'device_types',    'column' => 'name'],
        'Type_group'      => ['table' => 'Type_group',      'column' => 'Type_group'],
        'firmwares'       => ['table' => 'firmwares',       'column' => 'name'],
        'ip_address'      => ['table' => 'ip_address',      'column' => 'ip_address'],
        'node_types'      => ['table' => 'node_types',      'column' => 'name_node_type'],
        'cabinets'        => ['table' => 'cabinets',        'column' => 'id_cabinet'],
        'cabinet_heights' => ['table' => 'cabinet_heights', 'column' => 'height'],
        'device_models'   => ['table' => 'device_models',   'column' => 'name', 'extra' => true],   // extra = нужен vendor_id
    ];

    if (!isset($allowed[$list]) || $name === '') {
        echo json_encode(['success' => false, 'error' => 'Неверные данные']);
        exit;
    }

    $config = $allowed[$list];

    // Для моделей требуем vendor_id
    if ($list === 'device_models' && !$vendorId) {
        echo json_encode(['success' => false, 'error' => 'Не указан производитель']);
        exit;
    }

    // Получение одного оборудования по ID
if ($_GET['ajax'] === 'get_equipment_item' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("
        SELECT e.*,
               v.name AS vendor_name,
               m.name AS model_name,
               dt.name AS device_type_name,
               f.name AS firmware_name,
               c.id_cabinet AS cabinet_label,
               ip.ip_address AS ip_address_text,
               tg.Type_group AS groupe_name,
               n.KY_number
        FROM equipment e
        LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
        LEFT JOIN device_models m ON e.model_id = m.id
        LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
        LEFT JOIN firmwares f ON e.firmwares = f.id_firmware
        LEFT JOIN cabinets c ON e.id_cabinet = c.id_cabinet
        LEFT JOIN ip_address ip ON e.ip_address = ip.Id
        LEFT JOIN Type_group tg ON e.Groupe = tg.Id
        LEFT JOIN nodes n ON e.id_node = n.id_node
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($equipment) {
        echo json_encode($equipment);
    } else {
        echo json_encode(['error' => 'Оборудование не найдено']);
    }
    exit;
}

    try {
        if ($list === 'device_models') {
            $stmt = $pdo->prepare("INSERT INTO device_models (Vendor, name) VALUES (?, ?)");
            $stmt->execute([$vendorId, $name]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$config['table']} ({$config['column']}) VALUES (?)");
            $stmt->execute([$name]);
        }

        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ======================== УЗЛЫ / ОБОРУДОВАНИЕ ========================
if ($_GET['ajax'] === 'get_equipment' && isset($_GET['node_id'])) {
    require_once 'api/GetData/get_equipment.php';
    exit;
}
// Типы узлов
if ($_GET['ajax'] === 'get_node_types') {
    require_once __DIR__ . '/api/GetData/get_node_types.php';
    exit;
}
if ($_GET['ajax'] === 'delete_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM equipment WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}
if ($_GET['ajax'] === 'get_node_item' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $node = $pdo->query("SELECT * FROM nodes WHERE id_node = $id")->fetch();
    echo json_encode($node ? $node : ['error' => 'Узел не найден']);
    exit;
}
// Получение списка узлов с возможной фильтрацией по building_id
if ($_GET['ajax'] === 'get_nodes_list') {
    $sql = "
        SELECT n.id_node,
               n.status,
               n.KY_number,
               n.device_count,
               l.workshop,
               l.floor,
               l.room,
               b.Name_Building AS building_name,
               nt.name_node_type AS node_type_name
        FROM nodes n
        LEFT JOIN locations l ON n.id_location = l.id_location
        LEFT JOIN Buildings b ON l.building = b.Id
        LEFT JOIN node_types nt ON n.node_type_id = nt.id_node_type
    ";

    $where = [];
    $params = [];

    // Фильтр по зданию
    if (!empty($_GET['building_id'])) {
        $where[] = "l.building = ?";
        $params[] = (int)$_GET['building_id'];
    }

    // Поиск по тексту (если используется)
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = "(n.KY_number LIKE ? OR b.Name_Building LIKE ? OR l.workshop LIKE ? OR l.floor LIKE ? OR l.room LIKE ? OR nt.name_node_type LIKE ?)";
        $params = array_merge($params, [$search, $search, $search, $search, $search, $search]);
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY n.KY_number ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $nodes = $stmt->fetchAll();

    // Формируем display‑строку для локации
    foreach ($nodes as &$node) {
        $locationParts = [];
        if (!empty($node['building_name'])) $locationParts[] = $node['building_name'];
        if (!empty($node['workshop'])) $locationParts[] = 'Цех ' . $node['workshop'];
        if (!empty($node['floor'])) $locationParts[] = $node['floor'] . ' этаж';
        if (!empty($node['room'])) $locationParts[] = 'ком. ' . $node['room'];
        $node['location_display'] = implode(', ', $locationParts);
    }

    echo json_encode($nodes);
    exit;
}
if ($_GET['ajax'] === 'delete_node' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM nodes WHERE id_node = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}
if ($_GET['ajax'] === 'get_cabinet' && isset($_GET['equipment_id'])) {
    require_once 'api/GetData/get_cabinet.php';
    exit;
}
if ($_GET['ajax'] === 'get_building_item' && isset($_GET['id'])) {
    require_once 'api/GetData/get_building_item.php';
    exit;
}
if ($_GET['ajax'] === 'update_building' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/UpdateData/update_building.php';
    exit;
}
if ($_GET['ajax'] === 'delete_building' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/DeleteData/delete_building.php';
    exit;
}
if ($_GET['ajax'] === 'get_node_columns') {
    require_once 'api/GetData/get_node_columns.php';
    exit;
}
if ($_GET['ajax'] === 'get_equipment_columns') {
    require_once 'api/GetData/get_equipment_columns.php';
    exit;
}
if ($_GET['ajax'] === 'delete_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/DeleteData/delete_item.php';
    exit;
}
if ($_GET['ajax'] === 'get_locations') {
    require_once 'api/GetData/get_locations.php';
    exit;
}
if ($_GET['ajax'] === 'get_buildings') {
    require_once 'api/GetData/get_buildings.php';
    exit;
}
if ($_GET['ajax'] === 'add_node' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/AddData/add_node.php';
    exit;
}
if ($_GET['ajax'] === 'add_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/AddData/add_equipment.php';
    exit;
}
if ($_GET['ajax'] === 'add_location' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/AddData/add_location.php';
    exit;
}
if ($_GET['ajax'] === 'add_building' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/AddData/add_building.php';
    exit;
}
if ($_GET['ajax'] === 'add_node_type' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/AddData/add_node_type.php';
    exit;
}
if ($_GET['ajax'] === 'add_column' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/AddData/add_column.php';
    exit;
}
if ($_GET['ajax'] === 'check_mac') {
    require_once 'api/GetData/check_mac.php';
    exit;
}
if ($_GET['ajax'] === 'get_warehouses') {
    require_once 'api/GetData/get_warehouses.php';
    exit;
}
if ($_GET['ajax'] === 'move_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/AddData/move_equipment.php';
    exit;
}
if ($_GET['ajax'] === 'get_warehouse_buildings') {
    require_once 'api/GetData/get_warehouse_buildings.php';
    exit;
}
if ($_GET['ajax'] === 'get_warehouse_equipment') {
    require_once 'api/GetData/get_warehouse_equipment.php';
    exit;
}
if ($_GET['ajax'] === 'update_node' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/UpdateData/update_node.php';
    exit;
}
if ($_GET['ajax'] === 'update_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'api/UpdateData/update_equipment.php';
    exit;
}

// Если ни одно действие не подошло
echo json_encode(['error' => 'Unknown AJAX action']);
exit;