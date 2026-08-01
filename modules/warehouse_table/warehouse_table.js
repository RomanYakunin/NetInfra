// modules/warehouse_table/warehouse_table.js

let currentTab = 'Оборудование';
let currentWarehouse = 'all';
let warehouseBuildingSearchable = null;
let whEquipSearchableInstances = {};

let currentWarehouseName = '';   // "Главный корпус (каб. 44)"

// В начало файла (после глобальных переменных)
let selectedWarehouseGroup = null;

// ---------- Контекстное меню для оборудования склада ----------
let warehouseCtxEquipId = null;
let warehouseCtxEquipData = null;
let warehouseCtxMenu = null;

function initWarehouseCtxMenu() {
    warehouseCtxMenu = document.getElementById('warehouseCtxMenu');
    if (!warehouseCtxMenu) {
        warehouseCtxMenu = document.createElement('div');
        warehouseCtxMenu.id = 'warehouseCtxMenu';
        warehouseCtxMenu.className = 'context-menu';
        warehouseCtxMenu.style.cssText = 'display:none; position:fixed; z-index:10000; background:var(--bg-card); border:1px solid var(--border-color); border-radius:8px; padding:4px 0; min-width:180px; box-shadow:var(--shadow);';
        document.body.appendChild(warehouseCtxMenu);
    }
    document.addEventListener('click', hideWarehouseCtxMenu);
}

function showWarehouseContextMenu(x, y, items) {
    if (!warehouseCtxMenu) initWarehouseCtxMenu();
    warehouseCtxMenu.innerHTML = items.map(item => {
        if (item === '---') return '<hr>';
        let iconHtml = item.icon ? `<img src="${item.icon}" width="16" height="16" style="margin-right:8px; vertical-align:middle;">` : '';
        return `<div class="menu-item" data-action="${item.action}">${iconHtml}${item.text}</div>`;
    }).join('');
    warehouseCtxMenu.style.display = 'block';
    warehouseCtxMenu.style.left = x + 'px';
    warehouseCtxMenu.style.top = y + 'px';
    // Назначить обработчики пунктам
    warehouseCtxMenu.querySelectorAll('.menu-item').forEach(el => {
        el.onclick = function() {
            const action = this.dataset.action;
            hideWarehouseCtxMenu();
            warehouseHandleContextAction(action);
        };
    });
}

function hideWarehouseCtxMenu() {
    if (warehouseCtxMenu) warehouseCtxMenu.style.display = 'none';
}

function warehouseHandleContextAction(action) {
    if (!warehouseCtxEquipId) return;
    switch (action) {
        case 'edit':
            if (typeof openEditEquipmentForm === 'function') openEditEquipmentForm(warehouseCtxEquipId);
            break;
        case 'move':
            // Загрузить данные устройства и открыть диалог перемещения
            fetch(`?ajax=get_equipment_item&id=${warehouseCtxEquipId}`)
                .then(r => r.json())
                .then(eq => {
                    if (eq.error) { alert('Ошибка загрузки данных'); return; }
                    // Установить колбэк для обновления таблицы склада после перемещения
                    window.onMoveComplete = () => { loadTableData(); window.onMoveComplete = null; };
                    if (typeof openMoveDialog === 'function') openMoveDialog(warehouseCtxEquipId, eq);
                });
            break;
        case 'delete':
            if (typeof deleteEquipment === 'function') {
                deleteEquipment(warehouseCtxEquipId);
                loadTableData();  // обновить таблицу после удаления
            }
            break;
        case 'detailed':
            if (typeof showEquipmentDetails === 'function') showEquipmentDetails(warehouseCtxEquipId);
            break;
        case 'alerts':
            if (typeof openAlertSettings === 'function') openAlertSettings(warehouseCtxEquipId);
            break;
        case 'checklist':
            if (typeof openChecklistAddForm === 'function') openChecklistAddForm(warehouseCtxEquipData);
            break;
    }
}

// Навесить контекстное меню на все строки оборудования внутри контейнера
function attachWarehouseEquipmentContextMenu(container) {
    if (!container) return;
    container.querySelectorAll('.warehouse-equipment-row').forEach(row => {
        // Удаляем старый обработчик, чтобы не дублировать при повторной загрузке
        row.oncontextmenu = null;
        row.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            const equipId = this.dataset.equipmentId;
            if (!equipId) return;
            warehouseCtxEquipId = equipId;
            // Сохраняем базовые данные (можно дополнить чтением ячеек)
            warehouseCtxEquipData = {
                id: equipId,
                hostname: this.querySelector('td:first-child')?.textContent.trim() || '',
                ip_address: this.querySelector('td:nth-child(2)')?.textContent.trim() || ''
            };
            showWarehouseContextMenu(e.clientX, e.clientY, [
                { text: 'Редактировать', action: 'edit', icon: 'assets/icons/edit.png' },
                { text: 'Переместить', action: 'move', icon: 'assets/icons/move.png' },
                { text: 'Удалить', action: 'delete', icon: 'assets/icons/delete.png' },
                { text: 'Подробнее', action: 'detailed' },
                { text: 'Настройка оповещения', action: 'alerts' },
                { text: 'Добавить в чек-лист', action: 'checklist' }
            ]);
        });
    });
}

