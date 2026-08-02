// modules/nodes_table/nodes_table.js – финальная версия с исправлениями
window.onMoveComplete = null;

// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================
async function fetchJSON(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('Ошибка загрузки ' + url);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return data;
}

// ==================== ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ====================
let selectedEquipmentId = null;
let selectedEquipmentData = null;
let selectedNodeId = null;
let selectedNodeKy = '';
let currentBuildingFilter = 0;
let currentBuildingName = '';

// ---------- ПЕРЕМЕЩЕНИЕ ОБОРУДОВАНИЯ ----------
let currentMoveDirection = 'warehouse';
let currentMoveEquipId = null;
let currentMoveEquipData = null;
let currentStackMembers = [];
let moveAllStack = null;
let moveSourceNodeId = null;
let moveStackSelectionData = null; // данные для выбора стека при перемещении

// ==================== ФОРМАТИРОВАНИЕ MAC ====================
function macInputHandler(e) {
    const input = e.target;
    const cursorPos = input.selectionStart;
    const oldLength = input.value.length;
    input.value = formatMacAddress(input.value);
    const diff = input.value.length - oldLength;
    if (diff > 0) {
        input.setSelectionRange(cursorPos + diff, cursorPos + diff);
    } else {
        input.setSelectionRange(cursorPos, cursorPos);
    }
}

function formatMacAddress(value) {
    let hex = value.replace(/[^0-9a-fA-F]/g, '');
    hex = hex.toLowerCase();
    let result = '';
    for (let i = 0; i < hex.length; i += 4) {
        if (i > 0) result += '-';
        result += hex.substr(i, 4);
    }
    return result.substr(0, 14);
}

// ==================== РАСКРЫТИЕ УЗЛА ====================
function toggleNodeEquipment(row, nodeId) {
    const detailRow = document.getElementById('equip-row-' + nodeId);
    const container = document.getElementById('equip-container-' + nodeId);
    if (!detailRow || !container) return;

    if (detailRow.classList.contains('visible')) {
        detailRow.classList.remove('visible');
        row.classList.remove('expanded');
        const arrow = row.querySelector('.expand-arrow');
        if (arrow) arrow.style.transform = '';
        return;
    }

    if (container.dataset.loaded === 'true') {
        detailRow.classList.add('visible');
        row.classList.add('expanded');
        const arrow = row.querySelector('.expand-arrow');
        if (arrow) arrow.style.transform = 'rotate(90deg)';
        // прокрутить, чтобы увидеть загруженное оборудование
        detailRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }

    container.innerHTML = '<div class="no-equipment"><span class="load-indicator loading"></span> Загрузка оборудования...</div>';
    detailRow.classList.add('visible');
    row.classList.add('expanded');
    const arrow = row.querySelector('.expand-arrow');
    if (arrow) arrow.style.transform = 'rotate(90deg)';
    // предварительный скролл до индикатора загрузки
    detailRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    loadNodeEquipmentContent(nodeId, container);
}

