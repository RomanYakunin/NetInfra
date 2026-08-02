// modules/add_form/rack_form.js – блок "Шкаф(-ы)" в форме узла

// Поля, недоступные до выбора модели шкафа
const RACK_DEPENDENT_FIELDS = ['name', 'building_id', 'workshop', 'floor', 'room', 'status', 'notes'];

// Блокируем/разблокируем всё, кроме селектов производителя и модели
function setRackFieldsEnabled(enabled) {
    const form = document.getElementById('addRackForm');
    if (!form) return;
    RACK_DEPENDENT_FIELDS.forEach(fieldName => {
        const el = form.querySelector(`[name="${fieldName}"]`);
        if (!el) return;
        el.disabled = !enabled;
        // Поисковый селект рисует собственный input поверх исходного
        const wrapper = el.closest('.searchable-select');
        if (wrapper) {
            const searchInput = wrapper.querySelector('.searchable-select-input');
            if (searchInput) searchInput.disabled = !enabled;
            wrapper.style.opacity = enabled ? '' : '0.5';
        } else {
            el.style.opacity = enabled ? '' : '0.5';
        }
    });
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = !enabled;

    let hint = form.querySelector('.rack-model-hint');
    if (!enabled) {
        if (!hint) {
            hint = document.createElement('div');
            hint.className = 'rack-model-hint';
            hint.style.cssText = 'color:var(--text-secondary); font-size:0.8rem; margin-bottom:0.8rem;';
            hint.textContent = 'Сначала выберите модель шкафа — остальные поля станут доступны.';
            const modelGroup = form.querySelector('#rack-model-select')?.closest('.form-group');
            if (modelGroup) modelGroup.insertAdjacentElement('afterend', hint);
        }
    } else if (hint) {
        hint.remove();
    }
}

// Копируем цех / этаж / комнату из формы узла в форму шкафа.
// Здание обрабатывается отдельно (селект заполняется асинхронно).
function prefillRackLocationFromNode() {
    const nodeForm = document.getElementById('universalAddForm');
    const rackForm = document.getElementById('addRackForm');
    if (!nodeForm || !rackForm) return;

    ['workshop', 'floor', 'room'].forEach(fieldName => {
        const src = nodeForm.querySelector(`[name="${fieldName}"]`);
        const dst = rackForm.querySelector(`[name="${fieldName}"]`);
        if (src && dst) dst.value = src.value || '';
    });
}

window.openAddRackForm = async function() {
    const modal = document.getElementById('addRackModal');
    if (!modal) return;

    // ---------- Производители ----------
    const vendorSelect = document.getElementById('rack-vendor-select');
    vendorSelect.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const resp = await fetch('?ajax=get_list&list=vendors');
        const data = await resp.json();
        const vendors = data.data || [];
        vendorSelect.innerHTML = '<option value="">-- не выбрано --</option>';
        vendors.forEach(v => vendorSelect.add(new Option(v.name, v.id)));
        new SearchableSelect(vendorSelect);   // ← поисковый селект
    } catch (e) { vendorSelect.innerHTML = '<option value="">-- ошибка --</option>'; }

    // ---------- Модели ----------
    await updateRackModelSelect();

    // ---------- Здания ----------
    const buildingSelect = document.getElementById('rack-building-select');
    buildingSelect.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const resp = await fetch('?ajax=get_list&list=buildings');
        const data = await resp.json();
        const buildings = data.data || [];
        buildingSelect.innerHTML = '<option value="">-- не выбрано --</option>';
        buildings.forEach(b => buildingSelect.add(new Option(b.name, b.id)));
        const nodeBuilding = document.querySelector('#universalAddForm select[name="building_id"]')?.value;
        if (nodeBuilding) buildingSelect.value = nodeBuilding;
        new SearchableSelect(buildingSelect);
        if (buildingSelect.searchableInstance) buildingSelect.searchableInstance.syncInputWithSelect();
    } catch (e) { buildingSelect.innerHTML = '<option value="">-- ошибка --</option>'; }

    // ---------- Остальные поля локации подтягиваем из формы узла ----------
    prefillRackLocationFromNode();

    // Обработчик смены производителя (список моделей сбрасывается — снова блокируем поля)
    vendorSelect.onchange = async () => {
        await updateRackModelSelect(vendorSelect.value);
        setRackFieldsEnabled(false);
    };

    // Скрытое поле id_node
    document.getElementById('rack-node-id').value = window.AppState.currentRelatedId
        || window.AppState.currentExtraData?.node_id || '';

    // До выбора модели остальные поля заблокированы
    setRackFieldsEnabled(false);

    showModal(modal);
};

