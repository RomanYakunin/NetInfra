// modules/add_form/add_form_core.js – общее ядро форм

// ======================== Класс для поискового селекта ========================
class SearchableSelect {
    constructor(select) {
        this.select = select;
        this.options = Array.from(select.options).filter(opt => opt.value !== '__add_new__');
        this.addOption = select.querySelector('option[value="__add_new__"]');

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'searchable-select';

        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = 'searchable-select-input';
        this.searchInput.placeholder = 'Поиск...';
        this.searchInput.addEventListener('input', () => this.filterOptions());
        this.searchInput.addEventListener('focus', () => {
            this.filterOptions();
            this.dropdown.style.display = 'block';
        });
        this.searchInput.addEventListener('click', (e) => e.stopPropagation());

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'searchable-select-dropdown';
        this.updateDropdown();

        this.wrapper.appendChild(this.searchInput);
        this.wrapper.appendChild(this.dropdown);

        this.select.style.display = 'none';
        this.select.parentNode.insertBefore(this.wrapper, this.select);
        this.wrapper.appendChild(this.select);

        this.syncInputWithSelect();

        this._closeHandler = (e) => {
            if (!this.wrapper.contains(e.target)) {
                this.dropdown.style.display = 'none';
                this.syncInputWithSelect();
            }
        };
        document.addEventListener('click', this._closeHandler);

        this.select.searchableInstance = this;
    }

    syncInputWithSelect() {
        if (this.select.value && this.select.value !== '__add_new__') {
            const selectedOption = this.select.options[this.select.selectedIndex];
            if (selectedOption && selectedOption.textContent) {
                this.searchInput.value = selectedOption.textContent;
            } else {
                this.searchInput.value = '';
            }
        } else {
            this.searchInput.value = '';
        }
    }

    updateDropdown(filterText = '') {
        this.dropdown.innerHTML = '';
        const filtered = this.options.filter(opt =>
            opt.textContent.toLowerCase().includes(filterText.toLowerCase())
        );

        filtered.forEach(opt => {
            const div = document.createElement('div');
            div.className = 'searchable-select-option';
            div.textContent = opt.textContent;
            div.addEventListener('click', () => {
                this.searchInput.value = opt.textContent;
                this.select.value = opt.value;
                this.dropdown.style.display = 'none';
                this.select.dispatchEvent(new Event('change'));
            });
            this.dropdown.appendChild(div);
        });

        if (this.addOption) {
            const addDiv = document.createElement('div');
            addDiv.className = 'searchable-select-option add-new';
            addDiv.style.fontStyle = 'normal';
            addDiv.textContent = this.addOption.textContent;
            addDiv.addEventListener('click', () => {
                this.searchInput.value = '';
                this.select.value = '__add_new__';
                this.dropdown.style.display = 'none';
                this.select.dispatchEvent(new Event('change'));
            });
            this.dropdown.appendChild(addDiv);
        }
    }

    filterOptions() {
        this.dropdown.style.display = 'block';
        this.updateDropdown(this.searchInput.value);
    }

    destroy() {
        document.removeEventListener('click', this._closeHandler);
    }
}

// ======================== Загрузка данных ========================
async function fetchJSON(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error('Ошибка загрузки ' + url);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return data;
}

let formConfig = {
    node: {
        title: 'Добавить узел',
        fields: [],
        url: '?ajax=add_node'
    },
    equipment: {
        title: 'Добавить устройство',
        fields: [],
        url: '?ajax=add_equipment'
    }
};

let currentFormType = null;
let currentRelatedId = null;
let currentInitialData = null;
let currentLocationSelect = null;
let currentNodeTypeSelect = null;
let currentEquipmentNodeId = null;
let currentExtraData = null;

