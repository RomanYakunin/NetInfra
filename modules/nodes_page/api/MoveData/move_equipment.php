<?php
// Проверка прав: изменять данные может только администратор
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
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
        // ---------- Стек переезжает на склад целиком ----------
        // Стек не может быть «частично на складе»: если among выбранных есть
        // устройство из стека, забираем всех его собратьев и распускаем группу.
        $placeholders = implode(',', array_fill(0, count($cleanEquipIds), '?'));
        $stmtGroups = $pdo->prepare("SELECT DISTINCT group_id FROM equipment WHERE id IN ($placeholders) AND group_id IS NOT NULL");
        $stmtGroups->execute($cleanEquipIds);
        $groupIds = array_map('intval', $stmtGroups->fetchAll(PDO::FETCH_COLUMN));

        $movedIds = $cleanEquipIds;
        if ($groupIds) {
            $gp = implode(',', array_fill(0, count($groupIds), '?'));
            $stmtMembers = $pdo->prepare("SELECT id FROM equipment WHERE group_id IN ($gp)");
            $stmtMembers->execute($groupIds);
            $memberIds = array_map('intval', $stmtMembers->fetchAll(PDO::FETCH_COLUMN));
            $movedIds = array_values(array_unique(array_merge($movedIds, $memberIds)));
        }

        // Если выбрали не весь стек — просим подтверждение (кроме случая,
        // когда клиент уже подтвердил флагом confirm_stack_move)
        if ($groupIds && count($movedIds) > count($cleanEquipIds) && empty($_POST['confirm_stack_move'])) {
            $pdo->rollBack();
            $stmtNames = $pdo->prepare("SELECT hostname FROM equipment_groups WHERE id IN (" . implode(',', array_fill(0, count($groupIds), '?')) . ")");
            $stmtNames->execute($groupIds);
            echo json_encode([
                'need_stack_confirm' => true,
                'stack_total'   => count($movedIds),
                'selected'      => count($cleanEquipIds),
                'stack_names'   => $stmtNames->fetchAll(PDO::FETCH_COLUMN),
                'message'       => 'Будет перемещён весь стек (' . count($movedIds) . ' устройств). Продолжить?',
            ]);
            exit;
        }

        // Имя склада — для журнала и ответа
        $stmtWh = $pdo->prepare("SELECT name FROM warehouses WHERE id = ?");
        $stmtWh->execute([$destinationId]);
        $warehouseName = $stmtWh->fetchColumn() ?: ('склад #' . $destinationId);

        // Названия устройств до перемещения — для журнала
        $mp = implode(',', array_fill(0, count($movedIds), '?'));
        $stmtHosts = $pdo->prepare("SELECT id, hostname FROM equipment WHERE id IN ($mp)");
        $stmtHosts->execute($movedIds);
        $hostnames = $stmtHosts->fetchAll(PDO::FETCH_KEY_PAIR);

        // Столбца Groupe в схеме нет (удалён ранее) — принадлежность к стеку
        // определяется исключительно по group_id
        $stmt = $pdo->prepare("UPDATE equipment SET id_node = NULL, warehouse_id = ?, group_id = NULL WHERE id IN ($mp)");
        $stmt->execute(array_merge([$destinationId], $movedIds));

        // Распущенные стеки удаляем, чтобы не копился мусор
        if ($groupIds) {
            $gp = implode(',', array_fill(0, count($groupIds), '?'));
            $pdo->prepare("DELETE FROM equipment_groups WHERE id IN ($gp)")->execute($groupIds);
        }

        $pdo->commit();

        // Журналируем каждое перемещённое устройство
        require_once dirname(__FILE__, 5) . '/includes/logger.php';
        foreach ($movedIds as $mid) {
            logAction($pdo, 'move', 'equipment', $mid, $hostnames[$mid] ?? '',
                'Перемещено на склад «' . $warehouseName . '»'
                . ($groupIds ? ' (стек расформирован)' : ''));
        }

        echo json_encode([
            'success'        => true,
            'moved_count'    => count($movedIds),
            'warehouse_name' => $warehouseName,
            'stack_moved'    => !empty($groupIds),
            'stack_dissolved'=> count($groupIds),
        ]);
        exit;
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

    // Названия устройств для журнала (до commit — данные уже обновлены,
    // но hostname перемещением не меняется, кроме случая добавления в стек)
    $mp = implode(',', array_fill(0, count($cleanEquipIds), '?'));
    $stmtHosts = $pdo->prepare("SELECT id, hostname FROM equipment WHERE id IN ($mp)");
    $stmtHosts->execute($cleanEquipIds);
    $hostnames = $stmtHosts->fetchAll(PDO::FETCH_KEY_PAIR);

    $pdo->commit();

    // Журналируем перемещение в узел
    require_once dirname(__FILE__, 5) . '/includes/logger.php';
    $destLabel = $kyNumber ? 'КУ-' . $kyNumber : ('узел #' . $destinationId);
    foreach ($cleanEquipIds as $mid) {
        logAction($pdo, 'move', 'equipment', $mid, $hostnames[$mid] ?? '',
            'Перемещено в ' . $destLabel . ($isAddToStack ? ' (добавлено в стек)' : ''));
    }

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