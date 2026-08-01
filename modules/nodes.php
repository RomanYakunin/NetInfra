<?php
require_once __DIR__ . '/../includes/db.php';

function addNode($data) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("INSERT INTO nodes (name, logical_device_access, location, type, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['name'],
        $data['logical_device_access'] ?? '',
        $data['location'] ?? '',
        $data['type'] ?? '',
        $data['description'] ?? ''
    ]);
    return $pdo->lastInsertId();
}

function updateNode($id, $data) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE nodes SET name = ?, logical_device_access = ?, location = ?, type = ?, description = ? WHERE id = ?");
    $stmt->execute([
        $data['name'],
        $data['logical_device_access'] ?? '',
        $data['location'] ?? '',
        $data['type'] ?? '',
        $data['description'] ?? '',
        $id
    ]);
    return $stmt->rowCount();
}

function deleteNode($id) {
    $pdo = Database::getConnection();
    // Проверка наличия оборудования (по желанию)
    $stmt = $pdo->prepare("DELETE FROM nodes WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount();
}