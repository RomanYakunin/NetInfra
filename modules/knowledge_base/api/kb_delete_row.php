<?php
// modules/knowledge_base/api/kb_delete_row.php — удаление записи
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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$table = $input['table'] ?? '';
$pkVal = $input['pk_value'] ?? null;

if ($pkVal === null || $pkVal === '') {
    echo json_encode(['error' => 'Не указан идентификатор записи']);
    exit;
}

try {
    $table = kbValidateTable($pdo, $table);
    $pk = kbPrimaryKey($pdo, $table);
    if (!$pk) {
        echo json_encode(['error' => 'У таблицы нет первичного ключа — удаление недоступно']);
        exit;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
    $stmt->execute([$pkVal]);
    $affected = $stmt->rowCount();
    $pdo->commit();

    echo json_encode(['success' => true, 'affected' => $affected]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Частый случай — запись держит внешний ключ
    $msg = $e->getCode() === '23000'
        ? 'Запись используется в других таблицах и не может быть удалена'
        : 'Ошибка БД: ' . $e->getMessage();
    echo json_encode(['error' => $msg]);
}