function closeAddRackForm() {
    const modal = document.getElementById('addRackModal');
    if (modal) modal.classList.remove('visible');
    const form = document.getElementById('addRackForm');
    if (form) {
        // Снимаем блокировку, иначе form.reset() не очистит disabled-поля корректно
        form.querySelectorAll('input, select, textarea, button').forEach(el => { el.disabled = false; });
        form.reset();
        // Удаляем ошибки валидации и подсказку о выборе модели
        form.querySelectorAll('.unique-error-msg, .form-error, .rack-model-hint').forEach(el => el.remove());
        form.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        // Возвращаем прозрачность, выставленную при блокировке
        form.querySelectorAll('.searchable-select').forEach(w => { w.style.opacity = ''; });
        form.querySelectorAll('[style*="opacity"]').forEach(el => { el.style.opacity = ''; });
        // Скрытое поле узла
        const nodeIdInput = form.querySelector('#rack-node-id');
        if (nodeIdInput) nodeIdInput.value = '';
    }
    // Полностью сбрасываем поисковые селекты (значение + видимый input + выпадающий список)
    ['rack-vendor-select', 'rack-model-select', 'rack-building-select'].forEach(id => {
        const select = document.getElementById(id);
        if (!select) return;
        select.value = '';
        if (select.searchableInstance) {
            select.searchableInstance.syncInputWithSelect();
            const wrapper = select.closest('.searchable-select');
            const dropdown = wrapper?.querySelector('.searchable-select-dropdown');
            if (dropdown) dropdown.style.display = 'none';
        }
    });
}

// Закрытие по Escape (модалка шкафа приоритетнее формы узла)
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const modelModal = document.getElementById('addRackModelModal');
    if (modelModal?.classList.contains('visible')) {
        e.stopPropagation();
        closeAddRackModelForm();
        return;
    }
    const rackModal = document.getElementById('addRackModal');
    if (rackModal?.classList.contains('visible')) {
        e.stopPropagation();
        closeAddRackForm();
    }
}, true);

async function updateRackModelSelect(vendorId = null) {
    const modelSelect = document.getElementById('rack-model-select');
    modelSelect.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        let url = '?ajax=get_list&list=rack_models';
        if (vendorId) url += '&vendor_id=' + vendorId;
        const resp = await fetch(url);
        const data = await resp.json();
        const models = data.data || [];
        modelSelect.innerHTML = '<option value="">-- не выбрано --</option>';
        models.forEach(m => {
            // Формируем многострочный лейбл с полными характеристиками модели
            const title = m.model_name || m.name || 'Без названия';

            // Габариты: 42U, 600×800 мм
            const dims = [];
            if (m.height_u) dims.push(`${m.height_u}U`);
            if (m.width_mm && m.depth_mm) dims.push(`${m.width_mm}×${m.depth_mm} мм`);
            if (m.form_factor) dims.push(m.form_factor);

            // Дополнительно: дверь, класс защиты, нагрузка
            const extra = [];
            if (m.door_type) extra.push(`дверь: ${m.door_type}`);
            if (m.ip_rating) extra.push(m.ip_rating);
            if (m.max_load_kg) extra.push(`до ${m.max_load_kg} кг`);

            const lines = [title];
            if (dims.length) lines.push(dims.join(', '));
            if (extra.length) lines.push(extra.join(', '));

            const option = new Option(lines.join('\n'), m.id);
            option.dataset.vendorId = m.vendor_id || '';
            modelSelect.add(option);
        });
        const addOpt = new Option('Добавить...', '__add_new__');
        addOpt.style.fontStyle = 'italic';
        modelSelect.appendChild(addOpt);

        modelSelect.onchange = function() {
            if (this.value === '__add_new__') {
                this.value = '';
                setRackFieldsEnabled(false);
                openAddRackModelForm();
                return;
            }
            // Автозаполнение производителя по выбранной модели
            const selected = this.options[this.selectedIndex];
            const modelVendorId = selected?.dataset.vendorId;
            const vendorSelect = document.getElementById('rack-vendor-select');
            if (modelVendorId && vendorSelect && vendorSelect.value !== modelVendorId) {
                vendorSelect.value = modelVendorId;
                if (vendorSelect.searchableInstance) vendorSelect.searchableInstance.syncInputWithSelect();
            }
            // Модель выбрана — открываем остальные поля
            setRackFieldsEnabled(!!this.value);
        };

        // Создаём поисковый селект, если ещё не создан
        if (!modelSelect.searchableInstance) {
            new SearchableSelect(modelSelect);
        } else {
            modelSelect.searchableInstance.options = Array.from(modelSelect.options).filter(o => o.value !== '__add_new__');
            modelSelect.searchableInstance.updateDropdown('');
            modelSelect.searchableInstance.syncInputWithSelect();
        }
    } catch (e) { modelSelect.innerHTML = '<option value="">-- ошибка --</option>'; }
}

document.addEventListener('DOMContentLoaded', () => {
    const rackForm = document.getElementById('addRackForm');
    if (rackForm) {
        rackForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(rackForm);
            try {
                const resp = await fetch('?ajax=add_rack', { method: 'POST', body: formData });
                const data = await resp.json();
                if (data.success) {
                    closeAddRackForm();
                    // Добавляем плитку шкафа в блок (сразу отмеченной)
                    addRackTile(data.id, data.name, true, data.detail || 'шкаф');
                    showToast('Шкаф добавлен', 'success');
                } else {
                    showToast(data.error || 'Ошибка', 'error');
                }
            } catch (e) { showToast('Ошибка сети', 'error'); }
        });
    }
});

