<?php
// modules/knowledge_base/api/kb_get_tables.php — список таблиц БД
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 4) . '/config/db.php';
}
require_once __DIR__ . '/kb_helpers.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $tables = kbGetTables($pdo);

    // Для каждой таблицы — количество строк (примерное, из information_schema дешевле,
    // но точное значение нагляднее при небольших таблицах справочников)
    $result = [];
    foreach ($tables as $t) {
        $count = null;
        try {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        } catch (PDOException $e) {
            $count = null;
        }
        $result[] = ['name' => $t, 'rows' => $count];
    }

    echo json_encode(['success' => true, 'data' => $result]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
