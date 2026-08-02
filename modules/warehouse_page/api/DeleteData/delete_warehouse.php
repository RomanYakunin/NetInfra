<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');
$id = (int)$_POST['id'];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE warehouse_id=?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'На складе находится оборудование']);
        exit;
    }
    // Название запоминаем до удаления — для журнала
    $stmtName = $pdo->prepare("SELECT name FROM warehouses WHERE id = ?");
    $stmtName->execute([$id]);
    $whName = $stmtName->fetchColumn() ?: '';

    $pdo->prepare("DELETE FROM warehouses WHERE id=?")->execute([$id]);

    require_once dirname(__FILE__, 5) . '/includes/logger.php';
    logAction($pdo, 'delete_warehouse', 'warehouse', $id, $whName);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД']);
}