<?php
/**
 * modules/nodes_page/api/GetData/snmp_query.php
 *
 * Выполняет SNMP-опрос оборудования и возвращает результат в JSON.
 * НИЧЕГО НЕ СОХРАНЯЕТ В БД — данные только отображаются в интерфейсе.
 *
 * Параметры POST:
 *   ip_address — IP опрашиваемого устройства
 *   community  — SNMP community (v2c)
 *   query_type — sysName | sysDescr | serial | mac | lldp | ports | all
 *   vendor     — (необязательно) производитель для выбора OID
 */
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAuth();   // опрос доступен любому авторизованному: это чтение, не запись
require_once dirname(__FILE__, 5) . '/config/snmp_oids.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ---------------- Валидация входных данных ----------------
$ip          = trim($_POST['ip_address'] ?? '');
$community   = trim($_POST['community'] ?? 'public');
$queryType   = trim($_POST['query_type'] ?? '');
$vendor      = snmpNormalizeVendor($_POST['vendor'] ?? '');
$version     = trim($_POST['version'] ?? '2c');        // '2c' | '3'
$equipmentId = (int)($_POST['equipment_id'] ?? 0);

// Учётные данные для SNMPv3. Если клиент их не прислал, берём с сервера:
// сначала персональные (equipment_credentials), затем стандартные для
// производителя (vendor_defaults). Пароль при этом не уходит в браузер.
$snmpUser = trim($_POST['snmp_user'] ?? '');
$snmpPass = (string)($_POST['snmp_password'] ?? '');

if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode(['error' => 'Некорректный IP-адрес устройства']);
    exit;
}

if (!in_array($version, ['2c', '3'], true)) {
    echo json_encode(['error' => 'Поддерживаются версии SNMP 2c и 3']);
    exit;
}

// Значения уходят в командную строку. Помимо escapeshellarg ограничиваем
// набор символов — правила кавычек в cmd.exe нетривиальны, и это исключает
// любые сюрпризы с экранированием.
$SAFE_ARG = '/^[A-Za-z0-9_\-.@]{1,64}$/';

