// modules/rack_panel/rack_panel.js

let panelOpen = false;
let currentRackEquipId = null;
let dragData = null;
let currentRackData = null;

const deviceTypeColors = {
    'Коммутатор': '#42a5f5',
    'Маршрутизатор': '#ab47bc',
    'Сервер': '#66bb6a',
    'Дисковая полка': '#ffa726',
    'ИБП': '#ef5350',
    'Инженерное оборудование': '#ec407a',
    'МСЭ': '#26a69a',
    'IP-Телефон': '#78909c',
    'Принтер': '#8d6e63'
};

if (typeof showToast !== 'function') {
    window.showToast = function(msg, type) { alert(msg); };
}

function toggleRackPanel(open) {
    const panel = document.getElementById('rightPanel');
    const tab = document.getElementById('panelTab');
    if (!panel || !tab) return;

    panelOpen = open;
    if (open) {
        panel.classList.add('open');
        panel.style.transform = 'translateX(0)';
        panel.style.visibility = 'visible';
        tab.classList.add('hidden');
    } else {
        panel.classList.remove('open');
        panel.style.transform = 'translateX(100%)';
        panel.style.visibility = 'hidden';
        tab.classList.remove('hidden');
    }
}

function openRackPanel(equipId) {
    if (currentRackEquipId === equipId && panelOpen) {
        toggleRackPanel(false);
        currentRackEquipId = null;
        return;
    }
    currentRackEquipId = equipId;
    loadRackContent(equipId);
}

async function loadRackContent(equipId) {
    const panelBody = document.getElementById('panelBody');
    if (!panelBody) return;

    try {
        const resp = await fetch(`?ajax=get_rack&equipment_id=${equipId}`);
        const data = await resp.json();

        if (data.error) {
            panelBody.innerHTML = `<div style="padding:1rem; text-align:center; color:var(--danger);">${data.error}</div>`;
            document.getElementById('panelTitle').textContent = 'Стойка';
            showToast(data.error, 'warning');
            if (panelOpen) toggleRackPanel(false);
            return;
        }

        currentRackData = data;
        const { cabinet_name, cabinet_height, units, cabinets } = data;
        document.getElementById('panelTitle').textContent = `${cabinet_name || 'Стойка'} (${cabinet_height}U)`;

        // Вкладки
        let tabsHtml = '';
        if (cabinets && cabinets.length > 1) {
            tabsHtml = '<div class="cabinet-tabs">';
            cabinets.forEach(c => {
                const active = (c.rack_name === cabinet_name) ? ' active' : '';
                tabsHtml += `<span class="cabinet-tab${active}" data-rack-id="${c.id_rack}">${c.rack_name}</span>`;
            });
            tabsHtml += '</div>';
        }

        // Легенда
        const typeSet = new Set();
        units.forEach(u => { if (u.device_type_name) typeSet.add(u.device_type_name); });
        const typesArray = Array.from(typeSet).sort();
        let legendHtml = '<div class="legend">';
        typesArray.forEach(type => {
            const color = deviceTypeColors[type] || '#999';
            legendHtml += `<div class="legend-item"><span class="legend-dot" style="background:${color};"></span> ${type}</div>`;
        });
        legendHtml += '<div class="legend-item"><span class="legend-dot empty"></span> Свободно</div></div>';

        // Информация о стеке
        const activeUnit = units.find(u => u.id == equipId);
        let stackHtml = '';
        if (activeUnit && activeUnit.group_id) {
            const stackDevices = units.filter(u => u.group_id === activeUnit.group_id);
            if (stackDevices.length > 0) {
                stackHtml = `<div class="dossier-section" style="margin-top:1rem;">
                    <h4>📦 Стек: ${escapeHtml(activeUnit.stack_hostname || 'Без имени')}</h4>
                    <div class="dossier-grid">
                        ${stackDevices.map(u => `
                            <div class="dossier-item">
                                <div class="label">${escapeHtml(u.hostname)} (юнит ${u.unit_position})</div>
                                <div class="value">${escapeHtml(u.ip_address || '—')}</div>
                            </div>`).join('')}
                    </div>
                </div>`;
            }
        }

        panelBody.innerHTML = tabsHtml + legendHtml + '<div id="rackTableContainer"></div>' + stackHtml;
        renderRackTable(units, cabinet_height);

        // Переключение вкладок
        document.querySelectorAll('.cabinet-tab').forEach(tabEl => {
            tabEl.addEventListener('click', () => {
                const rackId = tabEl.dataset.rackId;
                const eqInRack = units.find(u => u.id_rack == rackId);
                if (eqInRack) {
                    currentRackEquipId = eqInRack.id;
                    loadRackContent(eqInRack.id);
                }
            });
        });

        // Открываем панель только при успехе
        toggleRackPanel(true);

    } catch (e) {
        panelBody.innerHTML = '<div style="padding:1rem; text-align:center; color:var(--danger);">Ошибка загрузки стойки</div>';
    }
}

