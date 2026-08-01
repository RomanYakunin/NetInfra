<?php
// modules/dashboard/dashboard.php – подготовка данных для дашборда

// Количество узлов
$totalNodes = $pdo->query("SELECT COUNT(*) FROM nodes")->fetchColumn();
$activeNodes = $pdo->query("SELECT COUNT(*) FROM nodes WHERE status = 'active'")->fetchColumn();
$inactiveNodes = $totalNodes - $activeNodes;

// Количество устройств
$totalEquipment = $pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
$activeEquipment = $pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'active'")->fetchColumn();
$inactiveEquipment = $totalEquipment - $activeEquipment;

// Количество ИБП (id_type_device = 5)
$totalUps = $pdo->query("SELECT COUNT(*) FROM equipment WHERE device_type_id = 5")->fetchColumn();

// Телефоны
$totalPhones = $pdo->query("SELECT COUNT(*) FROM phones")->fetchColumn();
$activePhones = $pdo->query("SELECT COUNT(*) FROM phones WHERE status = 'active'")->fetchColumn();
$inactivePhones = $totalPhones - $activePhones;

// Принтеры (id_type_device для Принтера)
$printerTypeId = $pdo->query("SELECT id_type_device FROM device_types WHERE name = 'Принтер'")->fetchColumn();
$totalPrinters = $printerTypeId ? $pdo->query("SELECT COUNT(*) FROM equipment WHERE device_type_id = $printerTypeId")->fetchColumn() : 0;

// Данные для графиков
// Узлы по статусу
$chartData['nodes_status'] = [
    'labels' => ['Активные', 'Неактивные'],
    'data'   => [$activeNodes, $inactiveNodes]
];

// Устройства по типу
$devicesByType = $pdo->query("
    SELECT dt.name AS type, COUNT(*) AS cnt
    FROM equipment e
    JOIN device_types dt ON e.device_type_id = dt.id_type_device
    GROUP BY dt.name
    ORDER BY cnt DESC
")->fetchAll();

$chartData['devices_type'] = [
    'labels' => array_column($devicesByType, 'type'),
    'data'   => array_column($devicesByType, 'cnt')
];

// Телефоны по статусу
$phonesByStatus = $pdo->query("SELECT status, COUNT(*) AS cnt FROM phones GROUP BY status")->fetchAll();
$chartData['phones_status'] = [
    'labels' => array_column($phonesByStatus, 'status'),
    'data'   => array_column($phonesByStatus, 'cnt')
];

// Оборудование по производителям (столбцы)
$devicesByVendor = $pdo->query("
    SELECT v.name AS vendor, COUNT(*) AS cnt
    FROM equipment e
    JOIN vendors v ON e.vendor_id = v.id_vendor
    GROUP BY v.name
    ORDER BY cnt DESC
    LIMIT 10
")->fetchAll();

$chartData['devices_vendor'] = [
    'labels' => array_column($devicesByVendor, 'vendor'),
    'data'   => array_column($devicesByVendor, 'cnt')
];

// Устройства по статусу (для круговой диаграммы)
$chartData['equipment_status'] = [
    'labels' => ['Активные', 'Неактивные'],
    'data'   => [$activeEquipment, $inactiveEquipment]
];

// Устройства по статусу (для круговой диаграммы)
$chartData['phone_status'] = [
    'labels' => ['Активные', 'Неактивные'],
    'data'   => [$activePhones, $inactivePhones]
];