if ($version === '2c') {
    if ($community === '' || !preg_match($SAFE_ARG, $community)) {
        echo json_encode(['error' => 'Community-строка: допустимы латиница, цифры и символы _-.@ (до 64 символов)']);
        exit;
    }
} else {
    // SNMPv3: если логин/пароль не переданы явно — подставляем из БД
    if ($snmpUser === '' && $equipmentId > 0) {
        require_once dirname(__FILE__, 5) . '/config/db.php';
        try {
            // 1) Персональные учётные данные устройства
            $st = $pdo->prepare("SELECT local_admin_login, local_admin_password
                                 FROM equipment_credentials WHERE equipment_id = ?");
            $st->execute([$equipmentId]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $snmpUser = (string)$row['local_admin_login'];
                $snmpPass = (string)$row['local_admin_password'];
            }
            // 2) Иначе — стандартные для производителя
            if ($snmpUser === '') {
                $st = $pdo->prepare("SELECT vd.default_login, vd.default_password
                                     FROM equipment e
                                     JOIN vendor_defaults vd ON vd.vendor_id = e.vendor_id
                                     WHERE e.id = ? AND vd.service_name = 'local_admin'
                                     LIMIT 1");
                $st->execute([$equipmentId]);
                if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $snmpUser = (string)$row['default_login'];
                    $snmpPass = (string)$row['default_password'];
                }
            }
        } catch (PDOException $e) {
            // Ошибку чтения учётных данных не раскрываем — ниже сработает проверка на пустой логин
        }
    }

    if ($snmpUser === '') {
        echo json_encode([
            'error'       => 'Для этого устройства не найдены учётные данные. Введите логин и пароль вручную.',
            'need_credentials' => true,
        ]);
        exit;
    }
    if (!preg_match($SAFE_ARG, $snmpUser)) {
        echo json_encode(['error' => 'Логин: допустимы латиница, цифры и символы _-.@ (до 64 символов)']);
        exit;
    }
    if ($snmpPass !== '' && !preg_match('/^[\x21-\x7E]{1,64}$/', $snmpPass)) {
        echo json_encode(['error' => 'Пароль содержит недопустимые символы (нужны печатаемые ASCII, до 64)']);
        exit;
    }
    if (strlen($snmpPass) < 8) {
        // Требование SNMPv3 (RFC 3414): пароль аутентификации не короче 8 символов
        echo json_encode([
            'error'            => 'Для SNMPv3 пароль должен быть не короче 8 символов',
            'need_credentials' => true,
        ]);
        exit;
    }
}

$oidMap = snmpOidMap();
if (!isset($oidMap[$queryType])) {
    echo json_encode(['error' => 'Неизвестный тип запроса']);
    exit;
}
$cfg = $oidMap[$queryType];

// OID: переопределение под конкретного производителя, иначе значение по умолчанию
$oid = $cfg['oid'];
if ($vendor !== '' && !empty($cfg['vendors'][$vendor])) {
    $oid = $cfg['vendors'][$vendor];
}

// ---------------- Путь 1: встроенное расширение PHP ----------------
// Если php_snmp включён, внешние утилиты не нужны вовсе: работаем напрямую
// через функции PHP. Это и безопаснее (нет вызова оболочки), и переносимо —
// ничего не надо класть в проект.
if (extension_loaded('snmp')) {
    // Числовой вывод OID: не зависим от наличия MIB-файлов и не ловим
    // предупреждения «Cannot find module», которые сломали бы JSON
    if (function_exists('snmp_set_oid_output_format')) {
        @snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
    }
    @snmp_set_quick_print(true);
    @snmp_set_enum_print(false);

    $timeoutUs = ((int)SNMP_TIMEOUT) * 1000000;
    $retries   = (int)SNMP_RETRIES;

    $result = null;
    // Предупреждения PHP гасим: об ошибке судим по false и сообщаем сами
    if ($version === '3') {
        $result = $cfg['mode'] === 'get'
            ? @snmp3_get($ip, $snmpUser, 'authNoPriv', 'MD5', $snmpPass, '', '', $oid, $timeoutUs, $retries)
            : @snmp3_real_walk($ip, $snmpUser, 'authNoPriv', 'MD5', $snmpPass, '', '', $oid, $timeoutUs, $retries);
    } else {
        $result = $cfg['mode'] === 'get'
            ? @snmp2_get($ip, $community, $oid, $timeoutUs, $retries)
            : @snmp2_real_walk($ip, $community, $oid, $timeoutUs, $retries);
    }

    if ($result === false || $result === null) {
        $err = mb_strtolower((string)(error_get_last()['message'] ?? ''));
        if (strpos($err, 'authentication') !== false || strpos($err, 'community') !== false
            || strpos($err, 'unknown user') !== false || strpos($err, 'security') !== false) {
            echo json_encode([
                'error' => $version === '3'
                    ? 'Логин или пароль не подходят для этого устройства (SNMPv3)'
                    : 'Community-строка не подходит для этого устройства',
                'auth_failed' => true,
            ]);
            exit;
        }
        echo json_encode(['error' => 'Устройство ' . $ip . ' не ответило на SNMP-запрос (таймаут, недоступность или запрет в ACL)']);
        exit;
    }

    // Приводим результат к тем же строкам «OID значение», что и вывод утилит,
    // чтобы переиспользовать готовые парсеры
    $lines = [];
    if (is_array($result)) {
        foreach ($result as $k => $v) {
            $lines[] = ltrim((string)$k, '.') . ' ' . (string)$v;
        }
    } else {
        $lines[] = ltrim((string)$oid, '.') . ' ' . (string)$result;
    }
    if (!empty($cfg['limit'])) {
        $lines = array_slice($lines, 0, (int)$cfg['limit']);
    }

    $parser = $cfg['parser'];
    $rows = function_exists($parser) ? $parser($lines, $cfg, $oid) : snmpParseRaw($lines, $cfg, $oid);

    echo json_encode([
        'success' => true,
        'label'   => $cfg['label'],
        'columns' => $cfg['columns'],
        'rows'    => $rows,
        'count'   => count($rows),
        'oid'     => $oid,
        'via'     => 'php-snmp',
    ]);
    exit;
}

// ---------------- Путь 2: внешние утилиты Net-SNMP ----------------
$binary = $cfg['mode'] === 'get' ? SNMPGET_PATH : SNMPWALK_PATH;

/**
 * Утилита доступна, если это существующий файл либо команда из PATH.
 */
function snmpBinaryAvailable($binary)
{
    if (is_file($binary)) return true;
    // Простая команда без пути — проверяем через PATH
    // strpos, а не str_contains: рабочий PHP — 7.4
    if (strpos($binary, DIRECTORY_SEPARATOR) === false && strpos($binary, '/') === false) {
        $out = @shell_exec('where ' . escapeshellarg($binary) . ' 2>NUL');
        return !empty(trim((string)$out));
    }
    return false;
}

if (!snmpBinaryAvailable($binary)) {
    echo json_encode([
        'error' => 'Утилита Net-SNMP не найдена: ' . $binary
                 . '. Установите Net-SNMP (см. инструкцию в config/snmp_oids.php) '
                 . 'или поправьте константы SNMPGET_PATH / SNMPWALK_PATH.',
        'not_installed' => true,
    ]);
    exit;
}

if (!function_exists('shell_exec') || in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
    echo json_encode(['error' => 'Функция shell_exec отключена в PHP — SNMP-опрос невозможен']);
    exit;
}

// ---------------- Выполнение запроса ----------------
// -v2c   версия протокола
// -c     community
// -t/-r  таймаут и повторы, чтобы недоступный узел не подвешивал запрос
// -O n   числовые OID (стабильный разбор без зависимости от установленных MIB)
// -O q   краткий вывод «OID значение»
if ($version === '3') {
    // authNoPriv: аутентификация по паролю без шифрования данных.
    // Логин/пароль берутся из БД (equipment_credentials -> vendor_defaults)
    // либо вводятся пользователем и в браузере не сохраняются.
    $auth = ' -v3 -l authNoPriv'
          . ' -u ' . escapeshellarg($snmpUser)
          . ' -a MD5'
          . ' -A ' . escapeshellarg($snmpPass);
} else {
    $auth = ' -v2c -c ' . escapeshellarg($community);
}

$cmd = escapeshellarg($binary)
     . $auth
     . ' -t ' . (int)SNMP_TIMEOUT
     . ' -r ' . (int)SNMP_RETRIES
     . ' -On -Oq '
     . escapeshellarg($ip) . ' '
     . escapeshellarg($oid)
     . ' 2>&1';

$raw = @shell_exec($cmd);

if ($raw === null || trim((string)$raw) === '') {
    echo json_encode(['error' => 'Устройство не ответило на SNMP-запрос (проверьте доступность, community и ACL)']);
    exit;
}
$raw = trim($raw);

// Типичные ответы Net-SNMP об ошибках
$lower = mb_strtolower($raw);
// strpos, а не str_contains: рабочий PHP — 7.4
if (strpos($lower, 'timeout') !== false) {
    echo json_encode(['error' => 'Таймаут: устройство ' . $ip . ' не ответило. Проверьте доступность и параметры доступа.']);
    exit;
}
if (strpos($lower, 'no such object') !== false || strpos($lower, 'no such instance') !== false) {
    echo json_encode(['error' => 'Устройство не поддерживает этот OID (' . $oid . ')']);
    exit;
}
// Неверные учётные данные: v2c — community, v3 — пользователь/пароль
if (strpos($lower, 'authentication failure') !== false
    || strpos($lower, 'bad community') !== false
    || strpos($lower, 'unknown user name') !== false
    || strpos($lower, 'authenticationfailure') !== false
    || strpos($lower, 'wrong digest') !== false
    || strpos($lower, 'authorizationerror') !== false) {
    echo json_encode([
        'error'     => $version === '3'
            ? 'Логин или пароль не подходят для этого устройства (SNMPv3)'
            : 'Community-строка не подходит для этого устройства',
        'auth_failed' => true,   // клиент предложит ввести учётные данные вручную
    ]);
    exit;
}
if (strpos($lower, 'unknown host') !== false || strpos($lower, 'cannot resolve') !== false) {
    echo json_encode(['error' => 'Не удалось обратиться к хосту ' . $ip]);
    exit;
}

// ---------------- Разбор вывода ----------------
$lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), fn($l) => $l !== ''));
if (!empty($cfg['limit'])) {
    $lines = array_slice($lines, 0, (int)$cfg['limit']);
}

