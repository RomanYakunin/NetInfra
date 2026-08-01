<?php
// api/AddData/add_location.php – добавляет новую локацию
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$buildingId = $_POST['building_id'] ?? null;
$workshop   = trim($_POST['workshop'] ?? '');
$floor      = trim($_POST['floor'] ?? '');
$room       = trim($_POST['room'] ?? '');

if (empty($buildingId)) {
    echo json_encode(['error' => 'Не выбрано здание']);
    exit;
}

// Приводим пустые строки к NULL
$workshop = $workshop !== '' ? $workshop : null;
$floor    = $floor !== '' ? $floor : null;
$room     = $room !== '' ? $room : null;

try {
    $stmt = $pdo->prepare("INSERT INTO locations (building, workshop, floor, room) VALUES (?, ?, ?, ?)");
    $stmt->execute([$buildingId, $workshop, $floor, $room]);
    $newId = $pdo->lastInsertId();

    // Получаем готовое display для новой локации
    $stmt = $pdo->prepare(
        "SELECT CONCAT_WS(' ',
            COALESCE(b.Name_Building, ''),
            NULLIF(l.workshop, ''),
            CONCAT('этаж ', NULLIF(l.floor, '')),
            F(l.room REGEXP '^[0-9]+$', CONCAT('каб. ', l.room), l.room)
        ) AS display
         FROM locations l
         LEFT JOIN Buildings b ON l.building = b.Id
         WHERE l.id_location = ?"
    );
    $stmt->execute([$newId]);
    $display = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'id' => $newId, 'display' => $display]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка добавления: ' . $e->getMessage()]);
}