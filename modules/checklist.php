<?php
require_once __DIR__ . '/../includes/db.php';

function addChecklistItem($data) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("INSERT INTO checklist (node_id, equipment_id, description, status, deadline, responsible_user) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['node_id'] ?? null,
        $data['equipment_id'] ?? null,
        $data['description'],
        $data['status'] ?? 'new',
        $data['deadline'] ?? null,
        $data['responsible_user'] ?? currentUser()['login']
    ]);
    return $pdo->lastInsertId();
}