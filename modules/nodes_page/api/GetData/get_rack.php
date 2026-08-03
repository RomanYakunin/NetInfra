<?php
// modules/nodes_page/api/GetData/get_rack.php
// Возвращает все шкафы узла и оборудование в них — для выдвижной панели стойки.
//
// Принимает один из параметров:
//   ?equipment_id=N — узел определяется по оборудованию, активным становится его шкаф
//   ?node_id=N      — все шкафы указанного узла
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
header('Content-Type: application/json; charset=utf-8');

/**
 * Разбирает значение unit_position: "4" → [4,1], "4-8" → [4,5].
 * Возвращает [начальный юнит, количество юнитов] или null, если позиция не задана.
 */
function parseUnitPosition($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') return null;

    if (preg_match('/^(\d+)\s*[-–—]\s*(\d+)$/u', $raw, $m)) {
        $from = (int)$m[1];
        $to   = (int)$m[2];
        if ($from > $to) { [$from, $to] = [$to, $from]; }
        return [$from, $to - $from + 1];
    }
    if (preg_match('/^\d+$/', $raw)) {
        return [(int)$raw, 1];
    }
    return null;
}

$equipmentId = (int)($_GET['equipment_id'] ?? 0);
$nodeId      = (int)($_GET['node_id'] ?? 0);
$activeRackId = null;

