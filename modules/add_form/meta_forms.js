// Формы зданий, локаций, типов узлов и мета-справочников

async function openLocationForm() {
    const modal = document.getElementById('addLocationModal');
    const select = document.getElementById('buildingSelect');
    const form = document.getElementById('addLocationForm');
    if (!modal || !select || !form) return;

    form.reset();
    const oldError = form.querySelector('.form-error');
    if (oldError) oldError.remove();
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = false;

    select.innerHTML = '';
    const buildings = await loadList('buildings');
    if (buildings.length > 0) {
        select.appendChild(new Option('-- не выбрано --', ''));
        buildings.forEach(b => select.appendChild(new Option(b.name, b.id)));
    } else {
        select.appendChild(new Option('Нет зданий', ''));
    }
    const addOpt = new Option('Добавить...', '__add_new__');
    addOpt.style.fontStyle = 'italic';
    select.appendChild(addOpt);
    select.onchange = function() { if (this.value === '__add_new__') { this.value = ''; openBuildingForm(); } };
    showModal(modal);
    new SearchableSelect(select);
}
function closeLocationForm() {
    // Полный сброс: уничтожает поисковые селекты вместе с обёртками
    if (typeof closeModalAndReset === 'function') closeModalAndReset('addLocationModal');
    else document.getElementById('addLocationModal')?.classList.remove('visible');
}

function openNodeTypeForm() {
    const modal = document.getElementById('addNodeTypeModal');
    const form = document.getElementById('addNodeTypeForm');
    if (!modal || !form) return;
    form.reset();
    const oldError = form.querySelector('.form-error');
    if (oldError) oldError.remove();
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = false;
    showModal(modal);
}
function closeNodeTypeForm() {
    if (typeof closeModalAndReset === 'function') closeModalAndReset('addNodeTypeModal');
    else document.getElementById('addNodeTypeModal')?.classList.remove('visible');
}

function openMetaForm(listName, displayName) {
    if (listName === 'device_models') {
        openModelMetaForm(displayName);
        return;
    }
    const modal = document.getElementById('metaAddModal');
    const form = document.getElementById('metaAddForm');
    const title = document.getElementById('metaAddTitle');
    const nameInput = document.getElementById('metaAddName');
    if (!modal || !form || !title || !nameInput) return;
    form.reset();
    const oldError = form.querySelector('.form-error');
    if (oldError) oldError.remove();
    document.getElementById('metaAddSubmit').disabled = false;
    title.textContent = 'Добавить ' + (displayName || listName);
    nameInput.placeholder = 'Введите название';
    form.dataset.list = listName;
    form.dataset.action = 'meta';
    modal.style.display = 'flex';
    showModal(modal);
}
function closeMetaForm() {
    const m = document.getElementById('metaAddModal');
    if (!m) return;
    m.classList.remove('visible');
    m.style.display = 'none';
    // Полный сброс: поисковые селекты, disabled, ошибки валидации
    if (typeof resetModalForm === 'function') resetModalForm(m);
}
function showMetaError(msg) {
    const form = document.getElementById('metaAddForm');
    let err = form.querySelector('.form-error');
    if (!err) { err = document.createElement('div'); err.className = 'form-error'; form.appendChild(err); }
    err.textContent = msg;
}

