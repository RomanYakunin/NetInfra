window.AppState = {
    currentFormType: null,
    currentRelatedId: null,
    currentInitialData: null,
    currentExtraData: {},
    currentLocationSelect: null,
    currentNodeTypeSelect: null,
    currentEquipmentNodeId: null,
    skipCloseConfirmation: false,
    currentStackGroupId: null,
    config: {
        node: { title: 'Добавить узел', fields: [], url: '?ajax=add_node' },
        equipment: { title: 'Добавить устройство', fields: [], url: '?ajax=add_equipment' }
    },
    allModels: []
    
};

async function loadNodeFields() {
    try {
        const cols = await fetchJSON('?ajax=get_node_columns');
        window.AppState.config.node.fields = cols.map(col => {
            if (col.type === 'select' && col.source === 'buildings') {
                return { name: col.name, label: col.label, type: 'select', source: 'buildings' };
            }
            if (col.type === 'select' && col.source === 'node_types') {
                return { name: col.name, label: col.label, type: 'select', source: 'node_types' };
            }
            return { name: col.name, label: col.label, type: col.type || 'text' };
        });
    } catch (err) {
        console.error(err);
        window.AppState.config.node.fields = [
            { name: 'KY_number', label: 'Номер КУ(только число)', type: 'text' },
            { name: 'building_id', label: 'Здание', type: 'select', source: 'buildings' },
            { name: 'workshop', label: 'Цех', type: 'text' },
            { name: 'floor', label: 'Этаж', type: 'text' },
            { name: 'room', label: 'Помещение', type: 'text' },
            { name: 'node_type_id', label: 'Тип узла', type: 'select', source: 'node_types' }
        ];
    }
}

async function loadEquipmentFields() {
    try {
        const cols = await fetchJSON('?ajax=get_equipment_columns');
        window.AppState.config.equipment.fields = cols.map(col => {
            if (col.name === 'Poe') col.type = 'switch';
            return col;
        });
    } catch (err) {
        console.error(err);
        window.AppState.config.equipment.fields = [
            { name: 'ip_address', label: 'IP-адрес', type: 'select', source: 'ip_address_list' },
            { name: 'hostname', label: 'Имя хоста', type: 'text' },
            { name: 'Poe', label: 'PoE', type: 'switch' },
            { name: 'device_type_id', label: 'Тип устройства', type: 'select', source: 'device_types_list' },
            { name: 'vendor_id', label: 'Производитель', type: 'select', source: 'vendors_list' },
            { name: 'model_id', label: 'Модель', type: 'select', source: 'device_models_list' },
            { name: 'serial_number', label: 'Серийный номер', type: 'text' },
            { name: 'mac_address', label: 'MAC-адрес', type: 'text' },
            { name: 'firmwares', label: 'Прошивка', type: 'select', source: 'firmwares_list' },
            { name: 'id_rack', label: 'Шкаф', type: 'select', source: 'racks_list' },
            { name: 'unit_position', label: 'Юнит', type: 'number' },
            { name: 'Annotation', label: 'Примечание', type: 'textarea' }
        ];
    }
}

async function loadModels() {
    try { window.AppState.allModels = await loadList('device_models'); } catch { window.AppState.allModels = []; }
}

