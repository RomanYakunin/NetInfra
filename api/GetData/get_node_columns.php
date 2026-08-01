<?php
require_once dirname(__FILE__, 3) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$allCols = $pdo->query("SHOW COLUMNS FROM nodes")->fetchAll(PDO::FETCH_COLUMN);
// Исключаем id_node, device_count, status и id_location
$exclude = ['id_node', 'device_count', 'status', 'id_location'];
$cols = array_values(array_diff($allCols, $exclude));

$translations = loadTranslations('nodes');

$result = [];
foreach ($cols as $col) {
    $field = [
        'name'  => $col,
        'label' => $translations[$col] ?? $col,
        'type'  => 'text'
    ];
    if ($col === 'node_type_id') {
        $field['type'] = 'select';
        $field['source'] = 'node_types';
    }
    $result[] = $field;
}

// Добавляем поля для блока "Расположение"
$result[] = ['name' => 'building_id', 'label' => 'Здание', 'type' => 'select', 'source' => 'buildings'];
$result[] = ['name' => 'workshop',     'label' => 'Цех',   'type' => 'text'];
$result[] = ['name' => 'floor',        'label' => 'Этаж',  'type' => 'text'];
$result[] = ['name' => 'room',         'label' => 'Комната(Помещение)', 'type' => 'text'];

echo json_encode($result);