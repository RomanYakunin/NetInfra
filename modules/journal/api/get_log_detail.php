<?php
// modules/journal/api/get_log_detail.php — одна запись журнала целиком (только админ)
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Не указан ID записи']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, user_id, username, ip_address, action,
               object_type, object_id, object_name, details, created_at
        FROM logs
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['error' => 'Запись не найдена']);
        exit;
    }

    // details может быть JSON — пробуем разобрать, чтобы показать читаемо
    $row['details_parsed'] = null;
    if (!empty($row['details'])) {
        $decoded = json_decode($row['details'], true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
            $row['details_parsed'] = $decoded;
        }
    }

    echo json_encode(['success' => true, 'data' => $row]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
