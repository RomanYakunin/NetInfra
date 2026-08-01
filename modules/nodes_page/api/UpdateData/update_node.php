<?php
// api/UpdateData/update_node.php – обновляет узел и его локацию
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = $_POST['id'] ?? 0;
if (!$id) {
    echo json_encode(['error' => 'ID не указан']);
    exit;
}

$kyNumber = $_POST['KY_number'] ?? '';
$nodeTypeId = $_POST['node_type_id'] ?? null;
$buildingId = $_POST['building_id'] ?? null;
$workshop = trim($_POST['workshop'] ?? '');
$floor = trim($_POST['floor'] ?? '');
$room = trim($_POST['room'] ?? '');

$kyNumber = $kyNumber !== '' ? $kyNumber : null;
$nodeTypeId = $nodeTypeId !== '' ? $nodeTypeId : null;

try {
    $locationId = $_POST['id_location'] ?? null;
    if ($buildingId) {
        if ($locationId) {
            $stmt = $pdo->prepare("UPDATE locations SET building = ?, workshop = ?, floor = ?, room = ? WHERE id_location = ?");
            $stmt->execute([$buildingId, $workshop ?: null, $floor ?: null, $room ?: null, $locationId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO locations (building, workshop, floor, room) VALUES (?, ?, ?, ?)");
            $stmt->execute([$buildingId, $workshop ?: null, $floor ?: null, $room ?: null]);
            $locationId = $pdo->lastInsertId();
        }
    } else {
        $locationId = null;
    }

    $stmt = $pdo->prepare("UPDATE nodes SET KY_number = ?, node_type_id = ?, id_location = ? WHERE id_node = ?");
    $stmt->execute([$kyNumber, $nodeTypeId, $locationId, $id]);

    echo json_encode(['success' => true, 'id_node' => $id]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка обновления: ' . $e->getMessage()]);
}