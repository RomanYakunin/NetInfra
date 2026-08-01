<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$logged = isset($_SESSION['user_id']);
$role = $logged ? $_SESSION['role'] : null;

echo json_encode([
    'logged_in' => $logged,
    'role'      => $role,
    'login'     => $logged ? $_SESSION['login'] : null
]);