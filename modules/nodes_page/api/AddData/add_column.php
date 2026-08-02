<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
// api/AddData/add_column.php – добавляет новый столбец в указанную таблицу
require_once dirname(__FILE__, 5) . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$table = $_POST['table'] ?? '';
$columnNameRus = trim($_POST['column_name'] ?? '');

// Разрешённые таблицы (защита от инъекций)
$allowedTables = ['nodes', 'equipment', 'phones', 'checklist', 'locations', 'Buildings', 'vendors', 'device_models', 'device_types', 'racks', 'rack_heights', 'firmwares', 'ip_address', 'node_types', 'Type_group', 'type_task', 'category_task'];
if (!in_array($table, $allowedTables)) {
    echo json_encode(['error' => 'Недопустимая таблица']);
    exit;
}

if (empty($columnNameRus)) {
    echo json_encode(['error' => 'Название столбца обязательно']);
    exit;
}

// Транслитерация русского названия в английское имя столбца
function transliterate($string) {
    $converter = [
        'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',  'д' => 'd',
        'е' => 'e',  'ё' => 'e',  'ж' => 'zh', 'з' => 'z',  'и' => 'i',
        'й' => 'y',  'к' => 'k',  'л' => 'l',  'м' => 'm',  'н' => 'n',
        'о' => 'o',  'п' => 'p',  'р' => 'r',  'с' => 's',  'т' => 't',
        'у' => 'u',  'ф' => 'f',  'х' => 'h',  'ц' => 'c',  'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch','ь' => '',   'ы' => 'y',  'ъ' => '',
        'э' => 'e',  'ю' => 'yu', 'я' => 'ya',
        'А' => 'A',  'Б' => 'B',  'В' => 'V',  'Г' => 'G',  'Д' => 'D',
        'Е' => 'E',  'Ё' => 'E',  'Ж' => 'Zh', 'З' => 'Z',  'И' => 'I',
        'Й' => 'Y',  'К' => 'K',  'Л' => 'L',  'М' => 'M',  'Н' => 'N',
        'О' => 'O',  'П' => 'P',  'Р' => 'R',  'С' => 'S',  'Т' => 'T',
        'У' => 'U',  'Ф' => 'F',  'Х' => 'H',  'Ц' => 'C',  'Ч' => 'Ch',
        'Ш' => 'Sh', 'Щ' => 'Sch','Ь' => '',   'Ы' => 'Y',  'Ъ' => '',
        'Э' => 'E',  'Ю' => 'Yu', 'Я' => 'Ya',
    ];
    $string = strtr($string, $converter);
    // Удаляем всё, кроме латинских букв, цифр и подчёркиваний
    $string = preg_replace('/[^a-zA-Z0-9_]/', '_', $string);
    // Убираем множественные подчёркивания
    $string = preg_replace('/_+/', '_', $string);
    return trim($string, '_');
}

$columnName = transliterate($columnNameRus);
if (empty($columnName)) {
    $columnName = 'col_' . uniqid();
}

// Проверяем, существует ли уже такой столбец
$existingCols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
if (in_array($columnName, $existingCols)) {
    echo json_encode(['error' => 'Столбец с таким именем уже существует']);
    exit;
}

// Выполняем ALTER TABLE
try {
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$columnName` VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка добавления столбца: ' . $e->getMessage()]);
    exit;
}

// Добавляем русское название в column_translations
try {
    // Убедимся, что таблица column_translations существует (создадим при необходимости)
    $pdo->exec("CREATE TABLE IF NOT EXISTS column_translations (
        table_name VARCHAR(100) NOT NULL,
        column_name VARCHAR(100) NOT NULL,
        lang CHAR(2) NOT NULL DEFAULT 'ru',
        translation VARCHAR(255) NOT NULL,
        PRIMARY KEY (table_name, column_name, lang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("REPLACE INTO column_translations (table_name, column_name, lang, translation) VALUES (?, ?, 'ru', ?)");
    $stmt->execute([$table, $columnName, $columnNameRus]);
} catch (PDOException $e) {
    // Не критично, просто логируем
}

echo json_encode(['success' => true, 'column_name' => $columnName, 'translation' => $columnNameRus]);