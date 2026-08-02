<?php
// modules/nodes_table/nodes_table.php – подготовка данных для таблицы узлов с фильтром по зданию
$sortField = 'status';   // поле по умолчанию
$sortOrder = 'asc';      // направление
$nodeColumns = $pdo->query("SHOW COLUMNS FROM nodes")->fetchAll(PDO::FETCH_COLUMN);
$pk = in_array('id_node', $nodeColumns) ? 'id_node' : 'id';

$visibleCols = array_values(array_diff($nodeColumns, ['id_node']));
if (!in_array('device_count', $visibleCols)) {
    $visibleCols[] = 'device_count';
}
$visibleCols = array_values(array_unique($visibleCols));

$nodeTranslations = loadTranslations('nodes');
$fallbackNames = [
    'status'        => 'Статус',
    'KY_number'     => 'Номер КУ',
    'id_location'   => 'Расположение',
    'node_type_id'  => 'Тип узла',
    'device_count'  => 'Кол-во устройств'
];
$headers = [];
foreach ($visibleCols as $col) {
    $headers[] = $nodeTranslations[$col] ?? $fallbackNames[$col] ?? $col;
}

// Фильтр по зданию
$buildingFilter = isset($_GET['building']) ? (int)$_GET['building'] : 0;

// Обновляем статус и количество устройств
$updateSql = "
    UPDATE nodes n
    SET n.status = CASE
        WHEN (SELECT COUNT(*) FROM equipment WHERE id_node = n.id_node) = 0 THEN 'inactive'
        WHEN (SELECT COUNT(*) FROM equipment WHERE id_node = n.id_node AND status = 'active') = (SELECT COUNT(*) FROM equipment WHERE id_node = n.id_node) THEN 'active'
        WHEN (SELECT COUNT(*) FROM equipment WHERE id_node = n.id_node AND status = 'active') > 0 THEN 'partial'
        ELSE 'inactive'
    END,
    n.device_count = (SELECT COUNT(*) FROM equipment WHERE id_node = n.id_node)
";
$pdo->exec($updateSql);

// Строим запрос с JOIN и возможной фильтрацией
$selectParts = ["n.`$pk`"];
$colAliases = [];
$joins = [];
$where = '';
$params = [];

foreach ($visibleCols as $col) {
    if ($col === 'device_count') {
        $selectParts[] = "n.device_count AS device_count";
        $colAliases[$col] = 'device_count';
    } elseif ($col === 'id_location') {
        $alias = 'location_display';
        $selectParts[] = "CONCAT_WS(' ',
            COALESCE(b.Name_Building, ''),
            NULLIF(l.workshop, ''),
            CONCAT('этаж ', NULLIF(l.floor, '')),
            IF(l.room REGEXP '^[0-9]+$', CONCAT('каб. ', l.room), l.room)
        ) AS `$alias`";
        $joins[] = "LEFT JOIN locations l ON n.id_location = l.id_location";
        $joins[] = "LEFT JOIN Buildings b ON l.building = b.Id";
        $colAliases[$col] = $alias;
    } elseif ($col === 'node_type_id') {
        $alias = 'node_type_name';
        $selectParts[] = "nt.name_node_type AS `$alias`";
        $joins[] = "LEFT JOIN node_types nt ON n.node_type_id = nt.id_node_type";
        $colAliases[$col] = $alias;
    } else {
        $selectParts[] = "n.`$col`";
        $colAliases[$col] = $col;
    }
}

// Если выбран фильтр по зданию, добавляем условие через JOIN на locations
if ($buildingFilter > 0) {
    // Убедимся, что locations уже есть в JOIN (если нет – добавим)
    if (!in_array("LEFT JOIN locations l ON n.id_location = l.id_location", $joins)) {
        $joins[] = "LEFT JOIN locations l ON n.id_location = l.id_location";
    }
    if (!in_array("LEFT JOIN Buildings b ON l.building = b.Id", $joins)) {
        $joins[] = "LEFT JOIN Buildings b ON l.building = b.Id";
    }
    $where = " WHERE l.building = ?";
    $params[] = $buildingFilter;
}

$sql = "SELECT " . implode(', ', $selectParts) . " FROM nodes n " . implode(' ', $joins) . $where . " ORDER BY n.KY_number IS NULL, n.KY_number ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$nodes = $stmt->fetchAll();
if (!is_array($nodes)) $nodes = [];

// Список всех зданий для боковой панели
$buildings = $pdo->query("SELECT Id AS id, Name_Building AS name FROM Buildings ORDER BY Name_Building")->fetchAll();
// ---------- Обработка AJAX-запроса ----------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <table id="nodesTable">
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h) ?> <span class="sort-icon">↕</span></th>
                <?php endforeach; ?>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($nodes as $node): 
                $nid = $node[$pk];
                $status = $node['status'] ?? 'inactive';
                $dotClass = ($status === 'active') ? 'active' : (($status === 'partial') ? 'partial' : 'inactive');
                $statusText = ($status === 'active') ? 'Активен' : (($status === 'partial') ? 'Частично' : 'Не активен');
            ?>
            <tr class="data-row" data-node-id="<?= htmlspecialchars($nid) ?>" onclick="toggleNodeEquipment(this, '<?= htmlspecialchars($nid) ?>')">
                <?php foreach ($visibleCols as $col): 
                    $alias = $colAliases[$col] ?? $col;
                    $value = $node[$alias] ?? '';
                    if ($col === 'status') {
                        echo "<td><span class='blink-dot $dotClass'></span> " . htmlspecialchars($statusText) . "</td>";
                    } elseif ($col === 'KY_number') {
                        $displayKy = $value !== '' ? 'КУ-' . htmlspecialchars($value) : '—';
                        echo "<td>$displayKy</td>";
                    } elseif ($col === 'device_count') {
                        echo "<td><span class='equipment-count'>" . (int)$value . " шт.</span></td>";
                    } else {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                endforeach; ?>
                <td class="expand-cell"><span class="expand-arrow">▶</span></td>
            </tr>
            <tr class="equipment-detail-row" id="equip-row-<?= htmlspecialchars($nid) ?>">
                <td colspan="<?= count($headers) + 1 ?>">
                    <div class="nested-container" id="equip-container-<?= htmlspecialchars($nid) ?>"></div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="pagination">
        <span class="page-info">Всего записей: <?= count($nodes) ?></span>
    </div>
    <?php
    exit;
}
?>