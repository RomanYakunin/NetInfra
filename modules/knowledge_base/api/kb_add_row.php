<?php
// modules/knowledge_base/api/kb_add_row.php — добавление записи
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
$values = $input['values'] ?? [];

if (!is_array($values) || !$values) {
    echo json_encode(['error' => 'Нет данных для добавления']);
    exit;
}

try {
    $table = kbValidateTable($pdo, $table);

    $fields = [];
    $params = [];
    foreach ($values as $col => $val) {
        $col = kbValidateColumn($pdo, $table, $col);
        $fields[] = $col;
        // Пустая строка для необязательных полей = NULL
        $params[] = ($val === '' ? null : $val);
    }

    $cols = '`' . implode('`, `', $fields) . '`';
    $ph   = implode(', ', array_fill(0, count($fields), '?'));

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($ph)");
    $stmt->execute($params);
    $newId = $pdo->lastInsertId();
    $pdo->commit();

    echo json_encode(['success' => true, 'id' => $newId]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
