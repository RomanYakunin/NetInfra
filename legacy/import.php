<?php
// ==================== НАСТРОЙКИ ПОДКЛЮЧЕНИЯ К БД ====================
$host = 'netinfra';
$dbname = 'NetInfrastructure';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// ==================== ЗАГРУЗКА ФАЙЛА ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    require_once 'autoload.php';

    $upload = $_FILES['excel_file'];
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        die('Ошибка загрузки файла.');
    }

    // Подключаемся к БД
    $mysqli = new mysqli($host, $username, $password, $dbname);
    if ($mysqli->connect_error) {
        die('Ошибка подключения: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');

    try {
        $spreadsheet = \PhpSpreadsheet\src\PhpSpreadsheet\IOFactory::load($upload['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
    } catch (\Exception $e) {
        die('Ошибка чтения Excel: ' . $e->getMessage());
    }

    // Удаляем заголовок (первую строку)
    array_shift($rows);

    // ==================== СБОР ДАННЫХ ====================
    $devices = [];          // массив устройств для вставки
    $kuGroups = [];         // группировка по КУ для определения стека
    $firmwares = [];        // уникальные прошивки

    foreach ($rows as $row) {
        // Пропускаем полностью пустые строки
        if (empty(array_filter($row))) continue;

        // Маппинг столбцов (индексы с 0)
        $ky         = (int)trim($row[0]);            // A
        $isStack    = trim($row[1]);                 // B
        $ipRaw      = trim($row[2]);                 // C
        $action     = trim($row[3]);                 // D
        $slotRaw    = trim($row[4]);                 // E
        // $vlan    = trim($row[5]);                 // F - не используется
        $hostname   = trim($row[6]);                 // G
        // $newIp ... пропускаем, не нужны
        $vendor     = trim($row[15]);                // P
        $model      = trim($row[16]);                // Q
        $mac        = strtolower(trim($row[17]));    // R
        $fw         = trim($row[21]);                // V
        $serial     = trim($row[22]);                // W

        // Если нет серийника, пропускаем (или генерируем?)
        if (empty($serial)) continue;

        // Корректировка MAC (на случай, если без двоеточий)
        if (strpos($mac, ':') === false && strlen($mac) == 12) {
            $mac = implode(':', str_split($mac, 2));
        }

        // Запоминаем прошивку
        if (!empty($fw)) {
            $firmwares[$fw] = true;
        }

        // Определяем слот
        $slot = ($slotRaw !== '' && is_numeric($slotRaw)) ? (int)$slotRaw : null;

        // Группируем по КУ для определения стека
        $kuGroups[$ky][] = [
            'serial'   => $serial,
            'ip'       => $ipRaw,
            'hostname' => $hostname,
            'slot'     => $slot,
            'vendor'   => $vendor,
            'model'    => $model,
            'mac'      => $mac,
            'fw'       => $fw,
            'action'   => $action,
            'isStackMark' => $isStack,   // '+' или пусто
        ];
    }

    // ==================== ФОРМИРОВАНИЕ SQL ====================
    $sql = "";
    $sql .= "-- Вставка новых прошивок\n";
    foreach (array_keys($firmwares) as $fwName) {
        $fwNameEsc = $mysqli->real_escape_string($fwName);
        $sql .= "INSERT INTO firmwares (name)\n";
        $sql .= "SELECT '$fwNameEsc'\n";
        $sql .= "WHERE NOT EXISTS (SELECT 1 FROM firmwares WHERE name = '$fwNameEsc');\n";
    }

    $sql .= "\n-- Вставка устройств\n";
    $sql .= "INSERT INTO equipment (status, Groupe, ip_address, hostname, id_node, Slot, device_type_id, vendor_id, model_id, serial_number, mac_address, firmwares, Annotation)\n";
    $sql .= "SELECT\n";
    $sql .= "    'inactive',\n";
    $sql .= "    CASE\n";

    // Строим WHEN для определения Groupe
    foreach ($kuGroups as $ky => $devs) {
        $count = count($devs);
        $stack = false;
        // Стек, если устройств > 1 или явно стоит '+'
        foreach ($devs as $d) {
            if ($d['isStackMark'] === '+' || $count > 1) {
                $stack = true;
                break;
            }
        }
        $groupe = $stack ? 2 : 1;
        // Для каждого устройства этого КУ
        foreach ($devs as $d) {
            // Добавим в WHEN позже, но проще построить весь запрос с UNION ALL
        }
    }

    // Упростим: сформируем список строк с готовыми значениями Groupe
    $deviceRows = [];
    $processedKUs = [];
    foreach ($kuGroups as $ky => $devs) {
        $count = count($devs);
        $stack = false;
        foreach ($devs as $d) {
            if ($d['isStackMark'] === '+' || $count > 1) {
                $stack = true;
                break;
            }
        }
        $groupe = $stack ? 2 : 1;
        foreach ($devs as $d) {
            $deviceRows[] = array_merge($d, ['ky' => $ky, 'groupe' => $groupe]);
        }
        $processedKUs[] = $ky;
    }

    // Теперь строим UNION ALL всех строк с выборками ID
    $unionParts = [];
    foreach ($deviceRows as $dev) {
        $ky = $dev['ky'];
        $ipEsc = $mysqli->real_escape_string($dev['ip']);
        $hostEsc = $mysqli->real_escape_string($dev['hostname']);
        $slotVal = is_null($dev['slot']) ? 'NULL' : (int)$dev['slot'];
        $vendorEsc = $mysqli->real_escape_string($dev['vendor']);
        $modelEsc = $mysqli->real_escape_string($dev['model']);
        $macEsc = $mysqli->real_escape_string($dev['mac']);
        $fwEsc = $mysqli->real_escape_string($dev['fw']);
        $serialEsc = $mysqli->real_escape_string($dev['serial']);
        $actionEsc = $mysqli->real_escape_string($dev['action']);

        $unionParts[] = "    SELECT 'inactive', {$dev['groupe']}, (SELECT Id FROM ip_address WHERE ip_address = '$ipEsc' LIMIT 1), '$hostEsc', (SELECT id_node FROM nodes WHERE KY_number = $ky LIMIT 1), $slotVal, 1, (SELECT id_vendor FROM vendors WHERE name = '$vendorEsc' LIMIT 1), (SELECT id FROM device_models WHERE name = '$modelEsc' LIMIT 1), '$serialEsc', '$macEsc', (SELECT id_firmware FROM firmwares WHERE name = '$fwEsc' LIMIT 1), '$actionEsc'";
    }

    $sql .= "    -- все варианты уже перечислены ниже через UNION\n";
    $sql .= implode("\n    UNION ALL\n", $unionParts);
    $sql .= "\nFROM DUAL\n";
    $sql .= "WHERE NOT EXISTS (SELECT 1 FROM equipment e WHERE e.serial_number = serial_number);\n";

    // Обновление счетчиков
    $sql .= "\n-- Обновление счетчиков устройств в узлах\n";
    $kuList = implode(',', array_unique($processedKUs));
    $sql .= "UPDATE nodes n\n";
    $sql .= "SET device_count = (\n";
    $sql .= "    SELECT COUNT(*) FROM equipment e WHERE e.id_node = n.id_node\n";
    $sql .= ")\n";
    $sql .= "WHERE n.KY_number IN ($kuList);\n";

    // ==================== ВЫВОД SQL ====================
    header('Content-Type: text/plain; charset=utf-8');
    echo $sql;
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Генератор SQL из Excel</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; }
        input[type="file"] { margin-top: 5px; }
        button { padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .info { background: #e9f5ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Конвертер Excel → SQL (NetInfrastructure)</h1>
    <div class="info">
        <p>Загрузите файл <strong>.xlsx</strong> с колонками в порядке:</p>
        <ol>
            <li>КУ</li>
            <li>КУ - 1 логическое устройство доступа (+ если стек)</li>
            <li>IP-адрес</li>
            <li>Что необходимо сделать</li>
            <li>Стек/слот</li>
            <li>VLAN</li>
            <li>Имя хоста</li>
            <li>Новый IP-адрес (не используется)</li>
            <li>Новый VLAN (не используется)</li>
            <li>Новое имя хоста (не используется)</li>
            <li>Uplink Host IP (не используется)</li>
            <li>Что необходимо сделать (дубль, не используется)</li>
            <li>Корпус (не используется)</li>
            <li>Расположение (не используется)</li>
            <li>Тип устройства (ожидается "Коммутатор")</li>
            <li>Вендор</li>
            <li>Модель устройства</li>
            <li>MAC адрес</li>
            <li>Артикул (CLEI) (не используется)</li>
            <li>PoE (не используется)</li>
            <li>Модель БП (не используется)</li>
            <li>Прошивка</li>
            <li>Серийный №</li>
        </ol>
        <p>Первая строка — заголовки (будут пропущены).</p>
    </div>
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="excel_file">Выберите Excel-файл (.xlsx):</label>
            <input type="file" name="excel_file" id="excel_file" accept=".xlsx" required>
        </div>
        <button type="submit">Сгенерировать SQL</button>
    </form>
</body>
</html>