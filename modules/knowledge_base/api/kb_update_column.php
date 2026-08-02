<?php
// modules/knowledge_base/api/kb_update_column.php — изменение столбца
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
require_once __DIR__ . '/kb_helpers.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$table     = $input['table'] ?? '';
$column    = $input['column'] ?? '';      // текущее имя (должно существовать)
$newName   = $input['new_name'] ?? '';    // новое имя (необязательно)
$type      = $input['type'] ?? '';
$nullable  = !empty($input['nullable']);
$default   = $input['default'] ?? '';

try {
    $table  = kbValidateTable($pdo, $table);
    $column = kbValidateColumn($pdo, $table, $column);
    $type   = kbValidateColumnType($type);

    // Новое имя проверяем как новый идентификатор; если не задано — оставляем текущее
    $targetName = ($newName !== '' && strcasecmp($newName, $column) !== 0)
        ? kbValidateNewIdentifier($newName)
        : $column;

    // Первичный ключ не трогаем — слишком легко разрушить схему
    $pk = kbPrimaryKey($pdo, $table);
    if ($pk !== null && strcasecmp($pk, $column) === 0) {
        echo json_encode(['error' => 'Первичный ключ изменять нельзя']);
        exit;
    }

    // CHANGE позволяет одновременно переименовать и изменить тип
    $sql = "ALTER TABLE `$table` CHANGE `$column` `$targetName` $type";
    $sql .= $nullable ? ' NULL' : ' NOT NULL';
    if ($default !== '') {
        $sql .= ' DEFAULT ' . $pdo->quote($default);
    } elseif ($nullable) {
        $sql .= ' DEFAULT NULL';
    }

    $pdo->exec($sql);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
