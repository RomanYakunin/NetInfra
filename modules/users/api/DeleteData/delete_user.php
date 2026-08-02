<?php
// modules/users/api/DeleteData/delete_user.php — удаление пользователя (только админ)
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'ID пользователя не указан']);
    exit;
}

// Нельзя удалить самого себя
if ($id === currentUserId()) {
    echo json_encode(['error' => 'Нельзя удалить собственную учётную запись']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT Role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $role = $stmt->fetchColumn();
    if ($role === false) {
        echo json_encode(['error' => 'Пользователь не найден']);
        exit;
    }

    // Нельзя удалить последнего администратора
    if ($role === 'admin') {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE Role = 'admin'")->fetchColumn();
        if ($cnt <= 1) {
            echo json_encode(['error' => 'Это последний администратор — удалить нельзя']);
            exit;
        }
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    require_once dirname(__FILE__, 5) . "/includes/logger.php";
    logAction($pdo, "delete_user", "user", $id, "");

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
