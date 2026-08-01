<?php
// api/GetData/get_equipment_list.php – возвращает JSON со списком справочника
// Адаптировано: удалена таблица Type_group (была удалена), добавлена поддержка equipment_groups для групп устройств (опционально)
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$list = $_GET['list'] ?? '';

$allowed = [
    'device_types'    => ['table' => 'device_types',   'id' => 'id_type_device', 'name' => 'name', 'extra' => null],
    'vendors'         => ['table' => 'vendors',        'id' => 'id_vendor',      'name' => 'name', 'extra' => null],
    'device_models'   => ['table' => 'device_models',  'id' => 'id',             'name' => 'name', 'extra' => 'Vendor'],
    'racks'        => ['table' => 'racks',       'id' => 'id_rack',     'name' => 'id_rack', 'extra' => null],
    'rack_heights' => ['table' => 'rack_heights','id' => 'id',             'name' => 'height',    'extra' => null],
    // 'Type_group' удалена, больше не используется
    'firmwares'       => ['table' => 'firmwares',      'id' => 'id_firmware',    'name' => 'name',      'extra' => null],
    'ip_address'      => ['table' => 'ip_address',     'id' => 'Id',             'name' => 'ip_address', 'extra' => null],
    'node_types'      => ['table' => 'node_types',     'id' => 'id_node_type',   'name' => 'name_node_type', 'extra' => null],
    // Если нужен список групп (для будущего использования), можно добавить:
    // 'equipment_groups' => ['table' => 'equipment_groups', 'id' => 'id', 'name' => 'hostname', 'extra' => null],
];

if (!isset($allowed[$list])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid list', 'data' => []]);
    exit;
}

$config = $allowed[$list];

try {
    // Проверяем существование таблицы
    $tableExists = $pdo->query("SHOW TABLES LIKE '{$config['table']}'")->rowCount() > 0;
    if (!$tableExists) {
        echo json_encode(['data' => [], 'list_name' => $list]);
        exit;
    }

    // Строим запрос
    $sql = "SELECT {$config['id']} AS id, {$config['name']} AS name";
    if ($config['extra']) {
        $sql .= ", {$config['extra']} AS extra";
    }
    $sql .= " FROM `{$config['table']}`";

    $params = [];
    // Фильтрация для моделей по vendor_id
    if ($list === 'device_models' && !empty($_GET['vendor_id'])) {
        $sql .= " WHERE Vendor = ?";
        $params[] = (int)$_GET['vendor_id'];
    }

    $sql .= " ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $data = array_map(function($row) use ($config) {
        $item = ['id' => $row['id'], 'name' => $row['name']];
        if ($config['extra']) {
            $item['Vendor'] = $row['extra'];
        }
        return $item;
    }, $rows);

    echo json_encode(['data' => $data, 'list_name' => $list]);
} catch (PDOException $e) {
    echo json_encode(['data' => [], 'list_name' => $list, 'error' => $e->getMessage()]);
}