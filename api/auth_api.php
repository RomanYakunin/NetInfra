<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST' && isset($input['action'])) {
    if ($input['action'] === 'login') {
        if (login($input['login'], $input['password_hash'])) {
            echo json_encode(['success' => true, 'user' => currentUser()]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Неверные данные']);
        }
    } elseif ($input['action'] === 'logout') {
        logout();
        echo json_encode(['success' => true]);
    }
} elseif ($method === 'GET') {
    if (isLoggedIn()) {
        echo json_encode(currentUser());
    } else {
        http_response_code(401);
        echo json_encode(null);
    }
}