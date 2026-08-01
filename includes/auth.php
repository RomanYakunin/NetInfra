<?php
session_start();
require_once __DIR__ . '/db.php';

function login($login, $password) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = admin");
    echo ($login);
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}