// ==================== ЗАГРУЗКА КОНТЕНТА ОБОРУДОВАНИЯ ====================
async function loadNodeEquipmentContent(nodeId, container) {
    try {
        const response = await fetch(`?ajax=get_equipment&node_id=${nodeId}&_=${Date.now()}`);
        if (!response.ok) throw new Error('Сервер вернул ошибку ' + response.status);
        const data = await response.json();
        if (data.error) {
            container.innerHTML = '<div class="no-equipment">Ошибка: ' + data.error + '</div>';
            return;
        }

        const groups = data.groups || [];
        const rawColumns = data.columns || [];
        const rawColumnTitles = data.column_titles || rawColumns;

        const visibleColumns = rawColumns.filter(col => col !== 'id');
        const visibleColumnTitles = rawColumnTitles.filter((_, i) => rawColumns[i] !== 'id');
        if (!visibleColumns.includes('Groupe_display')) {
            visibleColumns.splice(1, 0, 'Groupe_display');
            visibleColumnTitles.splice(1, 0, 'Группа');
        }

        groups.forEach(group => {
            const label = group.type === 'stack' ? 'Стек' : 'Одиночное';
            if (group.main_row) group.main_row['Groupe_display'] = label;
            if (group.members) group.members.forEach(m => m['Groupe_display'] = label);
        });

        const detailColumns = data.detail_columns || [];
        const detailTitles = data.detail_titles || detailColumns;

        let html = '<div class="nested-title">Оборудование узла</div>';

        if (groups.length === 0) {
            html += '<div class="no-equipment">Нет оборудования</div>';
        } else {
            html += '<div class="table-wrapper"><table><thead><tr>';
            visibleColumnTitles.forEach(title => html += `<th>${title}</th>`);
            html += '<th><span class="add-col-btn" onclick="openAddColumnForm(\'equipment\')">+</span></th>';
            html += '</tr></thead><tbody>';

            groups.forEach((group, idx) => {
                const gid = nodeId + '_' + idx;
                const main = group.main_row;
                const isStack = group.type === 'stack';

                let stackCommon = null;
                if (isStack && group.members.length > 0) {
                    stackCommon = {
                        group_id: group.main_row.group_id || null,
                        ip_address: main['ip_address_display'] || main['ip_address_original'] || (group.members[0]['ip_address_display'] || group.members[0]['ip_address_original']),
                        hostname: main.hostname || group.members[0]['hostname'],
                        device_type_id: group.members[0]['device_type_id_original'] ?? group.members[0]['device_type_id'],
                        vendor_id: group.members[0]['vendor_id_original'] ?? group.members[0]['vendor_id'],
                        model_id: group.members[0]['model_id_original'] ?? group.members[0]['model_id'],
                        members: group.members.map(m => ({
                            id: m.id,
                            hostname: m.hostname || '',
                            ip: m['ip_address_display'] || m['ip_address_original'] || ''
                        }))
                    };
                }

                let equipId = main.id;
                if (isStack && group.members.length > 0) {
                    equipId = group.members[0].id;
                }

                html += `<tr class="equipment-group-row ${isStack ? 'stack-group' : ''}" 
                    data-group-id="${gid}" 
                    data-equipment-id="${equipId}" 
                    data-node-id="${nodeId}" 
                    data-hostname="${main.hostname || ''}" 
                    data-stack='${isStack ? JSON.stringify(stackCommon) : ''}'>`;

                visibleColumns.forEach(col => {
                    let val = main[col] !== undefined ? main[col] : '';
                    if (col === 'status') {
                        const status = main[col] || 'inactive';
                        const dotClass = status === 'active' ? 'active' : (status === 'partial' ? 'partial' : 'inactive');
                        const statusText = status === 'active' ? 'Активен' : (status === 'partial' ? 'Частично' : 'Не активен');
                        html += `<td class="status-cell"><span class="blink-dot ${dotClass}"></span>${statusText}</td>`;
                    } else if (col === 'Poe') {
                        if (isStack) {
                            const allPoe = group.members.every(m => m.Poe == 1);
                            const poeClass = allPoe ? 'active' : (group.members.some(m => m.Poe == 1) ? 'partial' : 'inactive');
                            html += `<td style="text-align:center;"><span class="poe-indicator ${poeClass}" title="PoE стека"></span></td>`;
                        } else {
                            const isActive = val == 1 || val === true || val === '1';
                            html += `<td style="text-align:center;"><span class="poe-indicator ${isActive ? 'active' : 'inactive'}"></span></td>`;
                        }
                    } else {
                        html += `<td>${val}</td>`;
                    }
                });
                html += '<td></td></tr>';

                if (isStack) {
                    html += `<tr class="equipment-detail-row" id="stack-detail-${gid}" style="display:none;"><td colspan="${visibleColumns.length + 2}">`;
                    html += '<table><thead><tr>';
                    detailTitles.forEach(title => html += `<th>${title}</th>`);
                    html += '<th><span class="add-col-btn" onclick="openAddColumnForm(\'equipment\')">+</span></th>';
                    html += '</tr></thead><tbody>';
                    group.members.forEach(member => {
                        html += `<tr data-equipment-id="${member.id}" data-hostname="${member.hostname || ''}" class="equipment-stack-row">`;
                        detailColumns.forEach(col => {
                            let val = member[col] !== undefined ? member[col] : '';
                            if (col === 'status') {
                                const status = member[col] || 'inactive';
                                const dotClass = status === 'active' ? 'active' : (status === 'partial' ? 'partial' : 'inactive');
                                const statusText = status === 'active' ? 'Активен' : (status === 'partial' ? 'Частично' : 'Не активен');
                                html += `<td><span class="blink-dot ${dotClass}"></span> ${statusText}</td>`;
                            } else if (col === 'Poe') {
                                const isActive = val == 1 || val === true || val === '1';
                                html += `<td style="text-align:center;"><span class="poe-indicator ${isActive ? 'active' : 'inactive'}"></span></td>`;
                            } else {
                                html += `<td>${val}</td>`;
                            }
                        });
                        html += '<td></td></tr>';
                    });
                    html += '</tbody></table>';
                    html += '</td></tr>';
                }
            });

            html += '</tbody></table></div>';
        }

        container.innerHTML = html;
        container.dataset.loaded = 'true';
        attachEquipmentRowHandlers(container);
        // 🔽 Автопрокрутка, чтобы раскрытое оборудование стало видимым
        const detailRow = document.getElementById('equip-row-' + nodeId);
        if (detailRow) {
            detailRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    } catch (error) {
        container.innerHTML = '<div class="no-equipment">Ошибка загрузки: ' + error.message + '</div>';
    }
}

// ==================== ОБРАБОТЧИКИ СТРОК ОБОРУДОВАНИЯ ====================
function attachEquipmentRowHandlers(container) {
    container.querySelectorAll('.stack-group').forEach(stackRow => {
        stackRow.addEventListener('click', function(e) {
            if (e.ctrlKey || e.metaKey) return;
            e.stopPropagation();
            const gid = this.dataset.groupId;
            const detail = document.getElementById('stack-detail-' + gid);
            const arrow = this.querySelector('.expand-arrow');
            if (detail.style.display === 'none') {
    detail.style.display = 'table-row';
    if (arrow) arrow.style.transform = 'rotate(90deg)';
    smoothScrollTo(detail);
} else {
    detail.style.display = 'none';
    if (arrow) arrow.style.transform = '';
}
        });
    });

    container.querySelectorAll('.equipment-group-row').forEach(row => {
        row.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            const equipId = this.dataset.equipmentId;
            if (!equipId) return;
            selectedEquipmentId = equipId;
            selectedEquipmentData = getEquipmentDataFromRow(this);

            const isStack = this.classList.contains('stack-group');
            let items = [];
            if (isStack) {
                items.push({ text: 'Добавить оборудование в стек', action: 'add_to_stack', icon: 'assets/icons/add.png' });
                items.push({ text: 'Редактировать стек', action: 'edit_stack', icon: 'assets/icons/edit.png' });
            } else {
                items.push({ text: 'Редактировать', action: 'edit', icon: 'assets/icons/edit.png' });
            }
            items.push({ text: 'Переместить', action: 'move', icon: 'assets/icons/move.png' });
            items.push({ text: 'Настройка оповещения', action: 'alerts', icon: 'assets/icons/alerts.png' });
            items.push({ text: 'Добавить в чек-лист', action: 'checklist', icon: 'assets/icons/checklist.png' });
            items.push({ text: 'Удалить', action: 'delete', icon: 'assets/icons/delete.png' });
            items.push({ text: 'Подробнее', action: 'detailed' });
            items.push({ text: 'Отобразить стойку', action: 'show_rack', icon: 'assets/icons/rack.png' });
            items.push({ text: 'Импорт LLDP', action: 'import_lldp', icon: '📡' });

            showContextMenu(e.clientX, e.clientY, items);
        });
    });

    container.querySelectorAll('.equipment-stack-row').forEach(row => {
        row.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            const equipId = this.dataset.equipmentId;
            if (!equipId) return;
            selectedEquipmentId = equipId;
            selectedEquipmentData = getEquipmentDataFromRow(this);
            showContextMenu(e.clientX, e.clientY, [
                { text: 'Редактировать', action: 'edit', icon: 'assets/icons/edit.png' },
                { text: 'Переместить', action: 'move', icon: 'assets/icons/move.png' },
                { text: 'Настройка оповещения', action: 'alerts', icon: 'assets/icons/alerts.png' },
                { text: 'Добавить в чек-лист', action: 'checklist', icon: 'assets/icons/checklist.png' },
                { text: 'Удалить', action: 'delete', icon: 'assets/icons/delete.png' },
                { text: 'Подробнее', action: 'detailed' },
                { text: 'Отобразить стойку', action: 'show_rack', icon: 'assets/icons/rack.png' }
            ]);
        });
    });
}

function getEquipmentDataFromRow(row) {
    return {
        id: row.dataset.equipmentId || null,
        hostname: row.dataset.hostname || '',
        ip_address: row.dataset.ipAddress || '',
        Groupe: row.classList.contains('stack-group') ? 2 : 1,
        node_id: row.dataset.nodeId || null,
        stackData: row.dataset.stack ? JSON.parse(row.dataset.stack) : null
    };
}

// ==================== УНИВЕРСАЛЬНОЕ КОНТЕКСТНОЕ МЕНЮ ====================
let ctxMenuCloseHandler = null;   // глобально в модуле

