<div class="toolbar">
    <form method="get" style="display: flex; gap: 0.5rem; flex: 1;">
        <input type="hidden" name="page" value="nodes">
        <input type="text" name="mac" placeholder="Поиск..." value="<?= htmlspecialchars($macSearch ?? '') ?>" style="flex: 1;" />
        <button type="submit" class="btn secondary">🔍</button>
    </form>
    <!-- Количество записей на странице (обработчик в nodes_page.js) -->
    <select id="nodesPerPage" title="Записей на странице">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
    </select>
    <button class="btn" onclick="openNodeAddForm()">Добавить узел</button>
    <button class="btn secondary" onclick="openAddColumnForm('nodes')">Добавить столбец</button>
    <button class="btn secondary">Экспорт</button>
</div>

<div class="nodes-layout">
    <!-- Боковая панель зданий -->
    <div class="buildings-sidebar" id="buildingsSidebar">
        <div class="buildings-header">
            <h3>Здания</h3>
            <button class="collapse-buildings-btn" onclick="toggleBuildingsSidebar()" title="Свернуть панель">◀</button>
        </div>
        <ul class="buildings-list">
            <li class="building-item <?= $buildingFilter == 0 ? 'active' : '' ?>" onclick="filterByBuilding(0)">Все здания</li>
            <?php foreach ($buildings as $b): ?>
<li class="building-item <?= ($b['id'] == $currentBuildingId ? 'active' : '') ?>"
    data-building-id="<?= $b['id'] ?>"
    onclick="filterByBuilding(<?= $b['id'] ?>)"
    oncontextmenu="showBuildingContextMenu(event, <?= $b['id'] ?>, '<?= htmlspecialchars($b['name'], ENT_QUOTES) ?>')">
    <?= htmlspecialchars($b['name']) ?>
</li>
<?php endforeach; ?>
        </ul>
        <ul class="buildings-list"><li class="building-item" onclick="addBuildingModal()">+</li></ul>
    </div>

    <!-- Кнопка разворачивания панели -->
    <div class="expand-buildings-btn" id="expandBuildingsBtn" style="display:none;" onclick="toggleBuildingsSidebar()">
        <span class="expand-icon">▶</span>
    </div>

    <!-- Таблица узлов -->
    <div class="table-wrapper" id="table-wrapper">
        <table id="nodesTable">
            <thead>
    <tr>
        <th class="sortable" data-sort="status" onclick="sortNodesBy('status')">Статус <span class="sort-icon">↕</span></th>
        <th class="sortable" data-sort="ky" onclick="sortNodesBy('ky')">Номер КУ <span class="sort-icon">↕</span></th>
        <th class="sortable" data-sort="location" onclick="sortNodesBy('location')">Расположение <span class="sort-icon">↕</span></th>
        <th class="sortable" data-sort="nodetype" onclick="sortNodesBy('nodetype')">Тип узла <span class="sort-icon">↕</span></th>
        <th class="sortable" data-sort="devicecount" onclick="sortNodesBy('devicecount')">Кол-во <span class="sort-icon">↕</span></th>
        <th></th>
    </tr>
</thead>
            <tbody>
                <?php foreach ($nodes as $node): 
                    $nid = $node[$pk];
                    $status = $node['status'] ?? 'inactive';
                    $dotClass = ($status === 'active') ? 'active' : (($status === 'partial') ? 'partial' : 'inactive');
                    $statusText = ($status === 'active') ? 'Активен' : (($status === 'partial') ? 'Частично' : 'Не активен');
                    $ky = $node['KY_number'] ?? '';
                    $location = $node['location_display'] ?? '';
                    $nodeType = $node['node_type_name'] ?? '';
                    $deviceCount = (int)($node['device_count'] ?? 0);
                ?>
                <tr class="data-row" 
                    data-node-id="<?= htmlspecialchars($nid) ?>" 
                    data-status="<?= $status ?>" 
                    data-ky="<?= (int)$ky ?>" 
                    data-location="<?= htmlspecialchars($location) ?>" 
                    data-nodetype="<?= htmlspecialchars($nodeType) ?>" 
                    data-devicecount="<?= $deviceCount ?>"
                    oncontextmenu="showNodeContextMenu(event, <?= (int)$nid ?>, '<?= htmlspecialchars($node['KY_number'] ?? '') ?>')"
                    onclick="toggleNodeEquipment(this, '<?= $nid ?>')">
                    
                    <td><span class='blink-dot <?= $dotClass ?>'></span> <?= htmlspecialchars($statusText) ?></td>
                    <td><?= $ky !== '' ? 'КУ-' . htmlspecialchars($ky) : '—' ?></td>
                    <td><?= htmlspecialchars($location) ?></td>
                    <td><?= htmlspecialchars($nodeType) ?></td>
                    <td><span class='equipment-count'><?= $deviceCount ?> шт.</span></td>
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
    </div>
</div>

<!-- Пагинация: наполняется renderPagination() в nodes_page.js -->
<div class="pagination" id="nodesPagination">
    <span class="page-info">Всего записей: <?= count($nodes) ?></span>
</div>


<!-- Пагинация, фильтр по зданию и поиск: modules/nodes_page/nodes_page_pagination.js -->
<script src="modules/nodes_page/nodes_page_pagination.js"></script>
