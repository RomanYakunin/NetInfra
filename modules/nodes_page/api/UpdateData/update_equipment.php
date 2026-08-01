<?php
// api/UpdateData/update_equipment.php – финальная версия с поддержкой PoE, сервисов, модулей и учётных данных
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['error' => 'ID оборудования не указан']);
    exit;
}

// Разрешённые столбцы для обновления в таблице equipment
$allowedCols = [
    'Groupe', 'ip_address', 'hostname', 'Poe', 'speed', 'device_type_id', 'vendor_id',
    'model_id', 'serial_number', 'mac_address', 'firmwares',
    'id_rack', 'unit_position', 'id_node', 'Slot', 'Annotation',
    'warehouse_id'
];

// Собираем только переданные поля
$data = [];
foreach ($allowedCols as $col) {
    if (array_key_exists($col, $_POST)) {
        $val = $_POST[$col];
        if ($val !== '') {
            $data[$col] = $val;
        } else {
            $data[$col] = null;
        }
    }
}

// Обработка IP-адреса (если передан как строка)
if (isset($data['ip_address']) && !is_numeric($data['ip_address'])) {
    $ipValue = $data['ip_address'];
    $stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
    $stmt->execute([$ipValue]);
    $ipId = $stmt->fetchColumn();
    if (!$ipId) {
        $stmt = $pdo->prepare("INSERT INTO ip_address (ip_address) VALUES (?)");
        $stmt->execute([$ipValue]);
        $ipId = $pdo->lastInsertId();
    }
    $data['ip_address'] = $ipId;
}

// Обработка прошивки (если передана как строка)
if (isset($data['firmwares']) && !is_numeric($data['firmwares'])) {
    $fwValue = $data['firmwares'];
    $stmt = $pdo->prepare("SELECT id_firmware FROM firmwares WHERE name = ?");
    $stmt->execute([$fwValue]);
    $fwId = $stmt->fetchColumn();
    if (!$fwId) {
        $stmt = $pdo->prepare("INSERT INTO firmwares (name) VALUES (?)");
        $stmt->execute([$fwValue]);
        $fwId = $pdo->lastInsertId();
    }
    $data['firmwares'] = $fwId;
}

// Приведение числовых полей
$intFields = [
    'Groupe', 'ip_address', 'Poe', 'device_type_id', 'vendor_id',
    'model_id', 'firmwares', 'id_rack', 'unit_position',
    'id_node', 'Slot', 'warehouse_id'
];
foreach ($intFields as $field) {
    if (array_key_exists($field, $data)) {
        if (is_numeric($data[$field])) {
            $data[$field] = (int)$data[$field];
        } else {
            $data[$field] = null;
        }
    }
}

// Начинаем транзакцию, чтобы атомарно обновить всё
$pdo->beginTransaction();