function showContextMenu(x, y, items) {
    const menu = document.getElementById('ctxMenu');
    if (!menu) return;

    // Права доступа: пользователю оставляем только пункты просмотра
    if (typeof filterContextItems === 'function') {
        items = filterContextItems(items);
        if (!items.length) return;
    }

    // 1. Если есть старый обработчик – удаляем его
    if (ctxMenuCloseHandler) {
        document.removeEventListener('click', ctxMenuCloseHandler);
        document.removeEventListener('contextmenu', ctxMenuCloseHandler);
        ctxMenuCloseHandler = null;
    }

    // 2. Удаляем все старые обработчики с пунктов меню (чтобы не накапливались)
    menu.querySelectorAll('.menu-item').forEach(el => {
        el.replaceWith(el.cloneNode(true));
    });

    // 3. Строим содержимое меню
    menu.innerHTML = items.map(item => {
        if (item === '---') return '<hr>';
        let iconHtml = '';
        if (item.icon) {
            iconHtml = `<img src="${item.icon}" width="16" height="16" style="margin-right:8px; vertical-align:middle;">`;
        }
        return `<div class="menu-item" data-action="${item.action}">${iconHtml}${item.text}</div>`;
    }).join('');

    // 4. Позиционируем (сначала скрыто, чтобы измерить размеры)
    menu.style.visibility = 'hidden';
    menu.style.display = 'block';
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';

    const menuRect = menu.getBoundingClientRect();
    const windowWidth = window.innerWidth;
    const windowHeight = window.innerHeight;

    let adjustedLeft = x;
    if (menuRect.right > windowWidth) {
        adjustedLeft = windowWidth - menuRect.width - 5;
        if (adjustedLeft < 0) adjustedLeft = 0;
    }
    let adjustedTop = y;
    if (menuRect.bottom > windowHeight) {
        adjustedTop = windowHeight - menuRect.height - 5;
        if (adjustedTop < 0) adjustedTop = 0;
    }

    menu.style.left = adjustedLeft + 'px';
    menu.style.top = adjustedTop + 'px';
    menu.style.visibility = 'visible';

    // 5. Функция закрытия меню
    function closeMenu() {
        menu.style.display = 'none';
        if (ctxMenuCloseHandler) {
            document.removeEventListener('click', ctxMenuCloseHandler);
            document.removeEventListener('contextmenu', ctxMenuCloseHandler);
            ctxMenuCloseHandler = null;
        }
    }

    // 6. Навешиваем обработчики на пункты меню
    menu.querySelectorAll('.menu-item').forEach(el => {
        el.addEventListener('click', function(e) {
            e.stopPropagation();        // чтобы не сработал обработчик документа
            const action = this.dataset.action;
            closeMenu();
            handleContextAction(action);
        });
    });

    // 7. Обработчик закрытия по клику вне меню
    ctxMenuCloseHandler = function(e) {
        if (!menu.contains(e.target)) {
            closeMenu();
        }
    };

    // Добавляем обработчики с микро‑задержкой,
    // чтобы текущий правый клик не закрыл меню сразу же
    setTimeout(() => {
        document.addEventListener('click', ctxMenuCloseHandler);
        document.addEventListener('contextmenu', ctxMenuCloseHandler);
    }, 0);
}

document.addEventListener('click', function(e) {
    const menu = document.getElementById('ctxMenu');
    if (menu && !menu.contains(e.target)) menu.style.display = 'none';
});

function handleContextAction(action) {
    if (selectedNodeId) {
        switch (action) {
            case 'add_equipment': openAddForm('equipment', selectedNodeId); break;
            case 'add_stack':
                openAddForm('equipment', null, null, { force_stack: true, node_id: selectedNodeId });
                break;
            case 'edit': editNode(selectedNodeId); break;
            case 'move_equipment': openMoveDialogForNode(selectedNodeId); break;
            case 'alerts': openAlertSettings(selectedNodeId); break;
            case 'checklist': openChecklistAddForm({ id: null, node_id: selectedNodeId, ky: selectedNodeKy }); break;
            case 'delete': deleteNode(selectedNodeId); break;
            case 'detailed': showNodeDetails(selectedNodeId); break;
        }
    } else if (selectedEquipmentId) {
        switch (action) {
            case 'edit_stack': {
                const nodeId = selectedEquipmentData?.node_id;
                const groupId = selectedEquipmentData?.stackData?.group_id;
                openAddForm('equipment', null, null, {
                    force_stack: true,
                    stack_group_id: groupId,
                    node_id: nodeId
                });
                break;
            }
            case 'import_lldp':
    openLLDPImport();
    break;
            case 'edit':
                openEditEquipmentForm(selectedEquipmentId, { force_stack: false });
                break;
            case 'move': openMoveDialog(selectedEquipmentId, selectedEquipmentData); break;
            case 'alerts': openAlertSettings(selectedEquipmentId); break;
            case 'checklist': openChecklistAddForm(selectedEquipmentData); break;
            case 'delete': deleteEquipment(selectedEquipmentId); break;
            case 'detailed': showEquipmentDetails(selectedEquipmentId); break;
            case 'show_rack':
                if (typeof openRackPanel === 'function') openRackPanel(selectedEquipmentId);
                else showToast('Функция просмотра стойки недоступна', 'warning');
                break;
        }
    }
    selectedNodeId = null;
    selectedEquipmentId = null;
}

// ==================== КОНТЕКСТНОЕ МЕНЮ ДЛЯ УЗЛОВ ====================
function showNodeContextMenu(event, nodeId, ky) {
    event.preventDefault();
    selectedNodeId = nodeId;
    selectedNodeKy = ky;
    showContextMenu(event.clientX, event.clientY, [
        { text: 'Добавить устройство', action: 'add_equipment', icon: 'assets/icons/add.png' },
        { text: 'Добавить стек', action: 'add_stack', icon: 'assets/icons/add.png' },
        { text: 'Редактировать', action: 'edit', icon: 'assets/icons/edit.png' },
        { text: 'Переместить оборудование', action: 'move_equipment', icon: 'assets/icons/move.png' },
        { text: 'Настройка оповещения', action: 'alerts', icon: 'assets/icons/alerts.png' },
        { text: 'Добавить в чек-лист', action: 'checklist', icon: 'assets/icons/checklist.png' },
        { text: 'Удалить', action: 'delete', icon: 'assets/icons/delete.png' },
        { text: 'Подробнее', action: 'detailed' }
    ]);
}

// ==================== ФУНКЦИИ ДЛЯ УЗЛОВ ====================
async function editNode(nodeId) {
    try {
        const response = await fetch('?ajax=get_node&id=' + nodeId);
        const node = await response.json();
        if (node.error) { alert(node.error); return; }
        openAddForm('node', null, node);
    } catch (e) { alert('Ошибка загрузки данных узла'); }
}

