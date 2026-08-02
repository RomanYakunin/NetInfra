<?php
// modules/users/api/UpdateData/change_password.php — смена собственного пароля
// Доступно любому авторизованному пользователю (в т.ч. при must_change_password).
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($new === '' || $confirm === '') {
    echo json_encode(['error' => 'Заполните все поля']);
    exit;
}
if ($new !== $confirm) {
    echo json_encode(['error' => 'Пароли не совпадают']);
    exit;
}
if (mb_strlen($new) < 6) {
    echo json_encode(['error' => 'Пароль должен быть не короче 6 символов']);
    exit;
}

try {
    $userId = currentUserId();
    $stmt = $pdo->prepare("SELECT Password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    if ($hash === false) {
        echo json_encode(['error' => 'Пользователь не найден']);
        exit;
    }

    // Текущий пароль проверяем всегда (поддержка старых открытых паролей)
    if (!password_verify($current, $hash) && $current !== $hash) {
        echo json_encode(['error' => 'Текущий пароль неверен']);
        exit;
    }

    if (password_verify($new, $hash)) {
        echo json_encode(['error' => 'Новый пароль совпадает с текущим']);
        exit;
    }

    $pdo->prepare("UPDATE users SET Password = ?, must_change_password = 0 WHERE id = ?")
        ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);

    $_SESSION['must_change_password'] = 0;

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
