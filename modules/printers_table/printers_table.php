<?php
// modules/printers_table/printers_table.php
$tab = $_GET['tab'] ?? '95';
$allowedModels = ['95', '175', '845', '835'];
if (!in_array($tab, $allowedModels)) {
    $tab = '95';
}

// Получаем принтеры выбранной модели через подготовленный запрос
$stmt = $pdo->prepare("SELECT * FROM printers WHERE model = ? ORDER BY hostname");
$stmt->execute([$tab]);
$printers = $stmt->fetchAll();

// Заголовки таблицы
$headers = ['Модель', 'IP-адрес', 'Имя хоста', 'Расположение', 'Статус'];
$visibleCols = ['model', 'ip_address', 'hostname', 'location_id', 'status'];
$colAliases = array_combine($visibleCols, $visibleCols);