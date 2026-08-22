<?php
// ============================================================
// index.php – NetInfra Manager (единый файл)
// ============================================================
require_once __DIR__ . '/includes/helpers.php';
session_start();

// Выход
if (isset($_GET['logout'])) {
    // Журналируем выход ДО уничтожения сессии, иначе данные пользователя потеряются
    if (isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/config/db.php';
        require_once __DIR__ . '/includes/logger.php';
        logAction($pdo, 'logout', 'user', $_SESSION['user_id'], $_SESSION['login'] ?? '', 'Выход из системы');
    }
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

// Неавторизованным показываем форму входа.
// Роль 'user' допускается в приложение в режиме «только просмотр»:
// запись блокируется на сервере через includes/acl.php (requireAdmin),
// а элементы управления скрываются на клиенте по window.isAdmin.
if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="modules/warehouse_page/warehouse_page.css">
<link rel="stylesheet" href="modules/add_form/checkbox_google.css">

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
            <!-- Форма входа. Если учётной записи нужно сменить пароль,
                 поля нового пароля раскрываются прямо здесь, а логин и
                 текущий пароль остаются на месте.

                 Это не косметика: менеджер паролей сохраняет пару только
                 тогда, когда видит в одной форме и поле логина, и поле
                 нового пароля. Пока логин прятали, браузер запоминал
                 пароль без логина, и автозаполнение потом не работало. -->
            <div class="login-box">
                <h2 id="login-title">Вход в систему</h2>
                <div class="error" id="login-error"></div>

                <form id="login-form">
                    <div class="form-group">
                        <label>Логин</label>
                        <input type="text" name="login" id="login-login" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label id="login-password-label">Пароль</label>
                        <input type="password" name="password" id="login-password"
                               required autocomplete="current-password">
                    </div>

                    <div id="change-fields" style="display:none;">
                        <p class="login-hint">
                            Для этой учётной записи требуется сменить пароль при первом входе.
                        </p>
                        <div class="form-group">
                            <label>Новый пароль</label>
                            <input type="password" id="new-password"
                                   autocomplete="new-password" minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Подтвердите пароль</label>
                            <input type="password" id="confirm-password"
                                   autocomplete="new-password" minlength="6">
                        </div>
                    </div>

                    <button type="submit" id="login-submit">Войти</button>
                </form>
            </div>
            <script>
                var loginError  = document.getElementById('login-error');
                var changeMode  = false;   // раскрыты ли поля нового пароля

                function netError(e) {
                    return 'Сервер не ответил на запрос: '
                         + (e && e.message ? e.message : 'соединение прервано');
                }

                document.getElementById('login-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    loginError.textContent = '';

                    const login   = document.getElementById('login-login').value.trim();
                    const current = document.getElementById('login-password').value;

                    // ---------- Шаг 2: пароль меняем ----------
                    if (changeMode) {
                        const pass    = document.getElementById('new-password').value;
                        const confirm = document.getElementById('confirm-password').value;

                        if (pass !== confirm) {
                            loginError.textContent = 'Пароли не совпадают';
                            return;
                        }
                        if (pass.length < 6) {
                            loginError.textContent = 'Пароль должен быть не короче 6 символов';
                            return;
                        }
                        if (pass === current) {
                            loginError.textContent = 'Новый пароль совпадает с текущим';
                            return;
                        }

                        const fd = new FormData();
                        fd.append('current_password', current);
                        fd.append('new_password', pass);
                        fd.append('confirm_password', confirm);

                        try {
                            const res = await fetch('?ajax=change_password', { method: 'POST', body: fd });
                            const data = await res.json();
                            if (!data.success) {
                                loginError.textContent = data.error || 'Не удалось сменить пароль';
                                return;
                            }
                            // Перезагрузку даём браузеру заметить как успешную
                            // отправку формы — иначе предложения сохранить пароль не будет
                            location.reload();
                        } catch (err) {
                            loginError.textContent = netError(err);
                        }
                        return;
                    }

                    // ---------- Шаг 1: обычный вход ----------
                    const formData = new FormData();
                    formData.append('login', login);
                    formData.append('password', current);

                    try {
                        const res = await fetch('?ajax=auth', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (!data.success) {
                            loginError.textContent = data.error || 'Ошибка входа';
                            return;
                        }

                        if (data.must_change_password) {
                            // Логин и текущий пароль оставляем на месте: браузер
                            // должен видеть их вместе с новым паролем
                            changeMode = true;
                            document.getElementById('change-fields').style.display = '';
                            document.getElementById('login-title').textContent = 'Смена пароля';
                            document.getElementById('login-password-label').textContent = 'Текущий пароль';
                            document.getElementById('login-submit').textContent = 'Сменить пароль и войти';
                            document.getElementById('login-login').readOnly = true;
                            document.getElementById('login-password').readOnly = true;
                            document.getElementById('new-password').required = true;
                            document.getElementById('confirm-password').required = true;
                            document.getElementById('new-password').focus();
                            return;
                        }

                        // В приложение пускаем и админа, и пользователя (только просмотр)
                        location.reload();
                    } catch (err) {
                        loginError.textContent = netError(err);
                    }
                });
            </script>
    </body>
    </html>

    <?php
    exit; // Останавливаем вывод основного интерфейса
}

