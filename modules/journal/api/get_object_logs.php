<?php
// modules/journal/api/get_object_logs.php — история изменений одного объекта.
//
// В отличие от get_logs.php (общий журнал, только админ), эта выборка
// доступна любому авторизованному пользователю: она показывает историю
// конкретной карточки, которую он и так видит.
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAuth();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

$objectType = trim($_GET['object_type'] ?? '');
$objectId   = (int)($_GET['object_id'] ?? 0);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, [10, 20, 25, 50, 100], true)) {
    $perPage = 20;
}

// Тип объекта сверяем с белым списком — он подставляется в запрос как значение,
// но лишний контроль отсекает мусорные обращения
$allowedTypes = ['equipment', 'node', 'stack', 'building', 'warehouse', 'rack', 'user'];
if ($objectType === '' || !in_array($objectType, $allowedTypes, true)) {
    echo json_encode(['error' => 'Недопустимый тип объекта']);
    exit;
}
if (!$objectId) {
    echo json_encode(['error' => 'Не указан ID объекта']);
    exit;
}

try {
    $where  = 'WHERE object_type = ? AND object_id = ?';
    $params = [$objectType, $objectId];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET подставляем целыми — плейсхолдеры работают не во всех
    // режимах эмуляции PDO
    $sql = "SELECT id, created_at, username, ip_address, action,
                   object_type, object_id, object_name, details
            FROM logs
            $where
            ORDER BY created_at DESC, id DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'     => true,
        'data'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