try {
    // 1. Обновление основных полей оборудования (если есть что обновлять)
    if (!empty($data)) {
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = "`$col` = ?";
            $params[] = $val;
        }
        $params[] = $id;
        $sql = "UPDATE equipment SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // 2. Обновление сервисов
    if (isset($_POST['services'])) {
        $services = json_decode($_POST['services'], true);
        if (is_array($services)) {
            // Удаляем старые сервисы
            $pdo->prepare("DELETE FROM equipment_services WHERE equipment_id = ?")->execute([$id]);

            $insertService = $pdo->prepare("INSERT INTO equipment_services (equipment_id, service_name, is_connected, extra_data) VALUES (?, ?, ?, ?)");

            foreach ($services as $svc => $connected) {
                if ($svc === 'RADIUS' || $svc === 'TACACS+') {
                    $serviceName = 'radius_tacacs';
                    $extra = ($svc === 'RADIUS') ? 'radius' : 'tacacs';
                    $isConnected = $connected ? 1 : 0;
                    $insertService->execute([$id, $serviceName, $isConnected, $extra]);
                } else {
                    $serviceName = strtolower($svc);
                    if (!in_array($serviceName, ['zabbix', 'ntp', 'graylog'])) {
                        continue;
                    }
                    $isConnected = $connected ? 1 : 0;
                    $insertService->execute([$id, $serviceName, $isConnected, null]);
                }
            }
        }
    }

    // 3. Обновление модулей (если таблица существует)
    if (isset($_POST['modules'])) {
        $modules = json_decode($_POST['modules'], true);
        if (is_array($modules)) {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'equipment_modules'")->rowCount() > 0;
            if ($tableExists) {
                $pdo->prepare("DELETE FROM equipment_modules WHERE equipment_id = ?")->execute([$id]);
                $insertModule = $pdo->prepare("INSERT INTO equipment_modules 
                    (equipment_id, module_type, name, type, serial_number, wavelength, distance) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");

                foreach ($modules as $type => $modList) {
                    foreach ($modList as $mod) {
                        $insertModule->execute([
                            $id,
                            $type,
                            $mod['name'] ?? '',
                            $mod['type'] ?? '',
                            $mod['serial'] ?? '',
                            $mod['wavelength'] ?? null,
                            $mod['distance'] ?? null
                        ]);
                    }
                }
            }
        }
    }

    // 4. Обновление учётных данных локального администратора
    $login = trim($_POST['local_admin_login'] ?? '');
    $password = $_POST['local_admin_password'] ?? '';
    $vendorId = $data['vendor_id'] ?? null;

    if ($login !== '') {
        // Проверяем vendor_defaults, если есть vendor_id
        $needConfirm = false;
        $vendorName = '';

        if ($vendorId) {
            $stmtVendor = $pdo->prepare("SELECT name FROM vendors WHERE id_vendor = ?");
            $stmtVendor->execute([$vendorId]);
            $vendorName = $stmtVendor->fetchColumn() ?: 'производителя';

            $stmtDefault = $pdo->prepare("SELECT default_login, default_password FROM vendor_defaults WHERE vendor_id = ? AND service_name = 'local_admin'");
            $stmtDefault->execute([$vendorId]);
            $default = $stmtDefault->fetch(PDO::FETCH_ASSOC);

            if ($default) {
                // Если шаблон есть, проверяем совпадение (если пароль не пуст)
                if ($default['default_login'] !== $login || ($password !== '' && $default['default_password'] !== $password)) {
                    if (empty($_POST['force_credentials_update'])) {
                        // Запрашиваем подтверждение
                        $pdo->rollBack();
                        echo json_encode([
                            'confirm_credentials_update' => true,
                            'message' => "Учётные данные локального администратора для {$vendorName} отличаются от введённых. Если хотите их изменить, нажмите Да, иначе вернитесь и введите актуальные данные.",
                            'vendor_id' => $vendorId,
                            'login' => $login,
                            'password' => $password
                        ]);
                        exit;
                    } else {
                        // Подтверждено – обновляем шаблон
                        $stmtUpdDefault = $pdo->prepare("UPDATE vendor_defaults SET default_login = ?, default_password = ? WHERE vendor_id = ? AND service_name = 'local_admin'");
                        $stmtUpdDefault->execute([$login, $password, $vendorId]);
                    }
                }
            } else {
                // Шаблона нет – создаём новый
                $stmtInsertDefault = $pdo->prepare("INSERT INTO vendor_defaults (vendor_id, service_name, default_login, default_password) VALUES (?, 'local_admin', ?, ?)");
                $stmtInsertDefault->execute([$vendorId, $login, $password]);
            }
        }

        // В любом случае обновляем equipment_credentials
        $stmtCreds = $pdo->prepare("INSERT INTO equipment_credentials (equipment_id, local_admin_login, local_admin_password) 
                                    VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE local_admin_login = VALUES(local_admin_login), local_admin_password = VALUES(local_admin_password)");
        $stmtCreds->execute([$id, $login, $password]);
    } elseif (isset($_POST['local_admin_login']) && $login === '') {
        // Логин пустой – удаляем учётные данные для этого оборудования
        $pdo->prepare("DELETE FROM equipment_credentials WHERE equipment_id = ?")->execute([$id]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка обновления: ' . $e->getMessage()]);
}