// Пароль не сменён — доступ к приложению закрыт.
// Проверка на сервере обязательна: без неё шаг смены пароля обходился бы
// обычным обновлением страницы. Сам запрос смены при этом пропускаем,
// иначе сменить пароль было бы нечем.
$mustChangePassword = !empty($_SESSION['must_change_password']);
// ============================================================
// index.php – NetInfra Manager (единый файл)
// ============================================================
require_once 'config/db.php';
$routes = require __DIR__ . '/modules/nodes_page/Routes/nodes_page_routes.php';
// Маршруты карты сети. Складываем через +=, а не array_merge: при
// совпадении ключа побеждает уже существующий маршрут страницы «Узлы»,
// и новый модуль не может незаметно перехватить чужой обработчик.
$routes += require __DIR__ . '/modules/network_map/Routes/network_map_routes.php';
// Периферия (принтеры) и ВКС: подсети, адресный план и импорт книг
$routes += require __DIR__ . '/modules/peripherals/Routes/peripherals_routes.php';
// Планирование видеоконференций. Отдельный сервис: своя папка kazvks/,
// своя база, свои пользователи и права — с учётом оборудования не связан
$routes += require __DIR__ . '/kazvks/routes.php';
// Рабочие документы: служебки, накладные, проекты и связи с объектами
$routes += require __DIR__ . '/modules/work_docs/Routes/work_docs_routes.php';
// Профиль сотрудника и его личный инструмент
$routes += require __DIR__ . '/modules/tools/Routes/tools_routes.php';
// Расходные материалы склада: коннекторы, патч-корды, крепёж
$routes += require __DIR__ . '/modules/consumables/Routes/consumables_routes.php';
// «Полезности»: как что настраивается
$routes += require __DIR__ . '/modules/help/Routes/help_routes.php';
// Универсальная форма: описание разделов и полей для оборудования,
// стеков и шкафов — одно на все способы заполнения
$routes += require __DIR__ . '/modules/uni_form/Routes/uni_form_routes.php';
// Чек-лист: задачи отдела, дозаполнение данных и отчёты о работах
$routes += require __DIR__ . '/modules/checklist/Routes/checklist_routes.php';
// Галерея отдела: снимки с выездов, монтажа и корпоративов
$routes += require __DIR__ . '/modules/gallery/Routes/gallery_routes.php';


// ---------- Обработка AJAX-запросов ----------
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_GET['ajax'];

// Пока пароль не сменён, из всего API доступна только сама смена
if ($mustChangePassword && $action !== 'change_password') {
    http_response_code(403);
    echo json_encode(['error' => 'Сначала смените пароль']);
    exit;
}

if (isset($routes[$action])) {
    require_once __DIR__ . '/' . $routes[$action];
    exit;
}

    
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


// get_rack обрабатывается через таблицу маршрутов (принимает equipment_id или node_id)
if ($_GET['ajax'] === 'swap_rack_units' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/nodes_page/api/UpdateData/swap_rack_units.php';
    exit;
}
if ($_GET['ajax'] === 'get_equipment_details' && isset($_GET['id'])) {
    require_once 'modules/nodes_page/equipment_details/equipment_details.php';
    exit;
}

if ($_GET['ajax'] === 'delete_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'modules/nodes_page/api/DeleteData/delete_item.php';
    exit;
}

