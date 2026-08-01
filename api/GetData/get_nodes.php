<?php
// api/GetData/get_nodes.php – динамическая подгрузка узлов

require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$search     = $_GET['search'] ?? '';
$buildingId = $_GET['building_id'] ?? 0;
$sortField  = $_GET['sort'] ?? 'ky';
$sortOrder  = strtoupper($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = max(1, min(100, (int)($_GET['per_page'] ?? 10)));

// Разрешённые поля для сортировки (числовая сортировка для KY_number)
$allowedSorts = [
    'ky'          => 'n.KY_number + 0',   // ← числовая сортировка
    'status'      => 'n.status',
    'location'    => 'location_display',
    'nodetype'    => 'node_type_name',
    'devicecount' => 'device_count'
];
$sortCol = $allowedSorts[$sortField] ?? 'n.KY_number + 0';   // по умолчанию – по номеру КУ

// Базовый запрос с JOIN
$sql = "SELECT n.id_node, n.KY_number, n.status,
               CONCAT_WS(' ', COALESCE(b.Name_Building,''), NULLIF(l.workshop,''), CONCAT('этаж ', NULLIF(l.floor,'')), CONCAT('каб. ', NULLIF(l.room,''))) AS location_display,
               nt.name_node_type AS node_type_name,
               (SELECT COUNT(*) FROM equipment WHERE id_node = n.id_node) AS device_count
        FROM nodes n
        LEFT JOIN locations l ON n.id_location = l.id_location
        LEFT JOIN Buildings b ON l.building = b.Id
        LEFT JOIN node_types nt ON n.node_type_id = nt.id_node_type
        WHERE 1=1";
$countSql = "SELECT COUNT(*) FROM nodes n WHERE 1=1";
$params = [];

// Фильтр по зданию
if ($buildingId) {
    $sql .= " AND l.building = ?";
    $countSql .= " AND l.building = ?";
    $params[] = $buildingId;
}

// Поиск
if ($search) {
    // Исправлена опечатка: Name_Buildong → Name_Building
    $searchWhere = " AND (n.KY_number LIKE ? OR l.workshop LIKE ? OR l.floor LIKE ? OR l.room LIKE ? OR b.Name_Building LIKE ? OR nt.name_node_type LIKE ?)";
    $sql .= $searchWhere;
    $countSql .= $searchWhere;
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s, $s);
}

// Сортировка (по умолчанию – по возрастанию номера КУ)
$sql .= " ORDER BY $sortCol $sortOrder";

// Пагинация
$countParams = $params;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total = $countStmt->fetchColumn();

$offset = ($page - 1) * $perPage;
$sql .= " LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$nodes = $stmt->fetchAll();

echo json_encode([
    'data' => $nodes,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage
]);