async function loadNodeFields() {
    try {
        const cols = await fetchJSON('?ajax=get_node_columns');
        formConfig.node.fields = cols.map(col => {
            if (col.type === 'select' && col.source === 'buildings') {
                return { name: col.name, label: col.label, type: 'select', source: 'buildings' };
            }
            if (col.type === 'select' && col.source === 'node_types') {
                return { name: col.name, label: col.label, type: 'select', source: 'node_types' };
            }
            return { name: col.name, label: col.label, type: col.type || 'text' };
        });
    } catch (err) {
        console.error('Не удалось загрузить поля узла', err);
        formConfig.node.fields = [
            { name: 'KY_number', label: 'Номер КУ', type: 'text' },
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
        formConfig.equipment.fields = cols.map(col => {
            if (col.name === 'Poe') col.type = 'switch';
            return col;
        });
    } catch (err) {
        console.error(err);
        formConfig.equipment.fields = [
            { name: 'Groupe', label: 'Группа', type: 'select', source: 'Type_group_list' },
            { name: 'ip_address', label: 'IP-адрес', type: 'select', source: 'ip_address_list' },
            { name: 'hostname', label: 'Имя хоста', type: 'text' },
            { name: 'Poe', label: 'PoE', type: 'switch' },
            { name: 'speed', label: 'Скорость порта', type: 'radio', options: ['100Mb/s','1Gb/s','10Gb/s'] },
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

async function loadLocations(buildingId = null) {
    try {
        let url = '?ajax=get_locations';
        if (buildingId) url += '&building_id=' + buildingId;
        return await fetchJSON(url);
    } catch {
        return [];
    }
}
async function loadBuildings() { try { return await fetchJSON('?ajax=get_buildings'); } catch { return []; } }
async function loadNodeTypes() { try { return await fetchJSON('?ajax=get_node_types'); } catch { return []; } }
async function loadList(listName) { try { const resp = await fetchJSON('?ajax=get_list&list=' + listName); return resp.data || []; } catch { return []; } }

let allModels = [];
async function loadModels() { try { allModels = await loadList('device_models'); } catch { allModels = []; } }

function populateModelSelect(select, vendorId) {
    const currentValue = select.value;
    select.innerHTML = '';
    select.appendChild(new Option('-- не выбрано --', ''));
    if (allModels.length > 0) {
        allModels.forEach(m => select.appendChild(new Option(m.name, m.id)));
    } else {
        select.appendChild(new Option('Нет моделей', ''));
    }
    if (currentValue && Array.from(select.options).some(o => String(o.value) === String(currentValue))) {
        select.value = currentValue;
    }
    if (select.searchableInstance) {
        select.searchableInstance.options = Array.from(select.options).filter(o => o.value !== '__add_new__');
        select.searchableInstance.updateDropdown('');
        select.searchableInstance.syncInputWithSelect();
    }
}

function updateModelSelect(select, vendorId) { populateModelSelect(select, vendorId); }

async function reloadModelsForVendor(modelSelect, vendorId, selectModelId = null) {
    modelSelect.innerHTML = '<option value="">-- загрузка --</option>';
    if (modelSelect.searchableInstance) {
        modelSelect.searchableInstance.options = [];
        modelSelect.searchableInstance.updateDropdown('');
        modelSelect.searchableInstance.syncInputWithSelect();
    }

    if (!vendorId) {
        modelSelect.innerHTML = '<option value="">-- выберите модель --</option>';
        const addOpt = new Option('+ Добавить модель', '__add_new__');
        addOpt.style.fontStyle = 'italic';
        modelSelect.appendChild(addOpt);
        if (modelSelect.searchableInstance) {
            modelSelect.searchableInstance.options = Array.from(modelSelect.options).filter(o => o.value !== '__add_new__');
            modelSelect.searchableInstance.updateDropdown('');
            modelSelect.searchableInstance.syncInputWithSelect();
        }
        return;
    }

    try {
        const resp = await fetch(`?ajax=get_list_models&vendor_id=${vendorId}`);
        const data = await resp.json();
        const models = data.data || [];

        modelSelect.innerHTML = '<option value="">-- выберите модель --</option>';
        models.forEach(m => modelSelect.add(new Option(m.name, m.id)));
        const addOpt = new Option('+ Добавить модель', '__add_new__');
        addOpt.style.fontStyle = 'italic';
        modelSelect.appendChild(addOpt);

        if (selectModelId) modelSelect.value = selectModelId;

        if (modelSelect.searchableInstance) {
            modelSelect.searchableInstance.options = Array.from(modelSelect.options).filter(o => o.value !== '__add_new__');
            modelSelect.searchableInstance.updateDropdown('');
            modelSelect.searchableInstance.syncInputWithSelect();
        }
    } catch (e) {
        console.error('Ошибка загрузки моделей:', e);
        modelSelect.innerHTML = '<option value="">-- ошибка загрузки --</option>';
        if (modelSelect.searchableInstance) {
            modelSelect.searchableInstance.options = [];
            modelSelect.searchableInstance.updateDropdown('');
            modelSelect.searchableInstance.syncInputWithSelect();
        }
    }
}

function formatMacAddress(value) {
    let hex = value.replace(/[^0-9a-fA-F]/g, '');
    hex = hex.toLowerCase();
    let result = '';
    for (let i = 0; i < hex.length; i += 4) {
        if (i > 0) result += '-';
        result += hex.substr(i, 4);
    }
    return result.substr(0, 14);
}