<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../modules/data.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$table = $_GET['table'] ?? 'nodes';
$allowed = ['nodes','equipment','checklist','action_log'];
if (!in_array($table, $allowed)) { http_response_code(400); echo json_encode(['error'=>'Invalid table']); exit; }

$filters = ['search' => $_GET['search'] ?? ''];
echo json_encode(getTableData($table, currentUser()['id'], $filters));