<?php
// config/db.php – Подключение к базе данных и общие вспомогательные функции.

$host = 'netinfra';
$dbname = 'NetInfrastructure';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Определяем имя столбца в equipment для связи с nodes
$equipmentFkColumn = 'id_node';
try {
    $eqCols = $pdo->query("SHOW COLUMNS FROM equipment")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('id_node', $eqCols) && in_array('node_id', $eqCols)) {
        $equipmentFkColumn = 'node_id';
    }
} catch (PDOException $e) {}

/**
 * Пинг IP-адреса
 */
function myPing($ip) {
    $cmd = "ping -c 1 -W 1 " . escapeshellarg($ip) . " 2>&1";
    exec($cmd, $output, $returnVar);
    return $returnVar === 0;
}

/**
 * Собирает уникальные IP из массива записей
 */
function collectUniqueIps($rows, $column = 'ip_address') {
    $ips = [];
    foreach ($rows as $row) {
        $ip = $row[$column] ?? '';
        if ($ip !== '' && !in_array($ip, $ips)) {
            $ips[] = $ip;
        }
    }
    return $ips;
}

/**
 * Загружает русские названия столбцов из таблицы column_translations
 */
function loadTranslations($tableName, $lang = 'ru') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT column_name, translation FROM column_translations WHERE table_name = ? AND lang = ?");
        $stmt->execute([$tableName, $lang]);
        $translations = [];
        while ($row = $stmt->fetch()) {
            $translations[$row['column_name']] = $row['translation'];
        }
        return $translations;
    } catch (PDOException $e) {
        return [];
    }
}