// Список свободных IP-адресов (не занятых в оборудовании)
if ($_GET['ajax'] === 'get_free_ips') {
    $stmt = $pdo->query("SELECT Id, ip_address FROM ip_address WHERE Id NOT IN (SELECT ip_address FROM equipment WHERE ip_address IS NOT NULL) ORDER BY ip_address");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}


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
            $stmt = $pdo->prepare("UPDATE equipment SET id_node = ?, warehouse_id = NULL, ip_address = ?, hostname = ?, Slot = ? WHERE id = ?");
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


if ($_GET['ajax'] === 'get_warehouses') {
    require_once __DIR__ . '/modules/warehouse_page/api/GetData/get_warehouses.php';
    exit;
}



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


if ($_GET['ajax'] == 'add_ip') {
    require_once 'modules/nodes_page/api/AddData/add_ip.php';
    exit;
}
if ($_GET['ajax'] == 'add_model') {
    require_once 'modules/nodes_page/api/AddData/add_model.php';
    exit;
}

    if ($_GET['ajax'] === 'get_nodes') {
    require_once 'modules/nodes_page/api/GetData/get_nodes.php';
    exit;
}


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
          AND e.group_id IS NOT NULL
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

// Копии по расписанию. Планировщика на сервере нет, поэтому время
// проверяется при заходе на сайт: до этого расписание сохранялось,
// показывало «следующий запуск», но не срабатывало никогда — копий
// не появлялось вовсе.
require_once __DIR__ . '/modules/backup/schedule_tick.php';

// Подключаем header (статистика для верхней панели)
require_once 'modules/header/header.php';

// Нужен ли странице блок работы с оборудованием (узлы, шкафы, стеки,
// досье, пассивные устройства). Это два десятка скриптов и четыре
// таблицы стилей — на телефонах, в журнале и у пользователей они
// только замедляют загрузку.
$equipmentPages = ['nodes', 'warehouse', 'checklist', 'dashboard', 'monitoring'];
$needsEquipmentBundle = in_array($page, $equipmentPages, true);

// Страницы, доступные только администратору.
// «Настройки» сюда не входят: там же выбирается тема оформления, а это
// личная настройка каждого. Административные вкладки скрыты внутри
// страницы и не отдают данные обычным пользователям.
// Доступ к странице определяется правом page.<ключ>: право берётся из
// группы пользователя и перекрывается личным исключением. Раньше здесь
// был жёсткий список закрытых страниц, и закрыть одну страницу одному
// человеку было невозможно.
require_once __DIR__ . '/includes/acl.php';

// Свой профиль правом не закрывается: это карточка самого вошедшего, и
// отобрать у человека доступ к собственным данным незачем. Чужой профиль
// открыт на чтение — телефон и кабинет коллеги в справочнике и так видны,
// а правку чужого профиля не пускает уже сам обработчик.
$personalPages = ['profile'];

if ($page !== 'forbidden' && !in_array($page, $personalPages, true) && !can('page.' . $page)) {
    $error = 'Доступ закрыт: у вашей учётной записи нет права на просмотр раздела «'
           . permLabel('page.' . $page) . '». Обратитесь к суперадминистратору.';
    $page = 'forbidden';
}

// Вместо интерфейса показываем экран смены пароля, пока он не сменён
if ($mustChangePassword) {
    include __DIR__ . '/modules/users/force_password_change.php';
    exit;
}

// Подготовка данных для вкладок
switch ($page) {
    case 'nodes':
        require_once 'modules/nodes_page/nodes_page.php';
        break;
    case 'checklist':
        // Старый modules/checklist_table/ оставлен нетронутым: он читает
        // пустую таблицу checklist и заменяется этой страницей
        require_once 'modules/checklist/checklist_page.php';
        break;
    case 'gallery':
        require_once 'modules/gallery/gallery_page.php';
        break;
    case 'work_docs':
        require_once 'modules/work_docs/work_docs.php';
        break;
    case 'profile':
        require_once 'modules/tools/profile.php';
        break;
    case 'tools':
        require_once 'modules/tools/tools_page.php';
        break;
    case 'help':
        require_once 'modules/help/help.php';
        break;
    case 'dashboard':
        require_once 'modules/dashboard/dashboard.php';
        break;
    case 'monitoring':
        // Данные страница берёт из Zabbix через AJAX — готовить нечего
        break;
    case 'warehouse':
        require_once 'modules/warehouse_page/warehouse_page.php';
        break;
    case 'phones':
        require_once 'modules/phones_page/phones_page.php';
        break;
    case 'periphery':
        require_once 'modules/peripherals/periphery_page.php';
        break;
    case 'vks':
        require_once 'modules/peripherals/vks_page.php';
        break;
    case 'vks_calendar':
        // Планирование видеоконференций — отдельный сервис в kazvks/
        require_once 'kazvks/page.php';
        break;
    case 'database_manager':
        require_once 'modules/knowledge_base/knowledge_base.php';
        break;
    case 'users':
        require_once 'modules/users/users.php';
        break;
    case 'journal':
        require_once 'modules/journal/journal.php';
        break;
    case 'settings':
        require_once 'modules/settings/settings.php';
        break;
    case 'network_map':
        require_once 'modules/network_map/network_map.php';
        break;
    case 'reports':
        require_once 'modules/reports/reports.php';
        break;
    case 'graylog':
        require_once 'modules/graylog/graylog_page.php';
        break;
    case 'forbidden':
        // $error уже установлен выше
        break;
    default:
        $error = 'Страница не найдена';
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
        // «Современный стиль» применяем до отрисовки, чтобы не мигало
        if (localStorage.getItem('netinfra-modern-theme') === '1') {
            document.documentElement.classList.add('modern-theme');
        }
    })();