// ==================== ФУНКЦИИ ДЛЯ УЗЛОВ ====================
async function deleteNode(nodeId) {
    // 1. Получаем информацию об узле (включая KY_number)
    let kyNumber = '';
    try {
        const info = await fetch(`?ajax=get_node_item&id=${nodeId}`).then(r => r.json());
        if (info.device_count > 0) {
            showToast('Невозможно удалить узел: в нём есть оборудование', 'error');
            return;
        }
        kyNumber = info.KY_number || '';
    } catch (e) {
        showToast('Ошибка получения данных узла', 'error');
        return;
    }

    // 2. Подтверждение удаления
    if (!confirm('Удалить узел?')) return;

    // 3. Удаление
    try {
        const response = await fetch('?ajax=delete_node&id=' + nodeId, { method: 'POST' });
        const result = await response.json();
        if (result.success) {
            const displayKy = kyNumber ? `КУ-${kyNumber}` : `Узел #${nodeId}`;
            showToast(`${displayKy} успешно удалён`, 'success');
            removeNodeRow(nodeId);
        } else {
            showToast('Ошибка удаления: ' + result.error, 'error');
        }
    } catch (e) {
        showToast('Ошибка сети', 'error');
    }
}

function showNodeDetails(nodeId) {
    fetch('?ajax=get_node_item&id=' + nodeId)
        .then(r => r.json())
        .then(data => alert(JSON.stringify(data, null, 2)));
}

// ==================== ЗАГЛУШКИ ДЛЯ ОБОРУДОВАНИЯ ====================
function deleteEquipment(id) {
    if (!confirm('Удалить оборудование?')) return;
    fetch('?ajax=delete_equipment&id=' + id, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector(`tr[data-equipment-id="${id}"]`);
                const nodeId = row ? row.dataset.nodeId || row.closest('.nested-container')?.id?.replace('equip-container-', '') : null;
                if (nodeId) {
                    refreshNodeEquipment(nodeId);
                    if (typeof refreshSingleNode === 'function') refreshSingleNode(nodeId);
                } else {
                    document.querySelectorAll('.nested-container[data-loaded="true"]').forEach(container => {
                        const nid = container.id.replace('equip-container-', '');
                        refreshNodeEquipment(nid);
                        if (typeof refreshSingleNode === 'function') refreshSingleNode(nid);
                    });
                }
            } else alert('Ошибка: ' + data.error);
        });
}

function showEquipmentDetails(id) {
    fetch('?ajax=get_equipment_item&id=' + id)
        .then(r => r.json())
        .then(data => alert(JSON.stringify(data, null, 2)));
}

// ==================== РЕДАКТИРОВАНИЕ ОБОРУДОВАНИЯ ====================
async function openEditEquipmentForm(equipId, extraData = null) {
    if (!equipId) { alert('Не удалось определить ID оборудования'); return; }
    try {
        const response = await fetch('?ajax=get_equipment_item&id=' + equipId);
        const eq = await response.json();
        if (eq.error) { alert('Ошибка: ' + eq.error); return; }
        openAddForm('equipment', null, eq, extraData);
    } catch (e) { alert('Ошибка загрузки данных оборудования'); }
}

// ==================== ПЕРЕМЕЩЕНИЕ ОБОРУДОВАНИЯ ====================
async function openMoveDialog(equipId, data) {
    const modal = document.getElementById('moveModal');
    if (!modal) return;

    currentMoveDirection = 'warehouse';
    document.getElementById('move-warehouse').checked = true;
    document.getElementById('move-node').checked = false;
    document.getElementById('move-destination-label').textContent = 'Выберите склад';

    const modalTitle = modal.querySelector('h3');
    if (modalTitle) {
        let hostname = data.hostname;
        if (!hostname || hostname.trim() === '') {
            try {
                const resp = await fetch(`?ajax=get_equipment_item&id=${equipId}`);
                if (resp.ok) {
                    const eq = await resp.json();
                    hostname = eq.hostname || eq.ip_address || `ID: ${equipId}`;
                } else {
                    hostname = data.ip_address || `ID: ${equipId}`;
                }
            } catch (e) {
                hostname = data.ip_address || `ID: ${equipId}`;
            }
        }
        modalTitle.textContent = (data.Groupe == 2) ? `Переместить стек ${hostname}` : `Переместить устройство ${hostname}`;
    }

    currentMoveEquipId = equipId;
    currentMoveEquipData = data;
    moveSourceNodeId = data.node_id || null;

    if (data.Groupe == 2) {
        currentStackMembers = await loadStackMembers(equipId);
        document.getElementById('move-stack-list').style.display = 'block';
        renderStackList(currentStackMembers);
    } else {
        document.getElementById('move-stack-list').style.display = 'none';
        currentStackMembers = [];
    }

    loadDestinationSelect();
    showModal(modal);
    adjustMoveModalSize();
}

async function openMoveDialogForNode(nodeId) {
    const modal = document.getElementById('moveModal');
    if (!modal) return;

    currentMoveDirection = 'warehouse';
    document.getElementById('move-warehouse').checked = true;
    document.getElementById('move-node').checked = false;
    document.getElementById('move-destination-label').textContent = 'Выберите склад';

    let kyNumber = '';
    try {
        const nodeInfo = await fetch(`?ajax=get_node_item&id=${nodeId}`).then(r => r.json());
        kyNumber = nodeInfo.KY_number || nodeId;
    } catch (e) { kyNumber = nodeId; }

    const modalTitle = modal.querySelector('h3');
    if (modalTitle) modalTitle.textContent = `Переместить устройства КУ-${kyNumber}`;

    const resp = await fetch(`?ajax=get_node_equipment_for_move&node_id=${nodeId}`);
    const rawEquipment = await resp.json();
    if (rawEquipment.error) { alert(rawEquipment.error); return; }

    const allEquipment = rawEquipment.filter(item => item.id && item.id !== 'undefined' && item.id !== null);
    if (allEquipment.length === 0) {
        alert('В этом узле нет оборудования для перемещения');
        return;
    }

    currentMoveEquipId = null;
    currentMoveEquipData = { Groupe: 0 };
    currentStackMembers = allEquipment;
    moveAllStack = null;
    moveSourceNodeId = nodeId;

    resetMoveModal();
    document.getElementById('move-stack-list').style.display = 'block';
    renderStackList(allEquipment);
    loadDestinationSelect();

    showModal(modal);
    adjustMoveModalSize();
}

function resetMoveModal() {
    const safeHide = (id) => { const el = document.getElementById(id); if (el) el.style.display = 'none'; };
    safeHide('move-stack-list');
    safeHide('move-warehouse-select');
    safeHide('move-submit-btn');
    const select = document.getElementById('warehouseSelect');
    if (select) select.innerHTML = '';
}

