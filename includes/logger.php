<?php
/**
 * includes/logger.php — журналирование действий пользователей.
 *
 * Подключается в обработчиках, изменяющих данные:
 *     require_once dirname(__FILE__, N) . '/includes/logger.php';
 *     logAction($pdo, 'add_node', 'node', $newId, 'КУ-15');
 *
 * Пользователь, логин и IP берутся из сессии/запроса автоматически.
 * Журнал доступен только на чтение (страница ?page=journal).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Определяет IP-адрес клиента.
 * Учитываем прокси предприятия, но берём только первый адрес из X-Forwarded-For.
 */
function clientIp()
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

/**
 * Записывает событие в таблицu logs.
 *
 * Ошибки записи журнала намеренно проглатываются: сбой логирования
 * не должен ломать основную операцию пользователя.
 *
 * @param PDO         $pdo
 * @param string      $action     add_node, edit_node, delete_node, login, logout, ...
 * @param string|null $objectType node | equipment | stack | building | warehouse | rack | user
 * @param int|null    $objectId   ID изменённого объекта
 * @param string|null $objectName Человекочитаемое название объекта
 * @param mixed       $details    Строка либо массив (будет сохранён как JSON)
 * @return bool                   true, если запись добавлена
 */
function logAction(PDO $pdo, $action, $objectType = null, $objectId = null, $objectName = null, $details = '')
{
    try {
        // Массивы/объекты (например, старые и новые значения) кладём как JSON
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $stmt = $pdo->prepare("
            INSERT INTO logs (user_id, username, ip_address, action, object_type, object_id, object_name, details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['login'] ?? 'system',
            clientIp(),
            $action,
            $objectType ?: null,
            $objectId !== null && $objectId !== '' ? (int)$objectId : null,
            $objectName !== null && $objectName !== '' ? mb_substr((string)$objectName, 0, 255) : null,
            $details !== '' ? $details : null,
        ]);
        return true;
    } catch (PDOException $e) {
        // Журнал не должен мешать основной операции
        return false;
    }
}

/**
 * Удаляет записи журнала старше указанного количества дней.
 * Вызывать вручную или по расписанию — из интерфейса журнал не редактируется.
 */
function purgeOldLogs(PDO $pdo, $days = 90)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([(int)$days]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}
