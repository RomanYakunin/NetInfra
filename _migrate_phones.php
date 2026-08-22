<?php
// Разовый запуск миграции телефонов. Удаляется после применения.
require_once __DIR__ . '/config/db.php';
header('Content-Type: text/plain; charset=utf-8');

$sql = file_get_contents(__DIR__ . '/migrations/phones.sql');

// Режем на операторы по ';' в конце строки (внутри нет процедур с ';')
$statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql)));

foreach ($statements as $i => $st) {
    // Убираем строки-комментарии, чтобы не запускать пустышки
    $clean = trim(preg_replace('/^\s*--.*$/m', '', $st));
    if ($clean === '') continue;

    $label = mb_substr(trim(preg_replace('/\s+/', ' ', $clean)), 0, 72);
    try {
        $pdo->exec($clean);
        echo "[OK]   $label\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Повторный запуск: уже существующие объекты — не ошибка
        if (strpos($msg, 'Duplicate column') !== false
            || strpos($msg, 'Duplicate key') !== false
            || strpos($msg, 'already exists') !== false) {
            echo "[ПРОП] $label\n         (уже применено)\n";
        } else {
            echo "[СБОЙ] $label\n         $msg\n";
        }
    }
}

echo "\n--- Таблицы телефонии ---\n";
foreach (['phone_models','expansion_models','departments','deliveries','delivery_boxes',
          'phones','expansion_modules','phone_renames','phone_replacements'] as $t) {
    try {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo str_pad($t, 22) . " строк: $n\n";
    } catch (PDOException $e) {
        echo str_pad($t, 22) . " НЕТ ТАБЛИЦЫ\n";
    }
}
echo "\nlogs.category: ";
try {
    $c = $pdo->query("SHOW COLUMNS FROM logs LIKE 'category'")->fetch(PDO::FETCH_ASSOC);
    echo $c ? $c['Type'] : 'НЕТ';
} catch (PDOException $e) { echo 'ошибка'; }
echo "\n";
