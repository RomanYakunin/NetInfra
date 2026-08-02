<?php
// modules/knowledge_base/knowledge_base.php — подготовка данных страницы «База знаний».
// Доступ только администратору (дублируется проверкой $adminOnlyPages в index.php).
require_once __DIR__ . '/../../includes/acl.php';

if (!isAdmin()) {
    $error = 'Доступ запрещён: страница доступна только администратору';
    return;
}

// Разметка выводится из index.php (case 'database_manager' в блоке шаблонов),
// список таблиц подгружается клиентом через ?ajax=kb_get_tables.