// ==================== Переключение вкладок ====================
function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('#warehouseTabs a').forEach(a => a.classList.remove('active'));
    const active = document.querySelector(`#warehouseTabs a[data-tab="${tab}"]`);
    if (active) active.classList.add('active');
    updateAddButton();
    loadTableData();
}

function updateAddButton() {
    const btn = document.getElementById('addDeviceBtn');
    if (!btn) return;
    if (currentTab === 'Телефоны') {
        btn.textContent = '➕ Добавить телефон';
    } else {
        btn.textContent = '➕ Добавить устройство';
    }
}

// ==================== Фильтр по складу ====================
function applyWarehouseFilter(warehouseId) {
    currentWarehouse = warehouseId;
    document.querySelectorAll('.warehouse-building-btn[data-warehouse]').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.querySelector(`.warehouse-building-btn[data-warehouse="${warehouseId}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
        currentWarehouseName = activeBtn.textContent.trim();   // ← запомнили название
    } else {
        currentWarehouseName = '';
    }
    loadTableData();
}

// ==================== Загрузка основной таблицы ====================
async function loadTableData() {
    const search = document.getElementById('warehouseSearch')?.value.trim() || '';
    const params = new URLSearchParams({
        ajax: 'load_table',
        tab: currentTab,
        warehouse_id: currentWarehouse,
        search: search
    });
    try {
        const resp = await fetch('modules/warehouse_table/warehouse_table.php?' + params.toString());
        const data = await resp.json();
        if (data.success) {
            document.getElementById('warehouseTableBody').innerHTML = data.html;
            document.getElementById('warehouseFooter').innerHTML = `<span>Всего устройств на складе: ${data.total_count}</span>`;
            if (data.columns) renderTableHead(data.columns);
            if (String(data.active_warehouse_id) !== String(currentWarehouse)) {
                applyWarehouseFilter(data.active_warehouse_id);
            }
        } else {
            console.error('Ошибка сервера:', data.error);
        }
    } catch (err) {
        console.error('Сетевая ошибка:', err);
    }
}

function renderTableHead(columns) {
    const thead = document.getElementById('warehouseTableHead');
    if (!thead) return;
    thead.innerHTML = '<tr>' + columns.map(col => `<th>${col}</th>`).join('') + '</tr>';
}

// ==================== Раскрытие группы ====================
async function toggleWarehouseGroup(row) {
    const groupId = row.dataset.groupId;
    const detailRow = document.getElementById('warehouse-detail-' + groupId);
    const container = document.getElementById('warehouse-detail-content-' + groupId);
    if (!detailRow || !container) return;

    if (container.dataset.loaded === 'true') {
        const isVisible = detailRow.style.display !== 'none';
        detailRow.style.display = isVisible ? 'none' : 'table-row';
        const arrow = row.querySelector('.expand-arrow');
        if (arrow) arrow.textContent = isVisible ? '▶' : '▼';
        return;
    }

    detailRow.style.display = 'table-row';
    container.innerHTML = '<span class="load-indicator loading"></span> Загрузка...';
    const arrow = row.querySelector('.expand-arrow');
    if (arrow) arrow.textContent = '▼';

    const warehouse = row.dataset.warehouse || 'all';
    const deviceType = row.dataset.deviceType;
    const vendor = row.dataset.vendor;
    const model = row.dataset.model;

    try {
        const resp = await fetch(`modules/warehouse_table/warehouse_table.php?ajax=get_warehouse_equipment_group&warehouse_id=${warehouse}&device_type_id=${deviceType}&vendor_id=${vendor}&model_id=${model}`);
        const data = await resp.json();
        if (data.html) {
            container.innerHTML = `<table class="nested-table">
                <thead><tr><th>Имя хоста</th><th>IP</th><th>Серийный номер</th><th>MAC</th><th>Прошивка</th><th>Примечание</th><th></th></tr></thead>
                <tbody>${data.html}</tbody></table>`;
            container.dataset.loaded = 'true';
            attachWarehouseEquipmentContextMenu(container);  // ← контекстное меню
        } else {
            container.innerHTML = '<div class="no-equipment">Нет устройств</div>';
            container.dataset.loaded = 'true';
        }
    } catch (err) {
        container.innerHTML = '<div class="no-equipment">Ошибка загрузки</div>';
        container.dataset.loaded = 'true';
    }
}

// ==================== ДОБАВЛЕНИЕ СКЛАДА ====================
async function openAddWarehouseForm() {
    const modal = document.getElementById('warehouseModal');
    const select = document.getElementById('warehouseBuildingSelect');
    const form = document.getElementById('warehouseForm');
    if (!modal || !select) return;

    if (form) form.reset();
    if (warehouseBuildingSearchable) {
        warehouseBuildingSearchable.destroy();
        warehouseBuildingSearchable = null;
    }
    const wrapper = select.closest('.searchable-select');
    if (wrapper) {
        wrapper.parentNode.insertBefore(select, wrapper);
        wrapper.remove();
    }
    select.style.display = '';
    select.innerHTML = '<option value="">-- загрузка --</option>';

    try {
        const resp = await fetch('modules/warehouse_table/warehouse_table.php?ajax=get_buildings');
        const buildings = await resp.json();
        select.innerHTML = '<option value="">-- не выбрано --</option>';
        buildings.forEach(b => select.appendChild(new Option(b.name, b.id)));
        warehouseBuildingSearchable = new SearchableSelect(select);
    } catch (err) {
        select.innerHTML = '<option value="">-- ошибка --</option>';
    }

    modal.classList.add('visible');
}

function closeWarehouseModal() {
    const modal = document.getElementById('warehouseModal');
    if (modal) modal.classList.remove('visible');
    const form = document.getElementById('warehouseForm');
    if (form) form.reset();
    if (warehouseBuildingSearchable) {
        warehouseBuildingSearchable.destroy();
        warehouseBuildingSearchable = null;
    }
}

async function submitWarehouseForm(e) {
    e.preventDefault();

    const select = document.getElementById('warehouseBuildingSelect');
    const buildingId = select.value;
    const nameInput = document.querySelector('#warehouseForm input[name="name"]');
    const name = nameInput ? nameInput.value.trim() : '';

    if (!buildingId || buildingId === '__add_new__' || buildingId === '-- не выбрано --' || isNaN(parseInt(buildingId))) {
        alert('Выберите здание из списка');
        return;
    }
    if (!name) {
        alert('Введите название помещения');
        return;
    }

    const formData = new FormData();
    formData.append('building_id', buildingId);
    formData.append('name', name);

    try {
        const resp = await fetch('modules/warehouse_table/warehouse_table.php?ajax=add_warehouse', { method: 'POST', body: formData });
        const data = await resp.json();
        if (data.success) {
            addWarehouseButton(data.id, data.name, data.building_name);
            closeWarehouseModal();
        } else {
            alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (err) {
        alert('Ошибка сети');
    }
}

function addWarehouseButton(id, name, buildingName) {
    const filterDiv = document.getElementById('warehouseFilter');
    if (!filterDiv) return;
    const btn = document.createElement('button');
    btn.className = 'warehouse-building-btn';
    btn.setAttribute('data-warehouse', id);
    btn.setAttribute('onclick', `applyWarehouseFilter('${id}')`);
    btn.textContent = buildingName + ' (' + name + ')';
    const addBtn = filterDiv.querySelector('.add-warehouse-btn');
    filterDiv.insertBefore(btn, addBtn);
}

// ==================== ДОБАВЛЕНИЕ ОБОРУДОВАНИЯ НА СКЛАД ====================
async function openWarehouseEquipmentForm() {
    if (currentWarehouse === 'all') {
        alert('Пожалуйста, выберите конкретный склад.');
        return;
    }

    const modal = document.getElementById('warehouseEquipmentModal');
    if (!modal) return;

    await loadWarehouseSelects();

// Даём время на обновление DOM (особенно важно для Firefox)
await new Promise(resolve => setTimeout(resolve, 50));

// Предзаполняем склад
const whSelect = document.getElementById('whEquipWarehouseSelect');
if (whSelect) {
    if (currentWarehouseName) {
        const option = Array.from(whSelect.options).find(opt => opt.textContent.trim() === currentWarehouseName);
        if (option) whSelect.value = option.value;
    } else if (currentWarehouse) {
        whSelect.value = currentWarehouse;
    }
}

// Теперь создаём SearchableSelect для всех селектов
['whEquipWarehouseSelect', 'whEquipDeviceType', 'whEquipVendor', 'whEquipModel', 'whEquipIp', 'whEquipFirmware'].forEach(id => {
    const select = document.getElementById(id);
    if (!select) return;
    if (whEquipSearchableInstances[id]) {
        whEquipSearchableInstances[id].destroy();
    }
    whEquipSearchableInstances[id] = new SearchableSelect(select);
    // Синхронизируем текстовое поле с выбранным значением
    if (select.searchableInstance) {
        select.searchableInstance.syncInputWithSelect();
    }
});

    // Сброс PoE и MAC
    const poeGroup = document.getElementById('whPoEGroup');
    if (poeGroup) poeGroup.style.display = 'none';
    const poeCheckbox = document.getElementById('whPoECheckbox');
    if (poeCheckbox) poeCheckbox.checked = false;
    document.getElementById('warehouseEquipmentForm').reset();
    // Восстанавливаем склад после reset
    if (whSelect) {
        whSelect.value = currentWarehouse;
        if (whSelect.searchableInstance) whSelect.searchableInstance.syncInputWithSelect();
    }

    // Форматирование MAC
    if (typeof setupMacFormatting === 'function') setupMacFormatting();

    // Показывать PoE при выборе "Коммутатор"
    const deviceTypeSelect = document.getElementById('whEquipDeviceType');
    if (deviceTypeSelect) {
        deviceTypeSelect.addEventListener('change', function() {
            const selectedText = this.options[this.selectedIndex]?.textContent || '';
            const poeGroup = document.getElementById('whPoEGroup');
            if (poeGroup) {
                poeGroup.style.display = selectedText === 'Коммутатор' ? 'block' : 'none';
                if (selectedText !== 'Коммутатор') {
                    const cb = document.getElementById('whPoECheckbox');
                    if (cb) cb.checked = false;
                }
            }
        });
    }

    // Проверка серийного номера и MAC
    setupUniqueChecks();

    modal.classList.add('visible');
}

function closeWarehouseEquipmentForm() {
    const modal = document.getElementById('warehouseEquipmentModal');
    if (modal) modal.classList.remove('visible');
    const form = document.getElementById('warehouseEquipmentForm');
    if (form) form.reset();
    Object.values(whEquipSearchableInstances).forEach(instance => instance.destroy());
    whEquipSearchableInstances = {};
    // Очищаем оригинальные селекты
    const whSelect = document.getElementById('whEquipWarehouseSelect');
if (whSelect) {
    whSelect.innerHTML = '<option value="">-- загрузка --</option>';
}}

async function loadWarehouseSelects() {
    const warehouseSelect = document.getElementById('whEquipWarehouseSelect');
    const deviceTypeSelect = document.getElementById('whEquipDeviceType');
    const vendorSelect = document.getElementById('whEquipVendor');
    const modelSelect = document.getElementById('whEquipModel');
    const ipSelect = document.getElementById('whEquipIp');
    const firmwareSelect = document.getElementById('whEquipFirmware');

    // Очистка и сброс всех селектов
    [warehouseSelect, deviceTypeSelect, vendorSelect, modelSelect, ipSelect, firmwareSelect].forEach(select => {
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
        select.innerHTML = '';
    });

    // Загрузка данных
    const [whData, typeData, vendData, ipData, fwData] = await Promise.all([
        fetch('?ajax=get_warehouses').then(r => r.json()).catch(() => []),
        fetch('?ajax=get_list&list=device_types').then(r => r.json()).catch(() => ({ data: [] })),
        fetch('?ajax=get_list&list=vendors').then(r => r.json()).catch(() => ({ data: [] })),
        fetch('?ajax=get_list&list=ip_address').then(r => r.json()).catch(() => ({ data: [] })),
        fetch('?ajax=get_list&list=firmwares').then(r => r.json()).catch(() => ({ data: [] })),
    ]);

    const fillSelect = (select, items, idField, nameField) => {
        if (!select) return;
        select.innerHTML = '<option value="">-- не выбрано --</option>';
        const list = Array.isArray(items) ? items : (items.data || []);
        list.forEach(item => select.appendChild(new Option(item[nameField], item[idField])));
        if (select.searchableInstance) {
            select.searchableInstance.options = Array.from(select.options).filter(o => o.value !== '');
            select.searchableInstance.updateDropdown('');
        }
    };

    // Склады – используем поле display
    if (warehouseSelect) {
        warehouseSelect.innerHTML = '<option value="">-- не выбрано --</option>';
        const whList = Array.isArray(whData) ? whData : (whData.data || []);
        whList.forEach(wh => {
            warehouseSelect.appendChild(new Option(wh.display || wh.name, wh.id));
        });
        if (warehouseSelect.searchableInstance) {
            warehouseSelect.searchableInstance.options = Array.from(warehouseSelect.options).filter(o => o.value !== '');
            warehouseSelect.searchableInstance.updateDropdown('');
        }
    }

    fillSelect(deviceTypeSelect, typeData, 'id', 'name');
    fillSelect(vendorSelect, vendData, 'id', 'name');
    fillSelect(ipSelect, ipData, 'id', 'name');          // <-- используем поле name
    fillSelect(firmwareSelect, fwData, 'id', 'name');

    // Фильтрация моделей по производителю
    if (vendorSelect && modelSelect) {
        const updateModels = async () => {
            fillSelect(modelSelect, [], 'id', 'name');
            const vendorId = vendorSelect.value;
            if (vendorId) {
                const models = await fetch(`?ajax=get_list_models&vendor_id=${vendorId}`).then(r => r.json()).catch(() => ({ data: [] }));
                fillSelect(modelSelect, models, 'id', 'name');
            }
        };
        vendorSelect.addEventListener('change', updateModels);
    }
}

// Проверка уникальности серийного номера и MAC
function setupUniqueChecks() {
    const serialInput = document.querySelector('#warehouseEquipmentForm [name="serial_number"]');
    const macInput = document.querySelector('#warehouseEquipmentForm [name="mac_address"]');

    [serialInput, macInput].forEach(input => {
        if (!input) return;
        input.addEventListener('blur', async function() {
            const value = this.value.trim();
            if (!value) return;
            const field = this.name;
            const oldMsg = this.parentElement.querySelector('.unique-error');
            if (oldMsg) oldMsg.remove();

            try {
                const resp = await fetch(`?ajax=check_unique&field=${field}&value=${encodeURIComponent(value)}`);
                const data = await resp.json();
                if (data.exists) {
                    const msgDiv = document.createElement('div');
                    msgDiv.className = 'unique-error';
                    msgDiv.textContent = data.message || 'Такое значение уже используется';
                    msgDiv.style.color = 'var(--danger, #e63946)';
                    msgDiv.style.fontSize = '0.85rem';
                    msgDiv.style.marginTop = '0.3rem';
                    this.parentElement.appendChild(msgDiv);
                }
            } catch (e) {}
        });
    });
}

// ==================== Отправка формы ====================
document.addEventListener('DOMContentLoaded', () => {
    initWarehouseCtxMenu();  // ← инициализация контекстного меню

    updateAddButton();
    loadTableData();   // ← ОБЯЗАТЕЛЬНО для первоначальной загрузки

    const whEquipForm = document.getElementById('warehouseEquipmentForm');
    if (whEquipForm) {
        whEquipForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            // Значения из поисковых селектов
            ['whEquipWarehouseSelect', 'whEquipDeviceType', 'whEquipVendor', 'whEquipModel', 'whEquipIp', 'whEquipFirmware'].forEach(id => {
                const select = document.getElementById(id);
                if (select && select.value) {
                    formData.set(select.name, select.value);
                }
            });

            // PoE
            const poeCheckbox = document.getElementById('whPoECheckbox');
            if (poeCheckbox) {
                formData.set('Poe', poeCheckbox.checked ? '1' : '0');
            }

            try {
                const resp = await fetch('?ajax=add_warehouse_equipment', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();
                if (data.success) {
                    closeWarehouseEquipmentForm();
                    if (typeof loadTableData === 'function') loadTableData();
                } else {
                    alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                }
            } catch (err) {
                alert('Ошибка сети');
            }
        });
    }

    const warehouseForm = document.getElementById('warehouseForm');
    if (warehouseForm) {
        warehouseForm.removeEventListener('submit', submitWarehouseForm);
        warehouseForm.addEventListener('submit', submitWarehouseForm);
    }

    const searchInput = document.getElementById('warehouseSearch');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => loadTableData(), 300);
        });
    }
});

function initWarehouseCtxMenu() {
    warehouseCtxMenu = document.getElementById('warehouseCtxMenu');
    if (!warehouseCtxMenu) {
        warehouseCtxMenu = document.createElement('div');
        warehouseCtxMenu.id = 'warehouseCtxMenu';
        warehouseCtxMenu.className = 'context-menu';   // ← только класс
        document.body.appendChild(warehouseCtxMenu);
    }
    document.addEventListener('click', hideWarehouseCtxMenu);
}
// function initiateBuildStack() {
//     if (!selectedWarehouseGroup) {
//         alert('Сначала выберите группу оборудования (кликните по строке в таблице)');
//         return;
//     }
//     warehouseBuildStack();
// }