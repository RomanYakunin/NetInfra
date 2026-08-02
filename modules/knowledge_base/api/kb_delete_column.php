<?php
// modules/knowledge_base/api/kb_delete_column.php — удаление столбца
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

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$table  = $input['table'] ?? '';
$column = $input['column'] ?? '';

try {
    $table  = kbValidateTable($pdo, $table);
    $column = kbValidateColumn($pdo, $table, $column);

    // Первичный ключ удалять запрещаем
    $pk = kbPrimaryKey($pdo, $table);
    if ($pk !== null && strcasecmp($pk, $column) === 0) {
        echo json_encode(['error' => 'Первичный ключ удалить нельзя']);
        exit;
    }

    // Последний столбец удалить тоже нельзя
    if (count(kbColumnNames($pdo, $table)) <= 1) {
        echo json_encode(['error' => 'Нельзя удалить единственный столбец таблицы']);
        exit;
    }

    $pdo->exec("ALTER TABLE `$table` DROP COLUMN `$column`");
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // Столбец может участвовать во внешнем ключе или индексе
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
