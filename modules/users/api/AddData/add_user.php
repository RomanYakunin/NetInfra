<?php
// modules/users/api/AddData/add_user.php — создание пользователя (только админ)
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

$login    = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? 'user';
$mustChange = !empty($_POST['must_change_password']) ? 1 : 0;

if ($login === '' || $password === '') {
    echo json_encode(['error' => 'Логин и пароль обязательны']);
    exit;
}
if (mb_strlen($password) < 6) {
    echo json_encode(['error' => 'Пароль должен быть не короче 6 символов']);
    exit;
}
if (!in_array($role, ['admin', 'user'], true)) {
    $role = 'user';
}

try {
    // Проверяем уникальность логина
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Login = ?");
    $stmt->execute([$login]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['error' => 'Пользователь с таким логином уже существует']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (Login, Password, Role, must_change_password, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$login, $hash, $role, $mustChange]);

    // ID берём ДО записи в журнал: logAction делает свой INSERT,
    // после которого lastInsertId() вернул бы id строки журнала
    $newUserId = (int)$pdo->lastInsertId();

    require_once dirname(__FILE__, 5) . "/includes/logger.php";
    logAction($pdo, 'add_user', 'user', $newUserId, $login, ['role' => $role]);

    echo json_encode(['success' => true, 'id' => $newUserId]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
