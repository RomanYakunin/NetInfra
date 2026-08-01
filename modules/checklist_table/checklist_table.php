<?php
// checklist_table.php – подготавливает данные для таблицы чек-листа

try {
    $tasks = $pdo->query("SELECT * FROM checklist ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $tasks = [];
}
if (!is_array($tasks)) $tasks = [];