// ======================== Открытие формы ========================
async function openAddForm(type, relatedId = null, initialData = null, extraData = null) {
    const state = window.AppState;
    state.currentFormType = type;
    state.currentRelatedId = relatedId;
    state.currentInitialData = initialData;
    state.currentExtraData = extraData || {};
    const buildingId = extraData?.building_id || null;
    if (extraData?.node_id) state.currentExtraData.node_id = extraData.node_id;
       if (extraData?.force_stack && extraData.node_id) window.AppState.currentStackNodeId = extraData.node_id;

    const config = state.config[type];
    if (!config) return;

    if (initialData && (initialData.id || initialData.id_node)) {
        config.url = type === 'node' ? '?ajax=update_node' : '?ajax=update_equipment';
    } else {
        config.url = type === 'node' ? '?ajax=add_node' : '?ajax=add_equipment';
    }

    if (type === 'node' && config.fields.length === 0) await loadNodeFields();
    if (type === 'equipment' && config.fields.length === 0) {
        await loadEquipmentFields();
        await loadModels();
    }

    const modal = document.getElementById('universalAddModal');
    const titleEl = document.getElementById('addFormTitle');
    const fieldsContainer = document.getElementById('addFormFields');
    const form = document.getElementById('universalAddForm');
    if (!modal || !titleEl || !fieldsContainer || !form) return;

    // Заголовок
    if (extraData?.stack_mode) {
        const hostname = extraData.stack_hostname || 'стек';
        titleEl.textContent = `Добавление устройства в стек (${hostname})`;
        } else if (extraData?.force_stack) {
    let stackKyNumber = extraData?.ky || null;
    if (!stackKyNumber && extraData.node_id) {
        try {
            const nodeInfo = await fetchJSON(`?ajax=get_node_item&id=${extraData.node_id}`);
            stackKyNumber = nodeInfo.KY_number;
        } catch (e) { stackKyNumber = null; }
    }
    const nodeKy = stackKyNumber ? `КУ-${stackKyNumber}` : (extraData.node_id ? `узел ${extraData.node_id}` : 'узел');
    titleEl.textContent = `Добавление стека в ${nodeKy}`;
    } else if (initialData) {
        if (extraData?.update_stack) {
            titleEl.textContent = 'Редактировать стек';
        } else if (type === 'node') {
            const ky = initialData.KY_number ? 'КУ-' + initialData.KY_number : initialData.name || '';
            titleEl.textContent = 'Редактировать узел ' + ky;
        } else {
            const hostname = initialData.hostname || initialData.ip_address || '';
            titleEl.textContent = 'Редактировать устройство ' + hostname;
        }
    } else {
        if (type === 'equipment' && relatedId) {
            let kyNumber = extraData?.ky || null;
            if (!kyNumber) {
                try {
                    const nodeInfo = await fetchJSON(`?ajax=get_node_item&id=${relatedId}`);
                    kyNumber = nodeInfo.KY_number;
                } catch (e) { kyNumber = null; }
            }
            titleEl.textContent = kyNumber
                ? `Добавить оборудование в КУ-${kyNumber}`
                : `Добавить оборудование в узел ${relatedId}`;
        } else if (type === 'node') {
            const buildingName = extraData?.building_name;
            titleEl.textContent = buildingName ? `Добавить узел в ${buildingName}` : 'Добавить узел';
        }
    }

    initModalExpandButton(modal, type);

    fieldsContainer.innerHTML = '';

    if (type === 'node') {
    const buildings = await loadList('buildings');
    const nodeTypes = await loadList('node_types');
    await buildNodeForm(fieldsContainer, config, initialData, extraData, buildings, nodeTypes);
} else if (type === 'equipment') {
    await buildEquipmentDossier(fieldsContainer, config, initialData, extraData);
}

// === Валидация дубликатов ===
// === Валидация дубликатов ===
const excludeId = (initialData && initialData.id) ? initialData.id : null;
const groupId   = (initialData && initialData.group_id) ? initialData.group_id : null;
let nodeId = null;
let whId   = null;
if (extraData?.location_type === 'warehouse') {
    whId = extraData.warehouse_id || null;
} else {
    nodeId = relatedId || extraData?.node_id || null;
}
if (typeof setupEquipmentValidation === 'function') {
    setupEquipmentValidation(fieldsContainer, excludeId, groupId, nodeId, whId);
}

    // Скрытые поля
    const hiddenContainer = document.getElementById('addFormHiddenFields');
    if (hiddenContainer) {
        hiddenContainer.innerHTML = '';
        if (type === 'equipment') {
            const isWarehouse = extraData?.location_type === 'warehouse';
            if (isWarehouse) {
                const whHidden = document.createElement('input');
                whHidden.type = 'hidden';
                whHidden.name = 'warehouse_id';
                whHidden.value = extraData.warehouse_id || '';
                hiddenContainer.appendChild(whHidden);
            } else {
                if (relatedId && !initialData) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'id_node';
                    hidden.value = relatedId;
                    hiddenContainer.appendChild(hidden);
                } else if (extraData?.node_id && !initialData) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'id_node';
                    hidden.value = extraData.node_id;
                    hiddenContainer.appendChild(hidden);
                }
                if (initialData && initialData.id_node) {
                    if (!hiddenContainer.querySelector('input[name="id_node"]')) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'id_node';
                        hidden.value = initialData.id_node;
                        hiddenContainer.appendChild(hidden);
                    }
                }
            }
        }
        if (initialData) {
            const entityId = initialData.id || initialData.id_node;
            if (entityId && !hiddenContainer.querySelector('input[name="id"]')) {
                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'id';
                hiddenId.value = entityId;
                hiddenContainer.appendChild(hiddenId);
            }
        }
    }
    if (type === 'node' && initialData) {
        if (initialData.id_location) {
            const hiddenLoc = document.createElement('input');
            hiddenLoc.type = 'hidden';
            hiddenLoc.name = 'id_location';
            hiddenLoc.value = initialData.id_location;
            hiddenContainer.appendChild(hiddenLoc);
        }
        if (initialData.building_id) {
            const hiddenB = document.createElement('input');
            hiddenB.type = 'hidden';
            hiddenB.name = 'building_id';
            hiddenB.value = initialData.building_id;
            hiddenContainer.appendChild(hiddenB);
        }
        if (initialData.node_type_id) {
            const hiddenN = document.createElement('input');
            hiddenN.type = 'hidden';
            hiddenN.name = 'node_type_id';
            hiddenN.value = initialData.node_type_id;
            hiddenContainer.appendChild(hiddenN);
        }
    }

    showModal(modal);
}