async function submitMetaForm(e) {
    e.preventDefault();
    const form = document.getElementById('metaAddForm');
    const nameInput = document.getElementById('metaAddName');
    const name = nameInput.value.trim();
    if (!name) { showMetaError('Название обязательно'); return; }
    const submitBtn = document.getElementById('metaAddSubmit');
    submitBtn.disabled = true;

    if (form.dataset.action === 'add_column') {
        const table = form.dataset.table;
        fetch('?ajax=add_column', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'table=' + encodeURIComponent(table) + '&column_name=' + encodeURIComponent(name)
        }).then(r => r.json()).then(data => {
            submitBtn.disabled = false;
            if (data.success) { closeMetaForm(); location.reload(); } else showMetaError(data.error || 'Ошибка');
        }).catch(() => { submitBtn.disabled = false; showMetaError('Ошибка сети'); });
        return;
    }

    const listName = form.dataset.list;
    let body;
    if (listName === 'device_models') {
        const vendorSelect = document.getElementById('metaVendorSelect');
        const vendorId = vendorSelect ? vendorSelect.value : null;
        if (!vendorId) { showMetaError('Выберите производителя'); submitBtn.disabled = false; return; }
        body = JSON.stringify({ list: listName, name: name, vendor_id: vendorId });
    } else {
        body = JSON.stringify({ list: listName, name: name });
    }

    try {
        const res = await fetch('?ajax=add_meta', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body });
        const data = await res.json();
        submitBtn.disabled = false;
        if (data.success) {
            closeMetaForm();
            let select = document.querySelector(`#universalAddForm select[data-source="${listName}_list"]`);
            if (!select) select = document.querySelector(`select[data-source="${listName}_list"]`);
            if (select) {
                const opt = new Option(data.name, data.id);
                const addOpt = select.querySelector('option[value="__add_new__"]');
                if (addOpt) select.insertBefore(opt, addOpt);
                else select.appendChild(opt);
                select.value = data.id;
                if (select.searchableInstance) {
                    select.searchableInstance.options = Array.from(select.options).filter(o => o.value !== '__add_new__');
                    select.searchableInstance.updateDropdown('');
                    select.searchableInstance.syncInputWithSelect();
                }
            }
        } else showMetaError(data.message || 'Ошибка');
    } catch {
        submitBtn.disabled = false;
        showMetaError('Ошибка сети');
    }
}

// Специфичная форма для модели
function openModelMetaForm(displayName) {
    const modal = document.getElementById('metaAddModel');
    const form = document.getElementById('modelMetaForm');
    const title = document.getElementById('modelMetaTitle');
    const nameInput = document.getElementById('modelMetaName');
    const vendorSelect = document.getElementById('modelVendorSelect');
    if (!modal || !form || !title || !nameInput || !vendorSelect) return;
    form.reset();
    const oldError = form.querySelector('.form-error');
    if (oldError) oldError.remove();
    document.getElementById('modelMetaSubmit').disabled = false;
    title.textContent = 'Добавить модель';
    nameInput.placeholder = 'Введите название модели';
    form.dataset.list = 'device_models';
    form.dataset.action = 'meta';
    loadVendorsForModelSelect(vendorSelect);
    modal.style.display = 'flex';
    showModal(modal);
}
function closeModelMetaForm() {
    const modal = document.getElementById('metaAddModel');
    if (!modal) return;
    modal.classList.remove('visible');
    modal.style.display = 'none';
    if (typeof resetModalForm === 'function') resetModalForm(modal);
}
async function loadVendorsForModelSelect(select) {
    if (select.searchableInstance) { select.searchableInstance.destroy(); select.searchableInstance = null; }
    const wrapper = select.closest('.searchable-select');
    if (wrapper) { wrapper.parentNode.insertBefore(select, wrapper); wrapper.remove(); }
    select.style.display = '';
    select.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const resp = await fetchJSON('?ajax=get_list&list=vendors');
        const items = resp.data || [];
        select.innerHTML = '<option value="">-- выберите --</option>';
        items.forEach(v => select.appendChild(new Option(v.name, v.id)));
        const mainVendorSelect = document.querySelector('#universalAddForm select[name="vendor_id"]');
        if (mainVendorSelect && mainVendorSelect.value && mainVendorSelect.value !== '__add_new__') {
            select.value = mainVendorSelect.value;
        }
        new SearchableSelect(select);
    } catch (e) {
        select.innerHTML = '<option value="">-- ошибка загрузки --</option>';
    }
}

// Диалог добавления модуля
function openModuleDialog(type) {
    const modal = document.getElementById('moduleAddModal');
    if (!modal) return;

    const titles = {
        'sfp': 'Новый SFP модуль',
        'psu': 'Новый блок питания',
        'fan': 'Новый вентилятор',
        'linecard': 'Новая карта расширения',
        'supervisor': 'Новый супервизор',
        'other': 'Новый модуль'
    };
    modal.querySelector('.modal-title').textContent = titles[type] || 'Новый модуль';

    const sfpFields = modal.querySelectorAll('.sfp-only');
    sfpFields.forEach(el => {
        el.style.display = (type === 'sfp') ? 'block' : 'none';
    });

    modal.querySelectorAll('input').forEach(inp => inp.value = '');

    modal.dataset.moduleType = type;
    showModal(modal);
}

