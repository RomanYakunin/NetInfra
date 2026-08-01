<?php
require_once __DIR__ . '/../includes/db.php';
require_once 'columns.php';

function getTableData($tableName, $userId, $filters = []) {
    $pdo = Database::getConnection();
    $prefs = getUserColumnPrefs($userId, $tableName);
    $visible = array_intersect($prefs['visible_columns'], array_column(getColumnsMeta($tableName), 'column_name'));
    if (empty($visible)) $visible = ['id'];

    $cols = '`' . implode('`, `', $visible) . '`';
    $sql = "SELECT $cols FROM `$tableName`";
    $params = [];

    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $where = [];
        foreach ($visible as $col) {
            $where[] = "`$col` LIKE ?";
            $params[] = $search;
        }
        $sql .= " WHERE " . implode(' OR ', $where);
    }

    // Пагинация и сортировка могут быть добавлены здесь
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}