<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 6) . '/includes/acl.php';
requireAdmin();
require_once dirname(__FILE__, 6) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$id = (int)$_POST['id'];
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID не указан']);
    exit;
}

try {
    // Получить MAC-адрес и общий hostname стека (если есть)
    $stmt = $pdo->prepare("
        SELECT e.mac_address, e.hostname AS device_hostname, g.hostname AS stack_hostname
        FROM equipment e
        LEFT JOIN equipment_groups g ON e.group_id = g.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        echo json_encode(['success' => false, 'error' => 'Устройство не найдено']);
        exit;
    }

    // Сбросить group_id и очистить hostname (устройство остаётся как одиночное)
    $pdo->prepare("UPDATE equipment SET group_id = NULL, hostname = '' WHERE id = ?")->execute([$id]);

    require_once dirname(__FILE__, 6) . '/includes/logger.php';
    logAction($pdo, 'delete_stack_device', 'stack', $id,
        $device['stack_hostname'] ?? '', 'Устройство выведено из стека');

    echo json_encode([
        'success' => true,
        'mac_address' => $device['mac_address'] ?? '',
        'stack_hostname' => $device['stack_hostname'] ?? ''
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка БД']);
}