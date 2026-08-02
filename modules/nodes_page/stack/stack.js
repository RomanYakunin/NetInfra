// modules/nodes_page/stack/stack.js

window.initStackForm = function() {
    const ipSelect = document.getElementById('stack-ip');
    const vendorSelect = document.getElementById('stack-vendor');
    const hostnameInput = document.getElementById('stack-hostname');
    let currentStackHostname = hostnameInput.value.trim();
    let currentStackIpId = ipSelect?.value || null;

    if (window.AppState.currentExtraData && window.AppState.currentExtraData.stack_group_id) {
    window.AppState.currentStackGroupId = window.AppState.currentExtraData.stack_group_id;
    fetch(`?ajax=get_stack_info&group_id=${window.AppState.currentStackGroupId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                hostnameInput.value = data.hostname || '';
                if (ipSelect.searchableInstance) {
                    ipSelect.searchableInstance.select.value = data.ip_address_id || '';
                    ipSelect.searchableInstance.syncInputWithSelect();
                }
                if (vendorSelect.searchableInstance) {
                    vendorSelect.searchableInstance.select.value = data.vendor_id || '';
                    vendorSelect.searchableInstance.syncInputWithSelect();
                }
                document.getElementById('stack-note').value = data.annotation || '';
                currentStackHostname = data.hostname || '';
                currentStackIpId = data.ip_address_id || null;
            } else {
                window.AppState.currentStackGroupId = null;
            }
            // Сохраняем начальные данные сразу после загрузки
            window.AppState.currentStackInitialData = {
                hostname: hostnameInput.value.trim(),
                ip_address_id: ipSelect?.value || '',
                vendor_id: vendorSelect?.value || '',
                annotation: document.getElementById('stack-note')?.value || ''
            };
            refreshStackDeviceList();
        })
        .catch(() => {
            window.AppState.currentStackGroupId = null;
            window.AppState.currentStackInitialData = {};
            refreshStackDeviceList();
        });
} else {
    window.AppState.currentStackGroupId = null;
    window.AppState.currentStackInitialData = {
        hostname: '',
        ip_address_id: '',
        vendor_id: '',
        annotation: ''
    };
    refreshStackDeviceList();
}

    hostnameInput.addEventListener('input', () => { currentStackHostname = hostnameInput.value.trim(); });
    ipSelect.addEventListener('change', () => { currentStackIpId = ipSelect.value; });

    const devicesList = document.getElementById('stack-devices-list');
    if (devicesList) {
        devicesList.addEventListener('click', (e) => {
            if (e.target.closest('#add-stack-device-btn')) {
                const stackContainer = document.getElementById('stack-form-container');
                if (stackContainer && typeof validateAllFields === 'function') {
                    if (!validateAllFields(stackContainer)) {
                        return;
                    }
                }
                openStackDeviceForm();
            }
        });
    }
};

window.openStackDeviceForm = async function(existingDevice = null) {
    const hostname = document.getElementById('stack-hostname').value.trim();
    const ipId = document.getElementById('stack-ip').value;
    const vendorId = document.getElementById('stack-vendor').value;
    const nodeId = window.AppState.currentStackNodeId || window.AppState.currentExtraData?.node_id || '';
    const groupId = window.AppState.currentStackGroupId || '';

    if (!hostname || !ipId) {
        showToast('Сначала заполните IP-адрес и имя хоста', 'warning');
        return;
    }

    if (!groupId) {
        try {
            const fd = new FormData();
            fd.append('hostname', hostname);
            fd.append('ip_address_id', ipId);
            fd.append('vendor_id', vendorId);
            const resp = await fetch('?ajax=save_stack_group', { method: 'POST', body: fd });
            const grp = await resp.json();
            if (grp.success) {
                window.AppState.currentStackGroupId = grp.group_id;
            } else {
                showToast(grp.error || 'Ошибка создания группы стека', 'error');
                return;
            }
        } catch (e) {
            showToast('Ошибка сети при создании группы', 'error');
            return;
        }
    }

    const modal = document.getElementById('addStackDeviceModal');
    const form = document.getElementById('addStackDeviceForm');
    const fieldsContainer = document.getElementById('stackDeviceFormFields');
    const hiddenContainer = document.getElementById('stackDeviceFormHiddenFields');
    if (!modal || !form || !fieldsContainer) return;

    fieldsContainer.innerHTML = '';
    hiddenContainer.innerHTML = '';

    const hiddenFields = {
        ip_address: ipId,
        hostname: hostname,
        vendor_id: vendorId,
        id_node: nodeId,
        group_id: window.AppState.currentStackGroupId,
        stack_device: '1'
    };
    if (existingDevice) hiddenFields.id = existingDevice.id;

    Object.entries(hiddenFields).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        hiddenContainer.appendChild(input);
    });

    showModal(modal);

    try {
        await buildEquipmentDossier(fieldsContainer, window.AppState.config.equipment, existingDevice || null, {
            stack_mode: true,
            stack_ip: ipId,
            stack_hostname: hostname,
            stack_vendor_id: vendorId
        });

        if (vendorId) {
            const modelSelect = fieldsContainer.querySelector('select[name="model_id"]');
            if (modelSelect) {
                await reloadModelsForVendor(modelSelect, vendorId, existingDevice?.model_id || null);
            }
        }

        if (!fieldsContainer.querySelector('input[name="Slot"]')) {
            const slotDiv = document.createElement('div');
            slotDiv.className = 'dossier-item';
            slotDiv.innerHTML = `<div class="label">Слот</div><div class="value"><input type="number" name="Slot" class="dossier-input" min="0" value="${existingDevice?.Slot || ''}"></div>`;
            const dossierGrid = fieldsContainer.querySelector('.dossier-grid');
            if (dossierGrid) dossierGrid.prepend(slotDiv);
            else fieldsContainer.prepend(slotDiv);
        }

        const excludeId = existingDevice?.id || null;
const groupId   = window.AppState.currentStackGroupId || null;
const nodeId    = window.AppState.currentStackNodeId || window.AppState.currentExtraData?.node_id || null;
if (typeof setupEquipmentValidation === 'function') {
    setupEquipmentValidation(fieldsContainer, excludeId, groupId, nodeId, null);
}

        const title = modal.querySelector('.modal-title') || modal.querySelector('h2, h3');
        if (title) {
            title.textContent = existingDevice
                ? `Редактирование устройства в стеке (${hostname})`
                : `Добавление устройства в стек (${hostname})`;
        }
    } catch (err) {
        console.error('Ошибка построения формы стека:', err);
        showToast('Ошибка загрузки формы', 'error');
    }
};

window.closeStackDeviceForm = function() {
    const modal = document.getElementById('addStackDeviceModal');
    if (modal) {
        modal.classList.remove('visible');
        // Полный сброс: поисковые селекты уничтожаются вместе с обёртками,
        // иначе при повторном открытии формы они дублируются
        if (typeof resetModalForm === 'function') resetModalForm(modal);
    }
    // Поля стека строятся заново при каждом открытии
    const fields = document.getElementById('stackDeviceFormFields');
    if (fields) fields.innerHTML = '';
    const hidden = document.getElementById('stackDeviceFormHiddenFields');
    if (hidden) hidden.innerHTML = '';

    // Возвращаем на передний план форму узла — она остаётся открытой
    const mainModal = document.getElementById('universalAddModal');
    if (mainModal && !mainModal.classList.contains('visible')) {
        showModal(mainModal);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('addStackDeviceForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const formData = new FormData(this);
            const macInput = this.querySelector('[name="mac_address"]');
            if (macInput) macInput.value = formatMacAddress(macInput.value);

            if (typeof validateAllFields === 'function' && !validateAllFields(this)) {
                return;
            }

            try {
                const id = formData.get('id');
                const url = id ? '?ajax=update_equipment' : '?ajax=add_equipment';
                const res = await fetch(url, { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    if (!window.AppState.currentStackGroupId && data.group_id) {
                        window.AppState.currentStackGroupId = data.group_id;
                    }
                    closeStackDeviceForm();
                    if (typeof refreshStackDeviceList === 'function') {
                        refreshStackDeviceList();
                    }
                    showToast(id ? 'Устройство обновлено' : 'Устройство добавлено в стек', 'success');
                } else {
                    showToast(data.error || 'Ошибка сохранения', 'error');
                }
            } catch (e) {
                showToast('Ошибка сети', 'error');
            }
        });
    }
});

window.refreshStackDeviceList = function() {
    const container = document.getElementById('stack-devices-list');
    if (!container) return;

    const groupId = window.AppState.currentStackGroupId;
    if (!groupId) {
        container.innerHTML = `<div class="modules-empty-message">Нет устройств в стеке</div>
                               <div class="add-module-tile" id="add-stack-device-btn">+ Добавить устройство</div>`;
        return;
    }

    fetch(`?ajax=get_stack_devices&group_id=${groupId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                container.innerHTML = `<div class="modules-empty-message">${data.error || 'Ошибка загрузки'}</div>
                                       <div class="add-module-tile" id="add-stack-device-btn">+ Добавить устройство</div>`;
                return;
            }
            const devices = data.devices || [];
            let html = '';
            devices.forEach(dev => {
                const slotDisplay = (dev.Slot != null) ? dev.Slot : '?';
                html += `
                <div class="stack-device-tile">
                    <div class="stack-device-info">
                        <div class="device-name">Слот ${escapeHtml(slotDisplay)}</div>
                        <div class="device-details">
                            ${escapeHtml(dev.model_name || '--')} | ${escapeHtml(dev.serial_number || '--')} | ${escapeHtml(dev.mac_address || '--')}
                        </div>
                    </div>
                    <div class="stack-device-actions">
                        <button type="button" class="btn small secondary" data-action="edit" data-id="${dev.id}">✏️</button>
                        <button type="button" class="btn small danger" data-action="delete" data-id="${dev.id}">✕</button>
                    </div>
                </div>`;
            });
            if (devices.length === 0) html = `<div class="modules-empty-message">Нет устройств в стеке</div>`;
            html += `<div class="add-module-tile" id="add-stack-device-btn">+ Добавить устройство</div>`;
            container.innerHTML = html;

            container.querySelectorAll('[data-action="edit"]').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const id = btn.dataset.id;
                    try {
                        const res = await fetch(`?ajax=get_equipment_item&id=${id}`);
                        const dev = await res.json();
                        if (dev && !dev.error) {
                            openStackDeviceForm(dev);
                        } else {
                            showToast('Ошибка загрузки устройства', 'error');
                        }
                    } catch (e) {
                        showToast('Ошибка сети', 'error');
                    }
                });
            });

            container.querySelectorAll('[data-action="delete"]').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const id = btn.dataset.id;
                    const deviceTiles = container.querySelectorAll('.stack-device-tile');
                    const isLast = deviceTiles.length <= 1;

                    if (isLast) {
                        const overlay = document.createElement('div');
                        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;display:flex;justify-content:center;align-items:center;';
                        const dialog = document.createElement('div');
                        dialog.style.cssText = 'background:var(--bg-card);padding:2rem;border-radius:8px;max-width:400px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.3);';
                        dialog.innerHTML = `
                            <h3 style="margin-bottom:1rem;">Стек будет полностью удалён</h3>
                            <p style="margin-bottom:1.5rem;">Это действие удалит стек. Продолжить?</p>
                            <button id="confirmDeleteStackBtn" class="btn danger" style="margin-right:0.5rem;">Да</button>
                            <button id="cancelDeleteStackBtn" class="btn secondary">Нет</button>
                        `;
                        overlay.appendChild(dialog);
                        document.body.appendChild(overlay);

                        document.getElementById('cancelDeleteStackBtn').onclick = () => overlay.remove();
                        document.getElementById('confirmDeleteStackBtn').onclick = async () => {
                            overlay.remove();
                            await performDeleteDevice(id);
                        };
                    } else {
                        await performDeleteDevice(id);
                    }
                });
            });
        });
};