</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Вкладку подписываем страницей: в десятке открытых вкладок
         «NetInfra Manager» повсюду не помогает найти нужную -->
    <title><?= htmlspecialchars(pageTitle($page, false)) ?> — NetInfra</title>
    <?php require_once __DIR__ . '/includes/assets.php'; ?>
    <!-- Общие стили идут с отметкой времени правки: без неё браузер
         держит прежнюю версию до очистки кэша, и обновление оформления
         доезжает не до всех -->
    <link rel="stylesheet" href="<?= asset('assets/css/styles.css') ?>">
    <link rel="stylesheet" href="<?= asset('modules/header/header.css') ?>">
    <link rel="stylesheet" href="<?= asset('modules/sidebar/sidebar.css') ?>">
    <!-- Блок «Современный стиль» (можно удалить вместе с кнопкой в sidebar) -->
    <link rel="stylesheet" href="modules/sidebar/modern.css">
    <?php if ($needsEquipmentBundle): ?>
    <!-- Стили узлов, стеков и шкафов нужны не всем страницам -->
    <link rel="stylesheet" href="modules/nodes_page/nodes_page.css">
    <link rel="stylesheet" href="modules/nodes_page/stack/stack.css">
    <link rel="stylesheet" href="modules/nodes_page/equipment_details/equipment_details.css">
    <link rel="stylesheet" href="modules/rack_panel/rack_panel.css">
    <?php endif; ?>
    <link rel="stylesheet" href="modules/add_form/add_form.css">
    <!-- Единый выбор файла для всех форм (карточка телефона, формы, импорт) -->
    <link rel="stylesheet" href="modules/common/file_picker.css">
    <!-- Единый вид ошибок и предупреждений под полями форм -->
    <link rel="stylesheet" href="modules/common/form_field.css">
    <!-- Подвал с версией сборки -->
    <link rel="stylesheet" href="modules/footer/footer.css">
    <?php if ($page === 'dashboard'): ?>
    <link rel="stylesheet" href="modules/dashboard/dashboard.css">
    <script src="assets/js/chart.umd.min.js"></script>
    <?php endif; ?>
    <?php if ($page === 'vks_calendar'): ?>
    <!-- Сервис планирования ВКС: весь его код лежит в kazvks/ -->
    <link rel="stylesheet" href="kazvks/kazvks.css">
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
<script>
// Клиентские флаги только прячут элементы управления: настоящая проверка
// живёт на сервере в includes/acl.php, и обойти её подменой этих значений
// нельзя — каждый обработчик спрашивает право сам
window.isAdmin      = <?php echo isAdmin()      ? 'true' : 'false'; ?>;
window.isSuperAdmin = <?php echo isSuperAdmin() ? 'true' : 'false'; ?>;
</script>
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
                        // Две версии страницы живут рядом. Старая не
                        // тронута и остаётся полностью рабочей: узлы и
                        // оборудование заводятся в ней как раньше.
                        // Выбор запоминается за пользователем — см.
                        // $nodesView в modules/nodes_page/nodes_page.php
                        include ($nodesView ?? 'old') === 'new'
                            ? 'modules/nodes_page/nodes_page_template_v2.php'
                            : 'modules/nodes_page/nodes_page_template.php';
                        break;
                    case 'checklist':
                        include 'modules/checklist/checklist_template.php';
                        break;
                    case 'gallery':
                        include 'modules/gallery/gallery_template.php';
                        break;
                    case 'work_docs':
                        include 'modules/work_docs/work_docs_template.php';
                        break;
                    case 'profile':
                        include 'modules/tools/profile_template.php';
                        break;
                    case 'tools':
                        include 'modules/tools/tools_page_template.php';
                        break;
                    case 'help':
                        include 'modules/help/help_template.php';
                        break;
                    case 'dashboard':
                        include 'modules/dashboard/dashboard_template.php';
                        break;
                    case 'monitoring':
                        include 'modules/monitoring/monitoring_template.php';
                        break;
                    case 'warehouse':
                        include 'modules/warehouse_page/warehouse_page_template.php';
                        break;
                    case 'users':
                        include 'modules/users/users_template.php';
                        break;
                    case 'database_manager':
                        include 'modules/knowledge_base/knowledge_base_template.php';
                        break;
                    case 'journal':
                        include 'modules/journal/journal_template.php';
                        break;
                    case 'phones':
                        include 'modules/phones_page/phones_page_template.php';
                        break;
                    case 'periphery':
                        include 'modules/peripherals/periphery_page_template.php';
                        break;
                    case 'vks':
                        include 'modules/peripherals/vks_page_template.php';
                        break;
                    case 'vks_calendar':
                        include 'kazvks/template.php';
                        break;
                    case 'settings':
                        include 'modules/settings/settings_template.php';
                        break;
                    case 'network_map':
                        include 'modules/network_map/network_map_template.php';
                        break;
                    case 'reports':
                        include 'modules/reports/reports_template.php';
                        break;
                    case 'graylog':
                        include 'modules/graylog/graylog_template.php';
                        break;
                }
    ?>
            <?php endif; ?>
        </div>
        <?php include 'modules/footer/footer_template.php'; ?>
    </div>