async function loadDestinationSelect() {
    const select = document.getElementById('warehouseSelect');
    if (!select) return;

    if (select.searchableInstance) {
        select.searchableInstance.destroy();
        select.searchableInstance = null;
    }
    const wrapper = select.closest('.searchable-select');
    if (wrapper) {
        wrapper.parentNode.insertBefore(select, wrapper);
        wrapper.remove();
    }
    select.style.display = '';

    if (currentMoveDirection === 'warehouse') {
        document.getElementById('move-destination-label').textContent = 'Выберите склад';
        select.innerHTML = '<option value="">-- загрузка --</option>';
        try {
            const resp = await fetch('?ajax=get_warehouses');
            const data = await resp.json();
            const warehouses = Array.isArray(data) ? data : (data.data || []);
            select.innerHTML = '<option value="">-- выберите склад --</option>';
            warehouses.forEach(wh => {
                const display = wh.display || (wh.building_name ? wh.building_name + ' (' + wh.name + ')' : wh.name);
                select.appendChild(new Option(display, wh.id));
            });
        } catch (e) { select.innerHTML = '<option value="">-- ошибка загрузки --</option>'; }
    } else if (currentMoveDirection === 'another_node') {
        document.getElementById('move-destination-label').textContent = 'Выберите КУ';
        select.innerHTML = '<option value="">-- загрузка --</option>';
        try {
            const resp = await fetch('?ajax=get_nodes_list');
            const nodes = await resp.json();
            select.innerHTML = '<option value="">-- выберите КУ --</option>';
            nodes.forEach(node => {
                const display = node.KY_number ? `КУ-${node.KY_number} (${node.location_display || ''})` : `Узел ${node.id_node}`;
                select.appendChild(new Option(display, node.id_node));
            });
        } catch (e) { select.innerHTML = '<option value="">-- ошибка загрузки --</option>'; }
    }

    new SearchableSelect(select);

    document.getElementById('move-warehouse-select').style.display = 'block';
    document.getElementById('move-submit-btn').style.display = 'inline-block';
}

async function loadStackMembers(equipId) {
    try {
        const resp = await fetch(`?ajax=get_stack_members&equip_id=${equipId}`);
        const data = await resp.json();
        return data.members || [];
    } catch (e) { return []; }
}

function renderStackList(members) {
    const container = document.getElementById('stack-members-checkboxes');
    if (!container) return;
    container.className = 'stack-list';
    container.innerHTML = '';

    members.forEach(m => {
        const hasStack = m._stackMembers && m._stackMembers.length > 0;
        const html = `
        <div class="stack-list-item" style="flex-direction:column;">
            <label class="stack-list-item" style="width:100%;">
                <input type="checkbox" class="stack-checkbox" value="${m.id || ''}" data-stack-parent="${hasStack ? 'true' : 'false'}" checked>
                <div class="stack-details">
                    <span class="stack-detail"><span class="stack-detail-label">Хост:</span> <span class="stack-detail-value">${m.hostname || '—'}</span></span>
                    <span class="stack-detail"><span class="stack-detail-label">IP:</span> <span class="stack-detail-value">${m.ip_address || m.ip || '—'}</span></span>
                    <span class="stack-detail"><span class="stack-detail-label">Тип:</span> <span class="stack-detail-value">${m.device_type_name || m.device_type_id || '—'}</span></span>
                    <span class="stack-detail"><span class="stack-detail-label">Произв.:</span> <span class="stack-detail-value">${m.vendor_name || m.vendor_id || '—'}</span></span>
                    <span class="stack-detail"><span class="stack-detail-label">Модель:</span> <span class="stack-detail-value">${m.model_name || m.model_id || '—'}</span></span>
                    <span class="stack-detail"><span class="stack-detail-label">S/N:</span> <span class="stack-detail-value">${m.serial_number || '—'}</span></span>
                    <span class="stack-detail"><span class="stack-detail-label">MAC:</span> <span class="stack-detail-value">${m.mac_address || '—'}</span></span>
                </div>
            </label>
            ${hasStack ? `<div class="stack-nested" style="display:none;">` + m._stackMembers.map(sm => `
                <label class="stack-list-item">
                    <input type="checkbox" class="stack-checkbox stack-child" value="${sm.id || ''}" checked>
                    <div class="stack-details">
                        <span class="stack-detail"><span class="stack-detail-label">Хост:</span> <span class="stack-detail-value">${sm.hostname || '—'}</span></span>
                        <span class="stack-detail"><span class="stack-detail-label">IP:</span> <span class="stack-detail-value">${sm.ip_address || sm.ip || '—'}</span></span>
                        <span class="stack-detail"><span class="stack-detail-label">Тип:</span> <span class="stack-detail-value">${sm.device_type_name || sm.device_type_id || '—'}</span></span>
                        <span class="stack-detail"><span class="stack-detail-label">Произв.:</span> <span class="stack-detail-value">${sm.vendor_name || sm.vendor_id || '—'}</span></span>
                        <span class="stack-detail"><span class="stack-detail-label">Модель:</span> <span class="stack-detail-value">${sm.model_name || sm.model_id || '—'}</span></span>
                        <span class="stack-detail"><span class="stack-detail-label">S/N:</span> <span class="stack-detail-value">${sm.serial_number || '—'}</span></span>
                        <span class="stack-detail"><span class="stack-detail-label">MAC:</span> <span class="stack-detail-value">${sm.mac_address || '—'}</span></span>
                    </div>
                </label>`).join('') + '</div>' : ''}
        </div>`;
        container.innerHTML += html;
    });

    container.querySelectorAll('.stack-list-item').forEach(item => {
        const nested = item.querySelector('.stack-nested');
        const arrow = item.querySelector('.expand-arrow');
        if (!nested) return;
        item.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox') return;
            e.stopPropagation();
            const isHidden = nested.style.display === 'none' || nested.style.display === '';
            nested.style.display = isHidden ? 'block' : 'none';
            if (arrow) arrow.style.transform = isHidden ? 'rotate(90deg)' : '';
        });
    });

    container.querySelectorAll('.stack-checkbox[data-stack-parent="true"]').forEach(cb => {
        cb.addEventListener('change', function() {
            const parentDiv = this.closest('.stack-list-item');
            const children = parentDiv.querySelectorAll('.stack-child');
            children.forEach(child => child.checked = this.checked);
        });
    });

    container.querySelectorAll('.stack-child').forEach(cb => {
        cb.addEventListener('change', function() {
            const parentDiv = this.closest('.stack-list-item');
            const parentCheckbox = parentDiv.querySelector('.stack-checkbox[data-stack-parent="true"]');
            if (!parentCheckbox) return;
            const siblings = parentDiv.querySelectorAll('.stack-child');
            const allChecked = Array.from(siblings).every(c => c.checked);
            parentCheckbox.checked = allChecked;
        });
    });

    const selectAll = document.getElementById('select-all-stack');
    if (selectAll) {
        const newSelectAll = selectAll.cloneNode(true);
        selectAll.parentNode.replaceChild(newSelectAll, selectAll);
        newSelectAll.addEventListener('change', function() {
            const checked = this.checked;
            container.querySelectorAll('.stack-checkbox').forEach(cb => cb.checked = checked);
        });
    }
}