function closeAddForm(force = false) {
    const modal = document.getElementById('universalAddModal');
    if (!modal) return;

    const state = window.AppState;
    const isStack = state.currentExtraData?.force_stack;

    // Если установлен флаг пропуска подтверждения или передан force, игнорируем проверки
    const skipConfirm = state.skipCloseConfirmation || force;

    if (isStack) {
        if (!skipConfirm) {
            const currentData = {
                hostname: document.getElementById('stack-hostname')?.value.trim() || '',
                ip_address_id: document.getElementById('stack-ip')?.value || '',
                vendor_id: document.getElementById('stack-vendor')?.value || '',
                annotation: document.getElementById('stack-note')?.value || ''
            };
            const initialData = state.currentStackInitialData || {};
            const hasChanges = Object.keys(currentData).some(key => currentData[key] !== (initialData[key] || ''))
        }

        // Сброс формы стека и обновление таблицы
        if (typeof resetStackFormFields === 'function') resetStackFormFields();
        const nodeId = state.currentStackNodeId || state.currentExtraData?.node_id;
        if (nodeId) {
            if (typeof refreshNodeEquipment === 'function') refreshNodeEquipment(nodeId);
            if (typeof refreshSingleNode === 'function') refreshSingleNode(nodeId);
        }
    } else {
        if (!skipConfirm) {
            const form = document.getElementById('universalAddForm');
            if (form) {
                const inputs = form.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
                let hasChanges = false;
                inputs.forEach(el => {
                    if (el.type === 'checkbox' || el.type === 'radio') return;
                    if (el.value && el.value.trim() !== '') hasChanges = true;
                });
            }
        }
    }

    modal.classList.remove('visible');
    window.AppState.currentLocationSelect = null;
    window.AppState.currentStackInitialData = null;
    window.AppState.skipCloseConfirmation = false; // сбрасываем флаг
}

function initModalExpandButton(modal, type) {
    const titleEl = document.getElementById('addFormTitle');
    if (!titleEl) return;

    const existingRow = titleEl.parentElement;
    if (existingRow && existingRow.classList.contains('modal-title-row')) {
        existingRow.parentNode.insertBefore(titleEl, existingRow);
        existingRow.remove();
    }

    const titleRow = document.createElement('div');
    titleRow.className = 'modal-title-row';
    titleEl.parentNode.insertBefore(titleRow, titleEl);
    titleRow.appendChild(titleEl);

    const expandBtn = document.createElement('button');
    expandBtn.id = 'modalExpandBtn';
    expandBtn.type = 'button';
    expandBtn.className = 'modal-expand-btn';
    expandBtn.innerHTML = '⛶';
    expandBtn.title = 'Расширить форму';
    titleRow.appendChild(expandBtn);

    const modalContent = modal.querySelector('.modal-content');
    expandBtn.addEventListener('click', () => {
        if (!modalContent) return;
        modalContent.classList.toggle('wide');
        const isWide = modalContent.classList.contains('wide');
        expandBtn.innerHTML = isWide ? '✕' : '⛶';
        expandBtn.title = isWide ? 'Свернуть форму' : 'Расширить форму';

        if (type === 'equipment') {
            const annotationItem = modalContent.querySelector('.annotation-item');
            if (annotationItem) {
                annotationItem.style.gridColumn = isWide ? 'span 2' : '';
            }
        }
    });
}

