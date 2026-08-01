<?php
// ============================================================
// index.php – NetInfra Manager (единый файл)
// ============================================================
require_once __DIR__ . '/helpers.php';
session_start();

// Выход
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /');
    exit;
}

// Обработка AJAX-запроса авторизации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax']) && $_GET['ajax'] === 'auth') {
    require_once 'api/auth.php';
    exit;
}

// Определяем, что показывать
$isLoggedIn = isset($_SESSION['user_id']);
$role = $isLoggedIn ? $_SESSION['role'] : null;

// Если не админ – показываем страницу входа или запрета
if (!$isLoggedIn || $role !== 'admin') {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="modules/warehouse_page/warehouse.css">
<link rel="stylesheet" href="modules/add_form/checkbox_google.css".

        <meta charset="UTF-8">
        <title>Авторизация</title>
        <style>
            body {
                                background: var(--bg); 
                color: #333; 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: #f0f2f5;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
        header { background: #2c3e50; color: white; padding: 15px 30px; display: flex; align-items: center; justify-content: space-between; }
        header h1 { font-size: 20px; }
        nav a { color: white; text-decoration: none; margin-left: 20px; font-size: 15px; }
        nav a:hover { text-decoration: underline; }
        .container { padding: 20px; }
        button { padding: 8px 16px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #2980b9; }
        /* ========== Оверлей входа ========== */
        .login-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 9999;
            justify-content: center; align-items: center;
        }
        .login-overlay.active { display: flex; }
        .login-box {
            background: white; padding: 40px; border-radius: 8px; width: 360px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2); text-align: center;
        }
        .login-box h2 { margin-bottom: 25px; color: #2c3e50; }
        .login-box .form-group { margin-bottom: 20px; text-align: left; }
        .login-box label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        .login-box input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 15px; }
        .login-box input:focus { border-color: #3498db; outline: none; }
        .login-box button { width: 100%; padding: 12px; font-size: 16px; margin-top: 10px; }
        .login-error { color: #e74c3c; margin-bottom: 15px; font-size: 14px; }
        .modal {
    display: none;
}
.modal.visible {
    display: flex; /* или block */
}
        </style>
    </head>
    <body>
        <?php if ($isLoggedIn && $role === 'user'): ?>
            <!-- Доступ запрещён -->
            <div class="forbidden">
                <h2>Доступ запрещён</h2>
                <p>Ваша роль не позволяет просматривать эту страницу.</p>
                <a href="?logout=1">Выйти</a>
            </div>
        <?php else: ?>
            <!-- Форма входа -->
            <div class="login-box">
                <h2>Вход в систему</h2>
                <div class="error" id="login-error"></div>
                <form id="login-form">
                    <div class="form-group">
                        <label>Логин</label>
                        <input type="text" name="login" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label>Пароль</label>
                        <input type="password" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit">Войти</button>
                </form>
            </div>
            <script>
                document.getElementById('login-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const errorDiv = document.getElementById('login-error');
                    errorDiv.textContent = '';
                    const formData = new FormData(e.target);
                    try {
                        const res = await fetch('?ajax=auth', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            if (data.role === 'admin') {
                                location.reload();
                            } else {
                                document.body.innerHTML = `
                                    <div class="forbidden">
                                        <h2>Доступ запрещён</h2>
                                        <p>Ваша роль не позволяет просматривать эту страницу.</p>
                                        <a href="?logout=1">Выйти</a>
                                    </div>`;
                            }
                        } else {
                            errorDiv.textContent = data.error || 'Ошибка входа';
                        }
                    } catch {
                        errorDiv.textContent = 'Ошибка сети';
                    }
                });
            </script>
        <?php endif; ?>
    </body>
    </html>
    
    <?php
    exit; // Останавливаем вывод основного интерфейса
}
// ============================================================
// index.php – NetInfra Manager (единый файл)
// ============================================================
require_once 'config/db.php';
$routes = require __DIR__ . '/modules/nodes_page/Routes/nodes_page_routes.php';


// ---------- Обработка AJAX-запросов ----------
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_GET['ajax'];
if (isset($routes[$action])) {
    require_once __DIR__ . '/' . $routes[$action];
    exit;
}


    // Запрос оборудования узла
    // if ($_GET['ajax'] === 'get_equipment' && isset($_GET['node_id'])) {
    //     require_once 'modules/nodes_page/api/GetData/get_equipment.php';
    //     exit;
    // }
    
    // Удаление оборудования
if ($_GET['ajax'] === 'delete_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)$_GET['id'];
    try {
        $pdo->prepare("DELETE FROM equipment WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
// Пинг диапазона IP и обновление статусов оборудования
// Новый обработчик пинга (принимает JSON с ips)
if ($_GET['ajax'] === 'ping_worker' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/nodes_page/api/ping_worker.php';
    exit;
}
if ($_GET['ajax'] === 'save_ping_results' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $results = $input['results'] ?? [];

    foreach ($results as $r) {
        $ip = $r['ip'];
        $alive = $r['alive'];
        $stmt = $pdo->prepare("SELECT Id FROM ip_address WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $ipId = $stmt->fetchColumn();
        if ($ipId) {
            $newStatus = $alive ? 'active' : 'inactive';
            $pdo->prepare("UPDATE equipment SET status = ? WHERE ip_address = ?")
                ->execute([$newStatus, $ipId]);
        }
    }

    $activeDevices = (int)$pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'active'")->fetchColumn();
    $inactiveDevices = (int)$pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'inactive'")->fetchColumn();
    $activeNodes = (int)$pdo->query("SELECT COUNT(*) FROM nodes WHERE status = 'active'")->fetchColumn();
    $inactiveNodes = (int)$pdo->query("SELECT COUNT(*) FROM nodes WHERE status = 'inactive'")->fetchColumn();

    echo json_encode([
        'success' => true,
        'stats' => [
            'activeNodes' => $activeNodes,
            'inactiveNodes' => $inactiveNodes,
            'activeDevices' => $activeDevices,
            'inactiveDevices' => $inactiveDevices,
        ]
    ]);
    exit;
}
// Список оборудования на складе для мастера сборки стека
if ($_GET['ajax'] === 'get_warehouse_equipment_for_stack') {
    header('Content-Type: application/json; charset=utf-8');
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT e.id, e.hostname, e.serial_number, e.mac_address,
                   v.name AS vendor_name, dm.name AS model_name,
                   dt.name AS device_type_name
            FROM equipment e
            LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
            LEFT JOIN device_models dm ON e.model_id = dm.id
            LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
            WHERE e.warehouse_id IS NOT NULL";
    $params = [];
    if ($search !== '') {
        $like = '%' . $search . '%';
        $sql .= " AND (e.hostname LIKE ? OR e.serial_number LIKE ? OR e.mac_address LIKE ?
                         OR v.name LIKE ? OR dm.name LIKE ? OR dt.name LIKE ?)";
        $params = array_fill(0, 6, $like);
    }
    $sql .= " ORDER BY dt.name, v.name, dm.name, e.hostname";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Группировка по device_type_name + vendor_name + model_name
    $grouped = [];
    foreach ($equipment as $eq) {
        $key = ($eq['device_type_name'] ?? '—') . '|' . ($eq['vendor_name'] ?? '—') . '|' . ($eq['model_name'] ?? '—');
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'device_type_name' => $eq['device_type_name'],
                'vendor_name' => $eq['vendor_name'],
                'model_name' => $eq['model_name'],
                'items' => []
            ];
        }
        $grouped[$key]['items'][] = $eq;
    }

    echo json_encode(['success' => true, 'groups' => array_values($grouped)]);
    exit;
}

// if ($_GET['ajax'] === 'get_node_equipment_for_move' && isset($_GET['node_id'])) {
//     require_once 'modules/nodes_page/api/GetData/get_node_equipment_for_move.php';
//     exit;
// }



// Удаление узла
if ($_GET['ajax'] === 'delete_node' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)$_GET['id'];
    try {
        $pdo->prepare("DELETE FROM nodes WHERE id_node = ?")->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

//     if ($_GET['ajax'] === 'get_building_item' && isset($_GET['id'])) {
//     require_once 'modules/nodes_page/buildings/api/GetData/get_building_item.php';
//     exit;
// }
// if ($_GET['ajax'] === 'update_building' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/buildings/api/UpdateData/update_building.php';
//     exit;
// }
// if ($_GET['ajax'] === 'delete_building' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/buildings/api/DeleteData/delete_building.php';
//     exit;
// }

    // Запрос справочников (списков для селектов)
//     if ($_GET['ajax'] === 'get_list' && isset($_GET['list'])) {
//     header('Content-Type: application/json; charset=utf-8');
//     $list = $_GET['list'];
//     $allowed = [
//         'device_types'   => ['table' => 'device_types',   'id' => 'id_type_device', 'name' => 'name'],
//         'vendors'        => ['table' => 'vendors',        'id' => 'id_vendor',      'name' => 'name'],
//         'device_models'  => ['table' => 'device_models',  'id' => 'id',             'name' => 'name', 'extra' => 'Vendor'],
//         'racks'       => ['table' => 'racks',       'id' => 'id_rack',     'name' => 'id_rack'],
//         'rack_heights'=> ['table' => 'rack_heights','id' => 'id',             'name' => 'height'],
//         'Type_group'     => ['table' => 'Type_group',     'id' => 'Id',             'name' => 'Type_group'],
//         'firmwares'      => ['table' => 'firmwares',      'id' => 'id_firmware',    'name' => 'name'],
//         'ip_address'     => ['table' => 'ip_address',     'id' => 'Id',             'name' => 'ip_address'],
//         'node_types'     => ['table' => 'node_types',     'id' => 'id_node_type',   'name' => 'name_node_type'],
//     ];

//     if (!isset($allowed[$list])) {
//         echo json_encode(['error' => 'Invalid list', 'data' => []]);
//         exit;
//     }
//     if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_mac_unique') {
//     // Подавляем случайный вывод ошибок, чтобы JSON был чистым
//     ini_set('display_errors', 0);
//     error_reporting(0);
//     ob_clean(); // очищаем буфер вывода
//     require_once __DIR__ . '/modules/nodes_page/ajax/check_mac_unique.php';
//     exit;
// }

//     $config = $allowed[$list];
//     $data = [];
//     try {
//         $stmt = $pdo->query("SELECT {$config['id']} AS id, {$config['name']} AS name FROM {$config['table']} ORDER BY name");
//         $data = $stmt->fetchAll();
//     } catch (PDOException $e) {
//         // оставляем пустой массив
//     }

//     echo json_encode(['data' => $data, 'list_name' => $list]);
//     exit;
// }
if ($_GET['ajax'] === 'get_rack' && isset($_GET['equipment_id'])) {
    require_once 'modules/nodes_page/api/GetData/get_rack.php';
    exit;
}
if ($_GET['ajax'] === 'swap_rack_units' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/nodes_page/api/UpdateData/swap_rack_units.php';
    exit;
}
if ($_GET['ajax'] === 'get_equipment_details' && isset($_GET['id'])) {
    require_once 'modules/nodes_page/equipment_details/equipment_details.php';
    exit;
}
// if ($_GET['ajax'] === 'save_stack_device' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/stack/api/AddData/save_stack_device.php';
//     exit;
// }
// // Внутри блока if (isset($_GET['ajax']))
// if ($_GET['ajax'] === 'kb_get_tables') { require 'modules/knowledge_base/api/get_tables.php'; exit; }
// if ($_GET['ajax'] === 'kb_get_rows')   { require 'modules/knowledge_base/api/get_rows.php';   exit; }
// if ($_GET['ajax'] === 'kb_delete_row') { require 'modules/knowledge_base/api/delete_row.php'; exit; }

// // Для каждой таблицы (пример vendors)
// if ($_GET['ajax'] === 'kb_vendors_add')    { require 'modules/knowledge_base/api/vendors/add.php';    exit; }
// if ($_GET['ajax'] === 'kb_vendors_update') { require 'modules/knowledge_base/api/vendors/update.php'; exit; }

    // Запрос полей для формы узла
//     if ($_GET['ajax'] === 'get_node_columns') {
//     require_once 'modules/nodes_page/api/GetData/get_node_columns.php';
//     exit;
// }

    // Запрос полей для формы оборудования
//     if ($_GET['ajax'] === 'get_equipment_columns') {
//     require_once 'modules/nodes_page/api/GetData/get_equipment_columns.php';
//     exit;
// }
if ($_GET['ajax'] === 'delete_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/nodes_page/api/DeleteData/delete_item.php';
    exit;
}

    // Запрос локаций
//    if ($_GET['ajax'] === 'get_locations') {
//     require_once 'modules/nodes_page/buildings/api/GetData/get_locations.php';
//     exit;
// }

//     if ($_GET['ajax'] === 'get_buildings') {
//     require_once 'modules/nodes_page/buildings/api/GetData/get_buildings.php';
//     exit;
// }
// Список свободных IP-адресов (не занятых в оборудовании)
if ($_GET['ajax'] === 'get_free_ips') {
    $stmt = $pdo->query("SELECT Id, ip_address FROM ip_address WHERE Id NOT IN (SELECT ip_address FROM equipment WHERE ip_address IS NOT NULL) ORDER BY ip_address");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
// if ($_GET['ajax'] === 'check_unique' && isset($_GET['field'], $_GET['value'])) {
//     $field = $_GET['field'];
//     $value = $_GET['value'];
//     $allowed = ['hostname', 'serial_number', 'mac_address', 'ip_address'];
//     if (!in_array($field, $allowed)) {
//         echo json_encode(['error' => 'Invalid field']);
//         exit;
//     }

//     $currentId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
//     $sql = "SELECT e.id, e.id_node, e.warehouse_id, n.KY_number, w.name AS warehouse_name, 
//                    ip.ip_address AS ip_display
//             FROM equipment e
//             LEFT JOIN nodes n ON e.id_node = n.id_node
//             LEFT JOIN warehouses w ON e.warehouse_id = w.id
//             LEFT JOIN ip_address ip ON e.ip_address = ip.Id
//             WHERE e.`$field` = ?";
//     $params = [$value];
//     if ($currentId) {
//         $sql .= " AND e.id != ?";
//         $params[] = $currentId;
//     }
//     $stmt = $pdo->prepare($sql);
//     $stmt->execute($params);
//     $dup = $stmt->fetch();

//     if ($dup) {
//         if ($dup['id_node']) {
//             $ky = $dup['KY_number'] ? 'КУ-' . $dup['KY_number'] : 'узел ' . $dup['id_node'];
//             $message = "Оборудование с таким $field уже находится в $ky";
//         } elseif ($dup['warehouse_id']) {
//             $wh = $dup['warehouse_name'] ?: 'склад ' . $dup['warehouse_id'];
//             $message = "Оборудование с таким $field уже находится на складе «$wh»";
//         } else {
//             $message = "Оборудование с таким $field уже существует";
//         }
//         // Для ip_address покажем сам IP в сообщении
//         if ($field === 'ip_address' && $dup['ip_display']) {
//             $message = "IP-адрес {$dup['ip_display']} уже используется оборудованием в " . ($dup['id_node'] ? 'КУ-'.$dup['KY_number'] : 'на складе «'.$wh.'»');
//         }
//         echo json_encode(['exists' => true, 'message' => $message]);
//     } else {
//         echo json_encode(['exists' => false]);
//     }
//     exit;
// }

if ($_GET['ajax'] === 'build_stack' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $nodeId = (int)$_POST['node_id'];
    $ipAddressId = (int)$_POST['ip_address'];
    $hostname = trim($_POST['hostname']);
    $equipment = json_decode($_POST['equipment'], true);

    if (!$nodeId || !$ipAddressId || !$hostname || empty($equipment)) {
        echo json_encode(['error' => 'Не все параметры']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        foreach ($equipment as $item) {
            $eqId = (int)$item['id'];
            $slot = (int)$item['slot'];
            $stmt = $pdo->prepare("UPDATE equipment SET id_node = ?, warehouse_id = NULL, Groupe = 2, ip_address = ?, hostname = ?, Slot = ? WHERE id = ?");
            $stmt->execute([$nodeId, $ipAddressId, $hostname, $slot, $eqId]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// if ($_GET['ajax'] === 'add_meta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/api/AddData/add_equipment_meta.php';
//     exit;
// }
// Получить один узел
// if ($_GET['ajax'] === 'get_node_item' && isset($_GET['id'])) {
//     require_once 'modules/nodes_page/api/GetData/get_node_item.php';
//     exit;
// }
// Получить одно оборудование
// if ($_GET['ajax'] === 'get_equipment_item' && isset($_GET['id'])) {
//     require_once 'modules/nodes_page/api/GetData/get_equipment_item.php';
//     exit;
// }
// if ($_GET['ajax'] === 'get_nodes_list') {
//     require_once 'modules/nodes_page/api/GetData/get_nodes_list.php';
//     exit;
// }
// Получение узла для редактирования
// if ($_GET['ajax'] === 'get_node' && isset($_GET['id'])) {
//     require_once 'modules/nodes_page/api/GetData/get_node.php';
//     exit;
// }
// Обновление узла
if ($_GET['ajax'] === 'update_node' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $ky = $_POST['KY_number'] ?? '';
    if ($ky !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM nodes WHERE KY_number = ? AND id_node != ?");
        $stmt->execute([$ky, $id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Узел с таким номером КУ уже существует']);
            exit;
        }
    }
    require_once 'modules/nodes_page/api/UpdateData/update_node.php';
    exit;
}
if ($_GET['ajax'] === 'add_meta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $list   = $input['list'] ?? '';
    $name   = trim($input['name'] ?? '');
    $vendorId = isset($input['vendor_id']) ? (int)$input['vendor_id'] : null;

    if ($list === 'device_models' && $name && $vendorId) {
        $stmt = $pdo->prepare("INSERT INTO device_models (Vendor, name) VALUES (?, ?)");
        $stmt->execute([$vendorId, $name]);
        $newId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Неверные данные']);
    }
    exit;
}

// Обновление оборудования
// if ($_GET['ajax'] === 'update_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/api/UpdateData/update_equipment.php';
//     exit;
// }

// if ($_GET['ajax'] === 'check_mac') {
//     require_once 'api/GetData/check_mac.php';
//     exit;
// }

if ($_GET['ajax'] === 'get_warehouses') {
    require_once __DIR__ . '/modules/warehouse_page/api/GetData/get_warehouses.php';
    exit;
}

// Отдельный обработчик для фильтрованного списка моделей
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // ========== НАШ НОВЫЙ ОБРАБОТЧИК ==========
    // if ($_GET['ajax'] === 'get_list_models' && isset($_GET['vendor_id'])) {
    //     $vendorId = (int)$_GET['vendor_id'];
    //     $stmt = $pdo->prepare("SELECT id, name FROM device_models WHERE Vendor = ? ORDER BY name");
    //     $stmt->execute([$vendorId]);
    //     $data = $stmt->fetchAll();
    //     echo json_encode(['data' => $data]);
    //     exit;
    // }
    // =========================================

    // Остальные обработчики...
    // if ($_GET['ajax'] === 'get_equipment' && isset($_GET['node_id'])) {
    //     require_once 'modules/nodes_page/api/GetData/get_equipment.php';
    //     exit;
    // }
    // ...
} 
// ============================================
//     if ($_GET['ajax'] === 'move_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/api/MoveData/move_equipment.php';
//     exit;
// }

if ($_GET['ajax'] === 'get_vendor_default' && isset($_GET['vendor_id'])) {
    require_once 'modules/nodes_page/api/GetData/get_vendor_default.php';
    exit;
}
// Получение инструкции по сервису
if ($_GET['ajax'] === 'get_service_instruction') {
    require_once 'modules/nodes_page/api/GetData/get_service_instruction.php';
    exit;
}

// Сохранение/обновление инструкции (только админ)
if ($_GET['ajax'] === 'save_service_instruction' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/nodes_page/api/UpdateData/save_service_instruction.php';
    exit;
}
// if ($_GET['ajax'] === 'delete_stack_device' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/stack/api/DeleteData/delete_stack_device.php';
//     exit;
// }

    // // Запрос типов узлов
    // if ($_GET['ajax'] === 'get_node_types') {
    //     require_once 'modules/nodes_page/api/GetData/get_node_types.php';
    //     exit;
    // }
// if ($_GET['ajax'] === 'get_equipment_item' && isset($_GET['id'])) {
//     require_once 'modules/nodes_page/api/GetData/get_equipment_item.php';
//     exit;
// }
// if ($_GET['ajax'] === 'update_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/api/AddData/update_equipment.php';
//     exit;
// }
    // Добавление узла
if ($_GET['ajax'] === 'add_node' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка на дубликат KY_number
    $ky = $_POST['KY_number'] ?? '';
    if ($ky !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM nodes WHERE KY_number = ?");
        $stmt->execute([$ky]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Узел с таким номером КУ уже существует']);
            exit;
        }
    }
    require_once 'modules/nodes_page/api/AddData/add_node.php';
    exit;
}
if ($_GET['ajax'] == 'add_type_group') {
    require_once 'modules/nodes_page/api/AddData/add_type_group.php';
    exit;
}

if ($_GET['ajax'] === 'get_warehouse_buildings') {
    require_once __DIR__ . '/modules/warehouse_page/api/GetData/get_warehouse_buildings.php';
    exit;
}
if ($_GET['ajax'] === 'get_warehouse_equipment') {
    require_once __DIR__ . '/modules/warehouse_page/api/GetData/get_warehouse_equipment.php';
    exit;
}
if ($_GET['ajax'] === 'get_warehouse_item' && isset($_GET['id'])) {
    require_once __DIR__ . '/modules/warehouse_page/api/GetData/get_warehouse_item.php';
    exit;
}
if ($_GET['ajax'] === 'update_warehouse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/modules/warehouse_page/api/UpdateData/update_warehouse.php';
    exit;
}
if ($_GET['ajax'] === 'delete_warehouse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/modules/warehouse_page/api/DeleteData/delete_warehouse.php';
    exit;
}
if ($_GET['ajax'] === 'update_rack_unit_size' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/nodes_page/api/UpdateData/update_rack_unit_size.php';
    exit;
}

// Добавление оборудования
// if ($_GET['ajax'] === 'add_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/api/AddData/add_equipment.php';
//     exit;
// }

    /// Добавление столбца
// if ($_GET['ajax'] === 'add_column' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/api/AddData/add_column.php';
//     exit;
// }

if ($_GET['ajax'] == 'add_ip') {
    require_once 'modules/nodes_page/api/AddData/add_ip.php';
    exit;
}
if ($_GET['ajax'] == 'add_model') {
    require_once 'modules/nodes_page/api/AddData/add_model.php';
    exit;
}
// if ($_GET['ajax'] === 'get_stack_info' && isset($_GET['group_id'])) {
//     require_once 'modules/nodes_page/stack/api/GetData/get_stack_info.php';
//     exit;
// }
// if ($_GET['ajax'] === 'get_stack_devices') {
//     require_once 'modules/nodes_page/stack/api/GetData/get_stack_devices.php';
//     exit;
// }


    // // Добавление здания
    // if ($_GET['ajax'] === 'add_building' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    //    require_once 'modules/nodes_page/buildings/api/AddData/add_building.php';
    //     exit;
    // }

    // Добавление локации
    // if ($_GET['ajax'] === 'add_location' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    //     require_once 'modules/nodes_page/buildings/api/AddData/add_location.php';
    //     exit;
    // }
    if ($_GET['ajax'] === 'get_nodes') {
    require_once 'modules/nodes_page/api/GetData/get_nodes.php';
    exit;
}
// if ($_GET['ajax'] === 'save_stack_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     require_once 'modules/nodes_page/stack/api/UpdateData/save_stack_group.php';
//     exit;
// }

if ($_GET['ajax'] === 'add_warehouse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/warehouse_page/api/AddData/add_warehouse.php';
    exit;
}

    // Добавление типа узла
    if ($_GET['ajax'] === 'add_node_type' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/nodes_page/api/AddData/add_node_type.php';
    exit;
}
    if ($_GET['ajax'] === 'add_checklist_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/checklist_table/api/AddData/add_checklist_item.php';
    exit;
}
if ($_GET['ajax'] === 'add_warehouse_equipment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/warehouse_page/api/AddData/add_warehouse_equipment.php';
    exit;
}
// Проверка уникальности серийного номера и MAC-адреса
if ($_GET['ajax'] === 'check_unique' && isset($_GET['field'], $_GET['value'])) {
    require_once 'modules/nodes_page/api/GetData/check_unique.php';
    exit;
}

// Получение членов стека с полными данными для формы перемещения
if ($_GET['ajax'] === 'get_stack_members' && isset($_GET['equip_id'])) {
    $equipId = (int)$_GET['equip_id'];

    // Находим оборудование, чтобы узнать id_node и ip_address
    $stmt = $pdo->prepare("SELECT id_node, ip_address FROM equipment WHERE id = ?");
    $stmt->execute([$equipId]);
    $eq = $stmt->fetch();

    if (!$eq || !$eq['id_node'] || !$eq['ip_address']) {
        echo json_encode(['members' => []]);
        exit;
    }

    // Загружаем все устройства стека с полными JOIN-данными
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.hostname,
            e.serial_number,
            e.mac_address,
            e.Slot,
            ip.ip_address,
            dt.name AS device_type_name,
            v.name AS vendor_name,
            dm.name AS model_name
        FROM equipment e
        LEFT JOIN ip_address ip ON e.ip_address = ip.Id
        LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
        LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
        LEFT JOIN device_models dm ON e.model_id = dm.id
        WHERE e.id_node = ? 
          AND e.ip_address = ? 
          AND e.Groupe = 2 
        ORDER BY e.Slot ASC
    ");
    $stmt->execute([$eq['id_node'], $eq['ip_address']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['members' => $members]);
    exit;
}
    else {
        echo json_encode(['error' => 'Unknown AJAX action']);
    }
}

// ---------- Основная страница ----------
$page = $_GET['page'] ?? 'nodes';

// Подключаем header (статистика для верхней панели)
require_once 'modules/header/header.php';

// Подготовка данных для вкладок
switch ($page) {
    case 'nodes':
        require_once 'modules/nodes_page/nodes_page.php';
        break;
    //  case 'phones':
    // require_once 'modules/phones_table/phones_table.php';
    // break;
    case 'checklist':
        require_once 'modules/checklist_table/checklist_table.php';
        break;
    case 'dashboard':
        require_once 'modules/dashboard/dashboard.php';
        break;
    default:
        $error = 'Страница не найдена'; 
    case 'warehouse':
    require_once 'modules/warehouse_page/warehouse_page.php';
    break;
// case 'printers':
//     require_once 'modules/printers_table/printers_table.php';
//     break;
case 'database_manager':
    require_once 'modules/knowledge_base/knowledge_base.php';
    break;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <script>
    (function() {
        var theme = localStorage.getItem('netinfra-theme');
        if (!theme) theme = 'dark';   // по умолчанию тёмная
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetInfra Manager</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="modules/header/header.css">
    <link rel="stylesheet" href="modules/sidebar/sidebar.css">
    <link rel="stylesheet" href="modules/nodes_page/nodes_page.css">
    <link rel="stylesheet" href="modules/nodes_page/stack/stack.css">
    <link rel="stylesheet" href="modules/nodes_page/equipment_details/equipment_details.css">
    <link rel="stylesheet" href="modules/rack_panel/rack_panel.css">
    <link rel="stylesheet" href="modules/add_form/add_form.css">
    <?php if ($page === 'dashboard'): ?>
    <link rel="stylesheet" href="modules/dashboard/dashboard.css">
    <script src="assets/js/chart.umd.min.js"></script>
    <?php endif; ?>
    <style>/* Контекстное меню */
.context-menu {
    display: none;
    position: fixed;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 6px;
    box-shadow: 2px 2px 10px rgba(0,0,0,0.15);
    z-index: 9999;
    min-width: 180px;
    padding: 5px 0;
}
.context-menu ul {
    list-style: none;
    margin: 0;
    padding: 0;
}
.context-menu ul li {
    padding: 8px 20px;
    cursor: pointer;
    font-size: 14px;
    color: #333;
}
.context-menu ul li:hover {
    background: #f0f0f0;
}
.context-menu ul li.separator {
    border-top: 1px solid #eee;
    margin-top: 5px;
    padding-top: 5px;
}
/* Плитки для выбора устройств стека (как в примере) */
.stack-tile-group {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.8rem;
    user-select: none;
    max-height: 320px;
    overflow-y: auto;
    padding: 0.3rem;
}

.checkbox-wrapper {
    position: relative;
    display: inline-block;
}

.checkbox-input {
    clip: rect(0 0 0 0);
    clip-path: inset(100%);
    height: 1px;
    overflow: hidden;
    position: absolute;
    white-space: nowrap;
    width: 1px;
}

.checkbox-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 7.5rem;
    min-height: 7.5rem;
    border-radius: 0.5rem;
    border: 2px solid var(--border-color);
    background-color: var(--bg-card);
    box-shadow: 0 5px 10px rgba(0,0,0,0.1);
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s;
    cursor: pointer;
    position: relative;
    padding: 0.6rem 0.4rem;
    color: var(--text-primary);
}

.checkbox-tile::before {
    content: "";
    position: absolute;
    display: block;
    width: 1.1rem;
    height: 1.1rem;
    border: 2px solid var(--border-color);
    background-color: var(--bg-card);
    border-radius: 50%;
    top: 0.25rem;
    left: 0.25rem;
    opacity: 0;
    transform: scale(0);
    transition: 0.25s ease;
}

.checkbox-tile:hover {
    border-color: var(--accent);
}

.checkbox-tile:hover::before {
    transform: scale(1);
    opacity: 1;
}

.checkbox-input:checked + .checkbox-tile {
    border-color: var(--accent);
    box-shadow: 0 5px 10px rgba(0,0,0,0.15);
}

.checkbox-input:checked + .checkbox-tile::before {
    opacity: 1;
    transform: scale(1);
    background-color: var(--accent);
    border-color: var(--accent);
}

.checkbox-input:focus-visible + .checkbox-tile {
    outline: 2px dashed var(--accent);
    outline-offset: 2px;
}

/* Текст внутри плитки */
.checkbox-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
    margin-bottom: 0.25rem;
    color: inherit;
}

.checkbox-detail {
    font-size: 0.65rem;
    text-align: center;
    color: var(--text-secondary);
    margin-top: 0.15rem;
}

/* Кнопка "Выбрать всё" */
.stack-select-all {
    margin-top: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    color: var(--text-primary);
    font-size: 0.85rem;
    background: var(--bg-card);
    padding: 0.4rem 1rem;
    border-radius: 2rem;
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
    width: fit-content;
}

.stack-select-all:hover {
    background: var(--table-hover);
    border-color: var(--accent);
}

.stack-select-all input[type="checkbox"] {
    accent-color: var(--accent);
    width: 1rem;
    height: 1rem;
}</style>
<script>window.isAdmin = <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'true' : 'false'; ?>;</script>
</head>

<body>
<div class="app-layout">
    <?php include 'modules/sidebar/sidebar_template.php'; ?>
    <div class="main-area">
        <?php include 'modules/header/header_template.php'; ?>
        <div id="content">
            <?php if (isset($error)): ?>
                <p><?= $error ?></p>
            <?php else: ?>
                <?php
                switch ($page) {
                    case 'nodes':
                        include 'modules/nodes_page/nodes_page_template.php';
                        break;
                    case 'phones':
                        include 'modules/phones_table/phones_table_template.php';
                        break;
                    case 'checklist':
                        include 'modules/checklist_table/checklist_table_template.php';
                        break;
                    case 'dashboard':
                        include 'modules/dashboard/dashboard_template.php';
                        break;
                    case 'warehouse':
                        include 'modules/warehouse_page/warehouse_page_template.php';
                        break;      
    }
    ?>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- Контекстное меню -->
<div class="context-menu" id="ctxMenu" style="display:none;"></div>

<!-- Остальные модальные окна -->
<?php include 'modules/add_form/add_form_modals.php'; ?>
<?php include 'modules/nodes_page/buildings/buildings_modal.php'; ?>

<!-- Модальное окно добавления модуля -->
<div class="add-form-modal" id="moduleAddModal">
    <div class="modal-content">
        <h3 class="modal-title">Новый модуль</h3>
        <form id="moduleAddForm" autocomplete="off">
            <div class="form-group">
                <label>Название</label>
                <input type="text" name="name" required placeholder="Например: SFP-10G-LR">
            </div>
            <div class="form-group">
                <label>Тип (модель)</label>
                <input type="text" name="type" placeholder="SFP, SFP+, QSFP...">
            </div>
            <div class="form-group sfp-only">
                <label>Длина волны</label>
                <input type="text" name="wavelength" placeholder="1310 нм">
            </div>
            <div class="form-group sfp-only">
                <label>Дистанция</label>
                <input type="text" name="distance" placeholder="10 км">
            </div>
            <div class="form-group">
                <label>Серийный номер</label>
                <input type="text" name="serial_number" placeholder="SN...">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeModuleDialog()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно добавления в чек-лист -->
<div class="add-form-modal" id="checklistModal">
    <div class="modal-content">
        <h3>Добавить в чек-лист</h3>
        <form id="checklistForm">
            <input type="hidden" name="equipment_id" id="cl-equipment-id">
            <div class="form-group">
                <label>Номер КУ(только число)</label>
                <input type="text" id="cl-ky" disabled>
            </div>
            <div class="form-group">
                <label>IP-адрес</label>
                <input type="text" id="cl-ip" disabled>
            </div>
            <div class="form-group">
                <label>Категория задачи</label>
                <select name="category_task_id" id="cl-category"></select>
            </div>
            <div class="form-group">
                <label>Тип задачи</label>
                <select name="type_task_id" id="cl-type"></select>
            </div>
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" id="cl-desc"></textarea>
            </div>
            <div class="form-group">
                <label>Ответственный</label>
                <select name="responsible_user_id" id="cl-user"></select>
            </div>
            <div class="form-group">
                <label>Срок</label>
                <input type="date" name="deadline" id="cl-deadline">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeChecklistModal()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>
<!-- Модальное окно для добавления справочников (вендоры, модели, прошивки и т.д.) -->


<!-- Модальное окно удаления -->
<div class="add-form-modal" id="deleteConfirmModal">
    <div class="modal-content">
        <h3>Удалить</h3>
        <p>Выберите действие:</p>
        <div class="form-group">
            <button class="btn danger" id="delete-equipment-btn">Удалить только устройство</button>
        </div>
        <div class="form-group">
            <button class="btn danger" id="delete-node-btn">Удалить КУ (оборудование останется без КУ)</button>
        </div>
        <div class="modal-actions">
            <button class="btn secondary" onclick="closeDeleteModal()">Отмена</button>
        </div>
    </div>
</div>
<
<!-- Модальное окно перемещения -->
<div class="add-form-modal" id="moveModal">
    <div class="modal-content">
        <h3>Переместить устройство</h3>
        <div class="move-options radio-tiles" id="move-direction-group">
            <div class="radio-container">
                <input type="radio" name="move_direction" value="warehouse" id="move-warehouse" class="radio-input" checked>
                <div class="radio-tile"><span class="radio-tile-label">На склад</span></div>
            </div>
            <div class="radio-container">
                <input type="radio" name="move_direction" value="another_node" id="move-node" class="radio-input">
                <div class="radio-tile"><span class="radio-tile-label">В КУ</span></div>
            </div>
        </div>

        <!-- Список устройств стека -->
        <div id="move-stack-list" style="display:none; margin-top:1rem;">
            <div class="stack-list" id="stack-members-checkboxes"></div>
            <label class="stack-select-all">
                <input type="checkbox" id="select-all-stack" checked>
                <span>Выбрать всё</span>
            </label>
        </div>

        <!-- Селект склада или узла -->
        <div id="move-warehouse-select" style="display:none; margin-top:1rem;">
            <label id="move-destination-label">Выберите склад</label>
            <select id="warehouseSelect"></select>
        </div>
        <div id="stack-options" style="display:none; margin-top:1rem;"></div>       

        <!-- Кнопки -->
        <div class="modal-actions" style="margin-top:1.5rem;">
            <button class="btn secondary" onclick="closeMoveModal()">Отмена</button>
            <button class="btn" id="move-submit-btn" style="display:none;" onclick="submitMove()">Переместить</button>
        </div>
    </div>
</div>
<div id="addModelModal" class="add-form-modal">
    <div class="modal-content">
        <h3>Добавить новую модель</h3>
        <form id="addModelForm">
            <div class="form-group">
                <label>Производитель:</label>
                <select id="addModelVendor" name="vendor_id" required>
                    <option value="">-- загрузка --</option>
                </select>
            </div>
            <div class="form-group">
                <label>Название модели:</label>
                <input type="text" id="addModelName" name="model_name" required>
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn">Сохранить</button>
                <button type="button" class="btn secondary" onclick="closeAddModelModal()">Отмена</button>
            </div>
        </form>
    </div>
</div>
<!-- Выдвижная панель стойки -->
<div class="right-panel" id="rightPanel">
    <div class="panel-header">
        <span id="panelTitle">Стойка</span>
        <span class="panel-close" id="panelClose">✕</span>
    </div>
    <div class="panel-body" id="panelBody">
        <div style="padding:1rem; text-align:center; color:var(--text-secondary);">Выберите оборудование для просмотра стойки</div>
    </div>
</div>
<!-- Кнопка-ручка для открытия панели -->
<div class="panel-tab" id="panelTab" title="Показать стойку">🗄️</div>

<script src="assets/js/scripts.js"></script>
<script src="modules/sidebar/sidebar.js"></script>
<script src="modules/common/toast.js"></script>
<script src="modules/nodes_page/buildings/buildings.js"></script>
<script src="modules/nodes_page/nodes_page.js"></script>
<?php if ($page === 'dashboard'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($chartData)): ?>
    new Chart(document.getElementById('chart-nodes-status'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chartData['nodes_status']['labels']) ?>,
            datasets: [{
                data: <?= json_encode($chartData['nodes_status']['data']) ?>,
                backgroundColor: ['#28a745','#ffc107','#dc3545'],
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('chart-devices-type'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chartData['devices_type']['labels']) ?>,
            datasets: [{
                data: <?= json_encode($chartData['devices_type']['data']) ?>,
                backgroundColor: ['#3b5998','#5bc0de','#f0ad4e','#d9534f','#5cb85c','#999'],
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('chart-phones-status'), {
        type: 'doughnut',
        data: {
            labels: ['Активные', 'Неактивные'],
            datasets: [{
                data: [<?= $activePhones ?>, <?= $inactivePhones ?>],
                backgroundColor: ['#28a745','#dc3545'],
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });

    ctx = document.getElementById('chart-equipment-status').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Активные', 'Неактивные'],
        datasets: [{
            data: [<?= $activeEquipment ?>, <?= $inactiveEquipment ?>],
            backgroundColor: ['#28a745', '#dc3545']
        }]
    },
    options: {
        plugins: { legend: { position: 'bottom' } }
    }
});

    new Chart(document.getElementById('chart-devices-vendor'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartData['devices_vendor']['labels']) ?>,
            datasets: [{
                label: 'Количество',
                data: <?= json_encode($chartData['devices_vendor']['data']) ?>,
                backgroundColor: '#3b5998',
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
    <?php endif; ?>
});

  if (localStorage.getItem('sidebarCollapsed') === 'true') {
    document.getElementById('sidebar').classList.add('collapsed');
  }
</script>
<?php endif; ?>
<?php include 'modules/nodes_page/equipment_details/equipment_details_template.php'; ?>
<script src="<?= htmlspecialchars($basePath ?? '') ?>modules/add_form/esc_handler.js"></script>

<!-- Валидация дубликатов полей оборудования -->
<script src="modules/nodes_page/equipment_validation.js"></script>
<script src="modules/nodes_page/equipment_details/equipment_details.js"></script>
<script src="modules/header/ping.js"></script>
<script src="modules/edit_form/edit_form.js"></script>
<script src="modules/common/table_sort.js"></script>
<script src="modules/warehouse_page/warehouse_stack_wizard.js"></script>
<!-- 1. Класс поискового селекта (не зависит ни от чего) -->
<script src="modules/common/searchable_select.js"></script>

<!-- 2. Вспомогательные утилиты (fetchJSON, escapeHtml, loadSearchableSelectOptions, и т.д.) -->
<script src="modules/common/utils.js"></script>

<!-- 3. Формы мета-справочников, зданий, локаций (они используются при построении форм) -->
<script src="modules/add_form/meta_forms.js"></script>

<!-- 4. Построитель формы узла -->
<script src="modules/add_form/node_form.js"></script>
<script src="modules/rack_panel/rack_panel.js"></script>

<!-- 5. Построитель досье оборудования (содержит buildEquipmentDossier и вспомогательные функции) -->
<script src="modules/add_form/equipment_dossier.js"></script>

<!-- 6. Логика стека (инициализация, добавление/редактирование устройств) -->
<script src="modules/nodes_page/stack/stack.js"></script>
<script src="modules/add_form/lldp_parser.js"></script>
<!-- 7. Ядро – открытие формы и обработка отправки (использует все предыдущие) -->
<script src="modules/add_form/add_form.js"></script>
<div class="toast-container" id="toastContainer"></div>
</body>
</html>