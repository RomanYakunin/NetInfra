<?php
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$kyNumber   = $_POST['KY_number'] ?? '';
$nodeTypeId = $_POST['node_type_id'] ?? null;
$buildingId = $_POST['building_id'] ?? null;
$workshop   = trim($_POST['workshop'] ?? '');
$floor      = trim($_POST['floor'] ?? '');
$room       = trim($_POST['room'] ?? '');

// if (empty($kyNumber)) {
//     echo json_encode(['error' => 'Номер КУ обязателен']);
//     exit;
// }

$kyNumber = $kyNumber !== '' ? $kyNumber : null;
$nodeTypeId = $nodeTypeId !== '' ? $nodeTypeId : null;

try {
    // Определяем id_location: ищем существующую локацию или создаём новую
    $locationId = null;
    if (!empty($buildingId)) {
        $stmt = $pdo->prepare("SELECT id_location FROM locations WHERE building = ? AND COALESCE(workshop,'') = ? AND COALESCE(floor,'') = ? AND COALESCE(room,'') = ?");
        $stmt->execute([$buildingId, $workshop, $floor, $room]);
        $existingLocation = $stmt->fetchColumn();
        if ($existingLocation) {
            $locationId = $existingLocation;
        } else {
            $stmt = $pdo->prepare("INSERT INTO locations (building, workshop, floor, room) VALUES (?, ?, ?, ?)");
            $stmt->execute([$buildingId, $workshop ?: null, $floor ?: null, $room ?: null]);
            $locationId = $pdo->lastInsertId();
        }
    }

    // Вставляем узел
    $stmt = $pdo->prepare("INSERT INTO nodes (KY_number, id_location, node_type_id, status) VALUES (?, ?, ?, 'inactive')");
    $stmt->execute([$kyNumber, $locationId, $nodeTypeId]);
    $newId = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Узел успешно добавлен']);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка добавления: ' . $e->getMessage()]);
}