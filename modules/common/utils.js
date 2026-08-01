// modules/common/utils.js
// Общие утилиты

async function fetchJSONSafe(url) {
    const res = await fetch(url);
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        console.error('JSON parse error for', url, 'Response:', text);
        return { error: 'Ошибка обработки ответа сервера' };
    }
}

async function fetchJSON(url) {
    const data = await fetchJSONSafe(url);
    if (data.error) throw new Error(data.error);
    return data;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
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

function togglePasswordVisibility(btn) {
    const input = btn.parentElement.querySelector('input');
    if (input.type === 'password') {
        input.type = 'text';
        input.classList.remove('password-blur');
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        input.classList.add('password-blur');
        btn.textContent = '👁️';
    }
}

async function loadSearchableSelectOptions(select, url, sourceName) {
    if (!select) return;
    if (select.searchableInstance) {
        try {
            const resp = await fetchJSONSafe(url);
            const items = resp.data || [];
            select.innerHTML = '<option value="">-- не выбрано --</option>';
            items.forEach(item => select.appendChild(new Option(item.name, item.id)));
            const addOpt = new Option('Добавить...', '__add_new__');
            select.appendChild(addOpt);
            select.searchableInstance.options = Array.from(select.options).filter(o => o.value !== '__add_new__');
            select.searchableInstance.updateDropdown('');
            select.searchableInstance.syncInputWithSelect();
        } catch(e) { console.error(e); }
        return;
    }
    select.innerHTML = '<option value="">-- загрузка --</option>';
    try {
        const resp = await fetchJSONSafe(url);
        const items = resp.data || [];
        select.innerHTML = '<option value="">-- не выбрано --</option>';
        items.forEach(item => select.appendChild(new Option(item.name, item.id)));
        const addOpt = new Option('Добавить...', '__add_new__');
        select.appendChild(addOpt);
        new SearchableSelect(select);
        select.dataset.source = sourceName;
    } catch(e) {
        select.innerHTML = '<option value="">-- ошибка загрузки --</option>';
    }
}

async function loadList(listName) {
    try {
        const resp = await fetchJSON('?ajax=get_list&list=' + listName);
        return resp.data || [];
    } catch {
        return [];
    }
}

// ========== Сервисные инструкции ==========
function showServiceContextMenu(x, y, serviceName) {
    const oldMenu = document.getElementById('service-ctx-menu');
    if (oldMenu) oldMenu.remove();

    const menu = document.createElement('div');
    menu.id = 'service-ctx-menu';
    menu.className = 'context-menu';
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';

    if (window.isAdmin) {
        const checkItem = document.createElement('div');
        checkItem.className = 'menu-item';
        checkItem.textContent = 'Как проверить?';
        checkItem.addEventListener('click', () => {
            const vendorSelect = document.querySelector('select[name="vendor_id"]');
            const vendorId = vendorSelect ? vendorSelect.value : '';
            if (!vendorId || vendorId === '') {
                showToast('Выберите производителя', 'warning');
            } else {
                openServiceInstruction(serviceName, vendorId);
            }
            menu.remove();
        });
        menu.appendChild(checkItem);
    } else {
        menu.remove();
        return;
    }

    document.body.appendChild(menu);

    const closeHandler = (e) => {
        if (!menu.contains(e.target)) {
            menu.remove();
            document.removeEventListener('click', closeHandler);
        }
    };
    document.addEventListener('click', closeHandler);
}

function openServiceInstruction(service, vendorId) {
    const modal = document.getElementById('serviceInstructionModal');
    if (!modal) return;

    fetch(`?ajax=get_service_instruction&service=${encodeURIComponent(service)}&vendor_id=${vendorId}`)
        .then(r => {
            if (!r.ok) throw new Error('Ошибка сети');
            return r.json();
        })
        .then(data => {
            document.getElementById('instr-title').textContent = `Инструкция по проверке: ${service}`;
            let html = '';
            if (data.image) {
                html += `<img src="${data.image}" style="max-width:100%; margin-bottom:1rem;">`;
            }
            if (data.text) {
                html += `<div style="white-space:pre-wrap;">${data.text}</div>`;
            }
            document.getElementById('instr-body').innerHTML = html || 'Инструкция отсутствует.';

            // Кнопка "Редактировать" для админов
            if (window.isAdmin) {
                const editBtn = document.createElement('button');
                editBtn.className = 'btn';
                editBtn.textContent = 'Редактировать';
                editBtn.onclick = () => enableEditMode(service, vendorId);
                const actionsContainer = document.querySelector('#serviceInstructionModal .modal-actions');
                if (actionsContainer) actionsContainer.prepend(editBtn);
            }

            modal.classList.add('visible');
        })
        .catch(err => {
            console.error(err);
            showToast('Ошибка загрузки инструкции', 'error');
        });
}

function closeServiceInstruction() {
    const modal = document.getElementById('serviceInstructionModal');
    if (modal) modal.classList.remove('visible');
}

function enableEditMode(service, vendorId) {
    const body = document.getElementById('instr-body');
    const currentText = body.querySelector('div')?.innerText || '';
    body.innerHTML = `
        <textarea id="edit-instr-text" style="width:100%; height:200px;">${currentText}</textarea>
        <div class="form-group"><label>Изображение</label><input type="file" id="edit-instr-image"></div>
        <button class="btn" id="save-instr-btn">Сохранить</button>
    `;
    document.getElementById('save-instr-btn').onclick = async () => {
        const text = document.getElementById('edit-instr-text').value;
        const fileInput = document.getElementById('edit-instr-image');
        const formData = new FormData();
        formData.append('service', service);
        formData.append('vendor_id', vendorId);
        formData.append('instruction_text', text);
        if (fileInput.files[0]) {
            formData.append('image', fileInput.files[0]);
        }
        try {
            const res = await fetch('?ajax=save_service_instruction', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                showToast('Инструкция сохранена');
                closeServiceInstruction();
            } else {
                alert('Ошибка: ' + data.error);
            }
        } catch(e) { alert('Ошибка сети'); }
    };
}