<?php
// modules/nodes_page/api/AddData/add_rack.php – добавление шкафа (экземпляра)
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Имя шкафа обязательно']);
    exit;
}

$modelId    = !empty($_POST['model_id']) ? (int)$_POST['model_id'] : null;
$buildingId = !empty($_POST['building_id']) ? (int)$_POST['building_id'] : null;
$workshop   = trim($_POST['workshop'] ?? '');
$floor      = trim($_POST['floor'] ?? '');
$room       = trim($_POST['room'] ?? '');
$status     = $_POST['status'] ?? 'в эксплуатации';
$allowedStatuses = ['в эксплуатации', 'на складе', 'обслуживается', 'демонтирован'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'в эксплуатации';
}
$notes = trim($_POST['notes'] ?? '') ?: null;

try {
    $locationId = null;
    if ($buildingId) {
        $stmt = $pdo->prepare("SELECT id_location FROM locations WHERE building = ? AND COALESCE(workshop,'') = ? AND COALESCE(floor,'') = ? AND COALESCE(room,'') = ?");
        $stmt->execute([$buildingId, $workshop, $floor, $room]);
        $locationId = $stmt->fetchColumn();
        if (!$locationId) {
            $stmt = $pdo->prepare("INSERT INTO locations (building, workshop, floor, room) VALUES (?, ?, ?, ?)");
            $stmt->execute([$buildingId, $workshop ?: null, $floor ?: null, $room ?: null]);
            $locationId = $pdo->lastInsertId();
        }
    }

    $stmt = $pdo->prepare("INSERT INTO racks (name, model_id, location_id, status, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $modelId, $locationId, $status, $notes]);
    $newId = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'id' => $newId, 'name' => $name]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
}
