<?php
/**
 * modules/knowledge_base/api/kb_helpers.php
 *
 * Общие функции для обработчиков «Базы знаний».
 * Ключевой момент безопасности: имена таблиц и столбцов нельзя передать
 * через плейсхолдеры PDO, поэтому каждое имя сверяется с реальной схемой БД
 * (белый список строится из SHOW TABLES / SHOW COLUMNS), а затем
 * экранируется обратными кавычками.
 */

/** Системные схемы, которые не показываем и не даём редактировать. */
function kbSystemSchemas()
{
    return ['information_schema', 'performance_schema', 'mysql', 'sys'];
}

/**
 * Список таблиц текущей БД.
 * Работаем только с текущей схемой, поэтому системные БД сюда не попадают
 * в принципе — проверка ниже оставлена как защита от смены конфигурации.
 */
function kbGetTables(PDO $pdo)
{
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    if (in_array(strtolower((string)$dbName), kbSystemSchemas(), true)) {
        return [];
    }
    return $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Проверяет имя таблицы по белому списку и возвращает его же.
 * При несовпадении отдаёт 400 и прерывает выполнение.
 */
function kbValidateTable(PDO $pdo, $table)
{
    $table = (string)$table;
    foreach (kbGetTables($pdo) as $known) {
        if (strcasecmp($known, $table) === 0) {
            return $known;   // возвращаем имя в том регистре, что в схеме
        }
    }
    http_response_code(400);
    echo json_encode(['error' => 'Недопустимое имя таблицы']);
    exit;
}

/** Полное описание столбцов таблицы (SHOW FULL COLUMNS). */
function kbGetColumns(PDO $pdo, $table)
{
    $table = kbValidateTable($pdo, $table);
    $stmt = $pdo->query("SHOW FULL COLUMNS FROM `$table`");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Только имена столбцов. */
function kbColumnNames(PDO $pdo, $table)
{
    return array_column(kbGetColumns($pdo, $table), 'Field');
}

/**
 * Проверяет имя столбца по белому списку таблицы.
 * $required = false позволяет вернуть null, если имя не передано.
 */
function kbValidateColumn(PDO $pdo, $table, $column, $required = true)
{
    if ($column === null || $column === '') {
        if (!$required) return null;
        http_response_code(400);
        echo json_encode(['error' => 'Не указано имя столбца']);
        exit;
    }
    foreach (kbColumnNames($pdo, $table) as $known) {
        if (strcasecmp($known, (string)$column) === 0) {
            return $known;
        }
    }
    http_response_code(400);
    echo json_encode(['error' => 'Недопустимое имя столбца: ' . htmlspecialchars((string)$column)]);
    exit;
}

/**
 * Проверяет имя для НОВОГО столбца (его ещё нет в схеме, сверять не с чем).
 * Разрешаем только латиницу, цифры и подчёркивание.
 */
function kbValidateNewIdentifier($name)
{
    $name = trim((string)$name);
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Имя столбца: только латиница, цифры и «_», начинается не с цифры']);
        exit;
    }
    return $name;
}

/**
 * Белый список типов данных для создания/изменения столбца.
 * Произвольный SQL в определение типа не пропускаем.
 */
function kbValidateColumnType($type)
{
    $type = trim((string)$type);
    // Базовый тип + необязательная длина/точность: VARCHAR(100), DECIMAL(10,2)
    if (!preg_match('/^([A-Za-z]+)(\s*\(\s*\d+\s*(,\s*\d+\s*)?\))?$/', $type, $m)) {
        http_response_code(400);
        echo json_encode(['error' => 'Недопустимый тип столбца']);
        exit;
    }
    $base = strtoupper($m[1]);
    $allowed = [
        'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT',
        'DECIMAL', 'FLOAT', 'DOUBLE',
        'VARCHAR', 'CHAR', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT',
        'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR',
        'BOOLEAN', 'BOOL', 'JSON', 'BLOB',
    ];
    if (!in_array($base, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Тип «' . htmlspecialchars($base) . '» не разрешён']);
        exit;
    }
    return strtoupper($type);
}

/**
 * Первичный ключ таблицы. Нужен для адресного UPDATE/DELETE строки.
 * Составные ключи не поддерживаем — возвращаем первый столбец PRI.
 */
function kbPrimaryKey(PDO $pdo, $table)
{
    foreach (kbGetColumns($pdo, $table) as $col) {
        if ($col['Key'] === 'PRI') {
            return $col['Field'];
        }
    }
    return null;
}
