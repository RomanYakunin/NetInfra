<?php
require_once dirname(__FILE__, 2) . '/config/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$login    = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    echo json_encode(['error' => 'Логин и пароль обязательны']);
    exit;
}

// Ищем пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE Login = ?");
$stmt->execute([$login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'Неверный логин или пароль']);
    exit;
}

// Проверка пароля (с поддержкой как хешированных, так и открытых паролей)
$valid = false;
if (password_verify($password, $user['Password'])) {
    $valid = true;
} elseif ($password === $user['Password']) {
    // Открытый пароль – при первом входе автоматически захешируем
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET Password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
    $valid = true;
}

if (!$valid) {
    echo json_encode(['error' => 'Неверный логин или пароль']);
    exit;
}

// Заблокированным вход запрещён
if (array_key_exists('is_active', $user) && (int)$user['is_active'] === 0) {
    echo json_encode(['error' => 'Учётная запись заблокирована']);
    exit;
}

// Сохраняем в сессию
$_SESSION['user_id']   = $user['id'];
$_SESSION['login']     = $user['Login'];
$_SESSION['role']      = $user['Role'];
$_SESSION['must_change_password'] = (int)($user['must_change_password'] ?? 0);

echo json_encode([
    'success' => true,
    'role'    => $user['Role'],
    'login'   => $user['Login'],
    'must_change_password' => (int)($user['must_change_password'] ?? 0)
]);