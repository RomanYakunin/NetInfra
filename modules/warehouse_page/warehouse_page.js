// modules/warehouse_page/warehouse_page.js

let currentTab = 'Оборудование';
let currentWarehouse = 'all';
let warehouseBuildingSearchable = null;
let whEquipSearchableInstances = {};
let warehouseButtonCtxMenu = null;
let currentWarehouseButtonData = null;
let editingWarehouseId = null;

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
        const resp = await fetch('modules/warehouse_page/warehouse_page.php?' + params.toString());
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
        const resp = await fetch(`modules/warehouse_page/warehouse_page.php?ajax=get_warehouse_equipment_group&warehouse_id=${warehouse}&device_type_id=${deviceType}&vendor_id=${vendor}&model_id=${model}`);
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
    const title = modal.querySelector('h3');
    if (title) title.textContent = 'Добавить склад';

    resetSelectForSearchable(select);  // ← очистка
    window.warehouseBuildingSearchable = null;

    select.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const resp = await fetch('?ajax=get_buildings');
        const buildings = await resp.json();
        select.innerHTML = '<option value="">-- не выбрано --</option>';
        buildings.forEach(b => select.appendChild(new Option(b.name, b.id)));
        window.warehouseBuildingSearchable = new SearchableSelect(select);
    } catch (e) {
        select.innerHTML = '<option value="">-- ошибка --</option>';
    }

    showModal(modal);
}

function closeWarehouseModal() {
    const modal = document.getElementById('warehouseModal');
    if (modal) {
        modal.classList.remove('visible');
        // Уничтожает поисковые селекты вместе с обёртками — иначе при
        // повторном открытии формы они дублируются
        if (typeof resetModalForm === 'function') resetModalForm(modal);
    }
    window.warehouseBuildingSearchable = null;
    editingWarehouseId = null;
    const title = modal?.querySelector('h3');
    if (title) title.textContent = 'Добавить склад';
}

