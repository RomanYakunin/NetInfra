<?php
// modules/nodes_page/api/UpdateData/update_rack_unit.php
// Изменение занимаемых юнитов из панели шкафа (перетаскивание границы блока).
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAdmin();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$equipId = (int)($_POST['equip_id'] ?? 0);
$from    = (int)($_POST['unit_from'] ?? 0);
$to      = (int)($_POST['unit_to'] ?? 0);

if (!$equipId || $from < 1 || $to < 1) {
    echo json_encode(['error' => 'Не указано оборудование или диапазон юнитов']);
    exit;
}
if ($from > $to) { [$from, $to] = [$to, $from]; }

try {
    // Шкаф и его высота
    $stmt = $pdo->prepare("
        SELECT e.id_rack, e.id_node, COALESCE(rm.height_u, 42) AS height_u
        FROM equipment e
        LEFT JOIN racks r ON e.id_rack = r.id_rack
        LEFT JOIN rack_models rm ON r.model_id = rm.id
        WHERE e.id = ?
    ");
    $stmt->execute([$equipId]);
    $eq = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$eq) {
        echo json_encode(['error' => 'Оборудование не найдено']);
        exit;
    }
    if (empty($eq['id_rack'])) {
        echo json_encode(['error' => 'Оборудование не привязано к шкафу']);
        exit;
    }

    $height = (int)$eq['height_u'];
    if ($to > $height) {
        echo json_encode(['error' => "Диапазон выходит за пределы шкафа (высота {$height}U)"]);
        exit;
    }

    // Проверяем пересечение с соседями в этом же шкафу.
    // unit_position — varchar: «4» или «4-8», поэтому разбираем на стороне PHP.
    $stmt = $pdo->prepare("SELECT id, hostname, unit_position FROM equipment
                           WHERE id_rack = ? AND id != ? AND unit_position IS NOT NULL AND unit_position != ''");
    $stmt->execute([$eq['id_rack'], $equipId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $other) {
        $raw = trim((string)$other['unit_position']);
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $raw, $m)) {
            $oFrom = (int)$m[1]; $oTo = (int)$m[2];
            if ($oFrom > $oTo) { [$oFrom, $oTo] = [$oTo, $oFrom]; }
        } elseif (preg_match('/^\d+$/', $raw)) {
            $oFrom = $oTo = (int)$raw;
        } else {
            continue;
        }
        // Отрезки пересекаются, если начало одного не позже конца другого
        if ($from <= $oTo && $oFrom <= $to) {
            echo json_encode([
                'error' => 'Юниты ' . $oFrom . ($oFrom !== $oTo ? '-' . $oTo : '')
                         . ' уже занимает «' . ($other['hostname'] ?: 'устройство #' . $other['id']) . '»',
                'conflict' => true,
            ]);
            exit;
        }
    }

    $value = $from === $to ? (string)$from : $from . '-' . $to;
    $pdo->prepare("UPDATE equipment SET unit_position = ? WHERE id = ?")->execute([$value, $equipId]);

    // Журналируем изменение
    require_once dirname(__FILE__, 5) . '/includes/logger.php';
    $stmtName = $pdo->prepare("SELECT hostname FROM equipment WHERE id = ?");
    $stmtName->execute([$equipId]);
    logAction($pdo, 'edit_equipment', 'equipment', $equipId, $stmtName->fetchColumn() ?: '',
        'Изменены занимаемые юниты: ' . $value);

    echo json_encode([
        'success'       => true,
        'unit_position' => $value,
        'unit_start'    => $from,
        'unit_size'     => $to - $from + 1,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