// ======================== Отправка универсальной формы ========================
document.addEventListener('DOMContentLoaded', () => {
    const universalForm = document.getElementById('universalAddForm');
if (universalForm) {
    

    universalForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
if (submitBtn) submitBtn.disabled = true;

            // Блокируем основную форму, если открыта модалка устройства стека
if (document.getElementById('addStackDeviceModal')?.classList.contains('visible')) {
    return;
}
            const state = window.AppState;
            const macDuplicateMsg = this.querySelector('.mac-duplicate-msg');
            if (macDuplicateMsg && macDuplicateMsg.textContent.trim() !== '') {
                showToast('Исправьте MAC‑адрес перед сохранением', 'warning');
                return;
            }
            const config = state.config[state.currentFormType];
            if (!config) return;
            const formData = new FormData(this);

            this.querySelectorAll('.searchable-select select').forEach(select => {
                if (select.value && select.value !== '__add_new__') {
                    formData.set(select.name, select.value);
                }
            });

            const poeCheckbox = this.querySelector('input[name="Poe"]');
            if (poeCheckbox) {
                formData.set('Poe', poeCheckbox.checked ? '1' : '0');
            } else {
                formData.set('Poe', '0');
            }

            if (state.currentFormType === 'equipment') {
                // === Обработка формы устройства стека (stack_mode) ===
                if (state.currentExtraData?.stack_mode) {
                    // Собираем модули
                    const modulesContainer = document.getElementById('modules-container');
                    const modules = {};
                    if (modulesContainer) {
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
                    }
                    this.querySelector('input[name="modules"]')?.remove();
                    const modulesInput = document.createElement('input');
                    modulesInput.type = 'hidden';
                    modulesInput.name = 'modules';
                    modulesInput.value = JSON.stringify(modules);
                    this.appendChild(modulesInput);

                    // Собираем сервисы
                    const services = {};
                    document.querySelectorAll('#services-grid .service-card').forEach(card => {
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
                    this.querySelector('input[name="services"]')?.remove();
                    const servicesInput = document.createElement('input');
                    servicesInput.type = 'hidden';
                    servicesInput.name = 'services';
                    servicesInput.value = JSON.stringify(services);
                    this.appendChild(servicesInput);

                    const fd = new FormData(this);
                    // Добавляем скрытые/заблокированные поля
                    fd.append('ip_address', state.currentExtraData.stack_ip || '');
                    fd.append('hostname', state.currentExtraData.stack_hostname || '');
                    fd.append('vendor_id', state.currentExtraData.stack_vendor_id || '');
                    fd.append('id_node', state.currentExtraData.node_id || '');
                    fd.append('group_id', state.currentExtraData.group_id || '');

                    // Если редактирование существующего устройства – добавляем id
                    if (state.currentInitialData?.id) {
                        fd.append('id', state.currentInitialData.id);
                    }

                    fetch(state.currentInitialData?.id ? '?ajax=update_equipment' : '?ajax=add_equipment', {
    method: 'POST',
    body: fd
})
.then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            if (typeof closeStackDeviceForm === 'function') {
                                closeStackDeviceForm();
                            }
                            if (typeof refreshStackDeviceList === 'function') {
                                refreshStackDeviceList();
                            }
                            showToast(`Устройство ${state.currentInitialData?.id ? 'обновлено' : 'добавлено'} в стек`, 'success');
                        } else {
                            showToast(data.error || data.message || 'Ошибка', 'error');
                        }
                    })
                    .catch(() => showToast('Ошибка сети', 'error'))
                    .finally(() => { if (submitBtn) submitBtn.disabled = false; });
                    return;
                }

                // Сохранение основной формы стека (если открыта секция стека)
                // Сохранение основной формы стека (если активен режим force_stack)
const stackContainer = document.getElementById('stack-form-container');
if (stackContainer && state.currentExtraData?.force_stack) {
    const groupId = window.AppState.currentStackGroupId;
    const hostname = document.getElementById('stack-hostname')?.value.trim() || '';
    const ipId = document.getElementById('stack-ip')?.value || '';
    const vendorId = document.getElementById('stack-vendor')?.value || '';
    const annotation = document.getElementById('stack-note')?.value || '';

    if (!hostname || !ipId) {
        showToast('Заполните IP-адрес и имя хоста', 'warning');
        return;
    }

    // Проверка ошибок дубликатов в секции стека
    if (typeof validateAllFields === 'function' && !validateAllFields(stackContainer)) {
        return;
    }

    const fd2 = new FormData();
    if (groupId) fd2.append('group_id', groupId);
    fd2.append('hostname', hostname);
    fd2.append('ip_address_id', ipId);
    fd2.append('annotation', annotation);
    fd2.append('vendor_id', vendorId);

     fetch('?ajax=save_stack_group', { method: 'POST', body: fd2 })
        .then(r => r.json())
        .then(d => {
           if (d.success) {
    window.AppState.skipCloseConfirmation = true;   // <-- добавить
    closeAddForm();                                   // можно не передавать force
    if (typeof refreshNodeEquipment === 'function') {
        refreshNodeEquipment(state.currentRelatedId || state.currentInitialData?.id_node);
    }
    showToast('Стек сохранён', 'success');
} else {
                showToast(d.error || 'Ошибка', 'error');
            }
        })
        .catch(() => showToast('Ошибка сети', 'error'))
    .finally(() => { if (submitBtn) submitBtn.disabled = false; });
    return;
}
                // Обычное оборудование: модули, сервисы
                const modules = {};
                document.querySelectorAll('#modules-container .module-column[data-module-type]').forEach(col => {
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

                const services = {};
                document.querySelectorAll('#services-grid .service-card').forEach(card => {
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

            if (state.currentInitialData) {
                const entityId = state.currentInitialData.id || state.currentInitialData.id_node;
                if (entityId) formData.append('id', entityId);
            }

            const kyInput = this.querySelector('input[name="KY_number"]');
            if (kyInput) {
                const val = kyInput.value.trim();
                if (val !== '' && !/^\d+$/.test(val)) {
                    alert('Номер КУ должен содержать только цифры');
                    return;
                }
            }

            // Проверка дубликатов
if (state.currentFormType === 'equipment') {
    if (typeof validateAllFields === 'function' && !validateAllFields(this)) {
        return;
    }
}
// Сбор LLDP-соседей
if (state.currentFormType === 'equipment' || state.currentExtraData?.force_stack) {
    const lldpRows = document.querySelectorAll('.lldp-neighbors-table tbody tr');
    if (lldpRows.length > 0) {
        const lldpNeighbors = [];
        lldpRows.forEach(row => {
            const local = row.querySelector('.lldp-local-port')?.value.trim();
            const neighborPort = row.querySelector('.lldp-neighbor-port')?.value.trim();
            const neighborHost = row.querySelector('.lldp-neighbor-hostname')?.value.trim();
            if (local && neighborPort && neighborHost) {
                lldpNeighbors.push({
                    local_interface: local,
                    neighbor_interface: neighborPort,
                    neighbor_hostname: neighborHost
                });
            }
        });
        formData.append('lldp_neighbors', JSON.stringify(lldpNeighbors));
    }
}
// Сбор выбранных шкафов (для формы узла)
if (state.currentFormType === 'node') {
    document.querySelectorAll('#racks-tile-group .rack-tile-checkbox:checked').forEach(cb => {
        formData.append('rack_ids[]', cb.value);
    });
}

            fetch(config.url, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (state.currentFormType === 'equipment') {
                            const hostname = document.querySelector('#universalAddForm input[name="hostname"]')?.value.trim();
                            if (hostname) showToast(`Добавлено оборудование ${hostname}`, 'success');
                            else showToast('Добавлено новое оборудование', 'success');
                        } else if (state.currentFormType === 'node') {
    // Собираем номер КУ и название здания из формы
    const kyInput = document.querySelector('#universalAddForm input[name="KY_number"]');
    const kyNumber = kyInput ? kyInput.value.trim() : '';
    const buildingSelect = document.querySelector('#universalAddForm select[name="building_id"]');
    let buildingName = '';
    if (buildingSelect && buildingSelect.selectedIndex > 0) {
        const selectedOption = buildingSelect.options[buildingSelect.selectedIndex];
        // Исключаем служебные пункты «-- не выбрано --» и «Добавить...»
        if (selectedOption.value !== '' && selectedOption.value !== '__add_new__') {
            buildingName = selectedOption.textContent;
        }
    }
    // fallback – если форма открыта из боковой панели и здание не менялось
    if (!buildingName && state.currentExtraData?.building_name) {
        buildingName = state.currentExtraData.building_name;
    }

    let message;
    if (kyNumber) {
        message = `Узел КУ-${kyNumber}`;
    } else {
        message = 'Узел';
    }
    message += state.currentInitialData ? ' обновлён' : ' добавлен';
    if (buildingName) {
        message += ` в здание ${buildingName}`;
    }
    showToast(message, 'success');
}
                        closeAddForm();
    // Существующие обновления оборудования/узла
    if (state.currentFormType === 'equipment' && state.currentExtraData?.location_type === 'warehouse') {
        if (typeof loadTableData === 'function') loadTableData();
    } else if (state.currentFormType === 'equipment') {
        const nodeId = state.currentEquipmentNodeId || data.id_node;
        if (nodeId) {
            if (typeof refreshSingleNode === 'function') refreshSingleNode(nodeId);
            if (typeof refreshNodeEquipment === 'function') refreshNodeEquipment(nodeId);
        }
    } else if (state.currentFormType === 'node') {
        const newNodeId = data.id_node || data.id || (state.currentInitialData ? (state.currentInitialData.id_node || state.currentInitialData.id) : null);
        if (newNodeId) {
            if (typeof refreshSingleNode === 'function') refreshSingleNode(newNodeId);
            if (typeof refreshNodeEquipment === 'function') refreshNodeEquipment(newNodeId);
        }

        // === НОВОЕ: фильтруем таблицу по зданию, если узел добавлялся из конкретного здания ===
        if (state.currentExtraData?.building_id && typeof filterByBuilding === 'function') {
    filterByBuilding(state.currentExtraData.building_id);
                    }
                    if (typeof refreshBuildingsSidebar === 'function') {
                        refreshBuildingsSidebar();
                    }
                        }
                    } else {
                        const message = data.warning || data.error || data.message || 'Неизвестная ошибка';
                        const type = data.warning ? 'warning' : 'error';
                        showToast(message, type);
                    }
                })
                .finally(() => { if (submitBtn) submitBtn.disabled = false; });
                
        });
    }
});
window.refreshStackDeviceList = refreshStackDeviceList;

