<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0); ini_set('display_errors', 0);

$field       = $_GET['field'] ?? '';
$value       = trim($_GET['value'] ?? '');
$excludeId   = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
$groupId     = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$nodeId      = isset($_GET['node_id']) ? (int)$_GET['node_id'] : 0;
$warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;

$allowedFields = ['hostname', 'serial_number', 'mac_address', 'ip_address'];
// Названия полей в именительном падеже (для сообщений)
$fieldNames    = [
    'hostname'      => 'имя хоста',
    'serial_number' => 'серийный номер',
    'mac_address'   => 'MAC-адрес',
    'ip_address'    => 'IP-адрес'
];

if (!in_array($field, $allowedFields) || $value === '') {
    echo json_encode(['exists' => false, 'message' => '']);
    exit;
}

require_once dirname(__FILE__, 5) . '/config/db.php';

/**
 * Формирует читаемое сообщение о дубликате.
 */
function buildDuplicateMessage($row, $fieldDisplay, $nodeId, $warehouseId, $isWarehouse = false) {
    // 1. Тот же узел (КУ)
    if ($nodeId && $row['id_node'] == $nodeId) {
        return "Данный {$fieldDisplay} уже используется в этом КУ";
    }
    // 2. Тот же склад
    if ($warehouseId && $row['warehouse_id'] == $warehouseId) {
        return "Устройство уже находится на этом складе";
    }
    // 3. Дубликат в другом узле (с номером КУ)
    if (!empty($row['KY_number'])) {
        if ($isWarehouse) {
            return "Оборудование с таким {$fieldDisplay} находится в КУ-{$row['KY_number']}";
        }
        return "Данный {$fieldDisplay} уже используется в КУ-{$row['KY_number']}";
    }
    // 4. Дубликат с hostname, но без КУ
    if (!empty($row['hostname'])) {
        return "Данный {$fieldDisplay} уже используется устройством {$row['hostname']}";
    }
    // 5. На другом складе
    if ($row['warehouse_id']) {
        $wh = $row['warehouse_display'] ?: "склад {$row['warehouse_id']}";
        return "Устройство на складе ({$wh})";
    }
    // 6. Без привязки
    return "Оборудование с таким {$fieldDisplay} уже существует (без привязки)";
}

try {
    // Если добавляем в существующий стек – сначала проверяем внутри него
    if ($groupId > 0) {
        $sqlStack = "SELECT e.id, e.id_node, e.warehouse_id, n.KY_number, e.hostname,
                            w.name AS warehouse_name,
                            CONCAT_WS(' ', b.Name_Building, w.name) AS warehouse_display
                     FROM equipment e
                     LEFT JOIN nodes n ON e.id_node = n.id_node
                     LEFT JOIN warehouses w ON e.warehouse_id = w.id
                     LEFT JOIN Buildings b ON w.building = b.Id
                     WHERE e.`$field` = ? AND e.group_id = ?";
        $paramsStack = [$value, $groupId];
        if ($excludeId > 0) {
            $sqlStack .= " AND e.id != ?";
            $paramsStack[] = $excludeId;
        }
        $sqlStack .= " LIMIT 1";
        $stmtStack = $pdo->prepare($sqlStack);
        $stmtStack->execute($paramsStack);
        $rowStack = $stmtStack->fetch(PDO::FETCH_ASSOC);
        if ($rowStack) {
            echo json_encode([
                'exists'  => true,
                'message' => "Устройство с таким {$fieldNames[$field]} уже находится в этом стеке"
            ]);
            exit;
        }
    }

    // Основной запрос – поиск за пределами стека (если группа задана, исключаем её)
    $sql = "SELECT e.id, e.id_node, e.warehouse_id, n.KY_number, e.hostname,
                   w.name AS warehouse_name,
                   CONCAT_WS(' ', b.Name_Building, w.name) AS warehouse_display
            FROM equipment e
            LEFT JOIN nodes n ON e.id_node = n.id_node
            LEFT JOIN warehouses w ON e.warehouse_id = w.id
            LEFT JOIN Buildings b ON w.building = b.Id
            WHERE e.`$field` = ?";
    $params = [$value];

    if ($excludeId > 0) {
        $sql .= " AND e.id != ?";
        $params[] = $excludeId;
    }

    if ($groupId > 0) {
        $sql .= " AND (e.group_id IS NULL OR e.group_id != ?)";
        $params[] = $groupId;
    }

    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $isWarehouse = ($warehouseId > 0);
        $message = buildDuplicateMessage($row, $fieldNames[$field], $nodeId, $warehouseId, $isWarehouse);
        echo json_encode(['exists' => true, 'message' => $message]);
    } else {
        echo json_encode(['exists' => false, 'message' => '']);
    }
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'message' => 'Ошибка проверки']);
}