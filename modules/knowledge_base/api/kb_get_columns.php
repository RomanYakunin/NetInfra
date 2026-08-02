<?php
// modules/knowledge_base/api/kb_get_columns.php — структура таблицы
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
require_once __DIR__ . '/kb_helpers.php';
header('Content-Type: application/json; charset=utf-8');

$table = $_GET['table'] ?? '';

try {
    $table = kbValidateTable($pdo, $table);
    $columns = kbGetColumns($pdo, $table);

    echo json_encode([
        'success'     => true,
        'table'       => $table,
        'primary_key' => kbPrimaryKey($pdo, $table),
        'data'        => $columns,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