$parser = $cfg['parser'];
$rows = function_exists($parser) ? $parser($lines, $cfg, $oid) : snmpParseRaw($lines, $cfg, $oid);

echo json_encode([
    'success' => true,
    'label'   => $cfg['label'],
    'columns' => $cfg['columns'],
    'rows'    => $rows,
    'count'   => count($rows),
    'oid'     => $oid,
]);


// ============================ ПАРСЕРЫ ============================

/** Убирает кавычки и служебные префиксы из значения Net-SNMP. */
function snmpCleanValue($value)
{
    $v = trim((string)$value);
    $v = preg_replace('/^(STRING|INTEGER|Hex-STRING|OID|Timeticks|Gauge32|Counter32|IpAddress)\s*:\s*/i', '', $v);
    return trim($v, " \t\"");
}

/** Разбивает строку «OID значение» на пару. */
function snmpSplitLine($line)
{
    $parts = preg_split('/\s+/', trim($line), 2);
    return [$parts[0] ?? '', snmpCleanValue($parts[1] ?? '')];
}

/** Скалярный ответ (sysName, sysDescr): одна строка «Параметр | Значение». */
function snmpParseScalar($lines, $cfg, $oid)
{
    [, $value] = snmpSplitLine($lines[0] ?? '');
    return [[$cfg['label'], $value]];
}

/** Табличный ответ: последний сегмент OID как индекс + значение. */
function snmpParseIndexed($lines, $cfg, $oid)
{
    $rows = [];
    foreach ($lines as $line) {
        [$lineOid, $value] = snmpSplitLine($line);
        if ($value === '') continue;               // пустые значения не показываем
        $index = substr($lineOid, strrpos($lineOid, '.') + 1);
        $rows[] = [$index, $value];
    }
    return $rows;
}

