<?php
// api/MoveData/move_equipment.php – перемещение оборудования на склад или в другой узел

if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$direction     = $_POST['direction'] ?? 'warehouse';
$destinationId = $_POST['destination_id'] ?? null;
$equipIds      = $_POST['equip_ids'] ?? [];

if (!$destinationId || $destinationId === 'undefined' || $destinationId === 'null') {
    echo json_encode(['error' => 'Не указан склад или узел назначения']);
    exit;
}

$destinationId = (int)$destinationId;
if ($destinationId <= 0) {
    echo json_encode(['error' => 'Некорректный ID назначения']);
    exit;
}

$cleanEquipIds = [];
if (!is_array($equipIds)) {
    $equipIds = [$equipIds];
}

foreach ($equipIds as $id) {
    $id = trim($id);
    if ($id !== '' && $id !== 'undefined' && $id !== 'null' && is_numeric($id)) {
        $cleanEquipIds[] = (int)$id;
    }
}

if (empty($cleanEquipIds)) {
    echo json_encode(['error' => 'Не указано оборудование для перемещения']);
    exit;
}

// Проверка на добавление в существующий стек
if ($direction === 'another_node' && count($cleanEquipIds) === 1 && !isset($_POST['add_to_stack']) && !isset($_POST['skip_stack_check'])) {
    $stmt = $pdo->prepare("SELECT DISTINCT e.hostname, e.ip_address, eg.id AS group_id,
                           ip.ip_address AS ip_display
                           FROM equipment e
                           LEFT JOIN equipment_groups eg ON e.group_id = eg.id
                           LEFT JOIN ip_address ip ON e.ip_address = ip.Id
                           WHERE e.id_node = ? AND e.group_id IS NOT NULL
                           ORDER BY e.hostname");
    $stmt->execute([$destinationId]);
    $stacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($stacks)) {
        echo json_encode([
            'need_stack_selection' => true,
            'stacks' => $stacks,
            'destination_id' => $destinationId,
            'direction' => $direction,
            'equip_id' => $cleanEquipIds[0]
        ]);
        exit;
    }
}

// Проверка: не перемещаем туда, где оборудование уже находится
$alreadyThere = false;
$isAddToStack = isset($_POST['add_to_stack']) && $_POST['add_to_stack'] === '1';

if ($direction === 'warehouse') {
    $placeholders = implode(',', array_fill(0, count($cleanEquipIds), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE id IN ($placeholders) AND warehouse_id = ?");
    $stmt->execute(array_merge($cleanEquipIds, [$destinationId]));
    $alreadyThere = $stmt->fetchColumn() > 0;
} elseif ($direction === 'another_node') {
    if (!$isAddToStack) {
        $placeholders = implode(',', array_fill(0, count($cleanEquipIds), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE id IN ($placeholders) AND id_node = ?");
        $stmt->execute(array_merge($cleanEquipIds, [$destinationId]));
        $alreadyThere = $stmt->fetchColumn() > 0;
    }
}

if ($alreadyThere) {
    echo json_encode(['error' => 'Оборудование уже находится в указанном месте']);
    exit;
}

// Выполнение перемещения
try {
    $pdo->beginTransaction();

    if ($direction === 'warehouse') {
        $placeholders = implode(',', array_fill(0, count($cleanEquipIds), '?'));
        $params = array_merge([$destinationId], $cleanEquipIds);
        $stmt = $pdo->prepare("UPDATE equipment SET id_node = NULL, warehouse_id = ?, group_id = NULL WHERE id IN ($placeholders)");
        $stmt->execute($params);
    } elseif ($direction === 'another_node') {
        if ($isAddToStack) {
            $stackGroupId = (int)($_POST['stack_group_id'] ?? 0);
            $slot = (int)($_POST['slot'] ?? 0);
            if ($stackGroupId <= 0 || $slot < 1 || $slot > 8) {
                $pdo->rollBack();
                echo json_encode(['error' => 'Параметры стека или слота неверны']);
                exit;
            }
            $checkSlot = $pdo->prepare("SELECT COUNT(*) FROM equipment WHERE group_id = ? AND Slot = ?");
            $checkSlot->execute([$stackGroupId, $slot]);
            if ($checkSlot->fetchColumn() > 0) {
                $pdo->rollBack();
                echo json_encode(['error' => "Слот $slot уже занят в этом стеке"]);
                exit;
            }
            $stmtStack = $pdo->prepare("SELECT hostname, ip_address_id FROM equipment_groups WHERE id = ?");
            $stmtStack->execute([$stackGroupId]);
            $stackInfo = $stmtStack->fetch();
            if (!$stackInfo) {
                $pdo->rollBack();
                echo json_encode(['error' => 'Стек не найден']);
                exit;
            }
            $placeholders = implode(',', array_fill(0, count($cleanEquipIds), '?'));
            $params = array_merge([$destinationId, $stackGroupId, $stackInfo['hostname'], $stackInfo['ip_address_id'], $slot], $cleanEquipIds);
            $stmt = $pdo->prepare("UPDATE equipment SET id_node = ?, warehouse_id = NULL, group_id = ?, hostname = ?, ip_address = ?, Slot = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
        } else {
            $placeholders = implode(',', array_fill(0, count($cleanEquipIds), '?'));
            $params = array_merge([$destinationId], $cleanEquipIds);
            $stmt = $pdo->prepare("UPDATE equipment SET id_node = ?, warehouse_id = NULL, group_id = NULL WHERE id IN ($placeholders)");
            $stmt->execute($params);
        }
    } else {
        $pdo->rollBack();
        echo json_encode(['error' => 'Неизвестное направление перемещения']);
        exit;
    }

    $stmtNode = $pdo->prepare("SELECT KY_number FROM nodes WHERE id_node = ?");
    $stmtNode->execute([$destinationId]);
    $kyNumber = $stmtNode->fetchColumn();

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'moved_count' => count($cleanEquipIds),
        'device_hostname' => $stackInfo['hostname'] ?? '',
        'stack_hostname' => $stackInfo['hostname'] ?? '',
        'ky_number' => $kyNumber ?: $destinationId
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}