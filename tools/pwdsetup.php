<?php
// Готовит и убирает тестового пользователя для проверки смены пароля.
require_once dirname(__DIR__) . '/config/db.php';
header('Content-Type: text/plain; charset=utf-8');

$act   = $_GET['act'] ?? '';
$login = '_pwd_test';

if ($act === 'create') {
    $pdo->prepare("DELETE FROM users WHERE Login = ?")->execute([$login]);
    $pdo->prepare("INSERT INTO users (Login, Password, Role, must_change_password, is_active)
                   VALUES (?, ?, 'user', 1, 1)")
        ->execute([$login, password_hash('StartPass1', PASSWORD_DEFAULT)]);
    echo "пользователь $login создан, must_change_password=1";
    exit;
}

if ($act === 'flag') {
    $stmt = $pdo->prepare("SELECT must_change_password FROM users WHERE Login = ?");
    $stmt->execute([$login]);
    echo "признак в базе после смены: must_change_password=" . var_export($stmt->fetchColumn(), true);
    exit;
}

if ($act === 'drop') {
    $pdo->prepare("DELETE FROM users WHERE Login = ?")->execute([$login]);
    echo "тестовый пользователь удалён";
    exit;
}

echo 'нужен параметр act=create|flag|drop';
