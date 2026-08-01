<?php
// api/AddData/add_warehouse_equipment.php – добавление оборудования на склад (все поля необязательны)
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Список разрешённых столбцов (без Groupe, id_cabinet, unit_position)
$allowedCols = [
    'device_type_id', 'vendor_id', 'model_id', 'ip_address', 'hostname',
    'serial_number', 'mac_address', 'firmwares', 'Annotation', 'warehouse_id'
];

$data = [];
foreach ($allowedCols as $col) {
    $val = $_POST[$col] ?? null;
    // Приводим пустые строки к null
    if ($val === '') $val = null;
    $data[$col] = $val;
}

// warehouse_id обязателен
if (empty($data['warehouse_id'])) {
    echo json_encode(['error' => 'Не указан склад']);
    exit;
}

// Обработка IP-адреса (если передан)
// if (!empty($data['ip_address']) && !is_numeric($data['ip_address'])) {
//     $stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
//     $stmt->execute([$data['ip_address']]);
//     $ipId = $stmt->fetchColumn();
//     if (!$ipId) {
//         $stmt = $pdo->prepare("INSERT INTO ip_address (ip_address) VALUES (?)");
//         $stmt->execute([$data['ip_address']]);
//         $ipId = $pdo->lastInsertId();
//     }
//     $data['ip_address'] = $ipId;
// } else {
//     $data['ip_address'] = null;
// }

// Обработка прошивки (если передан текст)
if (!empty($data['firmwares']) && !is_numeric($data['firmwares'])) {
    $stmt = $pdo->prepare("SELECT id_firmware FROM firmwares WHERE name = ?");
    $stmt->execute([$data['firmwares']]);
    $fwId = $stmt->fetchColumn();
    if (!$fwId) {
        $stmt = $pdo->prepare("INSERT INTO firmwares (name) VALUES (?)");
        $stmt->execute([$data['firmwares']]);
        $fwId = $pdo->lastInsertId();
    }
    $data['firmwares'] = $fwId;
} else {
    $data['firmwares'] = null;
}

// Приведение целочисленных полей к int (или null)
$intFields = ['device_type_id', 'vendor_id', 'model_id', 'ip_address', 'firmwares', 'warehouse_id'];
foreach ($intFields as $field) {
    if (isset($data[$field]) && $data[$field] !== null && is_numeric($data[$field])) {
        $data[$field] = (int)$data[$field];
    } else {
        $data[$field] = null;
    }
}

// Статус по умолчанию
$data['status'] = 'inactive';

// Вставка
$columns = '`' . implode('`, `', array_keys($data)) . '`';
$placeholders = ':' . implode(', :', array_keys($data));
$sql = "INSERT INTO equipment ($columns) VALUES ($placeholders)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка добавления: ' . $e->getMessage()]);
}