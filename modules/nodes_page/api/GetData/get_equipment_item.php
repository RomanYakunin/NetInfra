<?php
// api/GetData/get_equipment_item.php – получение одного оборудования
// Адаптировано к новой схеме (group_id + equipment_groups), добавлена загрузка сервисов и учётных данных
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['error' => 'ID не указан']);
    exit;
}

try {
    // Основная информация об оборудовании
    $stmt = $pdo->prepare("
        SELECT e.*, 
               ip.ip_address AS ip_address_display,
               f.name AS firmware_name,
               dm.name AS model_name,
               v.name AS vendor_name,
               dt.name AS device_type_name,
               eg.hostname AS stack_hostname,
               eg.ip_address_id AS stack_ip_id,
               sip.ip_address AS stack_ip_display
        FROM equipment e
        LEFT JOIN ip_address ip ON e.ip_address = ip.Id
        LEFT JOIN firmwares f ON e.firmwares = f.id_firmware
        LEFT JOIN device_models dm ON e.model_id = dm.id
        LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
        LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
        LEFT JOIN equipment_groups eg ON e.group_id = eg.id
        LEFT JOIN ip_address sip ON eg.ip_address_id = sip.Id
        WHERE e.id = ?
    ");
    $stmt->execute([(int)$id]);
    $eq = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$eq) {
        echo json_encode(['error' => 'Оборудование не найдено']);
        exit;
    }

    // Эмуляция старого поля Groupe для обратной совместимости (1 - одиночное, 2 - стек)
    $eq['Groupe'] = $eq['group_id'] ? 2 : 1;
    $eq['is_stack'] = $eq['group_id'] ? true : false;

    // Информация о стеке
    if ($eq['group_id']) {
        $eq['stack_info'] = [
            'hostname'    => $eq['stack_hostname'],
            'ip_address'  => $eq['stack_ip_display'] ?? null,
            'ip_address_id' => $eq['stack_ip_id']
        ];
    }

    // ---------- ЗАГРУЗКА СЕРВИСОВ ----------
    $stmtServices = $pdo->prepare("SELECT service_name, is_connected, extra_data FROM equipment_services WHERE equipment_id = ?");
    $stmtServices->execute([$id]);
    $servicesRows = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

    $services = [];
    foreach ($servicesRows as $svc) {
        $name = $svc['service_name'];
        if ($name === 'radius_tacacs') {
            if ($svc['extra_data'] === 'radius') {
                $services['RADIUS']   = (bool)$svc['is_connected'];
                $services['TACACS+']  = false;
            } elseif ($svc['extra_data'] === 'tacacs') {
                $services['TACACS+']  = (bool)$svc['is_connected'];
                $services['RADIUS']   = false;
            }
        } else {
            switch ($name) {
                case 'zabbix':  $key = 'Zabbix'; break;
                case 'ntp':     $key = 'NTP'; break;
                case 'graylog': $key = 'Graylog'; break;
                default:        $key = $name;
            }
            $services[$key] = (bool)$svc['is_connected'];
        }
    }
    $eq['services'] = $services;

        // ---------- УЧЁТНЫЕ ДАННЫЕ ЛОКАЛЬНОГО АДМИНИСТРАТОРА ----------
    $stmtCreds = $pdo->prepare("SELECT local_admin_login, local_admin_password FROM equipment_credentials WHERE equipment_id = ?");
    $stmtCreds->execute([$id]);
    $creds = $stmtCreds->fetch(PDO::FETCH_ASSOC);
    if ($creds) {
        $eq['local_admin_login'] = $creds['local_admin_login'];
        // Только админам возвращаем пароль
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $eq['local_admin_password'] = $creds['local_admin_password'];
        }
    }

    echo json_encode($eq);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}