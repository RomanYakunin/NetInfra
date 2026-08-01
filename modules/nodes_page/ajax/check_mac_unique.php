<?php
// Отключаем отображение ошибок – вывод должен быть ТОЛЬКО JSON
ini_set('display_errors', 0);
error_reporting(0);
ob_clean();

header('Content-Type: application/json; charset=utf-8');

$mac = $_GET['mac'] ?? '';
if (empty($mac)) {
    echo json_encode(['exists' => false]);
    exit;
}

$mac = strtolower(trim($mac));
$hex = preg_replace('/[^0-9a-f]/', '', $mac);

if (strlen($hex) !== 12) {
    echo json_encode(['exists' => false, 'error' => 'Неверный формат MAC']);
    exit;
}

$macFormatted = substr($hex, 0, 4) . '-' . substr($hex, 4, 4) . '-' . substr($hex, 8, 4);

// Подключаем БД (путь должен быть корректным)
require_once dirname(__FILE__, 3) . '/config/db.php';  // возможно, у вас другой путь

if (!isset($pdo)) {
    echo json_encode(['error' => 'Нет подключения к БД']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE mac_address = :mac");
$stmt->execute([':mac' => $macFormatted]);
$exists = $stmt->fetchColumn() > 0;

echo json_encode(['exists' => $exists]);
exit;