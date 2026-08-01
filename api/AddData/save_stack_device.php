<?php
// api/AddData/save_stack_device.php – сохранение устройства стека (отдельная модалка)
if (!isset($pdo)) {
    require_once dirname(__FILE__, 3) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Обязательные поля
$ipAddress   = $_POST['ip_address'] ?? '';
$hostname    = trim($_POST['hostname'] ?? '');
$vendorId    = $_POST['vendor_id'] ?? null;
$groupId     = $_POST['group_id'] ?? null;  // может быть пустым, если стек ещё не сохранён
$deviceTypeId = $_POST['device_type_id'] ?? null;
$modelId     = $_POST['model_id'] ?? null;
$slot        = (int)($_POST['Slot'] ?? 0);
$nodeId      = $_POST['id_node'] ?? null;

if (!$hostname || !$ipAddress) {
    echo json_encode(['error' => 'IP-адрес и имя хоста обязательны']);
    exit;
}

// Приведение IP к ID
if (!is_numeric($ipAddress)) {
    $stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
    $stmt->execute([$ipAddress]);
    $ipId = $stmt->fetchColumn();
    if (!$ipId) {
        $stmt = $pdo->prepare("INSERT INTO ip_address (ip_address) VALUES (?)");
        $stmt->execute([$ipAddress]);
        $ipId = $pdo->lastInsertId();
    }
    $ipAddressId = $ipId;
} else {
    $ipAddressId = (int)$ipAddress;
}

// Обработка прошивки
$firmwares = $_POST['firmwares'] ?? '';
if (!empty($firmwares) && !is_numeric($firmwares)) {
    $stmt = $pdo->prepare("SELECT id_firmware FROM firmwares WHERE name = ?");
    $stmt->execute([$firmwares]);
    $fwId = $stmt->fetchColumn();
    if (!$fwId) {
        $stmt = $pdo->prepare("INSERT INTO firmwares (name) VALUES (?)");
        $stmt->execute([$firmwares]);
        $fwId = $pdo->lastInsertId();
    }
    $firmwares = $fwId;
}

// Сбор полей для вставки
$data = [
    'ip_address'    => $ipAddressId,
    'hostname'      => $hostname,
    'vendor_id'     => $vendorId ?: null,
    'device_type_id' => $deviceTypeId ?: null,
    'model_id'      => $modelId ?: null,
    'serial_number' => $_POST['serial_number'] ?? null,
    'mac_address'   => $_POST['mac_address'] ?? null,
    'firmwares'     => $firmwares ?: null,
    'id_cabinet'    => $_POST['id_cabinet'] ?? null,
    'unit_position' => $_POST['unit_position'] ?? null,
    'Slot'          => $slot,
    'Annotation'    => $_POST['Annotation'] ?? null,
    'id_node'       => $nodeId ?: null,
    'warehouse_id'  => null,
    'group_id'      => $groupId ?: null,
    'Groupe'        => $groupId ? 2 : 1,  // если есть группа, ставим Groupe=2
    'status'        => 'inactive'
];

// Обработка редактирования
$editId = $_POST['id'] ?? null;
if ($editId) {
    // Обновление
    $set = [];
    $params = [];
    foreach ($data as $col => $val) {
        $set[] = "`$col` = ?";
        $params[] = $val;
    }
    $params[] = $editId;
    $sql = "UPDATE equipment SET " . implode(', ', $set) . " WHERE id = ?";
} else {
    // Вставка
    $columns = '`' . implode('`, `', array_keys($data)) . '`';
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO equipment ($columns) VALUES ($placeholders)";
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare($sql);
    $stmt->execute($editId ? $params : $data);
    $equipId = $editId ?: $pdo->lastInsertId();

    // Обработка сервисов (если переданы)
    if (!empty($_POST['services'])) {
        $services = json_decode($_POST['services'], true);
        if (is_array($services)) {
            $pdo->prepare("DELETE FROM equipment_services WHERE equipment_id = ?")->execute([$equipId]);
            $insertService = $pdo->prepare("INSERT INTO equipment_services (equipment_id, service_name, is_connected, extra_data) VALUES (?, ?, ?, ?)");
            foreach ($services as $svc => $connected) {
                if ($svc === 'RADIUS' || $svc === 'TACACS+') {
                    $serviceName = 'radius_tacacs';
                    $extra = ($svc === 'RADIUS') ? 'radius' : 'tacacs';
                    $isConnected = $connected ? 1 : 0;
                    $insertService->execute([$equipId, $serviceName, $isConnected, $extra]);
                } else {
                    $serviceName = strtolower($svc);
                    if (!in_array($serviceName, ['zabbix', 'ntp', 'graylog'])) continue;
                    $isConnected = $connected ? 1 : 0;
                    $insertService->execute([$equipId, $serviceName, $isConnected, null]);
                }
            }
        }
    }

    // Модули (аналогично add_equipment.php)
    if (!empty($_POST['modules'])) {
        $modules = json_decode($_POST['modules'], true);
        if (is_array($modules)) {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'equipment_modules'")->rowCount() > 0;
            if ($tableExists) {
                $pdo->prepare("DELETE FROM equipment_modules WHERE equipment_id = ?")->execute([$equipId]);
                $insertModule = $pdo->prepare("INSERT INTO equipment_modules (equipment_id, module_type, name, type, serial_number, wavelength, distance) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($modules as $type => $modList) {
                    foreach ($modList as $mod) {
                        $insertModule->execute([$equipId, $type, $mod['name'] ?? '', $mod['type'] ?? '', $mod['serial'] ?? '', $mod['wavelength'] ?? null, $mod['distance'] ?? null]);
                    }
                }
            }
        }
    }

    // Учётные данные
    $login = trim($_POST['local_admin_login'] ?? '');
    $password = $_POST['local_admin_password'] ?? '';
    if ($login !== '') {
        $stmtCreds = $pdo->prepare("INSERT INTO equipment_credentials (equipment_id, local_admin_login, local_admin_password) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE local_admin_login = VALUES(local_admin_login), local_admin_password = VALUES(local_admin_password)");
        $stmtCreds->execute([$equipId, $login, $password]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $equipId, 'group_id' => $groupId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка сохранения: ' . $e->getMessage()]);
}