</div>


<!-- Контекстное меню -->
<div class="context-menu" id="ctxMenu" style="display:none;"></div>

<!-- Остальные модальные окна. Формы узлов, шкафов и пассивного
     оборудования нужны только страницам с оборудованием: на телефонах
     и в журнале это лишняя разметка, которую браузер всё равно разбирает -->
<?php if ($needsEquipmentBundle): ?>
<?php include 'modules/add_form/add_form_modals.php'; ?>
<?php include 'modules/passive_devices/passive_modals.php'; ?>
<?php include 'modules/nodes_page/buildings/buildings_modal.php'; ?>
<?php // Универсальная форма. Пока идёт рядом со старыми: вызовы
      // переводятся по одному, и до перевода ничего не ломается ?>
<?php include 'modules/uni_form/uni_form_template.php'; ?>
<?php endif; ?>

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

<?php if ($needsEquipmentBundle): ?>
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
<?php include 'modules/rack_panel/rack_panel_template.php'; ?>
<?php endif; ?>

<script src="assets/js/scripts.js"></script>
<script src="modules/sidebar/sidebar.js"></script>
<script src="modules/common/toast.js"></script>
<script src="modules/common/acl_client.js"></script>
<?php if ($needsEquipmentBundle): ?>
<script src="modules/nodes_page/buildings/buildings.js"></script>
<script src="modules/nodes_page/nodes_page.js"></script>
<?php // Новая версия страницы узлов: карточка со стеками, шкафами и
      // модулями. Строго после nodes_page.js — она подменяет раскрытие
      // строки, а всё остальное оставляет прежнему скрипту
      if ($page === 'nodes' && ($nodesView ?? 'old') === 'new'): ?>
<script src="modules/nodes_page/nodes_page_v2.js"></script>
<?php endif; ?>
<?php endif; ?>
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
<?php if ($needsEquipmentBundle): ?>
<?php include 'modules/nodes_page/equipment_details/equipment_details_template.php'; ?>
<?php endif; ?>

<!-- ---------- Общее ядро: нужно на любой странице ---------- -->
<script src="<?= htmlspecialchars($basePath ?? '') ?>modules/add_form/esc_handler.js"></script>
<script src="modules/common/table_sort.js"></script>
<!-- 1. Класс поискового селекта (не зависит ни от чего) -->
<script src="modules/common/searchable_select.js"></script>
<!-- 2. Вспомогательные утилиты (fetchJSON, escapeHtml, loadSearchableSelectOptions, и т.д.) -->
<script src="modules/common/utils.js"></script>
<!-- 2а. Оформление полей выбора файла: enhanceFileInputs() -->
<script src="modules/common/file_picker.js"></script>
<!-- 2б. Проверка полей на дубликаты: общая для телефонов и оборудования.
     Должна идти до форм, которые её вызывают -->