document.querySelectorAll('#move-direction-group .radio-input').forEach(radio => {
    radio.addEventListener('change', function() {
        currentMoveDirection = this.value;
        loadDestinationSelect();
    });
});

function submitMove() {
    const select = document.getElementById('warehouseSelect');
    const destinationId = select ? select.value : null;
    const destinationName = select ? select.options[select.selectedIndex]?.textContent : '';

    if (!destinationId || destinationId === 'undefined' || destinationId === 'null' || destinationId === '') {
        alert('Выберите склад или КУ');
        return;
    }

    const formData = new FormData();
    formData.append('destination_id', destinationId);
    formData.append('direction', currentMoveDirection);

    let movedDevices = [];
    let equipIdsToMove = [];

    const checkedCheckboxes = document.querySelectorAll('#stack-members-checkboxes .stack-checkbox:checked');
    if (checkedCheckboxes.length > 0) {
        checkedCheckboxes.forEach(cb => {
            const id = cb.value;
            if (id && id !== 'undefined' && id !== 'null' && id !== '') {
                equipIdsToMove.push(id);
                const label = cb.closest('label');
                const hostnameEl = label ? label.querySelector('.stack-detail-value') : null;
                const hostname = hostnameEl ? hostnameEl.textContent : id;
                movedDevices.push(hostname);
            }
        });
    } else if (currentMoveEquipId) {
        equipIdsToMove.push(currentMoveEquipId);
        const hostname = currentMoveEquipData?.hostname || currentMoveEquipId;
        movedDevices.push(hostname);
    }

    if (equipIdsToMove.length === 0) {
        alert('Не выбраны устройства для перемещения');
        return;
    }

    equipIdsToMove.forEach(id => formData.append('equip_ids[]', id));

    // Проверка параметров стека, если они отображаются
    const stackOpts = document.getElementById('stack-options');
    if (stackOpts && stackOpts.style.display === 'block') {
        const stackSelect = document.getElementById('stackSelect');
        if (stackSelect && stackSelect.value) {
            const slotInput = document.getElementById('stackSlotInput');
            const slot = parseInt(slotInput?.value);
            const errorSpan = document.getElementById('slot-error');
            if (!slot || slot < 1 || slot > 8) {
                if (errorSpan) {
                    errorSpan.textContent = 'Введите корректный слот (1-8)';
                    errorSpan.style.display = 'block';
                }
                return;
            }
            formData.append('add_to_stack', '1');
            formData.append('stack_group_id', stackSelect.value);
            formData.append('slot', slot);
        }
    }

    fetch('?ajax=move_equipment', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.need_stack_selection) {
                moveStackSelectionData = data;
                showStackSelection(data);
                return;
            }
            if (data.success) {
                closeMoveModal();
                if (data.device_hostname) {
                    showToast(`Устройство ${data.device_hostname} добавлено в стек ${data.stack_hostname || ''} в КУ-${data.ky_number || '?'}`, 'success');
                } else {
                    const devicesList = movedDevices.join(', ');
                    const directionText = currentMoveDirection === 'warehouse'
                        ? `на склад «${destinationName}»`
                        : `в ${destinationName}`;
                    showToast(`${devicesList} перемещено ${directionText}`, 'success');
                }
                const sourceNodeId = moveSourceNodeId || (currentMoveEquipData?.node_id);
                if (sourceNodeId) {
                    if (typeof refreshNodeEquipment === 'function') refreshNodeEquipment(sourceNodeId);
                    if (typeof refreshSingleNode === 'function') refreshSingleNode(sourceNodeId);
                }
                if (currentMoveDirection === 'another_node' && destinationId) {
                    if (typeof refreshNodeEquipment === 'function') refreshNodeEquipment(destinationId);
                    if (typeof refreshSingleNode === 'function') refreshSingleNode(destinationId);
                }
                if (typeof window.onMoveComplete === 'function') window.onMoveComplete();
            } else {
                showToast(data.error || 'Ошибка перемещения', 'error');
            }
        })
        .catch(err => alert('Ошибка сети'));
}

function closeMoveModal() {
    const modal = document.getElementById('moveModal');
    if (modal) modal.classList.remove('visible');
    currentMoveEquipId = null;
    currentMoveEquipData = null;
    currentStackMembers = [];
    moveAllStack = null;
    moveSourceNodeId = null;
    moveStackSelectionData = null;

    const stackOpts = document.getElementById('stack-options');
    if (stackOpts) {
        stackOpts.style.display = 'none';
        stackOpts.innerHTML = '';
    }
    const slotErr = document.getElementById('slot-error');
    if (slotErr) slotErr.style.display = 'none';
}

// ================== ВЫБОР СТЕКА И СЛОТА ПРИ ПЕРЕМЕЩЕНИИ ==================
function showStackSelection(data) {
    const container = document.getElementById('stack-options');
    if (!container) return;

    let optionsHtml = '<option value="">Как одиночное</option>';
    data.stacks.forEach(stack => {
        optionsHtml += `<option value="${stack.group_id}">${stack.hostname} (${stack.ip_display || ''})</option>`;
    });

    container.innerHTML = `
        <div class="form-group">
            <label>Добавить в стек</label>
            <select id="stackSelect">${optionsHtml}</select>
        </div>
        <div class="form-group" id="slot-group" style="display:none;">
            <label>Номер слота (1-8)</label>
            <input type="number" id="stackSlotInput" min="1" max="8" step="1" style="width:100px;">
            <span id="slot-error" style="color:var(--danger); font-size:0.8rem; display:none;"></span>
        </div>
    `;
    container.style.display = 'block';

    const stackSelect = document.getElementById('stackSelect');
    const slotGroup = document.getElementById('slot-group');
    stackSelect.addEventListener('change', function() {
        if (this.value) {
            slotGroup.style.display = 'block';
        } else {
            slotGroup.style.display = 'none';
            document.getElementById('stackSlotInput').value = '';
            document.getElementById('slot-error').style.display = 'none';
        }
    });
}

function submitMoveWithStack(groupId, slot) {
    const data = moveStackSelectionData;
    if (!data) return;
    const formData = new FormData();
    formData.append('destination_id', data.destination_id);
    formData.append('direction', data.direction);
    formData.append('equip_ids[]', data.equip_id);
    formData.append('add_to_stack', '1');
    formData.append('stack_group_id', groupId);
    formData.append('slot', slot);

    fetch('?ajax=move_equipment', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeMoveModal();
                showToast(`Устройство ${d.device_hostname || ''} добавлено в стек ${d.stack_hostname || ''} в КУ-${d.ky_number || '?'}`, 'success');
                if (typeof refreshNodeEquipment === 'function') refreshNodeEquipment(data.destination_id);
                if (typeof window.onMoveComplete === 'function') window.onMoveComplete();
            } else {
                alert(d.error || 'Ошибка');
            }
        });
}

