<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
// api/AddData/add_equipment.php – финальная версия с поддержкой склада, PoE, сервисов, модулей и учётных данных
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Разрешённые столбцы для вставки в equipment
$allowedCols = [
    'ip_address', 'hostname', 'Poe', 'device_type_id', 'vendor_id',
    'model_id', 'serial_number', 'mac_address', 'firmwares',
    'id_rack', 'unit_position', 'id_node', 'Slot', 'Annotation',
    'warehouse_id', 'group_id'
];

$data = [];
foreach ($allowedCols as $col) {
    $data[$col] = $_POST[$col] ?? '';
}

// ========== 1. Обработка IP-адреса ==========
$ipValue = $data['ip_address'];
if (!empty($ipValue) && !is_numeric($ipValue)) {
    $stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
    $stmt->execute([$ipValue]);
    $ipId = $stmt->fetchColumn();
    if (!$ipId) {
        $insertStmt = $pdo->prepare("INSERT INTO ip_address (ip_address) VALUES (?)");
        $insertStmt->execute([$ipValue]);
        $ipId = $pdo->lastInsertId();
    }
    $data['ip_address'] = $ipId;
} elseif (empty($ipValue)) {
    $data['ip_address'] = null;
}

// ========== 2. Обработка прошивки ==========
$fwValue = $data['firmwares'];
if (!empty($fwValue) && !is_numeric($fwValue)) {
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

// ========== 3. Проверка обязательных полей ==========
if (empty($data['device_type_id']) || (empty($data['id_node']) && empty($data['warehouse_id']) && empty($_POST['stack_device']))) {
    echo json_encode(['warning' => 'Тип устройства и узел или склад обязательны']);
    exit;
}

// ========== 4. Приведение числовых полей ==========
$intFields = [
    'ip_address', 'Poe', 'device_type_id', 'vendor_id',
    'model_id', 'firmwares', 'id_rack',
    'id_node', 'Slot', 'warehouse_id', 'group_id'
];
foreach ($intFields as $field) {
    if (isset($data[$field]) && $data[$field] !== '') {
        if (is_numeric($data[$field])) {
            $data[$field] = (int)$data[$field];
        } else {
            $data[$field] = null;
        }
    } else {
        $data[$field] = null;
    }
}

// Юнит: одиночное значение (4) либо диапазон (4-8). Столбец varchar,
// поэтому в $intFields его нет — приводим к каноничному виду и проверяем формат.
if (array_key_exists('unit_position', $data) && $data['unit_position'] !== null) {
    $raw = trim((string)$data['unit_position']);
    if ($raw === '') {
        $data['unit_position'] = null;
    } elseif (preg_match('/^(\d+)\s*-\s*(\d+)$/', $raw, $m)) {
        $from = (int)$m[1]; $to = (int)$m[2];
        if ($from > $to) { [$from, $to] = [$to, $from]; }
        $data['unit_position'] = $from === $to ? (string)$from : $from . '-' . $to;
    } elseif (preg_match('/^\d+$/', $raw)) {
        $data['unit_position'] = $raw;
    } else {
        echo json_encode(['error' => 'Юнит указывается числом (4) или диапазоном (4-8)']);
        exit;
    }
}
if ($data['ip_address'] === '') {
    $data['ip_address'] = null;
}

// ========== 5. Статус по умолчанию ==========
$data['status'] = 'inactive';

// ========== 6. Вставка в equipment ==========
$columns = '`' . implode('`, `', array_keys($data)) . '`';
$placeholders = ':' . implode(', :', array_keys($data));
$sql = "INSERT INTO equipment ($columns) VALUES ($placeholders)";

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    $newId = $pdo->lastInsertId();

    // ========== 7. Сохранение сервисов ==========
    if (!empty($_POST['services'])) {
        $services = json_decode($_POST['services'], true);
        if (is_array($services)) {
            // Удаляем старые записи для этого оборудования (на случай редактирования, но здесь добавление — просто для единообразия)
            $pdo->prepare("DELETE FROM equipment_services WHERE equipment_id = ?")->execute([$newId]);

            $insertService = $pdo->prepare("INSERT INTO equipment_services (equipment_id, service_name, is_connected, extra_data) VALUES (?, ?, ?, ?)");

            foreach ($services as $svc => $connected) {
                if ($svc === 'RADIUS' || $svc === 'TACACS+') {
                    $serviceName = 'radius_tacacs';
                    $extra = ($svc === 'RADIUS') ? 'radius' : 'tacacs';
                    $isConnected = $connected ? 1 : 0;
                    $insertService->execute([$newId, $serviceName, $isConnected, $extra]);
                } else {
                    // zabbix, ntp, graylog – приводим к нижнему регистру для БД
                    $serviceName = strtolower($svc);
                    if (!in_array($serviceName, ['zabbix', 'ntp', 'graylog'])) {
                        continue;
                    }
                    $isConnected = $connected ? 1 : 0;
                    $insertService->execute([$newId, $serviceName, $isConnected, null]);
                }
            }
        }
    }

    // ========== 8. Сохранение модулей (если таблица equipment_modules существует) ==========
    if (!empty($_POST['modules'])) {
        $modules = json_decode($_POST['modules'], true);
        if (is_array($modules)) {
            // Проверим существование таблицы
            $tableExists = $pdo->query("SHOW TABLES LIKE 'equipment_modules'")->rowCount() > 0;
            if ($tableExists) {
                $pdo->prepare("DELETE FROM equipment_modules WHERE equipment_id = ?")->execute([$newId]);
                $insertModule = $pdo->prepare("INSERT INTO equipment_modules 
                    (equipment_id, module_type, name, type, serial_number, wavelength, distance) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");

                foreach ($modules as $type => $modList) {
                    foreach ($modList as $mod) {
                        $insertModule->execute([
                            $newId,
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
            // Если таблицы нет – игнорируем модули (можно записать в лог)
        }
    }

    // ========== 9. Сохранение учётных данных локального администратора ==========
    $login = trim($_POST['local_admin_login'] ?? '');
    $password = $_POST['local_admin_password'] ?? '';
    $vendorId = $data['vendor_id'] ?? null;

    // Если логин не пустой – сохраняем в equipment_credentials
    if ($login !== '') {
        // Проверяем vendor_defaults только если задан vendor_id
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
                // Шаблон существует – проверяем совпадение
                if ($default['default_login'] !== $login || ($password !== '' && $default['default_password'] !== $password)) {
                    // Данные не совпадают
                    if (empty($_POST['force_credentials_update'])) {
                        // Запрос подтверждения
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

        // В любом случае сохраняем/обновляем equipment_credentials
        $stmtCreds = $pdo->prepare("INSERT INTO equipment_credentials (equipment_id, local_admin_login, local_admin_password) 
                                    VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE local_admin_login = VALUES(local_admin_login), local_admin_password = VALUES(local_admin_password)");
        $stmtCreds->execute([$newId, $login, $password]);
    }

    $pdo->commit();
    require_once dirname(__FILE__, 5) . "/includes/logger.php";
    logAction($pdo, "add_equipment", "equipment", $newId, $data["hostname"] ?? "", ["id_node" => $data["id_node"] ?? null]);

    echo json_encode(['success' => true, 'id' => $newId, 'id_node' => $data['id_node'] ?? null]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка добавления: ' . $e->getMessage()]);
}
