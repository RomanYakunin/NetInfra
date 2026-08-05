<?php
/**
 * modules/zabbix/api/zabbix_settings.php  (маршрут zabbix_settings)
 *
 * Чтение и сохранение параметров подключения к Zabbix.
 *
 * Настройки лежат в config/zabbix.php, а не в базе, намеренно: в
 * приложении есть страница «База знаний», которая показывает содержимое
 * любой таблицы. Пароль сервисной учётки там увидел бы каждый
 * администратор. Файл в .gitignore, поэтому в репозиторий не попадает.
 *
 * Пароль наружу не отдаётся никогда — только признак, задан он или нет.
 */
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
require_once dirname(__FILE__, 3) . '/zabbix/ZabbixClient.php';
header('Content-Type: application/json; charset=utf-8');

$configFile = dirname(__FILE__, 4) . '/config/zabbix.php';

// ---------------------------- Чтение ----------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $cfg = ZabbixClient::loadConfig();
    echo json_encode([
        'success' => true,
        'data' => [
            'enabled'         => !empty($cfg['enabled']),
            'url'             => $cfg['url'] ?? '',
            'user'            => $cfg['user'] ?? '',
            'has_password'    => !empty($cfg['password']),
            'timeout'         => (int)($cfg['timeout'] ?? 10),
            'connect_timeout' => (int)($cfg['connect_timeout'] ?? 4),
            'verify_ssl'      => !empty($cfg['verify_ssl']),
            'writable'        => is_writable($configFile) || is_writable(dirname($configFile)),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------- Сохранение ----------------------------
$current = ZabbixClient::loadConfig();

$url = trim($_POST['url'] ?? '');
if ($url !== '') {
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        echo json_encode(['success' => false, 'error' => 'Адрес должен начинаться с http:// или https://'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$enabled = !empty($_POST['enabled']) && $_POST['enabled'] !== '0';
$user    = trim($_POST['user'] ?? '');

// Пустое поле пароля означает «оставить прежний», а не «стереть»:
// иначе при правке адреса пароль терялся бы каждый раз
$password = $_POST['password'] ?? '';
if ($password === '') {
    $password = $current['password'] ?? '';
}

if ($enabled && ($url === '' || $user === '')) {
    echo json_encode(['success' => false, 'error' => 'Для включения нужны адрес API и учётная запись'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg = [
    'enabled'         => $enabled,
    'url'             => $url,
    'user'            => $user,
    'password'        => $password,
    'timeout'         => max(1, min(120, (int)($_POST['timeout'] ?? 10))),
    'connect_timeout' => max(1, min(60,  (int)($_POST['connect_timeout'] ?? 4))),
    'verify_ssl'      => !empty($_POST['verify_ssl']) && $_POST['verify_ssl'] !== '0',
];

// var_export даёт корректные литералы для любых кавычек в пароле
$php = "<?php\n"
     . "/**\n"
     . " * config/zabbix.php — подключение к Zabbix.\n"
     . " *\n"
     . " * Файл создан страницей настроек. Правится и вручную.\n"
     . " * В .gitignore: пароль предприятия не должен попасть в репозиторий.\n"
     . " *\n"
     . " * До версии 5.4 у Zabbix нет постоянных API-токенов, поэтому нужны\n"
     . " * логин и пароль — клиент логинится методом user.login.\n"
     . " */\n\n"
     . "return " . var_export($cfg, true) . ";\n";

// Пишем через временный файл: при обрыве записи конфиг не останется битым
$tmp = $configFile . '.tmp';
if (@file_put_contents($tmp, $php, LOCK_EX) === false || !@rename($tmp, $configFile)) {
    @unlink($tmp);
    echo json_encode([
        'success' => false,
        'error'   => 'Не удалось записать config/zabbix.php — проверьте права на файл',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
@chmod($configFile, 0640);

// Сессия относится к прежней учётке — сбрасываем, иначе клиент
// продолжит ходить со старым токеном
foreach (glob(sys_get_temp_dir() . '/netinfra_zbx_*.json') as $f) @unlink($f);

// В журнал пароль не пишем ни в каком виде
require_once dirname(__FILE__, 4) . '/config/db.php';
require_once dirname(__FILE__, 4) . '/includes/logger.php';
logAction($pdo, 'zabbix_settings', 'settings', null, 'Подключение к Zabbix', [
    'enabled' => $enabled,
    'url'     => $url,
    'user'    => $user,
]);

// Сразу проверяем связь с новыми параметрами — иначе пришлось бы
// сохранить, перейти на страницу и только там узнать об ошибке
$client = new ZabbixClient($cfg);
echo json_encode([
    'success'   => true,
    'diagnosis' => $client->diagnose(),
], JSON_UNESCAPED_UNICODE);
