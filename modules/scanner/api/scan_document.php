<?php
/**
 * modules/scanner/api/scan_document.php  (маршрут scan_document)
 *
 * Сканирует страницу и сразу кладёт её в хранилище как документ,
 * привязанный к телефону либо к коробке.
 *
 * Файл попадает туда же, куда и загруженный вручную:
 *     <docs_root>/<Подразделение>/<год>/<файл>
 * Подразделение берётся у телефона; для коробки его нет, поэтому
 * документ ложится в папку «Без подразделения».
 */
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
require_once dirname(__FILE__, 3) . '/scanner/ScannerWia.php';
require_once dirname(__FILE__, 3) . '/storage/storage_common.php';
require_once dirname(__FILE__, 3) . '/phones_page/documents_common.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$cfg = storageConfig();
if (empty($cfg['scan_enabled'])) {
    echo json_encode(['error' => 'Сканирование выключено в настройках хранилища'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!storageConfigured()) {
    echo json_encode(['error' => 'Не назначена папка хранения документов'], JSON_UNESCAPED_UNICODE);
    exit;
}

$phoneId = !empty($_POST['phone_id']) ? (int)$_POST['phone_id'] : null;
$boxId   = !empty($_POST['box_id'])   ? (int)$_POST['box_id']   : null;

// Ни телефона, ни коробки — значит сканируем из формы добавления, где
// объекта ещё не существует. Такой скан не записываем в базу: он
// возвращается клиенту файлом и уходит вместе с формой.
$previewOnly = ($phoneId === null && $boxId === null);

if (!$previewOnly && $phoneId !== null && $boxId !== null) {
    echo json_encode(['error' => 'Укажите либо телефон, либо коробку'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Подразделение определяет папку — берём его у телефона
$departmentId = null;
if ($phoneId !== null) {
    $st = $pdo->prepare("SELECT department_id FROM phones WHERE id = ?");
    $st->execute([$phoneId]);
    $departmentId = $st->fetchColumn() ?: null;
}

$format = in_array($_POST['format'] ?? '', ['jpeg','png','tiff','bmp'], true)
        ? $_POST['format'] : $cfg['scan_format'];
$ext = $format === 'jpeg' ? 'jpg' : $format;
$stored = generateStoredName($ext);

// В режиме предпросмотра пишем во временный каталог: в хранилище
// документу пока не место, объекта для привязки ещё нет
if ($previewOnly) {
    $dirInfo = null;
    $target = rtrim(sys_get_temp_dir(), '/\\') . '/' . $stored;
} else {
    $dirInfo = storagePrepareDir($pdo, $departmentId);
    if ($dirInfo === null) {
        echo json_encode([
            'error' => 'Не удалось создать папку в хранилище. Проверьте доступ к «' . storageRoot() . '».',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $target = $dirInfo['dir'] . '/' . $stored;
}

// Движок выбирается по устройству: WIA работает через COM, TWAIN —
// только через утилиту NAPS2, напрямую из PHP он недоступен
$backend  = ($_POST['backend'] ?? 'wia') === 'naps2' ? 'naps2' : 'wia';
$deviceId = $_POST['device_id'] ?? '';

if ($backend === 'naps2') {
    require_once dirname(__FILE__, 3) . '/scanner/ScannerNaps2.php';
    $scanner = new ScannerNaps2();
    $ok = $scanner->scanToFile($deviceId, $target, [
        'dpi'    => (int)($_POST['dpi'] ?? $cfg['scan_dpi']),
        'color'  => $_POST['color'] ?? $cfg['scan_color'],
        'driver' => $_POST['driver'] ?? 'twain',
    ]);
} else {
    $scanner = new ScannerWia();
    $ok = $scanner->scanToFile($deviceId, $target, [
        'dpi'    => (int)($_POST['dpi'] ?? $cfg['scan_dpi']),
        'format' => $format,
        'color'  => $_POST['color'] ?? $cfg['scan_color'],
    ]);
}

if (!$ok) {
    echo json_encode(['error' => $scanner->getError()], JSON_UNESCAPED_UNICODE);
    exit;
}

// Предпросмотр: отдаём скан клиенту и убираем временный файл.
// Дальше он поедет на сервер обычной загрузкой вместе с формой.
if ($previewOnly) {
    $formats = phoneDocFormats();
    $data = @file_get_contents($target);
    @unlink($target);
    if ($data === false) {
        echo json_encode(['error' => 'Скан получен, но прочитать файл не удалось'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'success'  => true,
        'preview'  => true,
        'filename' => $stored,
        'mime'     => $formats[$ext]['mime'] ?? 'application/octet-stream',
        'size'     => strlen($data),
        'content'  => base64_encode($data),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$size = filesize($target);
$hash = hash_file('sha256', $target);

try {
    // Тот же скан мог быть сохранён раньше — второй файл на диске не нужен
    $st = $pdo->prepare("SELECT id FROM phone_documents WHERE sha256 = ?");
    $st->execute([$hash]);
    $existingId = $st->fetchColumn();

    if ($existingId) {
        @unlink($target);
        linkDocument($pdo, $existingId, $phoneId, $boxId);
        echo json_encode([
            'success'   => true,
            'duplicate' => true,
            'id'        => (int)$existingId,
            'message'   => 'Такой скан уже есть в хранилище — документ привязан к нему',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $formats = phoneDocFormats();
    $docType = in_array($_POST['doc_type'] ?? '', phoneDocTypes(), true) ? $_POST['doc_type'] : 'накладная';

    $pdo->prepare("
        INSERT INTO phone_documents
            (doc_type, title, doc_number, doc_date, original_name, stored_name,
             rel_path, department_id, source, sha256, mime_type, file_size, notes, uploaded_by)
        VALUES (?,?,?,?,?,?,?,?, 'scan', ?,?,?,?,?)
    ")->execute([
        $docType,
        trim($_POST['title'] ?? '') ?: null,
        trim($_POST['doc_number'] ?? '') ?: null,
        trim($_POST['doc_date'] ?? '') ?: null,
        'Скан ' . date('d.m.Y H:i') . '.' . $ext,
        $stored,
        $dirInfo['rel'],
        $departmentId,
        $hash,
        $formats[$ext]['mime'] ?? 'application/octet-stream',
        $size,
        trim($_POST['notes'] ?? '') ?: null,
        $_SESSION['user_id'] ?? null,
    ]);
    $docId = (int)$pdo->lastInsertId();

    linkDocument($pdo, $docId, $phoneId, $boxId);

    require_once dirname(__FILE__, 4) . '/includes/logger.php';
    logAction($pdo, 'scan_document', 'phone_document', $docId, $docType, [
        'папка'  => $dirInfo['rel'],
        'размер' => $size,
    ]);

    echo json_encode([
        'success' => true,
        'id'      => $docId,
        'folder'  => $dirInfo['rel'],
        'size'    => formatFileSize($size),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    @unlink($target);
    echo json_encode(['error' => 'Ошибка сохранения: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