<script src="modules/common/duplicate_check.js"></script>

<?php if ($needsEquipmentBundle): ?>
<!-- ---------- Блок работы с оборудованием ----------
     Двадцать скриптов нужны только страницам, где есть узлы, шкафы
     и стеки. На телефонах, в журнале и у пользователей они лишние —
     это два десятка запросов на каждую загрузку страницы. -->

<!-- Валидация дубликатов полей оборудования -->
<script src="modules/nodes_page/equipment_validation.js"></script>
<script src="modules/nodes_page/equipment_details/equipment_details.js"></script>
<script src="modules/header/ping.js"></script>
<script src="modules/edit_form/edit_form.js"></script>
<script src="modules/warehouse_page/warehouse_stack_wizard.js"></script>

<!-- 3. Формы мета-справочников, зданий, локаций (они используются при построении форм) -->
<script src="modules/add_form/meta_forms.js"></script>

<!-- 4. Построитель формы узла -->
<script src="modules/add_form/node_form.js"></script>
<script src="modules/add_form/rack_form.js"></script>
<!-- Формы пассивного оборудования — до панели шкафа, она их вызывает -->
<script src="modules/passive_devices/passive_devices.js"></script>
<script src="modules/rack_panel/rack_panel.js"></script>

<!-- 5. Построитель досье оборудования (содержит buildEquipmentDossier и вспомогательные функции) -->
<!-- Плитка модуля общая для досье, вставки вывода и формы правки — идёт первой -->
<script src="modules/add_form/module_tiles.js"></script>
<!-- Карта профилей типов: что спрашивать у коммутатора, а что у ИБП.
     Досье читает её при построении, поэтому идёт раньше -->
<script src="modules/add_form/type_profiles.js"></script>
<script src="modules/add_form/equipment_dossier.js"></script>

<!-- 6. Логика стека (инициализация, участники строками, сохранение) -->
<!-- Таблица участников объявляет window.StackMembers, которым
     пользуется stack.js, — поэтому идёт первой -->
<script src="modules/nodes_page/stack/stack_members.js"></script>
<script src="modules/nodes_page/stack/stack.js"></script>
<script src="modules/add_form/module_paste.js"></script>
<!-- 7. Ядро – открытие формы и обработка отправки (использует все предыдущие) -->
<script src="modules/add_form/add_form.js"></script>
<?php endif; ?>

<?php if ($page === 'network_map'): ?>
<!-- ---------- Карта сети ----------
     Порядок обязателен: холст и отрисовка объявляют то, чем пользуется
     ядро страницы. Скрипты нужны только этой странице и на остальные
     не подключаются. -->
<?php
// pdf.js подключается, только если сборка положена в assets/js. Без неё
// подложка загружается готовой картинкой — это работает без зависимостей
if (is_file(__DIR__ . '/assets/js/pdf.min.js')): ?>
<script src="assets/js/pdf.min.js"></script>
<?php endif; ?>
<script src="modules/network_map/js/map_viewport.js"></script>
<script src="modules/network_map/js/map_render.js"></script>
<script src="modules/network_map/js/map_nodes.js"></script>
<script src="modules/network_map/js/map_links.js"></script>
<script src="modules/network_map/js/map_fiber.js"></script>
<script src="modules/network_map/js/map_views.js"></script>
<script src="modules/network_map/js/map_drill.js"></script>
<script src="modules/network_map/js/map_context.js"></script>
<script src="modules/network_map/js/map_tools.js"></script>
<!-- Палитра объектов: рельс, аккордеон, перетаскивание на холст.
     Идёт после ядра — берёт из него состояние карты -->
<script src="modules/network_map/js/map_palette.js"></script>
<script src="modules/network_map/js/map_floors.js"></script>
<script src="modules/network_map/js/map_backdrop.js"></script>
<script src="modules/network_map/js/map_shape_editor.js"></script>
<script src="modules/network_map/js/map_edit.js"></script>
<script src="modules/network_map/js/map_core.js"></script>
<?php endif; ?>

<div class="toast-container" id="toastContainer"></div>
</body>
</html>