async function submitWarehouseForm(e) {
    e.preventDefault();
    const select = document.getElementById('warehouseBuildingSelect');
    const buildingId = select ? select.value : '';
    const nameInput = document.querySelector('#warehouseForm input[name="name"]');
    const name = nameInput ? nameInput.value.trim() : '';

    if (!buildingId || isNaN(parseInt(buildingId))) {
        alert('Выберите здание');
        return;
    }

    // Получить название здания из селекта для уведомления
    const buildingName = select.options[select.selectedIndex]?.text || '';

    const formData = new FormData();
    formData.append('building_id', buildingId);
    formData.append('name', name);

    const isEdit = !!editingWarehouseId;
    const url = isEdit ? '?ajax=update_warehouse' : '?ajax=add_warehouse';
    if (isEdit) formData.append('id', editingWarehouseId);

    try {
        const resp = await fetch(url, { method: 'POST', body: formData });
        const data = await resp.json();
        if (data.success) {
    closeWarehouseModal();
    await refreshWarehouseButtons();

    const building = data.building_name || buildingName;  // buildingName взято из select
    const whName = data.name || data.new_name || name;
    // Строим читаемое название без пустых скобок
    const display = building ? (whName ? `${building} (${whName})` : building) : (whName || 'Склад');

    if (isEdit) {
        showToast(`Склад «${display}» переименован`, 'success');
    } else {
        showToast(`Склад «${display}» успешно добавлен`, 'success');
    }
    editingWarehouseId = null;
} else {
            showToast(data.error || 'Ошибка', 'error');
        }
    } catch (err) {
        showToast('Ошибка сети', 'error');
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

    // Загружаем конфигурацию полей оборудования, если ещё не загружена
    if (window.AppState.config.equipment.fields.length === 0) {
        await loadEquipmentFields();
    }

    const modal = document.getElementById('warehouseEquipmentModal');
    const fieldsContainer = document.getElementById('warehouseFormFields');
    const hiddenContainer = document.getElementById('warehouseFormHiddenFields');
    const titleEl = document.getElementById('warehouseEquipmentTitle');
    if (!modal || !fieldsContainer || !titleEl) return;

    titleEl.textContent = `Добавить оборудование на склад «${currentWarehouseName || 'склад'}»`;

    fieldsContainer.innerHTML = '';
    hiddenContainer.innerHTML = '';

    // Скрытое поле с ID склада
    const whHidden = document.createElement('input');
    whHidden.type = 'hidden';
    whHidden.name = 'warehouse_id';
    whHidden.value = currentWarehouse;
    hiddenContainer.appendChild(whHidden);

    // Строим досье (все поля, модули, сервисы, учётные данные)
    await buildEquipmentDossier(fieldsContainer, window.AppState.config.equipment, null, {
        location_type: 'warehouse',
        warehouse_id: currentWarehouse,
        warehouse_name: currentWarehouseName
    });

    // Валидация дубликатов
   if (typeof setupEquipmentValidation === 'function') {
    setupEquipmentValidation(fieldsContainer, null, null, null, currentWarehouse);
}
    // Настройка кнопки расширения
    const expandBtn = document.getElementById('warehouseExpandBtn');
    if (expandBtn) {
        expandBtn.onclick = () => {
            const content = modal.querySelector('.modal-content');
            if (content) {
                content.classList.toggle('wide');
                expandBtn.innerHTML = content.classList.contains('wide') ? '✕' : '⛶';
            }
        };
    }

    showModal(modal);
}

function closeWarehouseEquipmentForm() {
    const modal = document.getElementById('warehouseEquipmentModal');
    if (modal) {
        modal.classList.remove('visible');
        // Полный сброс: поисковые селекты + disabled + ошибки валидации
        if (typeof resetModalForm === 'function') resetModalForm(modal);
    }
    // Поля формы строятся заново при каждом открытии
    const fields = document.getElementById('warehouseFormFields');
    const hidden = document.getElementById('warehouseFormHiddenFields');
    if (fields) fields.innerHTML = '';
    if (hidden) hidden.innerHTML = '';
}

// async function loadWarehouseSelects() {
//     const warehouseSelect = document.getElementById('whEquipWarehouseSelect');
//     const deviceTypeSelect = document.getElementById('whEquipDeviceType');
//     const vendorSelect = document.getElementById('whEquipVendor');
//     const modelSelect = document.getElementById('whEquipModel');
//     const ipSelect = document.getElementById('whEquipIp');
//     const firmwareSelect = document.getElementById('whEquipFirmware');

//     // Очистка и сброс всех селектов
//     [warehouseSelect, deviceTypeSelect, vendorSelect, modelSelect, ipSelect, firmwareSelect].forEach(select => {
//         if (!select) return;
//         if (select.searchableInstance) {
//             select.searchableInstance.destroy();
//             select.searchableInstance = null;
//         }
//         const wrapper = select.closest('.searchable-select');
//         if (wrapper) {
//             wrapper.parentNode.insertBefore(select, wrapper);
//             wrapper.remove();
//         }
//         select.style.display = '';
//         select.innerHTML = '';
//     });

//     // Загрузка данных
//     const [whData, typeData, vendData, ipData, fwData] = await Promise.all([
//         fetch('?ajax=get_warehouses').then(r => r.json()).catch(() => []),
//         fetch('?ajax=get_list&list=device_types').then(r => r.json()).catch(() => ({ data: [] })),
//         fetch('?ajax=get_list&list=vendors').then(r => r.json()).catch(() => ({ data: [] })),
//         fetch('?ajax=get_list&list=ip_address').then(r => r.json()).catch(() => ({ data: [] })),
//         fetch('?ajax=get_list&list=firmwares').then(r => r.json()).catch(() => ({ data: [] })),
//     ]);

//     const fillSelect = (select, items, idField, nameField) => {
//         if (!select) return;
//         select.innerHTML = '<option value="">-- не выбрано --</option>';
//         const list = Array.isArray(items) ? items : (items.data || []);
//         list.forEach(item => select.appendChild(new Option(item[nameField], item[idField])));
//         if (select.searchableInstance) {
//             select.searchableInstance.options = Array.from(select.options).filter(o => o.value !== '');
//             select.searchableInstance.updateDropdown('');
//         }
//     };

//     // Склады – используем поле display
//     if (warehouseSelect) {
//         warehouseSelect.innerHTML = '<option value="">-- не выбрано --</option>';
//         const whList = Array.isArray(whData) ? whData : (whData.data || []);
//         whList.forEach(wh => {
//             warehouseSelect.appendChild(new Option(wh.display || wh.name, wh.id));
//         });
//         if (warehouseSelect.searchableInstance) {
//             warehouseSelect.searchableInstance.options = Array.from(warehouseSelect.options).filter(o => o.value !== '');
//             warehouseSelect.searchableInstance.updateDropdown('');
//         }
//     }

//     fillSelect(deviceTypeSelect, typeData, 'id', 'name');
//     fillSelect(vendorSelect, vendData, 'id', 'name');
//     fillSelect(ipSelect, ipData, 'id', 'name');          // <-- используем поле name
//     fillSelect(firmwareSelect, fwData, 'id', 'name');

//     // Фильтрация моделей по производителю
//     if (vendorSelect && modelSelect) {
//         const updateModels = async () => {
//             fillSelect(modelSelect, [], 'id', 'name');
//             const vendorId = vendorSelect.value;
//             if (vendorId) {
//                 const models = await fetch(`?ajax=get_list_models&vendor_id=${vendorId}`).then(r => r.json()).catch(() => ({ data: [] }));
//                 fillSelect(modelSelect, models, 'id', 'name');
//             }
//         };
//         vendorSelect.addEventListener('change', updateModels);
//     }
// }


// ==================== Отправка формы ====================
document.addEventListener('DOMContentLoaded', () => {
    // Инициализация контекстного меню для оборудования
    initWarehouseCtxMenu();

    // Обновление кнопки добавления в зависимости от вкладки
    updateAddButton();

    // Первоначальная загрузка таблицы склада
    loadTableData();

    // ===================== Форма добавления оборудования на склад =====================
    const whEquipForm = document.getElementById('warehouseEquipmentForm');
    if (whEquipForm) {
        whEquipForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            // Проверка активных ошибок валидации
            if (typeof validateAllFields === 'function' && !validateAllFields(this)) {
                if (submitBtn) submitBtn.disabled = false;
                return;
            }

            const formData = new FormData(this);

            // Сбор модулей
            const modulesContainer = document.getElementById('modules-container');
            if (modulesContainer) {
                const modules = {};
                modulesContainer.querySelectorAll('.module-column[data-module-type]').forEach(col => {
                    const type = col.dataset.moduleType;
                    const tiles = col.querySelectorAll('.module-tile');
                    if (tiles.length > 0) {
                        modules[type] = [];
                        tiles.forEach(tile => {
                            const inputs = tile.querySelectorAll('input');
                            const mod = {};
                            inputs.forEach(inp => mod[inp.dataset.field] = inp.value);
                            mod.name = tile.querySelector('.module-name')?.textContent?.trim() || '';
                            modules[type].push(mod);
                        });
                    }
                });
                formData.append('modules', JSON.stringify(modules));
            }

            // Сбор сервисов
            const servicesGrid = document.getElementById('services-grid');
            if (servicesGrid) {
                const services = {};
                servicesGrid.querySelectorAll('.service-card').forEach(card => {
                    const svc = card.dataset.service;
                    if (svc === 'radius_tacacs') {
                        const statusText = card.querySelector('#radius-tacacs-status span').textContent.trim();
                        if (statusText.includes('RADIUS')) services.RADIUS = true;
                        else if (statusText.includes('TACACS+')) services['TACACS+'] = true;
                    } else {
                        const connected = card.querySelector('.service-status').classList.contains('service-connected');
                        services[svc] = connected;
                    }
                });
                formData.append('services', JSON.stringify(services));
            }

            // Значения из поисковых селектов
            this.querySelectorAll('.searchable-select select').forEach(select => {
                if (select.value && select.value !== '__add_new__') {
                    formData.set(select.name, select.value);
                }
            });

            // PoE
            const poeCheckbox = this.querySelector('input[name="Poe"]');
            if (poeCheckbox) {
                formData.set('Poe', poeCheckbox.checked ? '1' : '0');
            } else {
                formData.set('Poe', '0');
            }

            try {
                const resp = await fetch('?ajax=add_equipment', { method: 'POST', body: formData });
                const data = await resp.json();
                if (data.success) {
                    closeWarehouseEquipmentForm();
                    if (typeof loadTableData === 'function') loadTableData();
                    showToast('Оборудование добавлено на склад', 'success');
                } else {
                    showToast(data.error || 'Ошибка добавления', 'error');
                }
            } catch (err) {
                showToast('Ошибка сети', 'error');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    // ===================== Форма добавления/редактирования склада =====================
    const warehouseForm = document.getElementById('warehouseForm');
    if (warehouseForm) {
        warehouseForm.removeEventListener('submit', submitWarehouseForm);
        warehouseForm.addEventListener('submit', submitWarehouseForm);
    }

    // Поиск
    const searchInput = document.getElementById('warehouseSearch');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => loadTableData(), 300);
        });
    }

    // Контекстное меню для кнопок складов
    initWarehouseButtonCtxMenu();
    refreshWarehouseButtons();
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

function initWarehouseButtonCtxMenu() {
    if (document.getElementById('warehouseButtonCtxMenu')) return;
    const menu = document.createElement('div');
    menu.id = 'warehouseButtonCtxMenu';
    menu.className = 'context-menu';
    document.body.appendChild(menu);
    warehouseButtonCtxMenu = menu;
    document.addEventListener('click', () => {
        if (warehouseButtonCtxMenu) warehouseButtonCtxMenu.style.display = 'none';
    });
}
function showWarehouseButtonContextMenu(x, y, data) {
    if (!warehouseButtonCtxMenu) initWarehouseButtonCtxMenu();
    currentWarehouseButtonData = data;   // сохраняем весь объект с buildingId
    const menu = warehouseButtonCtxMenu;
    menu.innerHTML = `
        <div class="menu-item" data-action="edit">✏️ Редактировать</div>
        <div class="menu-item" data-action="delete">🗑️ Удалить</div>
        <div class="menu-item" data-action="move">📦 Переместить</div>
    `;
    menu.style.display = 'block';
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
    const rect = menu.getBoundingClientRect();
    if (rect.right > window.innerWidth) menu.style.left = (window.innerWidth - rect.width - 5) + 'px';
    if (rect.bottom > window.innerHeight) menu.style.top = (window.innerHeight - rect.height - 5) + 'px';
    menu.querySelectorAll('.menu-item').forEach(item => {
        item.onclick = (e) => {
            e.stopPropagation();
            const action = item.dataset.action;
            menu.style.display = 'none';
            warehouseButtonHandleContextAction(action);
        };
    });
}
function warehouseButtonHandleContextAction(action) {
    if (!currentWarehouseButtonData) return;
    const { id, name, buildingName, buildingId } = currentWarehouseButtonData;
    switch (action) {
        case 'edit':
            openEditWarehouse(id, name, buildingId);   // теперь передаём buildingId
            break;
        case 'delete':
    // Формируем читаемое имя склада (здание + помещение)
    const displayName = buildingName 
        ? (name ? `${buildingName} (${name})` : buildingName) 
        : (name || 'Склад');
    deleteWarehouse(id, displayName);
    break;
        case 'move':
            showToast('В разработке...', 'info');
            break;
    }
}
async function openEditWarehouse(id, currentName, buildingId) {
    const modal = document.getElementById('warehouseModal');
    const select = document.getElementById('warehouseBuildingSelect');
    const nameInput = document.querySelector('#warehouseForm input[name="name"]');
    const title = modal?.querySelector('h3');
    if (!modal || !select || !nameInput || !title) return;

    editingWarehouseId = id;
    title.textContent = 'Редактировать склад';

    resetSelectForSearchable(select);
    window.warehouseBuildingSearchable = null;

    select.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const resp = await fetch('?ajax=get_buildings');
        const buildings = await resp.json();
        select.innerHTML = '<option value="">-- не выбрано --</option>';
        buildings.forEach(b => select.appendChild(new Option(b.name, b.id)));
        // Устанавливаем значение по buildingId
        if (buildingId && buildingId !== '') {
            select.value = buildingId;
        }
        window.warehouseBuildingSearchable = new SearchableSelect(select);
        // Принудительная синхронизация текстового поля
        if (select.searchableInstance && select.value) {
            select.searchableInstance.syncInputWithSelect();
        }
    } catch (e) {
        select.innerHTML = '<option value="">-- ошибка --</option>';
    }

    nameInput.value = currentName;
    showModal(modal);
}

