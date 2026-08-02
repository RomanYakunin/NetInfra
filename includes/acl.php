<?php
/**
 * includes/acl.php — проверка прав доступа для AJAX-обработчиков.
 *
 * Подключается в начале любого обработчика, изменяющего данные:
 *     require_once dirname(__FILE__, N) . '/includes/acl.php';
 *     requireAdmin();
 *
 * Роли:
 *   admin — полный доступ
 *   user  — только просмотр (любая операция записи запрещена)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Текущая роль пользователя ('admin' | 'user' | null, если не авторизован). */
function currentRole()
{
    return $_SESSION['role'] ?? null;
}

/** ID текущего пользователя или null. */
function currentUserId()
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** Является ли текущий пользователь администратором. */
function isAdmin()
{
    return currentRole() === 'admin';
}

/**
 * Прерывает выполнение с 403, если пользователь не администратор.
 * Отдаёт JSON — все вызывающие обработчики работают через AJAX.
 */
function requireAdmin()
{
    if (!isAdmin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Недостаточно прав: требуется роль администратора']);
        exit;
    }
}

/** Прерывает выполнение с 401, если пользователь не авторизован. */
function requireAuth()
{
    if (currentUserId() === null) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Требуется авторизация']);
        exit;
    }
}
