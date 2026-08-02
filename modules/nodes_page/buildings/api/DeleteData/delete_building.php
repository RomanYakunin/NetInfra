<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 6) . '/includes/acl.php';
requireAdmin();
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Поддержка как FormData, так и JSON
$buildingId = $_POST['id'] ?? 0;
if (!$buildingId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $buildingId = $input['id'] ?? 0;
}

if (!$buildingId) {
    echo json_encode(['error' => 'Не указан ID здания']);
    exit;
}

try {
    // Проверяем, есть ли связанные локации
    $check = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE building = ?");
    $check->execute([$buildingId]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['error' => 'Невозможно удалить здание, так как на него ссылаются локации. Сначала удалите или перенесите локации.']);
        exit;
    }

    // Название запоминаем до удаления — для журнала
    $stmtName = $pdo->prepare("SELECT Name_Building FROM Buildings WHERE Id = ?");
    $stmtName->execute([$buildingId]);
    $buildingName = $stmtName->fetchColumn() ?: '';

    $stmt = $pdo->prepare("DELETE FROM Buildings WHERE Id = ?");
    $stmt->execute([$buildingId]);

    if ($stmt->rowCount() > 0) {
        require_once dirname(__FILE__, 6) . '/includes/logger.php';
        logAction($pdo, 'delete_building', 'building', $buildingId, $buildingName);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Здание не найдено']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка удаления: ' . $e->getMessage()]);
}