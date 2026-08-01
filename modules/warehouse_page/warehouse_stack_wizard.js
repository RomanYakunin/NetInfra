// modules/warehouse_table/warehouse_stack_wizard.js

// Глобальные переменные
let wizardState = {
    step: 1,
    selectedEquipment: [],
    slots: {},
    commonIp: null,
    commonHostname: '',
    targetNodeId: null
};

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('buildStackBtn');
    if (btn) {
        btn.addEventListener('click', warehouseBuildStack);
    }

    document.getElementById('wizard-next-1')?.addEventListener('click', wizardNext1);
    document.getElementById('wizard-back-2')?.addEventListener('click', () => goToStep(1));
    document.getElementById('wizard-next-2')?.addEventListener('click', wizardNext2);
    document.getElementById('wizard-back-3')?.addEventListener('click', () => goToStep(2));
    document.getElementById('wizard-finish')?.addEventListener('click', wizardFinish);

    // Поиск на шаге 1
    const searchInput = document.getElementById('wizard-search');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            loadEquipmentForWizard(searchInput.value.trim());
        }, 400));
    }
});

// Открытие мастера
function warehouseBuildStack() {
    const modal = document.getElementById('stackWizardModal');
    if (!modal) return;

    wizardState = {
        step: 1,
        selectedEquipment: [],
        slots: {},
        commonIp: null,
        commonHostname: '',
        targetNodeId: null
    };

    loadEquipmentForWizard('');
    goToStep(1);
    showModal(modal);
}

// Закрытие мастера
function closeStackWizard() {
    const modal = document.getElementById('stackWizardModal');
    if (modal) modal.classList.remove('visible');
    wizardState = {};
}

// Анимация перехода
function goToStep(step) {
    const steps = document.querySelector('.wizard-steps');
    if (!steps) return;
    wizardState.step = step;
    const translateX = -(step - 1) * 33.33;
    steps.style.transform = `translateX(${translateX}%)`;
}

// ===================== ШАГ 1: ТАБЛИЦА С ГРУППИРОВКОЙ =====================
async function loadEquipmentForWizard(searchQuery = '') {
    const container = document.getElementById('wizard-equipment-list');
    if (!container) return;
    container.innerHTML = '<span class="load-indicator loading"></span> Загрузка...';

    try {
        const resp = await fetch(`?ajax=get_warehouse_equipment_for_stack&search=${encodeURIComponent(searchQuery)}`);
        const data = await resp.json();
        if (data.success && data.groups) {
            renderGroupedTable(data.groups, container);
        } else {
            container.innerHTML = '<div class="no-equipment">Нет устройств</div>';
        }
    } catch (e) {
        container.innerHTML = '<div class="no-equipment">Ошибка загрузки</div>';
    }
}

function renderGroupedTable(groups, container) {
    let html = '';
    groups.forEach((group, idx) => {
        const groupId = `stack-group-${idx}`;
        html += `
        <div class="warehouse-group-header" data-group-id="${groupId}">
            <span class="expand-arrow">▶</span>
            <strong>${group.device_type_name || '—'}</strong> | 
            ${group.vendor_name || '—'} | 
            ${group.model_name || '—'} 
            (${group.items.length} шт.)
        </div>
        <div class="warehouse-group-body" id="${groupId}" style="display:none;">
            <table style="width:100%; font-size:0.85rem;">
                <tbody>`;
        group.items.forEach(item => {
            html += `
                <tr>
                    <td style="width:30px;"><input type="checkbox" class="wizard-checkbox" value="${item.id}"></td>
                    <td>${item.hostname || '—'}</td>
                    <td>${item.serial_number || '—'}</td>
                    <td style="font-family:monospace;">${item.mac_address || '—'}</td>
                </tr>`;
        });
        html += '</tbody></table></div>';
    });
    container.innerHTML = html;

    // Обработчики раскрытия групп
    container.querySelectorAll('.warehouse-group-header').forEach(header => {
        header.addEventListener('click', function() {
            const groupId = this.dataset.groupId;
            const body = document.getElementById(groupId);
            const arrow = this.querySelector('.expand-arrow');
            if (body) {
                const isHidden = body.style.display === 'none';
                body.style.display = isHidden ? '' : 'none';
                if (arrow) arrow.style.transform = isHidden ? 'rotate(90deg)' : '';
            }
        });
    });
}

// Переход с шага 1 на шаг 2
function wizardNext1() {
    const checked = document.querySelectorAll('.wizard-checkbox:checked');
    if (checked.length === 0) {
        alert('Выберите хотя бы одно устройство');
        return;
    }
    const selected = [];
    checked.forEach(cb => {
        const row = cb.closest('tr');
        const cells = row.querySelectorAll('td');
        selected.push({
            id: parseInt(cb.value),
            hostname: cells[1]?.textContent || '',
            serial: cells[2]?.textContent || '',
            mac: cells[3]?.textContent || ''
        });
    });
    wizardState.selectedEquipment = selected;
    renderStep2();
    goToStep(2);
}