// Открыть модальное окно импорта LLDP (автоопределение вендора)
function openLLDPImport() {
    const modal = document.getElementById('lldpImportModal');
    if (!modal) return;
    document.getElementById('lldpOutput').value = '';
    document.getElementById('lldpResult').innerHTML = '';
    showModal(modal);
}

function closeLLDPImport() {
    const modal = document.getElementById('lldpImportModal');
    if (modal) modal.classList.remove('visible');
}

// Обработчик анализа
document.addEventListener('click', function(e) {
    if (e.target.id === 'analyzeLLDPBtn') {
        const output = document.getElementById('lldpOutput').value;
        const resultDiv = document.getElementById('lldpResult');

        if (!output.trim()) {
            resultDiv.innerHTML = '<div class="no-equipment">Вставьте вывод команды</div>';
            return;
        }

        // Автоопределение вендора
        const vendor = detectLLDPVendor(output);
        if (!vendor) {
            resultDiv.innerHTML = '<div class="no-equipment">Не удалось определить производителя. Проверьте вывод или выберите вручную.</div>';
            return;
        }

        const result = parseLLDPOutput(output, vendor);
        if (result.length === 0) {
            resultDiv.innerHTML = '<div class="no-equipment">Соседей не найдено</div>';
            return;
        }

        let html = '<table style="width:100%;"><thead><tr><th>Локальный порт</th><th></th><th>Порт соседа</th><th>Имя соседа</th></tr></thead><tbody>';
        result.forEach(r => {
            html += `<tr>
                <td>${escapeHtml(r.local_interface)}</td>
                <td style="text-align:center;">↔</td>
                <td>${escapeHtml(r.neighbor_interface)}</td>
                <td><strong>${escapeHtml(r.neighbor_hostname)}</strong></td>
            </tr>`;
        });
        html += '</tbody></table>';
        resultDiv.innerHTML = html;
    }
});