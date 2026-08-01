<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__FILE__, 5) . '/config/db.php';

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit;
}

$fragments = array_filter(array_map('trim', explode(',', $q)));
if (empty($fragments)) {
    echo json_encode([]);
    exit;
}

$placeholders = [];
$params = [];
foreach ($fragments as $frag) {
    $placeholders[] = "hostname LIKE ?";
    $params[] = $frag . '%';
}

$sql = "SELECT DISTINCT hostname FROM equipment WHERE " . implode(' OR ', $placeholders);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hostnames = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($hostnames);