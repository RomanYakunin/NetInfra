<?php
// api/GetData/get_equipment_item.php – получение одного оборудования (адаптировано под новую схему БД)
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo json_encode(['error' => 'ID оборудования обязателен']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT e.*, 
           ip.ip_address AS ip_address_display,
           f.name AS firmware_name,
           dm.name AS model_name,
           v.name AS vendor_name,
           dt.name AS device_type_name,
           eg.hostname AS stack_hostname,        -- общий hostname стека (если есть)
           eg.ip_address_id AS stack_ip_id,      -- ID IP-адреса стека
           sip.ip_address AS stack_ip_display    -- сам IP-адрес стека
    FROM equipment e
    LEFT JOIN ip_address ip ON e.ip_address = ip.Id
    LEFT JOIN firmwares f ON e.firmwares = f.id_firmware
    LEFT JOIN device_models dm ON e.model_id = dm.id
    LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
    LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
    LEFT JOIN equipment_groups eg ON e.group_id = eg.id
    LEFT JOIN ip_address sip ON eg.ip_address_id = sip.Id   -- IP стека
    WHERE e.id = ?
");
$stmt->execute([$id]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    echo json_encode(['error' => 'Оборудование не найдено']);
    exit;
}

// Для совместимости с JS, который ожидает поле Groupe == 2 для стека
$equipment['Groupe'] = $equipment['group_id'] ? 2 : 1;   // если group_id не NULL, значит стек (или другая группа)
// Дополнительно можно вернуть флаг is_stack
$equipment['is_stack'] = $equipment['group_id'] ? true : false;

// Передаём данные стека, если устройство в группе
if ($equipment['group_id']) {
    $equipment['stack_info'] = [
        'hostname' => $equipment['stack_hostname'],
        'ip_address' => $equipment['stack_ip_display'] ?? null,
        'ip_address_id' => $equipment['stack_ip_id']
    ];
}

echo json_encode($equipment);