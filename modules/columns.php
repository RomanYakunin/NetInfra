<?php
require_once __DIR__ . '/../includes/db.php';

function getColumnsMeta($tableName) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT column_name, column_type, ordinal_position FROM column_metadata WHERE table_name = ? ORDER BY ordinal_position");
    $stmt->execute([$tableName]);
    return $stmt->fetchAll();
}

function getUserColumnPrefs($userId, $tableName) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT column_order, visible_columns FROM user_column_prefs WHERE user_id = ? AND table_name = ?");
    $stmt->execute([$userId, $tableName]);
    $prefs = $stmt->fetch();
    if ($prefs) {
        return [
            'column_order' => json_decode($prefs['column_order']),
            'visible_columns' => json_decode($prefs['visible_columns'])
        ];
    }
    // Дефолт: все колонки из метаданных
    $meta = getColumnsMeta($tableName);
    $order = array_column($meta, 'column_name');
    return [
        'column_order' => $order,
        'visible_columns' => $order
    ];
}

function saveUserColumnPrefs($userId, $tableName, $columnOrder, $visibleColumns) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("INSERT INTO user_column_prefs (user_id, table_name, column_order, visible_columns) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE column_order = VALUES(column_order), visible_columns = VALUES(visible_columns)");
    $stmt->execute([$userId, $tableName, json_encode($columnOrder), json_encode($visibleColumns)]);
}

function addColumn($tableName, $columnName, $columnType) {
    $pdo = Database::getConnection();
    // Проверка существования
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM column_metadata WHERE table_name = ? AND column_name = ?");
    $stmt->execute([$tableName, $columnName]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Столбец '$columnName' уже существует");
    }
    // ALTER TABLE
    $pdo->exec("ALTER TABLE `$tableName` ADD `$columnName` $columnType");
    // Позиция
    $posStmt = $pdo->prepare("SELECT MAX(ordinal_position)+1 FROM column_metadata WHERE table_name = ?");
    $posStmt->execute([$tableName]);
    $position = $posStmt->fetchColumn() ?: 1;
    // Метаданные
    $pdo->prepare("INSERT INTO column_metadata (table_name, column_name, column_type, ordinal_position) VALUES (?, ?, ?, ?)")
        ->execute([$tableName, $columnName, $columnType, $position]);
    // Сброс пользовательских настроек
    $pdo->prepare("DELETE FROM user_column_prefs WHERE table_name = ?")->execute([$tableName]);
}

function deleteColumn($tableName, $columnName) {
    $pdo = Database::getConnection();
    if ($columnName === 'id') throw new Exception('Нельзя удалить столбец id');
    // Удалить из таблицы
    $pdo->exec("ALTER TABLE `$tableName` DROP COLUMN `$columnName`");
    // Метаданные
    $pdo->prepare("DELETE FROM column_metadata WHERE table_name = ? AND column_name = ?")->execute([$tableName, $columnName]);
    // Сброс настроек
    $pdo->prepare("DELETE FROM user_column_prefs WHERE table_name = ?")->execute([$tableName]);
}