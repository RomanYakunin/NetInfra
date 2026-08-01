<?php
// header.php – Собирает статистику для верхней панели (пинг устройств, подсчёт проблем).

// Получаем все устройства, узлы и телефоны для сбора IP-адресов.
$equipment = [];
try {
    $equipment = $pdo->query("SELECT * FROM equipment")->fetchAll();
} catch (PDOException $e) {}

// $nodeIps = [];
// try {
//     $nodeIps = $pdo->query("SELECT ip_address FROM nodes WHERE ip_address IS NOT NULL AND ip_address != ''")->fetchAll(PDO::FETCH_COLUMN);
// } catch (PDOException $e) {}

$phoneIps = [];
try {
    $phoneIps = $pdo->query("SELECT ip_address FROM phones WHERE ip_address IS NOT NULL AND ip_address != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// // Объединяем все уникальные IP и пингуем их.
// $uniqueIps = array_unique(array_merge(
//     collectUniqueIps($equipment),
//     // $nodeIps,
//     $phoneIps
// ));

// $pingResults = [];
// foreach ($uniqueIps as $ip) {
//     $pingResults[$ip] = myPing($ip);
// }

// Подсчитываем статистику
// $activeDevices = $inactiveDevices = $activeUps = 0;
// foreach ($equipment as $eq) {
//     $ip = $eq['ip_address'] ?? '';
//     if ($ip === '') continue;
//     $alive = $pingResults[$ip] ?? false;
//     if ($alive) {
//         $activeDevices++;
//         if (($eq['type'] ?? '') === 'ИБП') $activeUps++;
//     } else {
//         $inactiveDevices++;
//     }
// }
$activeDevices = $pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'active'")->fetchColumn();
    $inactiveDevices = $pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'inactive'")->fetchColumn();

 $activeNodesByIp = $pdo->query("SELECT COUNT(*) FROM nodes WHERE status = 'active'")->fetchColumn();
 $inactiveNodesByIp = $pdo->query("SELECT COUNT(*) FROM nodes WHERE status = 'inactive'")->fetchColumn();  
 
$allNodes = $pdo->query("SELECT COUNT(*) FROM nodes")->fetchColumn();
$allDevices = $pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
// $activeNodesByIp = $inactiveNodesByIp = 0;
// foreach ($nodeIps as $ip) {
//     if ($pingResults[$ip] ?? false) $activeNodesByIp++; else $inactiveNodesByIp++;
// }

$activePhones = $inactivePhones = 0;
foreach ($phoneIps as $ip) {
    if ($pingResults[$ip] ?? false) $activePhones++; else $inactivePhones++;
}

$openProblems = 0;
try {
    $openProblems = $pdo->query("SELECT COUNT(*) FROM checklist WHERE status IN ('new','in_progress')")->fetchColumn();
} catch (PDOException $e) {}


