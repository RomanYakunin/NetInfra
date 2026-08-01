// modules/add_form/equipment_validation.js

function setupEquipmentValidation(container, excludeId = null, groupId = null, nodeId = null, warehouseId = null) {
    if (!container) return;

    const fieldsToCheck = ['hostname', 'serial_number', 'mac_address', 'ip_address'];
    const debounceTimers = {};

    fieldsToCheck.forEach(fieldName => {
        const originalEl = container.querySelector(`[name="${fieldName}"]`);
        if (!originalEl) return;

        if (originalEl.tagName === 'SELECT') {
            if (originalEl.disabled) return;
            originalEl.addEventListener('change', function() {
                const value = originalEl.value;
                if (!value || value === '__add_new__') {
                    clearError(originalEl);
                    return;
                }
                checkUnique(fieldName, value, originalEl, excludeId, groupId);
            });
            return;
        }

        originalEl.addEventListener('input', function() {
            const value = originalEl.value.trim();
            if (debounceTimers[fieldName]) clearTimeout(debounceTimers[fieldName]);

            if (!value) {
                clearError(originalEl);
                return;
            }

            debounceTimers[fieldName] = setTimeout(() => {
                checkUnique(fieldName, value, originalEl, excludeId, groupId);
            }, 300);
        });
    });
}

async function checkUnique(fieldName, value, inputElement, excludeId, groupId, nodeId, warehouseId) {
    const params = new URLSearchParams({ ajax: 'check_unique', field: fieldName, value: value });
    if (excludeId)   params.append('exclude_id', excludeId);
    if (groupId)     params.append('group_id', groupId);
    if (nodeId)      params.append('node_id', nodeId);
     if (warehouseId) params.append('warehouse_id', warehouseId);

    try {
        const resp = await fetch('?' + params.toString());
        const data = await resp.json();
        if (data.exists) {
            showError(inputElement, data.message);
        } else {
            clearError(inputElement);
        }
    } catch (e) { /* игнорируем сетевые ошибки */ }
}

function showError(input, message) {
    clearError(input);
    let container = input.closest('.value') || input.closest('.form-group') || input.parentElement;
    const errorDiv = document.createElement('div');
    errorDiv.className = 'unique-error-msg';
    errorDiv.style.cssText = 'color: var(--danger, #e63946); font-size: 0.85rem; margin-top: 0.3rem;';
    errorDiv.textContent = message;
    errorDiv.dataset.fieldName = input.name;
    container.appendChild(errorDiv);
    input.classList.add('input-error');
    const wrapper = input.closest('.searchable-select');
    if (wrapper) {
        const visibleInput = wrapper.querySelector('.searchable-select-input');
        if (visibleInput) visibleInput.classList.add('input-error');
    }
}

function clearError(input) {
    let container = input.closest('.value') || input.closest('.form-group') || input.parentElement;
    if (container) {
        const msg = container.querySelector('.unique-error-msg');
        if (msg) msg.remove();
    }
    input.classList.remove('input-error');
    const wrapper = input.closest('.searchable-select');
    if (wrapper) {
        const visibleInput = wrapper.querySelector('.searchable-select-input');
        if (visibleInput) visibleInput.classList.remove('input-error');
    }
}

function validateAllFields(container) {
    const errors = container.querySelectorAll('.unique-error-msg');
    if (errors.length === 0) return true;

    const labels = [];
    errors.forEach(msg => {
        const fieldName = msg.dataset.fieldName;
        if (fieldName) {
            const input = container.querySelector(`[name="${fieldName}"]`);
            if (input) {
                const label =
                    input.closest('.dossier-item')?.querySelector('.label')?.textContent ||
                    input.closest('.form-group')?.querySelector('label')?.textContent ||
                    fieldName;
                labels.push(label);
            } else {
                labels.push(fieldName);
            }
        }
    });

    const uniqueLabels = [...new Set(labels)];
    const word = uniqueLabels.length === 1 ? 'поле' : 'поля';
    showToast(`Исправьте ${word}: ${uniqueLabels.join(', ')}`, 'error');
    return false;
}