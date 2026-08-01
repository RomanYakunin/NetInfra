<?php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$equip1Id = (int)$_POST['equip1_id'];
$equip2Id = (int)$_POST['equip2_id'];
$newUnit  = (int)$_POST['new_unit'];

if (!$equip1Id) {
    echo json_encode(['error' => 'Не указано устройство']);
    exit;
}

$pdo->beginTransaction();
try {
    if ($equip2Id) {
        // Обмен юнитами
        $stmt = $pdo->prepare("SELECT unit_position FROM equipment WHERE id = ?");
        $stmt->execute([$equip2Id]);
        $unit2 = $stmt->fetchColumn();
        $pdo->prepare("UPDATE equipment SET unit_position = ? WHERE id = ?")->execute([$unit2, $equip1Id]);
        $pdo->prepare("UPDATE equipment SET unit_position = ? WHERE id = ?")->execute([$newUnit, $equip2Id]);
    } else {
        // Перемещение в свободный юнит
        $pdo->prepare("UPDATE equipment SET unit_position = ? WHERE id = ?")->execute([$newUnit, $equip1Id]);
    }
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}