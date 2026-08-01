<?php
require_once dirname(__FILE__, 4) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$ignore = ['column_translations']; // можно добавить другие
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $table = $row[0];
    if (in_array($table, $ignore)) continue;
    $tables[] = $table;
}
sort($tables);
echo json_encode($tables);