/**
 * LLDP-соседи: собираем из lldpRemTable три колонки.
 * Индекс строки таблицы — «...<столбец>.<timeMark>.<localPort>.<index>»,
 * поэтому локальный порт берём как предпоследний сегмент.
 */
function snmpParseLldp($lines, $cfg, $oid)
{
    $byKey = [];
    foreach ($lines as $line) {
        [$lineOid, $value] = snmpSplitLine($line);
        if ($lineOid === '') continue;

        $segments = explode('.', trim($lineOid, '.'));
        if (count($segments) < 4) continue;

        $index     = array_pop($segments);          // индекс соседа
        $localPort = array_pop($segments);          // номер локального порта
        array_pop($segments);                       // timeMark — не нужен
        $column    = array_pop($segments);          // номер столбца lldpRemTable

        $key = $localPort . '.' . $index;
        if (!isset($byKey[$key])) {
            $byKey[$key] = ['local' => $localPort, 'name' => '', 'port' => ''];
        }
        // 7 — lldpRemSysName, 8 — lldpRemPortDesc, 4 — lldpRemPortId
        if ($column === '9' || $column === '7') {
            $byKey[$key]['name'] = $value;
        } elseif ($column === '8' || $column === '4') {
            if ($byKey[$key]['port'] === '') $byKey[$key]['port'] = $value;
        }
    }

    $rows = [];
    foreach ($byKey as $item) {
        if ($item['name'] === '' && $item['port'] === '') continue;
        $rows[] = [$item['local'], $item['name'] ?: '—', $item['port'] ?: '—'];
    }
    return $rows;
}

/** Сырой вывод: OID и значение как есть. */
function snmpParseRaw($lines, $cfg, $oid)
{
    $rows = [];
    foreach ($lines as $line) {
        [$lineOid, $value] = snmpSplitLine($line);
        $rows[] = [$lineOid, $value];
    }
    return $rows;
}

/**
 * Активность портов: собирает ifTable в строки «порт | состояние | админ | скорость».
 *
 * Столбцы IF-MIB (ifTable = 1.3.6.1.2.1.2.2.1):
 *   2 — ifDescr, 5 — ifSpeed, 7 — ifAdminStatus, 8 — ifOperStatus
 * Индекс интерфейса — последний сегмент OID.
 */
function snmpParsePortStatus($lines, $cfg, $oid)
{
    $ifStatus = [
        '1' => 'up', '2' => 'down', '3' => 'testing',
        '4' => 'unknown', '5' => 'dormant', '6' => 'notPresent', '7' => 'lowerLayerDown',
    ];
    $statusRu = [
        'up' => 'Активен', 'down' => 'Не активен', 'testing' => 'Тестирование',
        'unknown' => 'Неизвестно', 'dormant' => 'Ожидание',
        'notPresent' => 'Отсутствует', 'lowerLayerDown' => 'Нет несущей',
    ];

    $ports = [];
    foreach ($lines as $line) {
        [$lineOid, $value] = snmpSplitLine($line);
        if ($lineOid === '') continue;

        $segments = explode('.', trim($lineOid, '.'));
        $index  = array_pop($segments);       // индекс интерфейса
        $column = array_pop($segments);       // номер столбца ifTable

        if (!isset($ports[$index])) {
            $ports[$index] = ['descr' => '', 'admin' => '', 'oper' => '', 'speed' => ''];
        }
        if ($column === '2')      $ports[$index]['descr'] = $value;
        elseif ($column === '7')  $ports[$index]['admin'] = $value;
        elseif ($column === '8')  $ports[$index]['oper']  = $value;
        elseif ($column === '5')  $ports[$index]['speed'] = $value;
    }

    $rows = [];
    foreach ($ports as $index => $p) {
        if ($p['descr'] === '' && $p['oper'] === '') continue;

        $operKey  = $ifStatus[$p['oper']]  ?? $p['oper'];
        $adminKey = $ifStatus[$p['admin']] ?? $p['admin'];

        // Скорость в читаемый вид
        $speed = '';
        if (is_numeric($p['speed']) && $p['speed'] > 0) {
            $bps = (float)$p['speed'];
            if ($bps >= 1e9)      $speed = round($bps / 1e9, 1) . ' Гбит/с';
            elseif ($bps >= 1e6)  $speed = round($bps / 1e6) . ' Мбит/с';
            else                  $speed = round($bps / 1e3) . ' Кбит/с';
        }

        $rows[] = [
            $p['descr'] !== '' ? $p['descr'] : ('ifIndex ' . $index),
            $statusRu[$operKey]  ?? ($operKey  ?: '—'),
            $statusRu[$adminKey] ?? ($adminKey ?: '—'),
            $speed ?: '—',
        ];
    }
    return $rows;
}