function closeModuleDialog() {
    if (typeof closeModalAndReset === 'function') closeModalAndReset('moduleAddModal');
    else document.getElementById('moduleAddModal')?.classList.remove('visible');
}

// Обработчики для форм (здания, локации, типы узлов, модели, модули)
document.addEventListener('DOMContentLoaded', () => {
    const locationForm = document.getElementById('addLocationForm');
    if (locationForm) {
        locationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const oldError = form.querySelector('.form-error');
            if (oldError) oldError.remove();
            submitBtn.disabled = true;
            const formData = new FormData(form);
            fetch('?ajax=add_location', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    submitBtn.disabled = false;
                    if (data.success) {
                        closeLocationForm();
                        if (window.AppState.currentLocationSelect) {
                            const opt = document.createElement('option');
                            opt.value = data.id;
                            opt.textContent = data.display;
                            window.AppState.currentLocationSelect.insertBefore(opt, window.AppState.currentLocationSelect.lastChild);
                            window.AppState.currentLocationSelect.value = data.id;
                            if (window.AppState.currentLocationSelect.searchableInstance) window.AppState.currentLocationSelect.searchableInstance.options.push(opt);
                        }
                    } else {
                        const errorDiv = document.createElement('div'); errorDiv.className = 'form-error';
                        errorDiv.textContent = data.message || data.error || 'Ошибка';
                        form.appendChild(errorDiv);
                    }
                }).catch(() => { submitBtn.disabled = false; alert('Ошибка сети'); });
        });
    }


    const metaForm = document.getElementById('metaAddForm');
    if (metaForm) metaForm.addEventListener('submit', submitMetaForm);
    const metaModal = document.getElementById('metaAddModal');
    if (metaModal) metaModal.addEventListener('click', e => { if (e.target === metaModal) closeMetaForm(); });

    const addModelForm = document.getElementById('addModelForm');
    if (addModelForm) {
        addModelForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const vendorId = document.getElementById('addModelVendor').value;
            const modelName = document.getElementById('addModelName').value.trim();
            if (!vendorId || !modelName) { alert('Заполните все поля'); return; }
            try {
                const body = JSON.stringify({ list: 'device_models', name: modelName, vendor_id: vendorId });
                const res = await fetch('?ajax=add_meta', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body });
                const data = await res.json();
                if (data.success) {
                    closeAddModelModal();
                    const modelSelect = document.querySelector('#universalAddForm select[name="model_id"]');
                    if (modelSelect) {
                        const newOption = new Option(data.name || modelName, data.id);
                        const addNewOption = modelSelect.querySelector('option[value="__add_new__"]');
                        if (addNewOption) modelSelect.insertBefore(newOption, addNewOption);
                        else modelSelect.appendChild(newOption);
                        modelSelect.value = data.id;
                        if (modelSelect.searchableInstance) {
                            modelSelect.searchableInstance.options = Array.from(modelSelect.options).filter(o => o.value !== '__add_new__');
                            modelSelect.searchableInstance.updateDropdown('');
                            modelSelect.searchableInstance.syncInputWithSelect();
                        }
                    }
                } else alert('Ошибка: ' + (data.error || data.message || 'неизвестная ошибка'));
            } catch (err) { alert('Ошибка сети'); }
        });
    }

    const modelMetaForm = document.getElementById('modelMetaForm');
    if (modelMetaForm) {
        modelMetaForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const nameInput = document.getElementById('modelMetaName');
            const vendorSelect = document.getElementById('modelVendorSelect');
            const name = nameInput.value.trim();
            const vendorId = vendorSelect.value;
            if (!name) { showMetaError('Название обязательно'); return; }
            if (!vendorId) { showMetaError('Выберите производителя'); return; }
            const submitBtn = document.getElementById('modelMetaSubmit');
            submitBtn.disabled = true;
            try {
                const res = await fetch('?ajax=add_meta', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ list: 'device_models', name, vendor_id: vendorId }) });
                const data = await res.json();
                submitBtn.disabled = false;
                if (data.success) {
                    closeModelMetaForm();
                    const modelSelect = document.querySelector('#universalAddForm select[name="model_id"]');
                    if (modelSelect) {
                        const newOption = new Option(data.name, data.id);
                        const addOpt = modelSelect.querySelector('option[value="__add_new__"]');
                        if (addOpt) modelSelect.insertBefore(newOption, addOpt);
                        else modelSelect.appendChild(newOption);
                        modelSelect.value = data.id;
                        if (modelSelect.searchableInstance) {
                            modelSelect.searchableInstance.options = Array.from(modelSelect.options).filter(o => o.value !== '__add_new__');
                            modelSelect.searchableInstance.updateDropdown('');
                            modelSelect.searchableInstance.syncInputWithSelect();
                        }
                    }
                } else showMetaError(data.message || 'Ошибка');
            } catch { submitBtn.disabled = false; showMetaError('Ошибка сети'); }
        });
    }

    const moduleForm = document.getElementById('moduleAddForm');
    if (moduleForm) {
        moduleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const modal = document.getElementById('moduleAddModal');
            const type = modal.dataset.moduleType;
            const formData = new FormData(this);
            formData.append('module_type', type);
            fetch('?ajax=add_equipment_module', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        addModuleToDossier(type, data.module);
                        modal.classList.remove('visible');
                    } else alert(data.error || 'Ошибка');
                });
        });
    }
});

