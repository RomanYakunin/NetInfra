<?php
require_once __DIR__ . '/../includes/db.php';

function addUser($data) {
    $pdo = Database::getConnection();
    $hash = password_hash($data['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$data['login'], $hash, $data['full_name'], $data['email'], $data['role'] ?? 'viewer']);
    return $pdo->lastInsertId();
}

function getUsers() {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SELECT id, login, full_name, email, role FROM users");
    return $stmt->fetchAll();
}