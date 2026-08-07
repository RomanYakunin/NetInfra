<?php
/**
 * modules/storage/storage_common.php
 *
 * Хранилище сканов накладных и расписок в назначаемой папке.
 *
 * Раньше файлы лежали в uploads/phone_docs/ внутри проекта: при переносе
 * приложения их приходилось тащить вместе с кодом, а из сети они были
 * недоступны. Теперь корень задаётся настройкой и может быть сетевым
 * ресурсом, а внутри документы раскладываются по подразделениям:
 *
 *     <docs_root>/<Подразделение>/<год>/<файл>
 *
 * Корень достаточно оставить пустым — папки создаются при сохранении.
 */

/** Настройки хранилища; при отсутствии файла возвращает пустой корень. */
function storageConfig()
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $file = dirname(__FILE__, 3) . '/config/storage.php';
    $cfg = is_file($file) ? require $file : [];
    if (!is_array($cfg)) $cfg = [];

    $cfg += [
        'docs_root'       => '',
        'use_departments' => true,
        'unsorted_folder' => 'Без подразделения',
        'scan_enabled'    => true,
        'scan_dpi'        => 300,
        'scan_format'     => 'jpeg',
        'scan_color'      => 'color',
    ];
    return $cfg;
}

/** Корень хранилища без завершающего слэша; пустая строка — не настроен. */
function storageRoot()
{
    $root = trim((string)storageConfig()['docs_root']);
    return rtrim(str_replace('\\', '/', $root), '/');
}

function storageConfigured()
{
    return storageRoot() !== '';
}

/**
 * Каталог старого хранилища внутри проекта.
 * Документы, загруженные до перехода на сетевую папку, лежат там —
 * поэтому обращение к нему сохраняем.
 */
function legacyDocsDir()
{
    return dirname(__FILE__, 3) . '/uploads/phone_docs/';
}

/**
 * Превращает название подразделения в имя папки.
 *
 * Windows запрещает \ / : * ? " < > | и точку в конце; кириллицу
 * оставляем — по этим папкам ходят люди, транслит только мешал бы.
 */
function storageSafeFolder($name)
{
    $name = trim((string)$name);
    if ($name === '') return '';

    $name = preg_replace('/[\\\\\\/:*?"<>|]+/u', '-', $name);
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = trim($name, " .\t\n\r\0\x0B");

    // Зарезервированные имена устройств DOS: папку CON создать нельзя
    $reserved = ['CON','PRN','AUX','NUL','COM1','COM2','COM3','COM4','COM5',
                 'COM6','COM7','COM8','COM9','LPT1','LPT2','LPT3','LPT4',
                 'LPT5','LPT6','LPT7','LPT8','LPT9'];
    if (in_array(strtoupper($name), $reserved, true)) $name = '_' . $name;

    if (mb_strlen($name) > 90) $name = mb_substr($name, 0, 90);
    return $name;
}

/**
 * Имя папки подразделения по его идентификатору.
 * Код подразделения предпочтительнее названия: он короче и не меняется.
 */
