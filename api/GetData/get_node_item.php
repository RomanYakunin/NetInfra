<?php
// api/GetData/get_node_item.php – расширенная версия с форматированием локации
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) {
    echo json_encode(['error' => 'ID не указан или некорректен']);
    exit;
}

try {
    $sql = "SELECT n.*,
                   l.id_location,
                   l.building AS building_id,
                   l.workshop,
                   l.floor,
                   l.room,
                   nt.name_node_type AS node_type_name,
                   b.Name_Building AS building_name,
                   (SELECT COUNT(*) FROM equipment e WHERE e.id_node = n.id_node) AS device_count
            FROM nodes n
            LEFT JOIN locations l ON n.id_location = l.id_location
            LEFT JOIN node_types nt ON n.node_type_id = nt.id_node_type
            LEFT JOIN Buildings b ON l.building = b.Id
            WHERE n.id_node = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $node = $stmt->fetch();

    if (!$node) {
        echo json_encode(['error' => 'Узел не найден']);
        exit;
    }

    // Формируем location_display в формате "Здание этаж X каб. Y"
    $parts = [];
    if (!empty($node['building_name'])) {
        $parts[] = $node['building_name'];
    }
    if (!empty($node['workshop'])) {
        $parts[] = $node['workshop'];
    }
    if (!empty($node['floor'])) {
        if (is_numeric($node['floor'])) {
            $parts[] = 'этаж ' . $node['floor'];
        } else {
            $parts[] = $node['floor'];
        }
    }
    if (!empty($node['room'])) {
        if (is_numeric($node['room'])) {
            $parts[] = 'каб. ' . $node['room'];
        } else {
            $parts[] = $node['room'];
        }
    }
    $node['location_display'] = implode(' ', $parts);

    echo json_encode($node);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}