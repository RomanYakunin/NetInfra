// modules/cabinet_panel/cabinet_panel.js

let panelOpen = false;
let currentCabinetEquipId = null;   // ID оборудования, чей шкаф показывается
let dragData = null;

// Открыть/закрыть панель
function togglePanel(open) {
    const panel = document.getElementById('rightPanel');
    const tab = document.getElementById('panelTab');
    if (!panel || !tab) return;

    panelOpen = open;
    if (open) {
        panel.classList.add('open');
        tab.classList.add('hidden');
    } else {
        panel.classList.remove('open');
        tab.classList.remove('hidden');
    }
}

// Обработчик клика по оборудованию в таблице узлов (вызывается из nodes_table.js)
async function handleEquipmentClickForCabinet(equipId) {
    // Получаем данные оборудования
    const resp = await fetch(`?ajax=get_equipment_item&id=${equipId}`);
    if (!resp.ok) return;
    const eq = await resp.json();

    // Проверяем наличие шкафа и юнита
    if (!eq.id_cabinet || !eq.unit_position) {
        if (typeof showToast === 'function') {
            showToast('У этого оборудования нет шкафа или не указан юнит', 'warning');
        }
        if (panelOpen) {
            togglePanel(false);
            currentCabinetEquipId = null;
        }
        return;
    }

    // Если кликнули по тому же оборудованию, что и открыто — закрываем панель
    if (currentCabinetEquipId === equipId && panelOpen) {
        togglePanel(false);
        currentCabinetEquipId = null;
        return;
    }

    // Открываем панель для этого оборудования
    currentCabinetEquipId = equipId;
    await loadCabinetContent(equipId);
    togglePanel(true);
}

// Загрузка содержимого шкафа
async function loadCabinetContent(equipId) {
    const panelBody = document.getElementById('panelBody');
    if (!panelBody) return;

    // Получаем информацию о шкафе и всех устройствах в нём
    const resp = await fetch(`?ajax=get_cabinet&equipment_id=${equipId}`);
    const data = await resp.json();

    if (data.error) {
        panelBody.innerHTML = `<div style="padding:1rem; text-align:center; color:var(--danger);">${data.error}</div>`;
        document.getElementById('panelTitle').textContent = 'Шкаф';
        return;
    }

    const { cabinet_name, cabinet_height, units } = data;
    document.getElementById('panelTitle').textContent = `${cabinet_name || 'Шкаф'} (${cabinet_height}U)`;

    let html = `<div class="legend">
        <span><span class="legend-dot green"></span> Активно</span>
        <span><span class="legend-dot orange"></span> Неактивно</span>
        <span><span class="legend-dot gray"></span> Свободно</span>
    </div>`;
    html += '<table class="cabinet-table"><thead><tr><th>Юнит</th><th>Устройство</th><th>IP</th><th>Статус</th></tr></thead><tbody>';

    const unitMap = {};
    units.forEach(u => { unitMap[u.unit_position] = u; });

    for (let u = 1; u <= cabinet_height; u++) {
        const info = unitMap[u];
        if (info) {
            const cls = info.status === 'active' ? 'occupied' : 'occupied-inactive';
            html += `<tr class="${cls} cabinet-row" data-unit="${u}" data-equip-id="${info.id}" draggable="true">
                <td>${u}</td>
                <td>${info.hostname || '—'}</td>
                <td>${info.ip_address || '—'}</td>
                <td><span class="blink-dot ${info.status}"></span> ${info.status === 'active' ? 'Активен' : 'Не активен'}</td>
            </tr>`;
        } else {
            html += `<tr class="empty cabinet-row" data-unit="${u}" data-equip-id="">
                <td>${u}</td><td>—</td><td>—</td><td>Свободен</td>
            </tr>`;
        }
    }
    html += '</tbody></table>';
    panelBody.innerHTML = html;

    // Навешиваем обработчики Drag-and-Drop и контекстное меню
    setupCabinetDragDrop();
    setupCabinetContextMenu();
}

