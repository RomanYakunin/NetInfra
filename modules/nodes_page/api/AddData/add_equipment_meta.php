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
$name = trim($input['name'] ?? $input['model_name'] ?? '');
$vendorId = isset($input['vendor_id']) ? (int)$input['vendor_id'] : null;

if ($list === '' || $name === '') {
    echo json_encode(['success' => false, 'message' => 'Название обязательно']);
    exit;
}

// Каталог моделей шкафов: своя логика (много полей, не укладывается в общую схему)
if ($list === 'rack_models') {
    if (!$vendorId) {
        echo json_encode(['success' => false, 'message' => 'Производитель обязателен для модели шкафа']);
        exit;
    }
    $heightU  = (int)($input['height_u'] ?? 0);
    $widthMm  = (int)($input['width_mm'] ?? 0);
    $depthMm  = (int)($input['depth_mm'] ?? 0);
    if (!$heightU || !$widthMm || !$depthMm) {
        echo json_encode(['success' => false, 'message' => 'Высота, ширина и глубина обязательны']);
        exit;
    }
    $formFactor = in_array($input['form_factor'] ?? '', ['напольный', 'настенный'], true) ? $input['form_factor'] : 'напольный';
    $doorType   = trim($input['door_type'] ?? '') ?: 'перфорированная';
    $ipRating   = trim($input['ip_rating'] ?? '') ?: 'IP20';
    $maxLoadKg  = isset($input['max_load_kg']) && $input['max_load_kg'] !== '' ? (int)$input['max_load_kg'] : null;
    $notes      = trim($input['notes'] ?? '') ?: null;

    try {
        $stmt = $pdo->prepare("INSERT INTO rack_models (vendor_id, model_name, form_factor, height_u, width_mm, depth_mm, door_type, ip_rating, max_load_kg, notes)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$vendorId, $name, $formFactor, $heightU, $widthMm, $depthMm, $doorType, $ipRating, $maxLoadKg, $notes]);
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
    exit;
}

// Определяем таблицу, поле названия и дополнительные поля
$config = [
    'vendors'        => ['table' => 'vendors',       'name_field' => 'name',          'extra' => []],
    'device_types'   => ['table' => 'device_types',  'name_field' => 'name',          'extra' => []],
    'device_models'  => ['table' => 'device_models', 'name_field' => 'name',          'extra' => ['Vendor' => $vendorId]],
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