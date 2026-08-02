<?php
// modules/users/api/GetData/get_users.php — список пользователей (только админ)
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query("
        SELECT id, Login AS login, Role AS role,
               must_change_password, is_active,
               created_at, updated_at
        FROM users
        ORDER BY Login
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Приводим флаги к int — на клиенте удобнее
    foreach ($users as &$u) {
        $u['id'] = (int)$u['id'];
        $u['must_change_password'] = (int)$u['must_change_password'];
        $u['is_active'] = (int)$u['is_active'];
    }
    unset($u);

    echo json_encode(['success' => true, 'data' => $users]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