// Drag-and-Drop внутри шкафа
function setupCabinetDragDrop() {
    const rows = document.querySelectorAll('#panelBody .cabinet-row');
    rows.forEach(row => {
        row.addEventListener('dragstart', e => {
            if (!row.dataset.equipId) {
                e.preventDefault();
                return;
            }
            dragData = {
                equipId: parseInt(row.dataset.equipId),
                fromUnit: parseInt(row.dataset.unit)
            };
            row.classList.add('dragover-drag');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', row.dataset.equipId);
        });

        row.addEventListener('dragend', () => {
            row.classList.remove('dragover-drag');
            dragData = null;
        });

        row.addEventListener('dragover', e => {
            e.preventDefault();
            row.classList.add('drop-target');
        });

        row.addEventListener('dragleave', () => {
            row.classList.remove('drop-target');
        });

        row.addEventListener('drop', async e => {
            e.preventDefault();
            row.classList.remove('drop-target');
            if (!dragData) return;

            const toUnit = parseInt(row.dataset.unit);
            const toEquipId = row.dataset.equipId ? parseInt(row.dataset.equipId) : null;

            if (dragData.equipId === toEquipId) return; // тот же самый элемент

            // Отправляем запрос на обмен юнитами
            const formData = new FormData();
            formData.append('equip1_id', dragData.equipId);
            formData.append('equip2_id', toEquipId || 0);  // 0 — свободный юнит
            formData.append('new_unit', toUnit);

            try {
                const resp = await fetch('?ajax=swap_cabinet_units', { method: 'POST', body: formData });
                const result = await resp.json();
                if (result.success) {
                    if (typeof showToast === 'function') showToast('Оборудование перемещено', 'success');
                    // Перезагружаем содержимое панели и обновляем таблицу узлов
                    if (currentCabinetEquipId) await loadCabinetContent(currentCabinetEquipId);
                    if (typeof refreshAllVisibleNodes === 'function') refreshAllVisibleNodes();
                } else {
                    alert('Ошибка перемещения: ' + (result.error || ''));
                }
            } catch (err) {
                alert('Ошибка сети');
            }
        });
    });
}

// Контекстное меню для строк шкафа
function setupCabinetContextMenu() {
    const rows = document.querySelectorAll('#panelBody .cabinet-row');
    rows.forEach(row => {
        row.addEventListener('contextmenu', e => {
            e.preventDefault();
            const equipId = row.dataset.equipId;
            if (!equipId) return;

            const items = [
                { text: 'Редактировать', action: 'edit', icon: 'assets/icons/edit.png' },
                { text: 'Переместить в другой юнит', action: 'move_unit', icon: 'assets/icons/move.png' },
                { text: 'Переместить на склад', action: 'move', icon: 'assets/icons/move.png' },
            ];

            // Открываем стандартное контекстное меню (функция showContextMenu уже есть в nodes_table.js)
            // Устанавливаем selectedEquipmentId, чтобы handleContextAction сработало
            window.selectedEquipmentId = equipId;
            window.selectedEquipmentData = { /* можно заполнить данными строки */ };
            if (typeof showContextMenu === 'function') {
                showContextMenu(e.clientX, e.clientY, items);
            }
        });
    });
}

// Инициализация обработчиков кнопок панели
document.addEventListener('DOMContentLoaded', () => {
    const panelTab = document.getElementById('panelTab');
    if (panelTab) {
        panelTab.addEventListener('click', () => {
            if (currentCabinetEquipId) {
                togglePanel(true);
            } else {
                if (typeof showToast === 'function') showToast('Сначала выберите оборудование в таблице узлов', 'info');
            }
        });
    }

    const panelClose = document.getElementById('panelClose');
    if (panelClose) {
        panelClose.addEventListener('click', () => {
            togglePanel(false);
            currentCabinetEquipId = null;
        });
    }
});