// ========== НАСТРОЙКИ ОПОВЕЩЕНИЙ ==========
function openAlertSettings(equipmentId) {
    const modal = document.getElementById('alertSettingsModal');
    if (!modal) return;
    showModal(modal);
}
function closeAlertSettings() {
    const modal = document.getElementById('alertSettingsModal');
    if (modal) modal.classList.remove('visible');
}

// ========== ДОБАВЛЕНИЕ В ЧЕК-ЛИСТ ==========
function openChecklistAddForm(data) {
    const modal = document.getElementById('checklistModal');
    if (!modal) return;
    document.getElementById('cl-equipment-id').value = data.id;
    document.getElementById('cl-ky').value = data.node_id ? 'КУ-' + data.node_id : '';
    document.getElementById('cl-ip').value = data.ip_address;
    showModal(modal);
}
function closeChecklistModal() {
    const modal = document.getElementById('checklistModal');
    if (modal) modal.classList.remove('visible');
}

// ========== УДАЛЕНИЕ ОБОРУДОВАНИЯ ИЛИ УЗЛА ==========
function openDeleteDialog(equipId) {
    const modal = document.getElementById('deleteConfirmModal');
    if (!modal) return;
    modal.dataset.equipId = equipId;
    document.getElementById('delete-equipment-btn').onclick = function() {
        submitDelete('equipment', equipId);
    };
    document.getElementById('delete-node-btn').onclick = function() {
        const nodeId = selectedEquipmentData?.node_id;
        if (!nodeId) {
            alert('Не удалось определить узел');
            return;
        }
        submitDelete('node', nodeId);
    };
    showModal(modal);
}
function closeDeleteModal() {
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.classList.remove('visible');
}

async function submitDelete(itemType, id) {
    if (itemType === 'node') {
        const info = await fetch(`?ajax=get_node_item&id=${id}`).then(r => r.json());
        if (info.device_count > 0) {
            showToast('Невозможно удалить узел: в нём есть оборудование', 'error');
            closeDeleteModal();
            return;
        }
    }
    const formData = new FormData();
    formData.append('item_type', itemType);
    formData.append('id', id);
    try {
        const res = await fetch('?ajax=delete_item', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            closeDeleteModal();
            if (itemType === 'equipment') {
                const nodeId = selectedEquipmentData?.node_id;
                if (nodeId) {
                    refreshNodeEquipment(nodeId);
                    if (typeof refreshSingleNode === 'function') refreshSingleNode(nodeId);
                }
            } else if (itemType === 'node') {
                removeNodeRow(id);
            }
        } else {
            alert('Ошибка: ' + (data.error || 'неизвестная ошибка'));
        }
    } catch (e) {
        alert('Ошибка сети');
    }
}

function removeNodeRow(nodeId) {
    const row = document.querySelector(`tr.data-row[data-node-id="${nodeId}"]`);
    const detailRow = document.getElementById('equip-row-' + nodeId);
    if (row) row.remove();
    if (detailRow) detailRow.remove();
}

// ========== ОБНОВЛЕНИЕ УЗЛА И ОБОРУДОВАНИЯ ==========
function refreshNodeEquipment(nodeId) {
    const container = document.getElementById('equip-container-' + nodeId);
    const detailRow = document.getElementById('equip-row-' + nodeId);
    if (!container || !detailRow) return;

    const expandedStackIds = container.dataset.loaded === 'true' ? getExpandedStackIds(container) : [];

    if (detailRow.classList.contains('visible')) {
        loadNodeEquipmentContent(nodeId, container).then(() => {
            restoreExpandedStacks(container, expandedStackIds);
        });
    } else {
        container.dataset.loaded = 'false';
    }
}

function refreshSingleNode(nodeId) {
    fetch(`?ajax=get_node_item&id=${nodeId}&_=${Date.now()}`)
        .then(r => r.json())
        .then(node => {
            if (node.error) return;
            const row = document.querySelector(`tr.data-row[data-node-id="${nodeId}"]`);
            if (!row) return;

            row.dataset.status = node.status || 'inactive';
            row.dataset.ky = node.KY_number || '';
            row.dataset.devicecount = node.device_count || 0;

            const cells = row.querySelectorAll('td');
            if (cells.length >= 5) {
                const statusClass = node.status === 'active' ? 'active' : (node.status === 'partial' ? 'partial' : 'inactive');
                const statusText = node.status === 'active' ? 'Активен' : (node.status === 'partial' ? 'Частично' : 'Не активен');
                cells[0].innerHTML = `<span class="blink-dot ${statusClass}"></span> ${statusText}`;
                cells[1].textContent = node.KY_number ? 'КУ-' + node.KY_number : '—';
                cells[2].textContent = node.location_display || '—';
                cells[3].textContent = node.node_type_name || '—';
                cells[4].innerHTML = `<span class="equipment-count">${node.device_count || 0} шт.</span>`;
            }

            if (row.classList.contains('expanded')) {
                refreshNodeEquipment(nodeId);
            }
        });
}

function getExpandedStackIds(container) {
    const ids = [];
    container.querySelectorAll('[id^="stack-detail-"]').forEach(el => {
        if (el.style.display === 'table-row') {
            ids.push(el.id);
        }
    });
    return ids;
}

function restoreExpandedStacks(container, ids) {
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'table-row';
    });
}

// ========== ПОИСК ==========
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.toolbar input[type="text"]');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim();
            timeout = setTimeout(() => {
                searchNodes(query);
            }, 300);
        });
    }
});

