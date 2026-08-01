// modules/nodes_page/stack_form/stack_device_form.js
// Форма-оверлей для добавления устройства в стек

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

async function loadModelsForStackDevice(vendorId, selectElement) {
    selectElement.innerHTML = '<option value="">-- загрузка --</option>';
    if (!vendorId) {
        selectElement.innerHTML = '<option value="">-- не выбрано --</option>';
        return;
    }
    try {
        const resp = await fetch(`?ajax=get_list_models&list=device_models&vendor_id=${vendorId}`);
        const data = await resp.json();
        const models = data.data || [];
        selectElement.innerHTML = '<option value="">-- выберите модель --</option>';
        models.forEach(m => selectElement.add(new Option(m.name, m.id)));
    } catch (e) {
        selectElement.innerHTML = '<option value="">-- ошибка загрузки --</option>';
    }
}

async function loadFirmwaresForStackDevice(selectElement) {
    selectElement.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const resp = await fetch('?ajax=get_list&list=firmwares');
        const data = await resp.json();
        const firmwares = data.data || [];
        selectElement.innerHTML = '<option value="">-- не выбрано --</option>';
        firmwares.forEach(fw => selectElement.add(new Option(fw.name, fw.id)));
    } catch (e) {
        selectElement.innerHTML = '<option value="">-- ошибка загрузки --</option>';
    }
}

async function loadCabinetsForStackDevice(selectElement) {
    selectElement.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const resp = await fetch('?ajax=get_list&list=cabinets');
        const data = await resp.json();
        const cabinets = data.data || [];
        selectElement.innerHTML = '<option value="">-- не выбрано --</option>';
        cabinets.forEach(cab => selectElement.add(new Option(cab.name, cab.id)));
    } catch (e) {
        selectElement.innerHTML = '<option value="">-- ошибка загрузки --</option>';
    }
}

function openStackDeviceForm(existingDevice = null, editIndex = null) {
    const vendorId = document.getElementById('stack-vendor')?.value || '';

    const overlay = document.createElement('div');
    overlay.className = 'stack-device-overlay';
    overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3100; display: flex; justify-content: center; align-items: center;';

    const formHtml = `
        <div class="stack-device-form">
            <h3>${existingDevice ? 'Редактировать' : 'Добавить'} устройство стека</h3>
            <div class="form-group">
                <label>Слот</label>
                <input type="number" id="dev-slot" min="0" value="${escapeHtml(existingDevice?.Slot || '')}">
            </div>
            <div class="form-group">
                <label>PoE</label>
                <label class="checkbox-google">
                    <input type="checkbox" id="dev-poe" ${existingDevice?.Poe ? 'checked' : ''}>
                    <span class="checkbox-google-switch"></span>
                </label>
            </div>
            <div class="form-group">
                <label>Модель</label>
                <select id="dev-model"></select>
            </div>
            <div class="form-group">
                <label>Серийный номер</label>
                <input type="text" id="dev-serial" value="${escapeHtml(existingDevice?.serial_number || '')}">
            </div>
            <div class="form-group">
                <label>MAC-адрес</label>
                <input type="text" id="dev-mac" class="mac-address" value="${escapeHtml(existingDevice?.mac_address || '')}">
            </div>
            <div class="form-group">
                <label>Прошивка</label>
                <select id="dev-firmware"></select>
            </div>
            <div class="form-group">
                <label>Шкаф</label>
                <select id="dev-cabinet"></select>
            </div>
            <div class="form-group">
                <label>Юнит</label>
                <input type="number" id="dev-unit" min="1" value="${escapeHtml(existingDevice?.unit_position || '')}">
            </div>
            <div class="form-group">
                <label>Примечание</label>
                <textarea id="dev-annotation" rows="2">${escapeHtml(existingDevice?.Annotation || '')}</textarea>
            </div>
            <div class="modal-actions">
                <button class="btn secondary" id="cancel-device-form">Отмена</button>
                <button class="btn" id="save-device-form">Сохранить</button>
            </div>
        </div>
    `;
    overlay.innerHTML = formHtml;
    document.body.appendChild(overlay);

    const modelSelect = overlay.querySelector('#dev-model');
    const firmwareSelect = overlay.querySelector('#dev-firmware');
    const cabinetSelect = overlay.querySelector('#dev-cabinet');

    loadModelsForStackDevice(vendorId, modelSelect);
    loadFirmwaresForStackDevice(firmwareSelect);
    loadCabinetsForStackDevice(cabinetSelect);

    overlay.querySelector('#cancel-device-form').addEventListener('click', () => overlay.remove());
    overlay.querySelector('#save-device-form').addEventListener('click', () => {
        const device = {
            Slot: overlay.querySelector('#dev-slot').value,
            Poe: overlay.querySelector('#dev-poe').checked ? 1 : 0,
            model: modelSelect.options[modelSelect.selectedIndex]?.text || '',
            model_id: modelSelect.value,
            serial_number: overlay.querySelector('#dev-serial').value,
            mac_address: overlay.querySelector('#dev-mac').value,
            firmware: firmwareSelect.options[firmwareSelect.selectedIndex]?.text || '',
            firmware_id: firmwareSelect.value,
            cabinet: cabinetSelect.options[cabinetSelect.selectedIndex]?.text || '',
            cabinet_id: cabinetSelect.value,
            unit_position: overlay.querySelector('#dev-unit').value,
            Annotation: overlay.querySelector('#dev-annotation').value,
            ip_address: document.getElementById('stack-ip')?.value || '',
            hostname: document.getElementById('stack-hostname')?.value || '',
            vendor: document.getElementById('stack-vendor')?.options?.[document.getElementById('stack-vendor')?.selectedIndex]?.text || '',
            vendor_id: vendorId
        };

        if (editIndex !== null && editIndex !== undefined) {
            tempStackDevices[editIndex] = device;
        } else {
            tempStackDevices.push(device);
        }
        if (typeof renderStackDevices === 'function') {
            renderStackDevices();
        }
        overlay.remove();
    });

    overlay.addEventListener('click', e => {
        if (e.target === overlay) overlay.remove();
    });
}