// ===================== ШАГ 2: НАСТРОЙКА СЛОТОВ, IP, HOSTNAME =====================
async function renderStep2() {
    const configContainer = document.getElementById('wizard-config-list');
    if (!configContainer) return;

    document.getElementById('wizard-ip-warning').style.display = 'none';
    document.getElementById('wizard-hostname-warning').style.display = 'none';
    document.getElementById('wizard-ip-select').innerHTML = '';
    document.getElementById('wizard-hostname').value = '';

    configContainer.innerHTML = wizardState.selectedEquipment.map(eq => `
        <div class="form-group">
            <label>Слот для ${eq.hostname || eq.id}</label>
            <input type="number" class="wizard-slot-input" data-equip-id="${eq.id}" min="1" value="${wizardState.slots[eq.id] || ''}">
        </div>
    `).join('');

    try {
        const resp = await fetch('?ajax=get_free_ips');
        const ips = await resp.json();
        const select = document.getElementById('wizard-ip-select');
        select.innerHTML = '<option value="">-- выберите IP --</option>';
        ips.forEach(ip => {
            select.appendChild(new Option(ip.ip_address, ip.Id));
        });
        if (wizardState.commonIp) select.value = wizardState.commonIp;
    } catch (e) {}

    document.getElementById('wizard-ip-select').addEventListener('change', function() {
        const ipId = this.value;
        if (!ipId) return;
        fetch(`?ajax=check_unique&field=ip_address&value=${ipId}`)
            .then(r => r.json())
            .then(data => {
                const warn = document.getElementById('wizard-ip-warning');
                if (data.exists) {
                    warn.style.display = 'block';
                    warn.textContent = 'Этот IP-адрес уже используется';
                } else {
                    warn.style.display = 'none';
                }
            });
    });

    document.getElementById('wizard-hostname').addEventListener('input', debounce(function() {
        const hostname = this.value.trim();
        if (!hostname) return;
        fetch(`?ajax=check_unique&field=hostname&value=${encodeURIComponent(hostname)}`)
            .then(r => r.json())
            .then(data => {
                const warn = document.getElementById('wizard-hostname-warning');
                if (data.exists) {
                    warn.style.display = 'block';
                    warn.textContent = `Такое имя хоста уже используется (IP: ${data.ip_address || '—'})`;
                } else {
                    warn.style.display = 'none';
                }
            });
    }, 500));

    if (wizardState.commonHostname) {
        document.getElementById('wizard-hostname').value = wizardState.commonHostname;
    }
}

// Переход с шага 2 на шаг 3
function wizardNext2() {
    const slotInputs = document.querySelectorAll('.wizard-slot-input');
    let valid = true;
    const slots = {};
    slotInputs.forEach(input => {
        const id = parseInt(input.dataset.equipId);
        const slot = parseInt(input.value);
        if (!slot || slot < 1) {
            valid = false;
            input.style.borderColor = 'var(--danger)';
        } else {
            input.style.borderColor = '';
            slots[id] = slot;
        }
    });
    if (!valid) {
        alert('Заполните корректно все слоты (число от 1)');
        return;
    }
    const ipSelect = document.getElementById('wizard-ip-select');
    const ipId = ipSelect.value;
    if (!ipId) {
        alert('Выберите IP-адрес');
        return;
    }
    const hostname = document.getElementById('wizard-hostname').value.trim();
    if (!hostname) {
        alert('Введите имя хоста');
        return;
    }
    wizardState.slots = slots;
    wizardState.commonIp = ipId;
    wizardState.commonHostname = hostname;

    loadNodesForWizard();
    goToStep(3);
}

// ===================== ШАГ 3: ВЫБОР КУ =====================
async function loadNodesForWizard() {
    const select = document.getElementById('wizard-node-select');
    if (!select) return;
    select.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const resp = await fetch('?ajax=get_nodes_list');
        const nodes = await resp.json();
        select.innerHTML = '<option value="">-- выберите КУ --</option>';
        nodes.forEach(node => {
            const display = node.KY_number ? `КУ-${node.KY_number} (${node.location_display || ''})` : `Узел ${node.id_node}`;
            select.appendChild(new Option(display, node.id_node));
        });
        const addOpt = new Option('Добавить...', '__add_new__');
        addOpt.style.fontStyle = 'italic';
        select.appendChild(addOpt);

        select.addEventListener('change', function() {
            if (this.value === '__add_new__') {
                this.value = '';
                if (typeof openNodeAddForm === 'function') {
                    openNodeAddForm();
                    const checkNewNode = setInterval(async () => {
                        const modal = document.getElementById('universalAddModal');
                        if (!modal || !modal.classList.contains('visible')) {
                            clearInterval(checkNewNode);
                            await loadNodesForWizard();
                        }
                    }, 500);
                } else {
                    alert('Функция добавления узла недоступна');
                }
            }
        });

        if (wizardState.targetNodeId) select.value = wizardState.targetNodeId;
        new SearchableSelect(select);
    } catch (e) {
        select.innerHTML = '<option value="">-- ошибка --</option>';
    }
}

// Финальное действие
function wizardFinish() {
    const nodeSelect = document.getElementById('wizard-node-select');
    const nodeId = nodeSelect.value;
    if (!nodeId || nodeId === '__add_new__') {
        alert('Выберите коммутационный узел');
        return;
    }
    const formData = new FormData();
    formData.append('action', 'build_stack');
    // formData.append('warehouse_id', currentWarehouse || 'all');
    formData.append('node_id', nodeId);
    formData.append('ip_address', wizardState.commonIp);
    formData.append('hostname', wizardState.commonHostname);
    formData.append('equipment', JSON.stringify(wizardState.selectedEquipment.map(eq => ({
        id: eq.id,
        slot: wizardState.slots[eq.id]
    }))));

    fetch('?ajax=build_stack', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeStackWizard();
                showToast('Стек собран и перемещён', 'success');
                if (typeof loadTableData === 'function') loadTableData();
            } else {
                alert('Ошибка: ' + (data.error || ''));
            }
        })
        .catch(err => alert('Ошибка сети'));
}

// Вспомогательная функция debounce
function debounce(fn, delay) {
    let timer;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}