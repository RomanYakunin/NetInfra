<?php
require_once 'auth.php';

function requireRole($roles) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $user = currentUser();
    if (!in_array($user['role'], (array)$roles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

function isAdmin() {
    return isLoggedIn() && in_array(currentUser()['role'], ['admin', 'superadmin']);
}
function isSuperAdmin() {
    return isLoggedIn() && currentUser()['role'] === 'superadmin';
}