// openAddModelModal и closeAddModelModal
function openAddModelModal() {
    const modal = document.getElementById('addModelModal');
    if (!modal) return;

    const modelSelect = document.querySelector('#universalAddForm select[name="model_id"]');
    const vendorSelect = document.querySelector('#universalAddForm select[name="vendor_id"]');

    modal.dataset.modelSelectId = modelSelect ? modelSelect.id : '';
    if (!modelSelect) {
        console.warn('Не найден селект модели');
    }
    if (!modelSelect.id) {
        modelSelect.id = 'temp_model_select_' + Date.now();
        modal.dataset.modelSelectId = modelSelect.id;
    }
    modal.dataset.vendorSelectId = vendorSelect ? vendorSelect.id : '';

    // Сначала показываем модальное окно
    showModal(modal);

    // Затем загружаем вендоров (в фоне), и если получится – устанавливаем значение
    loadVendorsForModelModal()
        .then(() => {
            const addModelVendor = document.getElementById('addModelVendor');
            if (addModelVendor && vendorSelect && vendorSelect.value && vendorSelect.value !== '__add_new__') {
                addModelVendor.value = vendorSelect.value;
                if (addModelVendor.searchableInstance) {
                    addModelVendor.searchableInstance.syncInputWithSelect();
                }
            }
        })
        .catch(err => {
            console.warn('Не удалось загрузить список вендоров для модели:', err);
        });

    document.getElementById('addModelName').value = '';
}

function closeAddModelModal() {
    const modal = document.getElementById('addModelModal');
    if (modal) {
        modal.classList.remove('visible');
        const vendorSelect = document.getElementById('addModelVendor');
        if (vendorSelect && vendorSelect.searchableInstance) {
            vendorSelect.searchableInstance.destroy();
            vendorSelect.searchableInstance = null;
        }
    }
}

async function loadVendorsForModelModal() {
    const vendorSelect = document.getElementById('addModelVendor');
    if (!vendorSelect) return;

    if (vendorSelect.searchableInstance) {
        vendorSelect.searchableInstance.destroy();
        vendorSelect.searchableInstance = null;
    }

    const wrapper = vendorSelect.closest('.searchable-select');
    if (wrapper) {
        wrapper.parentNode.insertBefore(vendorSelect, wrapper);
        wrapper.remove();
    }
    vendorSelect.style.display = '';

    vendorSelect.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const response = await fetch('?ajax=get_list&list=vendors');
        const data = await response.json();
        const vendors = data.data || [];

        vendorSelect.innerHTML = '<option value="">-- выберите --</option>';
        vendors.forEach(v => {
            vendorSelect.add(new Option(v.name, v.id));
        });

        const addOpt = new Option('Добавить...', '__add_new__');
        vendorSelect.appendChild(addOpt);

        vendorSelect.setAttribute('data-source', 'vendors_list');

        vendorSelect.addEventListener('change', () => {
            if (vendorSelect.value === '__add_new__') {
                vendorSelect.value = '';
                openMetaForm('vendors', 'Производитель');
            }
        });

        new SearchableSelect(vendorSelect);

    } catch (e) {
        vendorSelect.innerHTML = '<option value="">-- ошибка загрузки --</option>';
    }
}