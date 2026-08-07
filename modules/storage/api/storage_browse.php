<?php
/**
 * modules/storage/api/storage_browse.php  (маршрут storage_browse)
 *
 * Обзор папок для выбора хранилища.
 *
 * Браузер не может открыть системный диалог выбора папки и отдать путь —
 * это запрещено из соображений безопасности: страница не должна узнавать
 * структуру дисков. Поэтому проводник рисуем сами: сервер перечисляет
 * каталоги, пользователь ходит по ним мышью.
 *
 * Отдаём только каталоги, без файлов и их содержимого. Доступ —
 * администратору: список папок сервера посторонним показывать незачем.
 *
 * Действия:
 *   list  — содержимое каталога (по умолчанию — список дисков)
 *   mkdir — создать вложенную папку
 */
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
require_once dirname(__FILE__, 3) . '/storage/storage_common.php';
header('Content-Type: application/json; charset=utf-8');

/** Приводит путь к одному виду: прямые слэши, без хвостового. */
function browseNormalize($path)
{
    $path = trim((string)$path);
    if ($path === '') return '';

    // UNC сохраняем как \\server\share — двойной слэш в начале значим
    $isUnc = (strncmp($path, '\\\\', 2) === 0) || (strncmp($path, '//', 2) === 0);
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    $path = rtrim($path, '/');

    if ($isUnc) $path = '//' . ltrim($path, '/');
    // C: без слэша операционная система понимает как «текущий каталог диска»
    if (preg_match('#^[A-Za-z]:$#', $path)) $path .= '/';
    return $path;
}

/** Родительский каталог или null, если выше некуда. */
function browseParent($path)
{
    $path = browseNormalize($path);
    if ($path === '' || preg_match('#^[A-Za-z]:/?$#', $path)) return null;

    // Для UNC выше \\server\share не поднимаемся: там уже не файловая система
    if (strncmp($path, '//', 2) === 0) {
        $rest = substr($path, 2);
        if (substr_count($rest, '/') <= 1) return null;
    }

    $pos = strrpos($path, '/');
    if ($pos === false) return null;
    $parent = substr($path, 0, $pos);

    if (preg_match('#^[A-Za-z]:$#', $parent)) return $parent . '/';
    if ($parent === '' || $parent === '/') return null;
    return $parent;
}

/** Список логических дисков. */
function browseDrives()
{
    $out = [];
    foreach (range('A', 'Z') as $letter) {
        $root = $letter . ':/';
        // is_dir на отсутствующем диске отрабатывает быстро и без ошибок
        if (@is_dir($root)) {
            $out[] = [
                'name'     => $letter . ':',
                'path'     => $root,
                'writable' => @is_writable($root),
            ];
        }
    }
    return $out;
}

$action = $_GET['action'] ?? 'list';
$path   = browseNormalize($_GET['path'] ?? '');

// ---------------------------- Создание папки ----------------------------
if ($action === 'mkdir') {
    $name = trim($_POST['name'] ?? $_GET['name'] ?? '');

    // Имя папки — только имя: разделители и «..» отсекаем, иначе
    // через него можно было бы уйти в произвольное место
    if ($name === '' || preg_match('#[\\\\/:*?"<>|]#', $name) || $name === '.' || $name === '..') {
        echo json_encode(['success' => false, 'error' => 'Недопустимое имя папки'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($path === '' || !@is_dir($path)) {
        echo json_encode(['success' => false, 'error' => 'Каталог не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $target = $path . '/' . $name;
    if (@is_dir($target)) {
        echo json_encode(['success' => true, 'path' => $target, 'existed' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!@mkdir($target, 0775)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Не удалось создать папку — проверьте права на «' . $path . '»',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'path' => $target], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------- Список ----------------------------
// Пустой путь — показываем диски: с чего-то начинать надо
if ($path === '') {
    echo json_encode([
        'success'  => true,
        'path'     => '',
        'parent'   => null,
        'is_root'  => true,
        'drives'   => browseDrives(),
        'folders'  => [],
        'current'  => storageRoot(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!@is_dir($path)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Каталог «' . $path . '» недоступен. Для сетевого ресурса проверьте, '
                   . 'что учётная запись веб-сервера имеет к нему доступ.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$folders = [];
$handle = @opendir($path);
if ($handle === false) {
    echo json_encode([
        'success' => false,
        'error'   => 'Нет доступа к содержимому «' . $path . '»',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

while (($entry = readdir($handle)) !== false) {
    if ($entry === '.' || $entry === '..') continue;
    $full = $path . '/' . $entry;
    // Файлы не показываем: выбирается папка, всё остальное — шум
    if (!@is_dir($full)) continue;
    $folders[] = [
        'name'     => $entry,
        'path'     => $full,
        'writable' => @is_writable($full),
    ];
}
closedir($handle);

// Сортируем по-человечески, с учётом регистра и цифр в названиях
usort($folders, function ($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});

echo json_encode([
    'success'  => true,
    'path'     => $path,
    'parent'   => browseParent($path),
    'is_root'  => false,
    'drives'   => [],
    'folders'  => $folders,
    'writable' => @is_writable($path),
    'current'  => storageRoot(),
], JSON_UNESCAPED_UNICODE);