async function performDeleteDevice(deviceId) {
    try {
        const formData = new FormData();
        formData.append('id', deviceId);
        const res = await fetch('?ajax=delete_stack_device', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            const mac = data.mac_address || '—';
            const stackName = data.stack_hostname || 'стек';
            showToast(`Устройство (${mac}) выведено из стека (${stackName})`, 'success');
            refreshStackDeviceList();
        } else {
            showToast(data.error || 'Ошибка удаления', 'error');
        }
    } catch (e) {
        showToast('Ошибка сети', 'error');
    }
}

window.resetStackFormFields = function() {
    const hostname = document.getElementById('stack-hostname');
    if (hostname) hostname.value = '';

    const ipSelect = document.getElementById('stack-ip');
    if (ipSelect && ipSelect.searchableInstance) {
        ipSelect.searchableInstance.select.value = '';
        ipSelect.searchableInstance.syncInputWithSelect();
    }

    const vendorSelect = document.getElementById('stack-vendor');
    if (vendorSelect && vendorSelect.searchableInstance) {
        vendorSelect.searchableInstance.select.value = '';
        vendorSelect.searchableInstance.syncInputWithSelect();
    }

    const note = document.getElementById('stack-note');
    if (note) note.value = '';

    window.AppState.currentStackGroupId = null;
    if (typeof refreshStackDeviceList === 'function') {
        refreshStackDeviceList();
    }
};