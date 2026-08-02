<?php
// modules/journal/journal.php — подготовка данных страницы «Журнал».
// Доступ только администратору (дублируется проверкой $adminOnlyPages в index.php).
require_once __DIR__ . '/../../includes/acl.php';

if (!isAdmin()) {
    $error = 'Доступ запрещён: страница доступна только администратору';
    return;
}

/**
 * Название действия по-русски (используется в фильтре шаблона).
 * Тот же словарь продублирован в journal.js для отрисовки таблицы.
 */
function journalActionLabel($action)
{
    static $map = [
        'login' => 'Вход', 'logout' => 'Выход',
        'add_node' => 'Добавление узла', 'edit_node' => 'Изменение узла', 'delete_node' => 'Удаление узла',
        'add_equipment' => 'Добавление оборудования', 'edit_equipment' => 'Изменение оборудования',
        'delete_equipment' => 'Удаление оборудования',
        'move' => 'Перемещение', 'move_equipment' => 'Перемещение',
        'add_stack' => 'Создание стека', 'edit_stack' => 'Изменение стека',
        'save_stack_device' => 'Изменение устройства стека',
        'delete_stack_device' => 'Вывод устройства из стека',
        'add_building' => 'Добавление здания', 'edit_building' => 'Изменение здания',
        'delete_building' => 'Удаление здания',
        'add_warehouse' => 'Добавление склада', 'edit_warehouse' => 'Изменение склада',
        'delete_warehouse' => 'Удаление склада',
        'add_rack' => 'Добавление шкафа',
        'add_user' => 'Добавление пользователя', 'edit_user' => 'Изменение пользователя',
        'delete_user' => 'Удаление пользователя',
    ];
    return $map[$action] ?? $action;
}

// Списки для фильтров берём из самих логов — показываем только то,
// что реально встречается в журнале.
$journalUsers = [];
$journalActions = [];
try {
    $journalUsers = $pdo->query("
        SELECT DISTINCT user_id, username
        FROM logs
        WHERE user_id IS NOT NULL
        ORDER BY username
    ")->fetchAll(PDO::FETCH_ASSOC);

    $journalActions = $pdo->query("
        SELECT DISTINCT action FROM logs ORDER BY action
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Таблица logs может ещё не существовать — страница откроется с пустыми фильтрами
}
