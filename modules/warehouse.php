<?php
require_once __DIR__ . '/../includes/db.php';

function moveToWarehouse($equipmentId) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE equipment SET location_type = 'warehouse', node_id = NULL, cabinet = '—', cabinet_type = 'Без шкафа', unit = NULL, is_active = 0 WHERE id = ?");
    $stmt->execute([$equipmentId]);
}

function moveToNode($equipmentId, $nodeId) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE equipment SET location_type = 'node', node_id = ?, is_active = 1 WHERE id = ?");
    $stmt->execute([$nodeId, $equipmentId]);
}