function storageDepartmentFolder(PDO $pdo, $departmentId)
{
    $cfg = storageConfig();
    if (empty($cfg['use_departments'])) return '';

    if (!$departmentId) return storageSafeFolder($cfg['unsorted_folder']);

    try {
        $st = $pdo->prepare("SELECT name, code FROM departments WHERE id = ?");
        $st->execute([$departmentId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $row = null;
    }
    if (!$row) return storageSafeFolder($cfg['unsorted_folder']);

    // В названии бывает дубль кода («ОМО СП-354» при коде «СП-354») —
    // берём название целиком, оно информативнее для человека
    $folder = storageSafeFolder($row['name'] ?: $row['code']);
    return $folder !== '' ? $folder : storageSafeFolder($cfg['unsorted_folder']);
}

/**
 * Создаёт каталог со всеми родителями.
 * @return string|null путь либо null, если создать не удалось
 */
function storageEnsureDir($path)
{
    if ($path === '' ) return null;
    if (is_dir($path)) return $path;
    if (@mkdir($path, 0775, true) || is_dir($path)) return $path;
    return null;
}

/**
 * Готовит папку под документ и возвращает путь относительно корня.
 *
 * @return array|null ['dir' => абсолютный путь, 'rel' => относительный]
 */
function storagePrepareDir(PDO $pdo, $departmentId, $year = null)
{
    if (!storageConfigured()) return null;

    $year = $year ?: date('Y');
    $parts = array_filter([
        storageDepartmentFolder($pdo, $departmentId),
        (string)$year,
    ], function ($p) { return $p !== ''; });

    $rel = implode('/', $parts);
    $dir = storageEnsureDir(storageRoot() . ($rel !== '' ? '/' . $rel : ''));
    return $dir === null ? null : ['dir' => $dir, 'rel' => $rel];
}

/**
 * Абсолютный путь к файлу документа.
 *
 * Строка из БД может относиться к обоим поколениям хранилища: если
 * rel_path пуст, файл лежит в старом uploads/phone_docs/.
 *
 * @return string|null путь либо null, если файла нет
 */
function storageDocPath($storedName, $relPath = null)
{
    $safe = basename((string)$storedName);
    if ($safe === '' || $safe !== $storedName) return null;   // попытка выйти из каталога

    if ($relPath !== null && $relPath !== '') {
        if (!storageConfigured()) return null;
        // Относительный путь строим сами, но всё равно отбрасываем «..»
        $rel = str_replace('\\', '/', (string)$relPath);
        if (strpos($rel, '..') !== false) return null;
        $path = storageRoot() . '/' . trim($rel, '/') . '/' . $safe;
        return is_file($path) ? $path : null;
    }

    $path = legacyDocsDir() . $safe;
    return is_file($path) ? $path : null;
}

/**
 * Состояние хранилища для страницы настроек.
 * Отдельно проверяем существование и доступность на запись: сетевой
 * ресурс может быть виден, но смонтирован только на чтение.
 */
function storageStatus()
{
    $root = storageRoot();
    $out = [
        'configured' => $root !== '',
        'root'       => $root,
        'exists'     => false,
        'writable'   => false,
        'error'      => '',
        'folders'    => [],
    ];
    if ($root === '') {
        $out['error'] = 'Папка хранения не назначена';
        return $out;
    }

    if (!is_dir($root)) {
        $out['error'] = 'Папка недоступна. Проверьте путь и права учётной записи, '
                      . 'под которой работает веб-сервер: к сетевым ресурсам она '
                      . 'обращается от своего имени, а не от имени пользователя.';
        return $out;
    }
    $out['exists'] = true;

    // is_writable на UNC-путях врёт — проверяем реальной записью
    $probe = $root . '/.netinfra_write_test';
    if (@file_put_contents($probe, 'test') !== false) {
        $out['writable'] = true;
        @unlink($probe);
    } else {
        $out['error'] = 'Папка доступна только на чтение';
    }

    foreach ((array)@scandir($root) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (is_dir($root . '/' . $entry)) $out['folders'][] = $entry;
    }
    sort($out['folders']);

    return $out;
}

/**
 * Создаёт папки для всех подразделений сразу.
 * Пустой корень — обычное состояние при первом запуске, и заводить
 * десятки папок вручную никто не станет.
 *
 * @return array ['created' => [..], 'existed' => [..], 'failed' => [..]]
 */
function storageCreateDepartmentFolders(PDO $pdo)
{
    $result = ['created' => [], 'existed' => [], 'failed' => []];
    if (!storageConfigured()) return $result;

    $names = [];
    try {
        foreach ($pdo->query("SELECT name, code FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $folder = storageSafeFolder($d['name'] ?: $d['code']);
            if ($folder !== '') $names[$folder] = true;
        }
    } catch (PDOException $e) {
        return $result;
    }

    $cfg = storageConfig();
    $unsorted = storageSafeFolder($cfg['unsorted_folder']);
    if ($unsorted !== '') $names[$unsorted] = true;

    foreach (array_keys($names) as $folder) {
        $path = storageRoot() . '/' . $folder;
        if (is_dir($path))            $result['existed'][] = $folder;
        elseif (storageEnsureDir($path)) $result['created'][] = $folder;
        else                          $result['failed'][]  = $folder;
    }
    return $result;
}
