<?php
// modules/passive_devices/api/DeleteData/delete_passive_device.php
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Не указан идентификатор устройства']);
    exit;
}

try {
    // Название читаем до удаления — для журнала
    $stmt = $pdo->prepare("SELECT name FROM passive_devices WHERE id = ?");
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();
    if ($name === false) {
        echo json_encode(['error' => 'Устройство не найдено']);
        exit;
    }

    // Порты уходят каскадом (FK ON DELETE CASCADE)
    $pdo->prepare("DELETE FROM passive_devices WHERE id = ?")->execute([$id]);

    require_once dirname(__FILE__, 5) . '/includes/logger.php';
    logAction($pdo, 'delete_passive_device', 'passive_device', $id, $name);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
