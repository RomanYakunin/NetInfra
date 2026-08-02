<?php
// modules/knowledge_base/api/kb_add_column.php — добавление столбца
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

$input      = json_decode(file_get_contents('php://input'), true) ?: [];
$table      = $input['table'] ?? '';
$columnName = $input['column'] ?? '';
$type       = $input['type'] ?? '';
$nullable   = !empty($input['nullable']);
$default    = $input['default'] ?? '';

try {
    $table = kbValidateTable($pdo, $table);
    $columnName = kbValidateNewIdentifier($columnName);
    $type = kbValidateColumnType($type);

    // Столбец с таким именем уже есть?
    foreach (kbColumnNames($pdo, $table) as $existing) {
        if (strcasecmp($existing, $columnName) === 0) {
            echo json_encode(['error' => 'Столбец с таким именем уже существует']);
            exit;
        }
    }

    $sql = "ALTER TABLE `$table` ADD COLUMN `$columnName` $type";
    $sql .= $nullable ? ' NULL' : ' NOT NULL';

    // Значение по умолчанию подставляем через кавычки PDO
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
