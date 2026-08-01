<!-- modules/edit_form/edit_form_modals.php -->
<!-- Модальное окно редактирования узла / оборудования -->

<div class="add-form-modal" id="editFormModal">
    <div class="modal-content">
        <h3 id="editFormTitle">Редактировать</h3>
        <form id="editForm">
            <div id="editFormFields"></div>
            <input type="hidden" name="id" id="editFormId">
            <input type="hidden" name="type" id="editFormType">
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeEditForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить изменения</button>
            </div>
        </form>
    </div>
</div>

<script>
// Флаг, чтобы поля формы загружались один раз
let editFormFieldsLoaded = {};

// Загрузка списков справочников (упрощённый вариант)
async function loadSelectOptions(select, source) {
    if (select.options.length > 1) return; // уже заполнены
    const listName = source.replace('_list', '');
    if (typeof loadList !== 'function') {
        // fallback если loadList не определён (из add_form.js)
        // попробуем встроенный fetch
        try {
            const res = await fetch(`?ajax=get_list&list=${listName}`);
            const data = await res.json();
            const items = data.data || [];
            for (const item of items) {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.name;
                select.appendChild(opt);
            }
        } catch(e) {}
    } else {
        const items = await loadList(listName);
        for (const item of items) {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.name;
            select.appendChild(opt);
        }
    }
}

// Загружает поля формы редактирования (один раз для типа)
async function loadEditFormFields(type) {
    const container = document.getElementById('editFormFields');
    if (editFormFieldsLoaded[type]) return; // уже загружены
    editFormFieldsLoaded[type] = true;

    let fields = [];
    if (type === 'node') {
        try {
            const res = await fetch('?ajax=get_node_columns');
            const cols = await res.json();
            fields = cols.map(col => ({
                name: col.name,
                label: col.label,
                type: col.type || 'text',
                source: col.source || null
            }));
        } catch {
            fields = [
                {name:'KY_number', label:'Номер КУ', type:'text'},
                {name:'id_location', label:'Расположение', type:'select', source:'locations'},
                {name:'node_type_id', label:'Тип узла', type:'select', source:'node_types'}
            ];
        }
    } else if (type === 'equipment') {
        try {
            const res = await fetch('?ajax=get_equipment_columns');
            const cols = await res.json();
            fields = cols;
        } catch {
            fields = [
                {name:'Groupe', label:'Группа', type:'select', source:'Type_group_list'},
                {name:'ip_address', label:'IP-адрес', type:'select', source:'ip_address_list'},
                {name:'hostname', label:'Имя хоста', type:'text'},
                {name:'device_type_id', label:'Тип устройства', type:'select', source:'device_types_list'},
                {name:'vendor_id', label:'Производитель', type:'select', source:'vendors_list'},
                {name:'model_id', label:'Модель', type:'select', source:'device_models_list'},
                {name:'serial_number', label:'Серийный номер', type:'text'},
                {name:'mac_address', label:'MAC-адрес', type:'text'},
                {name:'firmwares', label:'Прошивка', type:'select', source:'firmwares_list'},
                {name:'id_cabinet', label:'Шкаф', type:'select', source:'cabinets_list'},
                {name:'unit_position', label:'Юнит', type:'number'}
            ];
        }
    }

    // Генерируем поля
    container.innerHTML = '';
    for (const field of fields) {
        const div = document.createElement('div');
        div.className = 'form-group';
        const label = document.createElement('label');
        label.textContent = field.label;

        if (field.type === 'select' && field.source) {
            const select = document.createElement('select');
            select.name = field.name;
            select.dataset.source = field.source;
            // Добавляем пустой option по умолчанию
            select.appendChild(new Option('-- не выбрано --', ''));
            div.appendChild(label);
            div.appendChild(select);
        } else {
            const input = document.createElement('input');
            input.type = field.type;
            input.name = field.name;
            div.appendChild(label);
            div.appendChild(input);
        }
        container.appendChild(div);
    }
}

// Заполняет поля формы данными
async function fillEditForm(type, data) {
    const selects = document.querySelectorAll('#editForm select[data-source]');
    // Сначала загрузим опции для всех селектов
    for (const select of selects) {
        const source = select.dataset.source;
        await loadSelectOptions(select, source);
        // Устанавливаем значение из data
        const fieldName = select.name;
        if (data[fieldName] !== undefined) {
            select.value = data[fieldName];
        }
    }
    // Заполняем текстовые поля
    for (const [key, value] of Object.entries(data)) {
        const field = document.querySelector(`#editForm [name="${key}"]`);
        if (field && field.tagName !== 'SELECT') {
            field.value = value;
        }
    }
}

// Открывает окно редактирования для узла
async function openEditNodeModal(nodeId) {
    try {
        const res = await fetch(`?ajax=get_node&id=${encodeURIComponent(nodeId)}`);
        const data = await res.json();
        if (data.error) { alert(data.error); return; }

        // Загружаем поля для узла (если ещё не)
        await loadEditFormFields('node');
        // Сбрасываем форму
        document.getElementById('editForm').reset();
        document.getElementById('editFormId').value = data.id;
        document.getElementById('editFormType').value = 'node';
        document.getElementById('editFormTitle').textContent = 'Редактировать узел: КУ-' + data.KY_number;

        // Заполняем поля
        await fillEditForm('node', data);

        // Показываем модальное окно
        document.getElementById('editFormModal').classList.add('visible');
    } catch (e) {
        console.error(e);
        alert('Ошибка загрузки узла');
    }
}

// Открывает окно редактирования для оборудования
async function openEditEquipmentModal(equipmentId) {
    try {
        const res = await fetch(`?ajax=get_equipment_item&id=${encodeURIComponent(equipmentId)}`);
        const data = await res.json();
        if (data.error) { alert(data.error); return; }

        await loadEditFormFields('equipment');
        document.getElementById('editForm').reset();
        document.getElementById('editFormId').value = data.id;
        document.getElementById('editFormType').value = 'equipment';
        document.getElementById('editFormTitle').textContent = 'Редактировать: ' + (data.hostname || ('Оборудование #' + data.id));

        await fillEditForm('equipment', data);

        document.getElementById('editFormModal').classList.add('visible');
    } catch (e) {
        console.error(e);
        alert('Ошибка загрузки оборудования');
    }
}

// Закрыть окно
function closeEditForm() {
    document.getElementById('editFormModal').classList.remove('visible');
}

// Отправка формы редактирования
document.getElementById('editForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const type = document.getElementById('editFormType').value;
    const id = document.getElementById('editFormId').value;
    const formData = new FormData(this);
    formData.append('id', id);

    let url = type === 'node' ? '?ajax=update_node' : '?ajax=update_equipment';

    try {
        const res = await fetch(url, { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            closeEditForm();
            location.reload();
        } else {
            alert(result.error || 'Ошибка обновления');
        }
    } catch {
        alert('Ошибка сети');
    }
});
</script>