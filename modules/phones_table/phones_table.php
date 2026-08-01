<?php
// modules/phones_table/phones_table.php
$tab = $_GET['tab'] ?? 'phones'; // phones или renames
$buildingFilter = isset($_GET['building']) ? (int)$_GET['building'] : 0;

// Список зданий для боковой панели
$buildings = $pdo->query("SELECT Id AS id, Name_Builder AS name FROM Buildings ORDER BY Name_Builder")->fetchAll();

// Столбцы таблицы телефонов
$phoneColumns = $pdo->query("SHOW COLUMNS FROM phones")->fetchAll(PDO::FETCH_COLUMN);
$visibleCols = array_values(array_diff($phoneColumns, ['id']));
$headers = [];
$fallbackNames = [
    'name' => 'Название',
    'ip_address' => 'IP-адрес',
    'location' => 'Расположение',
    'status' => 'Статус',
    'created_at' => 'Создан'
];
foreach ($visibleCols as $col) {
    $headers[] = $fallbackNames[$col] ?? $col;
}
$colAliases = array_combine($visibleCols, $visibleCols);

if ($tab === 'phones') {
    // Таблица телефонов с фильтром по зданию
    if ($buildingFilter > 0) {
        $stmt = $pdo->prepare("SELECT * FROM phones WHERE location_id = ?");
        $stmt->execute([$buildingFilter]);
        $phones = $stmt->fetchAll();
    } else {
        $phones = $pdo->query("SELECT * FROM phones")->fetchAll();
    }
} else {
    // Таблица переименований
    $renames = $pdo->query("
        SELECT pr.id, p.name AS phone_name, pr.old_name, pr.new_name, pr.changed_at
        FROM phone_renames pr
        LEFT JOIN phones p ON pr.phone_id = p.id
        ORDER BY pr.changed_at DESC
    ")->fetchAll();
}