<div class="toolbar">
    <form method="get" style="display: flex; gap: 0.5rem; flex: 1;">
        <input type="hidden" name="page" value="nodes">
        <input type="text" name="mac" placeholder="Поиск..." value="<?= htmlspecialchars($macSearch ?? '') ?>" style="flex: 1;" />
        <button type="submit" class="btn secondary">🔍</button>
    </form>
    <select>
        <option>10</option><option>25</option><option>50</option><option>100</option>
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

<div class="pagination">
    <span class="page-info">Всего записей: <?= count($nodes) ?></span>
</div>


<script>
// Функция фильтрации по зданию без перезагрузки
async function filterByBuilding(buildingId) {
    const tableBody = document.querySelector('#nodesTable tbody');
    if (!tableBody) return;

    // Обновляем активный класс в списке зданий
    document.querySelectorAll('.building-item').forEach(item => {
        item.classList.toggle('active', 
            (buildingId == 0 && item.textContent.trim() === 'Все здания') || 
            (item.onclick && item.onclick.toString().includes('filterByBuilding(' + buildingId + ')')));
    });

    try {
        const url = buildingId ? `?ajax=get_nodes_list&building_id=${buildingId}` : '?ajax=get_nodes_list';
        const response = await fetch(url);
        const nodes = await response.json();

        tableBody.innerHTML = '';

        if (nodes.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:2rem;">Ничего не найдено</td></tr>';
            return;
        }

        nodes.forEach(node => {
            const nid = node.id_node;
            const status = node.status || 'inactive';
            const dotClass = status === 'active' ? 'active' : (status === 'partial' ? 'partial' : 'inactive');
            const statusText = status === 'active' ? 'Активен' : (status === 'partial' ? 'Частично' : 'Не активен');
            const kyDisplay = node.KY_number ? 'КУ-' + node.KY_number : '—';
            const deviceCount = node.device_count || 0;

            const tr = document.createElement('tr');
            tr.className = 'data-row';
            tr.setAttribute('data-node-id', nid);
            tr.onclick = function() { toggleNodeEquipment(this, nid); };

            tr.innerHTML = `
                <td><span class="blink-dot ${dotClass}"></span> ${statusText}</td>
                <td>${kyDisplay}</td>
                <td>${node.location_display || ''}</td>
                <td>${node.node_type_name || ''}</td>
                <td><span class="equipment-count">${deviceCount} шт.</span></td>
                <td class="expand-cell"><span class="expand-arrow">▶</span></td>
            `;
            tableBody.appendChild(tr);

            const detailRow = document.createElement('tr');
            detailRow.className = 'equipment-detail-row';
            detailRow.id = 'equip-row-' + nid;
            detailRow.innerHTML = `<td colspan="10"><div class="nested-container" id="equip-container-${nid}"></div></td>`;
            tableBody.appendChild(detailRow);
        });
    } catch (err) {
        console.error('Ошибка фильтрации:', err);
    }
}

// Сворачивание/разворачивание панели зданий
function toggleBuildingsSidebar() {
    const sidebar = document.getElementById('buildingsSidebar');
    const expandBtn = document.getElementById('expandBuildingsBtn');
    if (sidebar.style.display === 'none') {
        sidebar.style.display = 'block';
        expandBtn.style.display = 'none';
    } else {
        sidebar.style.display = 'none';
        expandBtn.style.display = 'block';
    }
}
</script>