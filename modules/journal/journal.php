<?php
// modules/journal/journal.php — подготовка данных страницы «Журнал».
// Доступ только администратору (дублируется проверкой $adminOnlyPages в index.php).
require_once __DIR__ . '/../../includes/acl.php';

if (!isAdmin()) {
    $error = 'Доступ запрещён: страница доступна только администратору';
    return;
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
