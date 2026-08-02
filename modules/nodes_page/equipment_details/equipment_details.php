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
               r.id_rack AS rack_id,
               r.name AS rack_name,
               rm.model_name AS rack_model_name,
               rm.height_u AS rack_height,
               n.KY_number,
               w.name AS warehouse_name,
               eg.hostname AS stack_hostname
        FROM equipment e
        LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
        LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
        LEFT JOIN device_models dm ON e.model_id = dm.id
        LEFT JOIN firmwares f ON e.firmwares = f.id_firmware
        LEFT JOIN ip_address ip ON e.ip_address = ip.Id
        LEFT JOIN racks r ON e.id_rack = r.id_rack
        LEFT JOIN rack_models rm ON r.model_id = rm.id
        LEFT JOIN nodes n ON e.id_node = n.id_node
        LEFT JOIN warehouses w ON e.warehouse_id = w.id
        LEFT JOIN equipment_groups eg ON e.group_id = eg.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipment) {
        echo json_encode(['error' => 'Устройство не найдено']);
        exit;
    }

    // Совместимость с фронтендом: 1 — одиночное, 2 — член стека
    $equipment['Groupe'] = $equipment['group_id'] ? 2 : 1;

    // ---------- Модули ----------
    $modules = ['sfp' => [], 'psu' => []];
    $stmtMod = $pdo->prepare("SELECT module_type, name, type, model, serial_number, wavelength, distance, port, status
                              FROM equipment_modules WHERE equipment_id = ? ORDER BY module_type, name");
    $stmtMod->execute([$id]);
    foreach ($stmtMod->fetchAll(PDO::FETCH_ASSOC) as $mod) {
        $key = ($mod['module_type'] === 'SFP') ? 'sfp' : (($mod['module_type'] === 'Модуль питания') ? 'psu' : 'other');
        if (!isset($modules[$key])) $modules[$key] = [];
        $modules[$key][] = $mod;
    }
    $equipment['modules'] = $modules;

    // ---------- Сервисы ----------
    $services = [
        'Zabbix'   => false,
        'NTP'      => false,
        'Graylog'  => false,
        'RADIUS'   => false,
        'TACACS+'  => false,
    ];
    $stmtSvc = $pdo->prepare("SELECT service_name, is_connected, extra_data FROM equipment_services WHERE equipment_id = ?");
    $stmtSvc->execute([$id]);
    foreach ($stmtSvc->fetchAll(PDO::FETCH_ASSOC) as $svc) {
        $connected = (bool)$svc['is_connected'];
        switch ($svc['service_name']) {
            case 'zabbix':  $services['Zabbix']  = $connected; break;
            case 'ntp':     $services['NTP']     = $connected; break;
            case 'graylog': $services['Graylog'] = $connected; break;
            case 'radius_tacacs':
                if ($svc['extra_data'] === 'tacacs') $services['TACACS+'] = $connected;
                else $services['RADIUS'] = $connected;
                break;
        }
    }
    $equipment['services'] = $services;

    // ---------- Учётные данные локального администратора ----------
    $stmtCreds = $pdo->prepare("SELECT local_admin_login, local_admin_password FROM equipment_credentials WHERE equipment_id = ?");
    $stmtCreds->execute([$id]);
    $creds = $stmtCreds->fetch(PDO::FETCH_ASSOC);
    $equipment['local_admin_login']    = $creds['local_admin_login'] ?? '';
    $equipment['local_admin_password'] = $creds['local_admin_password'] ?? '';

    // ---------- LLDP-соседи ----------
    $stmtLldp = $pdo->prepare("
        SELECT l.local_port, l.remote_device_id, l.remote_port, l.remote_device_type,
               l.remote_equipment_id, l.last_seen,
               re.hostname AS remote_hostname,
               rip.ip_address AS remote_ip,
               rn.KY_number AS remote_ky_number
        FROM lldp_neighbors l
        LEFT JOIN equipment re ON l.remote_equipment_id = re.id
        LEFT JOIN ip_address rip ON re.ip_address = rip.Id
        LEFT JOIN nodes rn ON re.id_node = rn.id_node
        WHERE l.local_equipment_id = ?
           OR (l.local_group_id IS NOT NULL AND l.local_group_id = ?)
        ORDER BY l.local_port
    ");
    $stmtLldp->execute([$id, $equipment['group_id']]);
    $equipment['lldp_neighbors'] = $stmtLldp->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($equipment);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