function renderRackTable(units, cabinetHeight) {
    const container = document.getElementById('rackTableContainer');
    if (!container) return;

    const unitMap = {};
    units.forEach(u => {
        const size = u.unit_size || 1;
        for (let pos = u.unit_position; pos < u.unit_position + size; pos++) {
            unitMap[pos] = { ...u, isStart: pos === u.unit_position, isEnd: pos === u.unit_position + size - 1 };
        }
    });

    let html = '<table class="rack-table"><thead><tr><th>Юнит</th><th>Устройство</th><th>IP</th><th>Статус</th></tr></thead><tbody>';
    for (let u = 1; u <= cabinetHeight; u++) {
        const info = unitMap[u];
        if (info) {
            const typeColor = deviceTypeColors[info.device_type_name] || '#ddd';
            const rowStyle = `background: ${typeColor}; color: #000;`;
            html += `<tr class="rack-row" style="${rowStyle}" data-unit="${u}" data-equip-id="${info.id}" data-is-start="${info.isStart}" data-is-end="${info.isEnd}" data-unit-size="${info.unit_size || 1}" draggable="true">
                <td>${u}</td>
                <td>${info.hostname || '—'}</td>
                <td>${info.ip_address || '—'}</td>
                <td><span class="blink-dot ${info.status}"></span> ${info.status === 'active' ? 'Активен' : 'Не активен'}</td>
            </tr>`;
        } else {
            html += `<tr class="rack-row empty" data-unit="${u}" data-equip-id="" data-drop-target="true">
                <td>${u}</td><td>—</td><td>—</td><td>Свободен</td>
            </tr>`;
        }
    }
    html += '</tbody></table>';
    container.innerHTML = html;

    // Автоматически навешиваем обработчики после каждой перерисовки
    setupRackDragDrop();
    setupRackResize();
    setupRackContextMenu();
}

function setupRackDragDrop() {
    const rows = document.querySelectorAll('#rackTableContainer .rack-row');
    rows.forEach(row => {
        row.addEventListener('dragstart', e => {
            if (!row.dataset.equipId) { e.preventDefault(); return; }
            dragData = { equipId: parseInt(row.dataset.equipId), fromUnit: parseInt(row.dataset.unit) };
            row.classList.add('dragover-drag');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', row.dataset.equipId);
        });
        row.addEventListener('dragend', () => { row.classList.remove('dragover-drag'); dragData = null; });
        row.addEventListener('dragover', e => { e.preventDefault(); row.classList.add('drop-target'); });
        row.addEventListener('dragleave', () => row.classList.remove('drop-target'));
        row.addEventListener('drop', async e => {
            e.preventDefault();
            row.classList.remove('drop-target');
            if (!dragData) return;
            const toUnit = parseInt(row.dataset.unit);
            const toEquipId = row.dataset.equipId ? parseInt(row.dataset.equipId) : null;
            if (dragData.equipId === toEquipId) return;
            const formData = new FormData();
            formData.append('equip1_id', dragData.equipId);
            formData.append('equip2_id', toEquipId || 0);
            formData.append('new_unit', toUnit);
            try {
                const resp = await fetch('?ajax=swap_rack_units', { method: 'POST', body: formData });
                const result = await resp.json();
                if (result.success) {
                    showToast('Оборудование перемещено', 'success');
                    if (currentRackEquipId) loadRackContent(currentRackEquipId);
                } else {
                    showToast('Ошибка перемещения: ' + (result.error || ''), 'error');
                }
            } catch (err) { showToast('Ошибка сети', 'error'); }
        });
    });
}

