<?php
// modules/nodes_page/equipment_details/equipment_details.php
error_reporting(0);
ini_set('display_errors', 0);
ob_clean();

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/db.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Не указан ID']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT e.*, 
               dt.name AS device_type_name,
               v.name AS vendor_name,
               dm.name AS model_name,
               f.name AS firmware_name,
               ip.ip_address AS ip_address_display,
               cab.id_cabinet AS cabinet_id,
               cab.height AS cabinet_height
        FROM equipment e
        LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
        LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
        LEFT JOIN device_models dm ON e.model_id = dm.id
        LEFT JOIN firmwares f ON e.firmwares = f.id_firmware
        LEFT JOIN ip_address ip ON e.ip_address = ip.Id
        LEFT JOIN cabinets cab ON e.id_cabinet = cab.id_cabinet
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipment) {
        echo json_encode(['error' => 'Устройство не найдено']);
        exit;
    }

    // Модули – заглушка (таблица equipment_modules ещё не создана)
    $equipment['modules'] = ['sfp' => [], 'psu' => []];

    // Сервисы (заглушка)
    $equipment['services'] = [
        'Zabbix' => false,
        'NTP' => false,
        'Graylog' => false,
        'RADIUS' => false,
        'TACACS+' => false,
    ];

    echo json_encode($equipment);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}