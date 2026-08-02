<?php
// modules/knowledge_base/api/kb_update_row.php — обновление записи
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
$pkVal  = $input['pk_value'] ?? null;
$values = $input['values'] ?? [];

if (!is_array($values) || !$values) {
    echo json_encode(['error' => 'Нет данных для обновления']);
    exit;
}
if ($pkVal === null || $pkVal === '') {
    echo json_encode(['error' => 'Не указан идентификатор записи']);
    exit;
}

try {
    $table = kbValidateTable($pdo, $table);
    $pk = kbPrimaryKey($pdo, $table);
    if (!$pk) {
        echo json_encode(['error' => 'У таблицы нет первичного ключа — редактирование недоступно']);
        exit;
    }

    $set = [];
    $params = [];
    foreach ($values as $col => $val) {
        $col = kbValidateColumn($pdo, $table, $col);
        if (strcasecmp($col, $pk) === 0) continue;   // первичный ключ не меняем
        $set[] = "`$col` = ?";
        $params[] = ($val === '' ? null : $val);
    }

    if (!$set) {
        echo json_encode(['error' => 'Нет полей для обновления']);
        exit;
    }

    $params[] = $pkVal;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE `$table` SET " . implode(', ', $set) . " WHERE `$pk` = ?");
    $stmt->execute($params);
    $affected = $stmt->rowCount();
    $pdo->commit();

    echo json_encode(['success' => true, 'affected' => $affected]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