async function deleteWarehouse(warehouseId, warehouseDisplayName) {
    // warehouseDisplayName — например, "Главный корпус (Серверная)" или "Главный корпус"
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;display:flex;justify-content:center;align-items:center;';
    
    const dialog = document.createElement('div');
    dialog.style.cssText = 'background:var(--bg-card);padding:2rem;border-radius:8px;max-width:400px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.3);';
    dialog.innerHTML = `
        <h3 style="margin-bottom:1rem;">Удалить склад?</h3>
        <p style="margin-bottom:1.5rem;">${warehouseDisplayName ? `«${warehouseDisplayName}»` : 'Выбранный склад'}</p>
        <button id="confirmDeleteWhBtn" class="btn danger" style="margin-right:0.5rem;">Удалить</button>
        <button id="cancelDeleteWhBtn" class="btn secondary">Отмена</button>
    `;
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);

    document.getElementById('cancelDeleteWhBtn').onclick = () => overlay.remove();
    document.getElementById('confirmDeleteWhBtn').onclick = async () => {
        overlay.remove();
        const formData = new FormData();
        formData.append('id', warehouseId);
        try {
            const resp = await fetch('?ajax=delete_warehouse', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success) {
                if (currentWarehouse == warehouseId) applyWarehouseFilter('all');
                await refreshWarehouseButtons();
                // Формируем имя для уведомления из ответа (на случай, если displayName не отражает актуальное)
                const building = data.building_name || '';
                const whName = data.name || '';
                const display = building ? (whName ? `${building} (${whName})` : building) : (whName || 'Склад');
                showToast(`Склад «${display}» удалён`, 'success');
            } else {
                showToast(data.error || 'Ошибка удаления', 'error');
            }
        } catch (err) {
            showToast('Ошибка сети', 'error');
        }
    };
}
async function refreshWarehouseButtons() {
    const filterDiv = document.getElementById('warehouseFilter');
    if (!filterDiv) {
        console.warn('Элемент #warehouseFilter не найден');
        return;
    }

    try {
        const resp = await fetch('?ajax=get_warehouses');
        if (!resp.ok) throw new Error('Ошибка сети: ' + resp.status);
        const warehouses = await resp.json();
        if (!Array.isArray(warehouses)) {
            console.error('Ожидался массив складов, получено:', warehouses);
            return;
        }

        // Удаляем все кнопки складов (кроме "Все склады" и кнопки "+")
        const allButtons = filterDiv.querySelectorAll('.warehouse-building-btn');
        allButtons.forEach(btn => {
            if (btn.dataset.warehouse === 'all' || btn.classList.contains('add-warehouse-btn')) return;
            btn.remove();
        });

        // Ищем кнопку добавления (она должна остаться)
        const addBtn = filterDiv.querySelector('.add-warehouse-btn');
        if (!addBtn) {
            console.warn('Кнопка "Добавить склад" не найдена, вставка новых кнопок пропущена');
            return;
        }

        // Добавляем новые кнопки
        warehouses.forEach(wh => {
    const btn = document.createElement('button');
    btn.className = 'warehouse-building-btn';
    btn.dataset.warehouse = wh.id;
    btn.dataset.warehouseName = wh.name;
    btn.dataset.warehouseBuilding = wh.building_name;
    btn.dataset.buildingId = wh.building_id;   // ← добавили
    btn.textContent = wh.display || (wh.building_name + ' (' + wh.name + ')');
    btn.onclick = () => applyWarehouseFilter(wh.id);
    btn.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        showWarehouseButtonContextMenu(e.clientX, e.clientY, {
            id: wh.id,
            name: wh.name,
            buildingName: wh.building_name,
            buildingId: wh.building_id      // ← передаём
        });
    });
    filterDiv.insertBefore(btn, addBtn);
});

        // Восстанавливаем активную кнопку
        if (currentWarehouse !== 'all') {
            const activeBtn = filterDiv.querySelector(`[data-warehouse="${currentWarehouse}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            } else {
                // Если текущий склад был удалён, переключаемся на "Все склады"
                applyWarehouseFilter('all');
            }
        } else {
            const allBtn = filterDiv.querySelector('[data-warehouse="all"]');
            if (allBtn) allBtn.classList.add('active');
        }
    } catch (err) {
        console.error('Ошибка в refreshWarehouseButtons:', err);
    }
}

function resetSelectForSearchable(select) {
    if (!select) return;
    // Уничтожаем экземпляр SearchableSelect, если он есть
    if (select.searchableInstance) {
        select.searchableInstance.destroy();
        select.searchableInstance = null;
    }
    // Удаляем все родительские обёртки .searchable-select
    let parent = select.parentElement;
    while (parent) {
        if (parent.classList.contains('searchable-select')) {
            parent.parentNode.insertBefore(select, parent);
            parent.remove();
            break;
        }
        parent = parent.parentElement;
    }
    // Показываем оригинальный select (если был скрыт)
    select.style.display = '';
    // Удаляем возможный data-атрибут
    select.removeAttribute('data-searchable');
}