try {
    // ---------- Определяем узел ----------
    if ($equipmentId) {
        $stmt = $pdo->prepare("SELECT id_node, id_rack FROM equipment WHERE id = ?");
        $stmt->execute([$equipmentId]);
        $eq = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$eq) {
            echo json_encode(['error' => 'Оборудование не найдено']);
            exit;
        }
        $nodeId = (int)$eq['id_node'];
        $activeRackId = $eq['id_rack'] ? (int)$eq['id_rack'] : null;
    }

    if (!$nodeId) {
        echo json_encode(['error' => 'Оборудование не привязано к узлу']);
        exit;
    }

    // ---------- Шкафы узла ----------
    $stmt = $pdo->prepare("
        SELECT r.id_rack,
               r.name,
               r.status,
               rm.model_name,
               rm.height_u,
               rm.width_mm,
               rm.depth_mm,
               rm.form_factor,
               v.name AS vendor_name,
               b.Name_Building AS building_name,
               l.workshop, l.floor, l.room
        FROM racks r
        LEFT JOIN rack_models rm ON r.model_id = rm.id
        LEFT JOIN vendors v ON rm.vendor_id = v.id_vendor
        LEFT JOIN locations l ON r.location_id = l.id_location
        LEFT JOIN Buildings b ON l.building = b.Id
        WHERE r.id_node = ?
        ORDER BY r.name
    ");
    $stmt->execute([$nodeId]);
    $racks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$racks) {
        echo json_encode([
            'error'   => 'У этого узла пока нет шкафов',
            'node_id' => $nodeId,
            'racks'   => [],
        ]);
        exit;
    }

    // ---------- Оборудование во всех шкафах узла ----------
    $stmt = $pdo->prepare("
        SELECT e.id,
               e.id_rack,
               e.hostname,
               e.unit_position,
               e.status,
               e.Poe,
               e.serial_number,
               e.group_id,
               ip.ip_address,
               dt.name AS device_type_name,
               dm.name AS model_name,
               v.name  AS vendor_name,
               eg.hostname AS stack_name
        FROM equipment e
        LEFT JOIN ip_address ip ON e.ip_address = ip.Id
        LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
        LEFT JOIN device_models dm ON e.model_id = dm.id
        LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
        LEFT JOIN equipment_groups eg ON e.group_id = eg.id
        WHERE e.id_node = ? AND e.id_rack IS NOT NULL
        ORDER BY e.id_rack, e.unit_position
    ");
    $stmt->execute([$nodeId]);
    $equipmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------- Пассивное оборудование в шкафах узла ----------
    // Патч-панели и кроссы лежат в отдельной таблице, но в шкафу занимают
    // юниты наравне с активным оборудованием.
    $passiveRows = [];
    try {
        $stmt = $pdo->prepare("
            SELECT pd.id, pd.rack_id, pd.type, pd.name, pd.model, pd.unit_position,
                   pd.ports_count, pd.port_type, pd.port_rows, pd.status, pd.serial_number,
                   v.name AS vendor_name,
                   (SELECT COUNT(*) FROM passive_device_ports p
                     WHERE p.device_id = pd.id AND p.is_connected = 1) AS ports_connected
            FROM passive_devices pd
            LEFT JOIN vendors v ON pd.vendor_id = v.id_vendor
            WHERE pd.node_id = ? AND pd.rack_id IS NOT NULL
            ORDER BY pd.rack_id, pd.unit_position
        ");
        $stmt->execute([$nodeId]);
        $passiveRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Таблицы может ещё не быть (миграция не применена) — панель работает без неё
        $passiveRows = [];
    }

    // Порты пассивных устройств — для отрисовки и подсказок
    $portsByDevice = [];
    if ($passiveRows) {
        $ids = array_column($passiveRows, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = $pdo->prepare("
                SELECT p.device_id, p.port_number, p.label, p.is_connected, p.fiber_type,
                       n.KY_number, b.Name_Building AS building_name, l.room,
                       e.hostname AS equipment_hostname
                FROM passive_device_ports p
                LEFT JOIN nodes     n ON p.destination_node_id     = n.id_node
                LEFT JOIN Buildings b ON p.destination_building_id = b.Id
                LEFT JOIN locations l ON p.destination_location_id = l.id_location
                LEFT JOIN equipment e ON p.destination_equipment_id = e.id
                WHERE p.device_id IN ($ph)
                ORDER BY p.device_id, p.port_number
            ");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                // Читаемое направление для подсказки над портом
                $parts = [];
                if (!empty($p['KY_number']))          $parts[] = 'КУ-' . $p['KY_number'];
                if (!empty($p['building_name']))      $parts[] = $p['building_name'];
                if (!empty($p['room']))               $parts[] = 'ком. ' . $p['room'];
                if (!empty($p['equipment_hostname'])) $parts[] = $p['equipment_hostname'];

                $portsByDevice[(int)$p['device_id']][] = [
                    'port_number'  => (int)$p['port_number'],
                    'label'        => $p['label'],
                    'is_connected' => (int)$p['is_connected'],
                    'fiber_type'   => $p['fiber_type'],
                    'destination'  => implode(', ', $parts),
                ];
            }
        } catch (PDOException $e) {
            $portsByDevice = [];
        }
    }

    $passiveByRack = [];
    foreach ($passiveRows as $row) {
        $parsed = parseUnitPosition($row['unit_position']);
        $row['id']              = (int)$row['id'];
        $row['unit_start']      = $parsed ? $parsed[0] : null;
        $row['unit_size']       = $parsed ? $parsed[1] : 1;
        $row['ports_count']     = (int)$row['ports_count'];
        $row['port_rows']       = (int)$row['port_rows'];
        $row['ports_connected'] = (int)$row['ports_connected'];
        $row['is_passive']      = true;   // по этому флагу панель отличает их от equipment
        $row['ports']           = $portsByDevice[$row['id']] ?? [];
        $passiveByRack[(int)$row['rack_id']][] = $row;
    }

    // ---------- Раскладываем оборудование по шкафам ----------
    $byRack = [];
    foreach ($equipmentRows as $row) {
        $parsed = parseUnitPosition($row['unit_position']);

        $row['unit_start'] = $parsed ? $parsed[0] : null;   // начальный юнит
        $row['unit_size']  = $parsed ? $parsed[1] : 1;      // сколько юнитов занимает
        $row['Poe']        = (int)$row['Poe'];
        $row['stack_id']   = $row['group_id'] !== null ? (int)$row['group_id'] : null;
        $row['id']         = (int)$row['id'];

        $byRack[(int)$row['id_rack']][] = $row;
    }

    // Подписи шкафов — чтобы в панели можно было сказать, где стоит соседнее
    // устройство стека («в другом шкафу: ШК-2, ком. 226»)
    $rackLabels = [];
    foreach ($racks as $r) {
        $loc = [];
        if (!empty($r['building_name'])) $loc[] = $r['building_name'];
        if (!empty($r['room']))          $loc[] = 'ком. ' . $r['room'];
        $rackLabels[(int)$r['id_rack']] = ($r['name'] ?: 'Шкаф #' . $r['id_rack'])
            . ($loc ? ' (' . implode(', ', $loc) . ')' : '');
    }

    // Для каждого стека — в каких шкафах лежат его участники.
    // Нужно, чтобы отметить устройства, чьи собратья стоят в другом шкафу.
    $stackRacks = [];
    foreach ($equipmentRows as $row) {
        if ($row['group_id'] === null) continue;
        $gid = (int)$row['group_id'];
        $rid = (int)$row['id_rack'];
        if (!isset($stackRacks[$gid])) $stackRacks[$gid] = [];
        if (!in_array($rid, $stackRacks[$gid], true)) $stackRacks[$gid][] = $rid;
    }

    foreach ($racks as &$rack) {
        $rack['id_rack']  = (int)$rack['id_rack'];
        $rack['height_u'] = (int)($rack['height_u'] ?: 42);   // если модель не задана — стандартные 42U

        // Читаемое расположение шкафа (для подписи вкладки)
        $parts = [];
        if (!empty($rack['building_name'])) $parts[] = $rack['building_name'];
        if (!empty($rack['workshop']))      $parts[] = 'цех ' . $rack['workshop'];
        if (!empty($rack['floor']))         $parts[] = $rack['floor'] . ' этаж';
        if (!empty($rack['room']))          $parts[] = 'ком. ' . $rack['room'];
        $rack['location_display'] = implode(', ', $parts);

        $rack['equipment'] = $byRack[$rack['id_rack']] ?? [];
        $rack['passive']   = $passiveByRack[$rack['id_rack']] ?? [];

        // Отмечаем устройства, часть стека которых стоит в другом шкафу
        foreach ($rack['equipment'] as &$eqRow) {
            $eqRow['other_racks'] = [];
            if ($eqRow['stack_id'] !== null) {
                foreach (($stackRacks[$eqRow['stack_id']] ?? []) as $rid) {
                    if ($rid !== $rack['id_rack'] && isset($rackLabels[$rid])) {
                        $eqRow['other_racks'][] = $rackLabels[$rid];
                    }
                }
            }
        }
        unset($eqRow);
    }
    unset($rack);

    // Активный шкаф: тот, где стоит запрошенное оборудование, иначе первый
    if ($activeRackId === null || !in_array($activeRackId, array_column($racks, 'id_rack'), true)) {
        $activeRackId = $racks[0]['id_rack'];
    }

    echo json_encode([
        'node_id'        => $nodeId,
        'active_rack_id' => $activeRackId,
        'active_equipment_id' => $equipmentId ?: null,
        'racks'          => $racks,
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка БД: ' . $e->getMessage()]);
}
