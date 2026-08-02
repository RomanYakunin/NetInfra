<?php
// modules/journal/api/get_logs.php — список записей журнала (только админ)
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10, 25, 50, 100], true)) {
    $perPage = 25;
}

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$userId   = trim($_GET['user_id'] ?? '');
$action   = trim($_GET['action'] ?? '');
$search   = trim($_GET['search'] ?? '');

try {
    $where = [];
    $params = [];

    // Фильтр по датам (включая весь конечный день)
    if ($dateFrom !== '') {
        $where[] = 'l.created_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        $where[] = 'l.created_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }
    if ($userId !== '') {
        $where[] = 'l.user_id = ?';
        $params[] = (int)$userId;
    }
    if ($action !== '') {
        $where[] = 'l.action = ?';
        $params[] = $action;
    }
    // Поиск по названию объекта, типу объекта и логину
    if ($search !== '') {
        $where[] = '(l.object_name LIKE ? OR l.object_type LIKE ? OR l.username LIKE ? OR l.details LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    // Всего записей с учётом фильтров
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs l$whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET подставляем целыми — плейсхолдеры работают не во всех режимах эмуляции PDO
    $sql = "SELECT l.id, l.user_id, l.username, l.ip_address, l.action,
                   l.object_type, l.object_id, l.object_name, l.details, l.created_at
            FROM logs l$whereSql
            ORDER BY l.created_at DESC, l.id DESC
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
