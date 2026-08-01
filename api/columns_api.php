<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../modules/columns.php';

header('Content-Type: application/json');
requireRole(['admin', 'superadmin']);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    if ($method === 'POST') {
        $action = $_GET['action'] ?? $input['action'] ?? '';
        if ($action === 'add') {
            addColumn($input['table'], $input['name'], $input['type']);
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete') {
            deleteColumn($input['table'], $input['name']);
            echo json_encode(['success' => true]);
        }
    } elseif ($method === 'GET') {
        $table = $_GET['table'] ?? 'nodes';
        $userId = currentUser()['id'];
        echo json_encode(getUserColumnPrefs($userId, $table));
    } elseif ($method === 'PUT') {
        $table = $input['table'];
        $userId = currentUser()['id'];
        saveUserColumnPrefs($userId, $table, $input['column_order'], $input['visible_columns']);
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}