function setupRackResize() {
    const rows = document.querySelectorAll('#rackTableContainer .rack-row[data-is-end="true"]');
    rows.forEach(row => {
        row.addEventListener('mousedown', e => {
            const rect = row.getBoundingClientRect();
            if (e.clientY < rect.bottom - 8 || e.clientY > rect.bottom + 4) return;
            e.stopPropagation();
            e.preventDefault();

            const equipId = parseInt(row.dataset.equipId);
            const startRow = document.querySelector(`.rack-row[data-equip-id="${equipId}"][data-is-start="true"]`);
            if (!startRow || !currentRackData) return;

            const unitEntry = currentRackData.units.find(u => u.id == equipId);
            if (!unitEntry) return;

            const originalSize = unitEntry.unit_size || 1;
            let currentSize = originalSize;

            const startY = e.clientY;
            const rowHeight = row.offsetHeight || 30;

            const onMouseMove = me => {
                const dy = me.clientY - startY;
                const deltaRows = Math.round(dy / rowHeight);
                let newSize = originalSize + deltaRows;
                if (newSize < 1) newSize = 1;
                if (newSize > 20) newSize = 20;

                if (newSize !== currentSize) {
                    currentSize = newSize;
                    unitEntry.unit_size = currentSize;
                    renderRackTable(currentRackData.units, currentRackData.cabinet_height);
                }
            };

            const onMouseUp = async () => {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);

                if (currentSize !== originalSize) {
                    try {
                        const resp = await fetch('?ajax=update_rack_unit_size', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `equip_id=${equipId}&unit_size=${currentSize}`
                        });
                        const data = await resp.json();
                        if (data.success) {
                            showToast(`Размер изменён до ${currentSize} юнитов`, 'success');
                            // Обновляем кэш для последующих ресайзов
                            if (currentRackData) {
                                const u = currentRackData.units.find(u => u.id == equipId);
                                if (u) u.unit_size = currentSize;
                            }
                        } else {
                            showToast(data.error || 'Ошибка', 'error');
                            unitEntry.unit_size = originalSize;
                            renderRackTable(currentRackData.units, currentRackData.cabinet_height);
                        }
                    } catch (e) {
                        showToast('Ошибка сети', 'error');
                        unitEntry.unit_size = originalSize;
                        renderRackTable(currentRackData.units, currentRackData.cabinet_height);
                    }
                }
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
    });
}

function setupRackContextMenu() {
    const rows = document.querySelectorAll('#rackTableContainer .rack-row');
    rows.forEach(row => {
        row.addEventListener('contextmenu', e => {
            e.preventDefault();
            const equipId = row.dataset.equipId;
            if (!equipId) return;
            window.selectedEquipmentId = equipId;
            window.selectedEquipmentData = {};
            if (typeof showContextMenu === 'function') {
                showContextMenu(e.clientX, e.clientY, [
                    { text: 'Редактировать', action: 'edit', icon: 'assets/icons/edit.png' },
                    { text: 'Переместить в другой юнит', action: 'move_unit', icon: 'assets/icons/move.png' },
                    { text: 'Переместить на склад', action: 'move', icon: 'assets/icons/move.png' },
                ]);
            }
        });
    });
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && panelOpen) {
        toggleRackPanel(false);
        currentRackEquipId = null;
    }
});

document.getElementById('panelTab')?.addEventListener('click', () => {
    if (currentRackEquipId) toggleRackPanel(true);
    else showToast('Сначала выберите оборудование через контекстное меню', 'info');
});
document.getElementById('panelClose')?.addEventListener('click', () => {
    toggleRackPanel(false);
    currentRackEquipId = null;
});

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}