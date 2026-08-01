<?php
// api/GetData/get_node.php – возвращает JSON с данными узла и его локации
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID не указан']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $sql = "SELECT n.*,
                   l.id_location,
                   l.building AS building_id,
                   l.workshop,
                   l.floor,
                   l.room
            FROM nodes n
            LEFT JOIN locations l ON n.id_location = l.id_location
            WHERE n.id_node = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $node = $stmt->fetch();

    if (!$node) {
        echo json_encode(['error' => 'Узел не найден']);
        exit;
    }
    // Убираем дублирующее поле id_location, оставляем building_id и т.д.
    // но можно оставить для совместимости, не страшно
    echo json_encode($node);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}