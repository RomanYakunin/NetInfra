<?php
// modules/nodes_table/nodes_table_ajax.php – AJAX-эндпоинт для получения данных таблицы узлов
require_once dirname(__FILE__, 2) . '/config/db.php';   // путь к config/db.php относительно modules/nodes_table
header('Content-Type: application/json; charset=utf-8');

// Параметры пагинации и поиска
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, (int)($_GET['per_page'] ?? 50));
$search = trim($_GET['search'] ?? '');
$sortField = $_GET['sort'] ?? 'KY_number';
$sortOrder = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

// Список разрешённых полей для сортировки
$allowedSorts = ['KY_number', 'status', 'device_count', 'location_display', 'node_type_name'];
if (!in_array($sortField, $allowedSorts)) {
    $sortField = 'KY_number';
}

// Формируем WHERE для поиска
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE (n.KY_number LIKE ? OR n.status LIKE ? OR l.building_name LIKE ? OR nt.name_node_type LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s, $s];
}

// Основной запрос (аналогично nodes_table.php, но без полного перестроения)
$sql = "SELECT n.id_node, n.KY_number, n.status,
               (SELECT COUNT(*) FROM equipment WHERE id_node = n.id_node) AS device_count,
               CONCAT_WS(' ', COALESCE(b.Name_Builder, ''), NULLIF(l.workshop, ''), CONCAT('этаж ', NULLIF(l.floor, '')), CONCAT('каб. ', NULLIF(l.room, ''))) AS location_display,
               nt.name_node_type
        FROM nodes n
        LEFT JOIN locations l ON n.id_location = l.id_location
        LEFT JOIN Buildings b ON l.building = b.Id
        LEFT JOIN node_types nt ON n.node_type_id = nt.id_node_type
        $where
        ORDER BY $sortField $sortOrder
        LIMIT " . (($page-1)*$perPage) . ", $perPage";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$nodes = $stmt->fetchAll();

// Общее количество записей (для пагинации)
$countSql = "SELECT COUNT(*) FROM nodes n
             LEFT JOIN locations l ON n.id_location = l.id_location
             LEFT JOIN Buildings b ON l.building = b.Id
             LEFT JOIN node_types nt ON n.node_type_id = nt.id_node_type
             $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

echo json_encode([
    'data' => $nodes,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage
]);