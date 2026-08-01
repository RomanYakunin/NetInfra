<?php
// api/AddData/add_equipment_meta.php
// Универсальное добавление записей в справочники

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$list = $input['list'] ?? '';
$name = trim($input['name'] ?? '');
$vendorId = isset($input['vendor_id']) ? (int)$input['vendor_id'] : null;

if ($list === '' || $name === '') {
    echo json_encode(['success' => false, 'message' => 'Название обязательно']);
    exit;
}

// Определяем таблицу, поле названия и дополнительные поля
$config = [
    'vendors'        => ['table' => 'vendors',       'name_field' => 'name',          'extra' => []],
    'device_types'   => ['table' => 'device_types',  'name_field' => 'name',          'extra' => []],
    'device_models'  => ['table' => 'device_models', 'name_field' => 'name',          'extra' => ['Vendor' => $vendorId]],
    'racks'       => ['table' => 'racks',      'name_field' => 'id_rack',    'extra' => ['Vendor' => null, 'height' => null]],
    'firmwares'      => ['table' => 'firmwares',     'name_field' => 'name',          'extra' => []],
    'ip_address'     => ['table' => 'ip_address',    'name_field' => 'ip_address',    'extra' => []],
    'Type_group'     => ['table' => 'Type_group',    'name_field' => 'Type_group',    'extra' => []],
];

// Для модели обязательно наличие vendor_id
if ($list === 'device_models' && !$vendorId) {
    echo json_encode(['success' => false, 'message' => 'Производитель обязателен для модели']);
    exit;
}

if (!isset($config[$list])) {
    echo json_encode(['success' => false, 'message' => 'Недопустимый тип справочника']);
    exit;
}

$conf = $config[$list];
$table = $conf['table'];
$nameField = $conf['name_field'];
$extra = $conf['extra'];

try {
    if ($list === 'racks') {
        // Для шкафов просто создаём запись с NULL в Vendor и height
        $stmt = $pdo->prepare("INSERT INTO $table (Vendor, height) VALUES (?, ?)");
        $stmt->execute([null, null]);
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'name' => $newId]);
        exit;
    }

    // Проверка на дубликат
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $nameField = ?");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Запись с таким названием уже существует']);
        exit;
    }

    // Формируем SQL для вставки
    $fields = [$nameField => $name];
    foreach ($extra as $col => $val) {
        if ($val !== null) {
            $fields[$col] = $val;
        }
    }

    $columns = implode(', ', array_keys($fields));
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $values = array_values($fields);

    $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
    $stmt->execute($values);
    $newId = $pdo->lastInsertId();

    $newName = $name;
    echo json_encode(['success' => true, 'id' => $newId, 'name' => $newName]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
}