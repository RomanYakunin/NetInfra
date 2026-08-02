<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__, 5) . '/config/db.php';

$list = $_GET['list'] ?? '';
if (!$list) {
    echo json_encode(['error' => 'Не указан параметр list']);
    exit;
}

// Каталог моделей шкафов: своя логика (доп. поля + опциональный фильтр по производителю)
if ($list === 'rack_models') {
    $vendorId = (int)($_GET['vendor_id'] ?? 0);
    // Возвращаем ВСЕ столбцы таблицы + имя производителя (name — алиас для совместимости с селектами)
    $sql = "SELECT rm.*, rm.model_name AS name, v.name AS vendor_name
            FROM rack_models rm
            LEFT JOIN vendors v ON rm.vendor_id = v.id_vendor";
    $params = [];
    if ($vendorId) {
        $sql .= " WHERE rm.vendor_id = ?";
        $params[] = $vendorId;
    }
    $sql .= " ORDER BY rm.model_name";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $data, 'list_name' => $list]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Шкафы: своя логика — фильтр по узлу и составное название
if ($list === 'racks') {
    // node_id передаётся, когда оборудование добавляется в конкретный узел:
    // тогда показываем только шкафы этого узла. Без параметра — все шкафы
    // (например, при добавлении оборудования на склад).
    $nodeId = isset($_GET['node_id']) ? (int)$_GET['node_id'] : 0;

    $sql = "SELECT r.id_rack AS id,
                   TRIM(CONCAT_WS(' · ',
                        NULLIF(r.name, ''),
                        NULLIF(rm.model_name, ''),
                        IF(rm.height_u IS NOT NULL, CONCAT(rm.height_u, 'U'), NULL)
                   )) AS name
            FROM racks r
            LEFT JOIN rack_models rm ON r.model_id = rm.id";
    $params = [];
    if ($nodeId > 0) {
        $sql .= " WHERE r.id_node = ?";
        $params[] = $nodeId;
    }
    $sql .= " ORDER BY r.name";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Шкаф без названия и модели — показываем хотя бы его ID
        foreach ($data as &$row) {
            if ($row['name'] === null || $row['name'] === '') {
                $row['name'] = 'Шкаф #' . $row['id'];
            }
        }
        unset($row);
        echo json_encode(['data' => $data, 'list_name' => $list]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$allowed = [
    'buildings'          => ['table' => 'Buildings',        'id' => 'Id',             'name' => 'Name_Building'],
    'node_types'         => ['table' => 'node_types',       'id' => 'id_node_type',   'name' => 'name_node_type'],
    'device_types'       => ['table' => 'device_types',     'id' => 'id_type_device', 'name' => 'name'],
    'vendors'            => ['table' => 'vendors',          'id' => 'id_vendor',      'name' => 'name'],
    'device_models'      => ['table' => 'device_models',    'id' => 'id',             'name' => 'name'],
    'firmwares'          => ['table' => 'firmwares',        'id' => 'id_firmware',    'name' => 'name'],
    'hostnames' => ['table' => 'equipment', 'id' => 'id', 'name' => 'hostname'],
    'rack_heights'       => ['table' => 'rack_heights',     'id' => 'id',             'name' => 'height'],       // ← исправлено
    'ip_address'         => ['table' => 'ip_address',       'id' => 'Id',             'name' => 'ip_address'],   // ← добавлено
];

if (!array_key_exists($list, $allowed)) {
    echo json_encode(['error' => 'Неизвестный список']);
    exit;
}

$config = $allowed[$list];
try {
    $stmt = $pdo->query("SELECT `{$config['id']}` AS id, `{$config['name']}` AS name FROM `{$config['table']}` ORDER BY name");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['data' => $data, 'list_name' => $list]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}