<?php
// api/GetData/get_equipment.php – групппировка по group_id + equipment_groups
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../modules/nodes_page/data_checks/data_checks.php';

if (!isset($_GET['node_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'node_id required']);
    exit;
}

$nodeId = (int)$_GET['node_id'];

try {
    $allColumns = $pdo->query("SHOW COLUMNS FROM equipment")->fetchAll(PDO::FETCH_COLUMN);
    $exclude = ['id_node', 'created_at', 'warehouse_id', 'Groupe', 'unit_size'];
    $allVisible = array_values(array_diff($allColumns, $exclude));

    if (!in_array('group_id', $allVisible)) {
        $allVisible[] = 'group_id';
    }

    $mainColumns = array_values(array_diff($allVisible, ['Slot', 'id_rack_heights', 'group_id']));
    $requiredMainColumns = ['status', 'ip_address', 'hostname', 'device_type_id', 'vendor_id', 'model_id', 'serial_number', 'mac_address'];
    foreach ($requiredMainColumns as $col) {
        if (!in_array($col, $mainColumns)) {
            $mainColumns[] = $col;
        }
    }
    if (($key = array_search('status', $mainColumns)) !== false) {
        unset($mainColumns[$key]);
        array_unshift($mainColumns, 'status');
    }

    $joinMap = [
        'device_type_id' => ['table' => 'device_types',   'pk' => 'id_type_device', 'display' => 'name'],
        'vendor_id'      => ['table' => 'vendors',        'pk' => 'id_vendor',      'display' => 'name'],
        'model_id'       => ['table' => 'device_models',  'pk' => 'id',             'display' => 'name'],
        'firmwares'      => ['table' => 'firmwares',      'pk' => 'id_firmware',    'display' => 'name'],
        'ip_address'     => ['table' => 'ip_address',     'pk' => 'Id',             'display' => 'ip_address'],
    ];

    try {
        if ($pdo->query("SHOW TABLES LIKE 'racks'")->rowCount() > 0) {
            $cabCols = $pdo->query("SHOW COLUMNS FROM racks")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('name', $cabCols)) {
                $joinMap['id_rack'] = ['table' => 'racks', 'pk' => 'id_rack', 'display' => 'name'];
            } else {
                $joinMap['id_rack'] = ['table' => 'racks', 'pk' => 'id_rack', 'display' => 'id_rack'];
            }
        }
    } catch (PDOException $e) {}

    $select = [];
    foreach ($mainColumns as $col) {
        if (isset($joinMap[$col])) {
            $j = $joinMap[$col];
            $alias = $col . '_display';
            $select[] = "COALESCE({$j['table']}.{$j['display']}, e.$col) AS `$alias`";
            $select[] = "e.`$col` AS `{$col}_original`";
        } else {
            $select[] = "e.`$col`";
        }
    }
    $select[] = "e.group_id";
    $select[] = "e.Slot";
    

    // JOIN с equipment_groups и поля для группировки
    $joins = ["LEFT JOIN equipment_groups g ON e.group_id = g.id"];
    $select[] = "g.hostname AS group_hostname";
    $select[] = "g.ip_address_id AS group_ip_id";

    foreach ($joinMap as $col => $j) {
        $joins[] = "LEFT JOIN {$j['table']} ON e.$col = {$j['table']}.{$j['pk']}";
    }

    $sql = "SELECT " . implode(', ', $select) . " FROM equipment e " . implode(' ', $joins) . " WHERE e.id_node = ? ORDER BY e.group_id, e.Slot";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nodeId]);
    $rows = $stmt->fetchAll();

    $finalMainColumns = [];
    foreach ($mainColumns as $col) {
        $finalMainColumns[] = isset($joinMap[$col]) ? $col . '_display' : $col;
    }

    $stackMainColumns = ['status', 'ip_address', 'hostname', 'device_type_id', 'vendor_id'];
    $finalStackMainColumns = [];
    foreach ($stackMainColumns as $col) {
        $finalStackMainColumns[] = isset($joinMap[$col]) ? $col . '_display' : $col;
    }

    $detailColumns = ['Slot', 'serial_number', 'mac_address', 'Poe', 'model_id', 'firmwares', 'id_rack', 'unit_position', 'Annotation'];
    $finalDetailColumns = [];
    foreach ($detailColumns as $col) {
        $finalDetailColumns[] = isset($joinMap[$col]) ? $col . '_display' : $col;
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Группировка по group_id
$groups = [];
foreach ($rows as $row) {
    $groupId = $row['group_id'];
    // Приведение к строке и проверка на пустоту
    if ($groupId === null || $groupId === '' || $groupId == 0) {
        $groups[] = ['type' => 'single', 'main_row' => $row, 'members' => []];
    } else {
        // Используем строковый ключ, чтобы избежать проблем с числовыми индексами
        $key = (string)$groupId;
        if (!isset($groups[$key])) {
            $groups[$key] = ['type' => 'stack', 'main_row' => null, 'members' => []];
        }
        $groups[$key]['members'][] = $row;
    }
}

$resultGroups = [];
foreach ($groups as $key => $group) {
    if ($group['type'] === 'stack') {
        usort($group['members'], function($a, $b) { return ($a['Slot'] ?? 0) - ($b['Slot'] ?? 0); });
        $first = $group['members'][0];
        $mainRow = [];
        foreach ($finalStackMainColumns as $col) {
            if ($col === 'hostname' || $col === 'hostname_display') {
                $mainRow[$col] = $first['group_hostname'] ?? $first['hostname'] ?? '';
            } elseif ($col === 'ip_address' || $col === 'ip_address_display') {
                $mainRow[$col] = $first['ip_address_display'] ?? $first['ip_address_original'] ?? '';
            } else {
                $mainRow[$col] = $first[$col] ?? '';
            }
        }
        $mainRow['device_type_id_original'] = $first['device_type_id_original'] ?? null;
        $mainRow['vendor_id_original'] = $first['vendor_id_original'] ?? null;
        $mainRow['model_id_original'] = $first['model_id_original'] ?? null;
        $mainRow['ip_address_original'] = $first['ip_address_original'] ?? null;
        $mainRow['group_id'] = $key;

        $resultGroups[] = ['type' => 'stack', 'main_row' => $mainRow, 'members' => $group['members']];
    } else {
        $resultGroups[] = $group;
    }
}

$translations = loadTranslations('equipment');
$fallback = [
    'status' => 'Статус', 'ip_address' => 'IP-адрес', 'hostname' => 'Имя хоста',
    'device_type_id' => 'Тип устройства', 'vendor_id' => 'Производитель', 'model_id' => 'Модель',
    'serial_number' => 'Серийный номер', 'mac_address' => 'MAC-адрес', 'firmwares' => 'Прошивка',
    'id_rack' => 'Шкаф', 'unit_position' => 'Юнит', 'Slot' => 'Слот'
];

$mainTitles = [];
foreach ($finalMainColumns as $col) {
    $cleanCol = str_replace('_display', '', $col);
    $mainTitles[] = $translations[$cleanCol] ?? $fallback[$cleanCol] ?? $col;
}

$stackMainTitles = [];
foreach ($finalStackMainColumns as $col) {
    $cleanCol = str_replace('_display', '', $col);
    $stackMainTitles[] = $translations[$cleanCol] ?? $fallback[$cleanCol] ?? $col;
}

$detailTitles = [];
foreach ($finalDetailColumns as $col) {
    $cleanCol = str_replace('_display', '', $col);
    $detailTitles[] = $translations[$cleanCol] ?? $fallback[$cleanCol] ?? $col;
}

echo json_encode([
    'groups'              => $resultGroups,
    'columns'             => $finalMainColumns,
    'column_titles'       => $mainTitles,
    'stack_main_columns'  => $finalStackMainColumns,
    'stack_main_titles'   => $stackMainTitles,
    'detail_columns'      => $finalDetailColumns,
    'detail_titles'       => $detailTitles
]);