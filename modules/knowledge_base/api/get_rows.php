<?php
require_once dirname(__FILE__, 4) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$table = $_GET['table'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;

// Проверка существования таблицы
$stmt = $pdo->query("SHOW TABLES LIKE '$table'");
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Таблица не найдена']);
    exit;
}

// Получаем колонки
$cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);

$where = '';
$params = [];
if ($search !== '') {
    $likes = [];
    foreach ($cols as $col) {
        $likes[] = "`$col` LIKE ?";
        $params[] = "%$search%";
    }
    $where = ' WHERE ' . implode(' OR ', $likes);
}

// Считаем общее количество
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`" . $where);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$offset = ($page - 1) * $perPage;
$sql = "SELECT * FROM `$table`" . $where . " LIMIT $perPage OFFSET $offset";
$dataStmt = $pdo->prepare($sql);
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'columns' => $cols,
    'rows' => $rows,
    'total' => $total,
    'page' => $page,
    'perPage' => $perPage
]);