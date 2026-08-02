<?php
// modules/knowledge_base/api/kb_get_rows.php — данные таблицы (пагинация, поиск, сортировка)
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
require_once __DIR__ . '/kb_helpers.php';
header('Content-Type: application/json; charset=utf-8');

$table   = $_GET['table'] ?? '';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, [10, 25, 50, 100, 200], true)) {
    $perPage = 25;
}

try {
    $table   = kbValidateTable($pdo, $table);
    $columns = kbColumnNames($pdo, $table);
    if (!$columns) {
        echo json_encode(['error' => 'В таблице нет столбцов']);
        exit;
    }

    // Сортировка: имя столбца сверяем с белым списком
    $sort = $_GET['sort'] ?? '';
    $sortSql = '';
    if ($sort !== '') {
        $sortCol = kbValidateColumn($pdo, $table, $sort);
        $dir = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $sortSql = " ORDER BY `$sortCol` $dir";
    }

    // Поиск по всем столбцам
    $where = '';
    $params = [];
    if ($search !== '') {
        $parts = [];
        foreach ($columns as $col) {
            $parts[] = "CAST(`$col` AS CHAR) LIKE ?";
            $params[] = '%' . $search . '%';
        }
        $where = ' WHERE ' . implode(' OR ', $parts);
    }

    // Всего строк (с учётом фильтра)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`$where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
    if ($totalPages > 0 && $page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET подставляем как целые числа (плейсхолдеры в LIMIT работают
    // не во всех режимах эмуляции PDO, поэтому приводим к int явно)
    $sql = "SELECT * FROM `$table`$where$sortSql LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'     => true,
        'table'       => $table,
        'columns'     => $columns,
        'primary_key' => kbPrimaryKey($pdo, $table),
        'data'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => max(1, $totalPages),
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
