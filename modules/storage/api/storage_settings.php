<?php
/**
 * modules/storage/api/storage_settings.php  (маршрут storage_settings)
 *
 * Чтение и сохранение настроек хранилища документов, а также создание
 * папок подразделений в назначенном корне.
 *
 * Настройки лежат в config/storage.php, а не в базе: страница «База
 * знаний» показывает содержимое любой таблицы, и путь к сетевому
 * ресурсу предприятия попал бы туда вместе со всем остальным.
 */
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
require_once dirname(__FILE__, 3) . '/storage/storage_common.php';
require_once dirname(__FILE__, 3) . '/scanner/ScannerWia.php';
header('Content-Type: application/json; charset=utf-8');

$configFile = dirname(__FILE__, 4) . '/config/storage.php';

// ---------------------------- Чтение ----------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $cfg = storageConfig();
    echo json_encode([
        'success' => true,
        'data' => [
            'docs_root'       => $cfg['docs_root'],
            'use_departments' => !empty($cfg['use_departments']),
            'unsorted_folder' => $cfg['unsorted_folder'],
            'scan_enabled'    => !empty($cfg['scan_enabled']),
            'scan_dpi'        => (int)$cfg['scan_dpi'],
            'scan_format'     => $cfg['scan_format'],
            'scan_color'      => $cfg['scan_color'],
            'writable_config' => is_writable($configFile) || is_writable(dirname($configFile)),
            'status'          => storageStatus(),
            'scan_available'  => ScannerWia::available(),
            'scan_reason'     => ScannerWia::unavailableReason(),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------- Создание папок подразделений --------------------
if (($_POST['action'] ?? '') === 'create_folders') {
    if (!storageConfigured()) {
        echo json_encode(['success' => false, 'error' => 'Сначала назначьте папку хранения'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $res = storageCreateDepartmentFolders($pdo);

    require_once dirname(__FILE__, 4) . '/includes/logger.php';
    logAction($pdo, 'storage_folders', 'settings', null, 'Папки подразделений', [
        'создано' => count($res['created']),
        'было'    => count($res['existed']),
        'ошибок'  => count($res['failed']),
    ]);

    echo json_encode([
        'success' => true,
        'created' => $res['created'],
        'existed' => $res['existed'],
        'failed'  => $res['failed'],
        'status'  => storageStatus(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------- Сохранение ----------------------------
$root = trim($_POST['docs_root'] ?? '');
// Приводим к прямым слэшам: путь может прийти в любом виде,
// а сравнивать и склеивать удобнее в одном
$root = rtrim(str_replace('\\', '/', $root), '/');

// Указанной папки может ещё не быть — заводим её сразу. Иначе
// пользователь сохранил бы настройку и тут же получил «папка
// недоступна», хотя путь верный.
if ($root !== '' && !is_dir($root)) {
    @mkdir($root, 0775, true);
}

$cfg = [
    'docs_root'       => $root,
    'use_departments' => !empty($_POST['use_departments']) && $_POST['use_departments'] !== '0',
    'unsorted_folder' => trim($_POST['unsorted_folder'] ?? '') ?: 'Без подразделения',
    'scan_enabled'    => !empty($_POST['scan_enabled']) && $_POST['scan_enabled'] !== '0',
    'scan_dpi'        => max(75, min(1200, (int)($_POST['scan_dpi'] ?? 300))),
    'scan_format'     => in_array($_POST['scan_format'] ?? '', ['jpeg','png','tiff','bmp'], true)
                            ? $_POST['scan_format'] : 'jpeg',
    'scan_color'      => in_array($_POST['scan_color'] ?? '', ['color','gray','bw'], true)
                            ? $_POST['scan_color'] : 'color',
];

$php = "<?php\n"
     . "/**\n"
     . " * config/storage.php — где хранятся сканы накладных и расписок.\n"
     . " *\n"
     . " * Файл создан страницей настроек, правится и вручную.\n"
     . " * В .gitignore: путь к сетевому ресурсу предприятия не должен\n"
     . " * попадать в репозиторий.\n"
     . " *\n"
     . " * Раскладка: <docs_root>/<Подразделение>/<год>/<файл>\n"
     . " */\n\n"
     . "return " . var_export($cfg, true) . ";\n";

// Пишем через временный файл: при обрыве записи конфиг не останется битым
$tmp = $configFile . '.tmp';
if (@file_put_contents($tmp, $php, LOCK_EX) === false || !@rename($tmp, $configFile)) {
    @unlink($tmp);
    echo json_encode([
        'success' => false,
        'error'   => 'Не удалось записать config/storage.php — проверьте права на файл',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__FILE__, 4) . '/includes/logger.php';
logAction($pdo, 'storage_settings', 'settings', null, 'Хранилище документов', [
    'docs_root'    => $root,
    'scan_enabled' => $cfg['scan_enabled'],
]);

// Конфигурация уже прочитана в статическую переменную — перечитываем
// её в новом процессе, поэтому статус считаем по свежим значениям
echo json_encode([
    'success' => true,
    'status'  => (function () use ($cfg) {
        // storageStatus() читает через storageConfig(), а он закеширован
        $root = rtrim(str_replace('\\', '/', $cfg['docs_root']), '/');
        if ($root === '') return ['configured' => false, 'root' => '', 'exists' => false,
                                  'writable' => false, 'error' => 'Папка хранения не назначена',
                                  'folders' => []];
        $out = ['configured' => true, 'root' => $root, 'exists' => is_dir($root),
                'writable' => false, 'error' => '', 'folders' => []];
        if (!$out['exists']) {
            $out['error'] = 'Папка недоступна. Проверьте путь и права учётной записи веб-сервера.';
            return $out;
        }
        $probe = $root . '/.netinfra_write_test';
        if (@file_put_contents($probe, 'test') !== false) { $out['writable'] = true; @unlink($probe); }
        else $out['error'] = 'Папка доступна только на чтение';
        foreach ((array)@scandir($root) as $e) {
            if ($e !== '.' && $e !== '..' && is_dir($root . '/' . $e)) $out['folders'][] = $e;
        }
        sort($out['folders']);
        return $out;
    })(),
], JSON_UNESCAPED_UNICODE);
