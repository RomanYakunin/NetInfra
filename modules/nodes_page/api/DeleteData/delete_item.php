<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
// api/AddData/delete_item.php – удаление устройства или узла
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$itemType = $_POST['item_type'] ?? '';   // 'equipment' или 'node'
$id = (int)($_POST['id'] ?? 0);

if (!$id || !in_array($itemType, ['equipment', 'node'])) {
    echo json_encode(['error' => 'Неверные параметры']);
    exit;
}

try {
    if ($itemType === 'equipment') {
        // Удаляем только устройство
        $stmt = $pdo->prepare("DELETE FROM equipment WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['error' => 'Устройство не найдено']);
            exit;
        }
    } else {
        // Удаляем узел, но оставляем оборудование (id_node = NULL)
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE equipment SET id_node = NULL WHERE id_node = ?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM nodes WHERE id_node = ?");
        $stmt->execute([$id]);
        $pdo->commit();
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка удаления: ' . $e->getMessage()]);
}