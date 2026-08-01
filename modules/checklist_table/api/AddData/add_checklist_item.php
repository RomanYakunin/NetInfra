<?php
// api/AddData/add_checklist_item.php – добавляет запись в чек-лист
require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = [
    'equipment_id' => $_POST['equipment_id'] ?? null,
    'node_id' => $_POST['node_id'] ?? null,
    'ip_address' => $_POST['ip_address'] ?? null,
    'category_task_id' => $_POST['category_task_id'] ?? null,
    'type_task_id' => $_POST['type_task_id'] ?? null,
    'description' => $_POST['description'] ?? '',
    'responsible_user_id' => $_POST['responsible_user_id'] ?? null,
    'deadline' => $_POST['deadline'] ?? null,
];

$cols = implode(',', array_keys($data));
$ph = ':' . implode(',:', array_keys($data));
$sql = "INSERT INTO checklist ($cols) VALUES ($ph)";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    $newId = $pdo->lastInsertId();

    // Обновляем статус оборудования
    if ($data['equipment_id']) {
        $pdo->prepare("UPDATE equipment SET status = 'update_needed' WHERE id = ?")
            ->execute([$data['equipment_id']]);
    }

    echo json_encode(['success' => true, 'id' => $newId]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка добавления: ' . $e->getMessage()]);
}