function addRackTile(rackId, rackName, checked = false, detail = 'шкаф') {
    const group = document.getElementById('racks-tile-group');
    if (!group) return;
    if (group.querySelector(`.rack-tile-checkbox[value="${rackId}"]`)) return;
    const label = document.createElement('label');
    label.className = 'rack-tile-label';
    label.innerHTML = `
        <input type="checkbox" class="rack-tile-checkbox" value="${rackId}" ${checked ? 'checked' : ''}>
        <div class="rack-tile-content">
            <div class="rack-tile-name">${escapeHtml(rackName)}</div>
            <div class="rack-tile-detail">${escapeHtml(detail)}</div>
        </div>
    `;
    const addTile = group.querySelector('.rack-tile-add');
    group.insertBefore(label, addTile);
}

async function loadNodeRacks(nodeId) {
    const group = document.getElementById('racks-tile-group');
    if (!group) return;
    // Очищаем существующие плитки (кроме кнопки добавления)
    group.querySelectorAll('.rack-tile-label').forEach(el => el.remove());
    try {
        const resp = await fetch(`?ajax=get_node_racks&node_id=${nodeId}`);
        const racks = await resp.json();
        if (Array.isArray(racks)) {
            racks.forEach(r => {
                const detailParts = [];
                if (r.vendor_name) detailParts.push(r.vendor_name);
                if (r.height_u) detailParts.push(`${r.height_u}U`);
                if (r.width_mm && r.depth_mm) detailParts.push(`${r.width_mm}×${r.depth_mm} мм`);
                // Шкафы узла отмечаем чекбоксами
                addRackTile(r.id_rack, r.name, true, detailParts.join(', ') || 'шкаф');
            });
        }
    } catch (e) {}
}

// ---------- Добавление новой модели шкафа (полная форма по столбцам БД) ----------
window.openAddRackModelForm = function() {
    const modal = document.getElementById('addRackModelModal');
    const form = document.getElementById('addRackModelForm');
    const vendorSelect = document.getElementById('rack-model-vendor-select');
    if (!modal || !form || !vendorSelect) return;
    form.reset();
    form.querySelectorAll('.form-error').forEach(el => el.remove());

    vendorSelect.innerHTML = '<option value="">-- загрузка --</option>';
    fetch('?ajax=get_list&list=vendors')
        .then(r => r.json())
        .then(data => {
            const vendors = data.data || [];
            vendorSelect.innerHTML = '<option value="">-- не выбрано --</option>';
            vendors.forEach(v => vendorSelect.add(new Option(v.name, v.id)));
            const currentVendor = document.getElementById('rack-vendor-select')?.value;
            if (currentVendor) vendorSelect.value = currentVendor;
            new SearchableSelect(vendorSelect);
        })
        .catch(() => { vendorSelect.innerHTML = '<option value="">-- ошибка --</option>'; });

    showModal(modal);
};

function closeAddRackModelForm() {
    const modal = document.getElementById('addRackModelModal');
    if (modal) modal.classList.remove('visible');
}

document.addEventListener('DOMContentLoaded', () => {
    const modelForm = document.getElementById('addRackModelForm');
    if (modelForm) {
        modelForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(modelForm);
            const body = {
                list: 'rack_models',
                vendor_id: fd.get('vendor_id'),
                model_name: fd.get('model_name'),
                form_factor: fd.get('form_factor'),
                height_u: fd.get('height_u'),
                width_mm: fd.get('width_mm'),
                depth_mm: fd.get('depth_mm'),
                door_type: fd.get('door_type'),
                ip_rating: fd.get('ip_rating'),
                max_load_kg: fd.get('max_load_kg'),
                notes: fd.get('notes')
            };
            try {
                const resp = await fetch('?ajax=add_meta', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                const data = await resp.json();
                if (data.success) {
                    closeAddRackModelForm();
                    showToast('Модель шкафа добавлена', 'success');
                    // Обновляем список моделей и выбираем только что созданную
                    const vendorSelect = document.getElementById('rack-vendor-select');
                    if (vendorSelect) vendorSelect.value = body.vendor_id;
                    await updateRackModelSelect(body.vendor_id);
                    const modelSelect = document.getElementById('rack-model-select');
                    if (modelSelect) {
                        modelSelect.value = data.id;
                        if (modelSelect.searchableInstance) modelSelect.searchableInstance.syncInputWithSelect();
                        // Новая модель выбрана — открываем остальные поля
                        setRackFieldsEnabled(true);
                    }
                } else {
                    let err = modelForm.querySelector('.form-error');
                    if (!err) { err = document.createElement('div'); err.className = 'form-error'; modelForm.appendChild(err); }
                    err.textContent = data.message || 'Ошибка';
                }
            } catch (e) { showToast('Ошибка сети', 'error'); }
        });
    }
});