async function searchNodes(query) {
    const tableBody = document.querySelector('#nodesTable tbody');
    if (!tableBody) return;

    try {
        const response = await fetch('?ajax=get_nodes_list&search=' + encodeURIComponent(query));
        const result = await response.json();
        const nodes = result.nodes || result;
        const warehouseMatch = result.warehouse_match;

        tableBody.innerHTML = '';

        if (nodes.length === 0) {
            if (warehouseMatch) {
                const display = warehouseMatch.hostname || warehouseMatch.serial_number || warehouseMatch.mac_address;
                const msg = `Устройство (${display}) находится на складе «${warehouseMatch.warehouse}»`;
                tableBody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding:2rem;">${msg}</td></tr>`;
            } else {
                tableBody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:2rem;">Ничего не найдено</td></tr>';
            }
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
            tr.setAttribute('data-status', status);
            tr.setAttribute('data-ky', node.KY_number || '');
            tr.setAttribute('data-location', node.location_display || '');
            tr.setAttribute('data-nodetype', node.node_type_name || '');
            tr.setAttribute('data-devicecount', deviceCount);
            tr.addEventListener('click', function(e) {
                if (e.ctrlKey || e.metaKey) return;
                toggleNodeEquipment(this, nid);
            });
            tr.oncontextmenu = function(e) {
                showNodeContextMenu(e, nid, node.KY_number || '');
            };

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

        if (warehouseMatch) {
            const display = warehouseMatch.hostname || warehouseMatch.serial_number || warehouseMatch.mac_address;
            const msg = `Устройство (${display}) находится на складе «${warehouseMatch.warehouse}»`;
            showToast(msg, 'info');
        }
    } catch (err) {
        console.error('Ошибка поиска:', err);
    }
}

// ========== ФИЛЬТРАЦИЯ ПО ЗДАНИЮ ==========
async function filterByBuilding(buildingId) {
    currentBuildingFilter = buildingId;
    const activeItem = document.querySelector('.building-item.active');
    currentBuildingName = activeItem ? activeItem.textContent.trim() : '';
    const tableBody = document.querySelector('#nodesTable tbody');
    if (!tableBody) return;

    document.querySelectorAll('.building-item').forEach(item => {
        if (buildingId == 0) {
            item.classList.toggle('active', item.textContent.trim() === 'Все здания');
        } else {
            const onclickAttr = item.getAttribute('onclick');
            item.classList.toggle('active', onclickAttr && onclickAttr.includes('filterByBuilding(' + buildingId + ')'));
        }
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
            tr.setAttribute('data-status', status);
            tr.setAttribute('data-ky', node.KY_number || '');
            tr.setAttribute('data-location', node.location_display || '');
            tr.setAttribute('data-nodetype', node.node_type_name || '');
            tr.setAttribute('data-devicecount', deviceCount);
            tr.onclick = function(e) {
                if (e.ctrlKey || e.metaKey) return;
                toggleNodeEquipment(this, nid);
            };
            tr.oncontextmenu = function(e) {
                showNodeContextMenu(e, nid, node.KY_number || '');
            };

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

function openNodeAddForm() {
    const active = document.querySelector('.building-item.active');
    let buildingId = 0;
    let name = '';

    if (active) {
        // пробуем data-атрибут
        const dataId = active.getAttribute('data-building-id');
        if (dataId) {
            buildingId = parseInt(dataId) || 0;
        } else {
            // fallback: вытаскиваем из onclick, например, onclick="filterByBuilding(42)"
            const onclick = active.getAttribute('onclick') || '';
            const match = onclick.match(/filterByBuilding\((\d+)\)/);
            buildingId = match ? parseInt(match[1]) : 0;
        }
        name = active.textContent.trim();
    }
    openAddForm('node', null, null, { building_id: buildingId || 0, building_name: name });
}

// ========== РЕЖИМ КОПИРОВАНИЯ ==========
let ctrlPressed = false;

document.addEventListener('keydown', function(e) {
    if (e.key === 'Control') {
        ctrlPressed = true;
        document.body.classList.add('ctrl-copy-mode');
    }
});

document.addEventListener('keyup', function(e) {
    if (e.key === 'Control') {
        ctrlPressed = false;
        document.body.classList.remove('ctrl-copy-mode');
    }
});

document.addEventListener('click', function(e) {
    if (!e.ctrlKey && !e.metaKey) return;

    const target = e.target.closest('td');
    if (!target) return;

    if (target.classList.contains('expand-cell') || target.querySelector('input, button, .blink-dot, .poe-indicator')) {
        return;
    }

    const text = target.textContent.trim();
    if (!text) return;

    e.preventDefault();
    e.stopPropagation();

    copyText(text, e.clientX, e.clientY);
}, true);

function copyText(text, x, y) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => showCopyToast(x, y));
    } else {
        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.focus();
        area.select();
        try { document.execCommand('copy'); showCopyToast(x, y); } catch (err) {}
        document.body.removeChild(area);
    }
}

function showCopyToast(x, y) {
    const toast = document.createElement('div');
    toast.textContent = 'Скопировано';
    toast.style.cssText = `
        position: fixed; left: ${x + 10}px; top: ${y - 30}px;
        background: var(--accent); color: white; padding: 4px 8px;
        border-radius: 4px; font-size: 0.8rem; z-index: 10000;
        pointer-events: none; opacity: 1; transition: opacity 0.3s;
    `;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 800);
}

function adjustMoveModalSize() {
    const modal = document.getElementById('moveModal');
    const container = document.getElementById('stack-members-checkboxes');
    if (!modal || !container) return;

    const itemsCount = container.querySelectorAll('.stack-checkbox').length;

    if (itemsCount <= 1) {
        modal.classList.add('single-device');
    } else {
        modal.classList.remove('single-device');
    }

    const listDiv = document.getElementById('move-stack-list');
    if (listDiv) {
        if (itemsCount <= 3) {
            listDiv.style.maxHeight = 'none';
        } else {
            listDiv.style.maxHeight = '50vh';
        }
    }
}

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

/**
 * Загружает справочник с сервера через ?ajax=get_list&list=имя
 * @param {string} listName - системное имя справочника (buildings, node_types, vendors и т.д.)
 * @returns {Promise<Array<{id: number|string, name: string}>>}
 */
async function loadList(listName) {
    try {
        const url = `?ajax=get_list&list=${encodeURIComponent(listName)}`;
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`Сервер вернул статус ${response.status}`);
        }
        const data = await response.json();

        // Поддержка двух форматов ответа:
        // 1. Просто массив:         [ {id:1, name:"Здание А"}, ... ]
        // 2. Объект с полем data:   { data: [ {id:1, name:"Здание А"}, ... ] }
        if (Array.isArray(data)) {
            return data;
        } else if (data && Array.isArray(data.data)) {
            return data.data;
        } else {
            console.warn(`loadList("${listName}"): ответ не является массивом`, data);
            return [];
        }
    } catch (error) {
        console.error(`Ошибка загрузки списка "${listName}":`, error);
        return [];
    }
}

/**
 * Плавно прокручивает ближайший прокручиваемый родитель так, чтобы элемент был виден.
 * @param {HTMLElement} element - целевой элемент.
 */
function smoothScrollTo(element) {
    // Ищем ближайший контейнер с overflow-y: auto или scroll
    let parent = element.parentElement;
    while (parent) {
        const style = window.getComputedStyle(parent);
        if (style.overflowY === 'auto' || style.overflowY === 'scroll') {
            const parentRect = parent.getBoundingClientRect();
            const elementRect = element.getBoundingClientRect();
            // Вычисляем новое положение scrollTop, чтобы элемент был примерно в верхней трети контейнера
            const offset = parentRect.top + parent.clientHeight / 3;
            const scrollTop = parent.scrollTop + elementRect.top - offset;
            parent.scrollTo({ top: scrollTop, behavior: 'smooth' });
            return;
        }
        parent = parent.parentElement;
    }
    // Если контейнер не найден, прокручиваем всю страницу
    element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}