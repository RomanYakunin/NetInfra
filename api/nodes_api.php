<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../modules/nodes.php';

header('Content-Type: application/json');
requireRole(['admin','superadmin']);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
    $id = addNode($input);
    echo json_encode(['success' => true, 'id' => $id]);
} elseif ($method === 'PUT' && isset($_GET['id'])) {
    $res = updateNode($_GET['id'], $input);
    echo json_encode(['success' => $res > 0]);
} elseif ($method === 'DELETE' && isset($_GET['id'])) {
    $res = deleteNode($_GET['id']);
    echo json_encode(['success' => $res > 0]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}