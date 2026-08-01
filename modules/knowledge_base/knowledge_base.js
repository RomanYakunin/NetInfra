// modules/knowledge_base/knowledge_base.js

let currentTable = 'buildings';
let currentColumns = [];
let currentRows = [];
let state = {
    page: 1,
    perPage: 25,
    search: '',
    sortField: null,
    sortOrder: 'asc'
};

const tableList = [
    'buildings', 'cabinets', 'device_models', 'device_types',
    'equipment', 'firmwares', 'ip_address', 'locations',
    'nodes', 'node_types', 'vendors', 'warehouses', 'equipment_groups', 'equipment_services', 'service_instructions'
];

// Инициализация
document.addEventListener('DOMContentLoaded', () => {
    renderTableList();
    if (tableList.length > 0) selectTable(tableList[0]);
    document.getElementById('kbSearch').addEventListener('input', debounce(() => { state.search = this.value; state.page = 1; loadTableData(); }, 400));
    document.getElementById('kbPerPage').addEventListener('change', (e) => { state.perPage = parseInt(e.target.value); state.page = 1; loadTableData(); });
    document.getElementById('kbAddBtn').addEventListener('click', () => openRecordForm());
    document.getElementById('kbExportBtn').addEventListener('click', exportTable);
    document.getElementById('kbModalCancel').addEventListener('click', closeModal);
    document.getElementById('kbModalForm').addEventListener('submit', saveRecord);
});

function renderTableList() {
    const ul = document.getElementById('kbTableList');
    ul.innerHTML = tableList.map(t => `<li data-table="${t}">${capitalize(t)}</li>`).join('');
    ul.querySelectorAll('li').forEach(li => {
        li.addEventListener('click', () => selectTable(li.dataset.table));
    });
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ');
}

function selectTable(table) {
    currentTable = table;
    document.querySelectorAll('#kbTableList li').forEach(li => li.classList.remove('active'));
    const activeLi = document.querySelector(`#kbTableList li[data-table="${table}"]`);
    if (activeLi) activeLi.classList.add('active');
    state.page = 1;
    state.sortField = null;
    loadTableData();
}

async function loadTableData() {
    const params = new URLSearchParams({
        table: currentTable,
        action: 'list',
        page: state.page,
        per_page: state.perPage,
        search: state.search,
        sort_field: state.sortField || '',
        sort_order: state.sortOrder
    });
    const resp = await fetch(`?ajax=kb&${params.toString()}`);
    const data = await resp.json();
    if (data.error) {
        showToast(data.error, 'error');
        return;
    }
    currentRows = data.rows;
    renderTable(data.rows, data.total);
}

function renderTable(rows, total) {
    // Получим столбцы из первой строки или из метаданных (если нет строк, столбцы неизвестны)
    let cols = [];
    if (rows.length > 0) {
        cols = Object.keys(rows[0]);
    } else {
        cols = currentColumns;
    }
    currentColumns = cols;

    const thead = document.getElementById('kbTableHead');
    thead.innerHTML = '<tr>' + cols.map(col => `<th data-field="${col}" class="${col===state.sortField?'sorted':''}">${col} <span class="sort-icon">${col===state.sortField ? (state.sortOrder==='asc'?'↑':'↓') : '↕'}</span></th>`).join('') + '<th></th></tr>';
    thead.querySelectorAll('th[data-field]').forEach(th => {
        th.addEventListener('click', () => {
            const field = th.dataset.field;
            if (state.sortField === field) {
                state.sortOrder = state.sortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                state.sortField = field;
                state.sortOrder = 'asc';
            }
            loadTableData();
        });
    });

    const tbody = document.getElementById('kbTableBody');
    tbody.innerHTML = rows.map(row => `<tr>${cols.map(col => `<td>${escapeHtml(String(row[col] ?? ''))}</td>`).join('')}<td>
        <button class="btn small secondary edit-btn" data-id="${row[cols[0]]}">✏️</button>
        <button class="btn small danger delete-btn" data-id="${row[cols[0]]}">✕</button>
    </td></tr>`).join('');

    // Навесить обработчики кнопок
    tbody.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => openRecordForm(btn.dataset.id));
    });
    tbody.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', () => deleteRecord(btn.dataset.id));
    });

    renderPagination(total);
}

function renderPagination(total) {
    const totalPages = Math.ceil(total / state.perPage);
    const container = document.getElementById('kbPagination');
    if (totalPages <= 1) {
        container.innerHTML = `<span class="page-info">Всего записей: ${total}</span>`;
        return;
    }
    let html = `<button ${state.page===1?'disabled':''} data-page="1">««</button>`;
    html += `<button ${state.page===1?'disabled':''} data-page="${state.page-1}">«</button>`;
    const start = Math.max(1, state.page-2);
    const end = Math.min(totalPages, state.page+2);
    for (let i = start; i <= end; i++) {
        html += `<button class="${i===state.page?'active':''}" data-page="${i}">${i}</button>`;
    }
    html += `<button ${state.page===totalPages?'disabled':''} data-page="${state.page+1}">»</button>`;
    html += `<button ${state.page===totalPages?'disabled':''} data-page="${totalPages}">»»</button>`;
    html += `<span class="page-info">Стр. ${state.page} из ${totalPages} (${total} записей)</span>`;
    container.innerHTML = html;
    container.querySelectorAll('button[data-page]').forEach(btn => {
        btn.addEventListener('click', () => {
            state.page = parseInt(btn.dataset.page);
            loadTableData();
        });
    });
}

// Форма редактирования / добавления
async function openRecordForm(id = null) {
    const modal = document.getElementById('kbModalOverlay');
    const fieldsDiv = document.getElementById('kbModalFields');
    const title = document.getElementById('kbModalTitle');
    let record = {};
    if (id) {
        title.textContent = 'Редактировать запись';
        // Загружаем данные строки
        const row = currentRows.find(r => r[currentColumns[0]] == id);
        if (row) record = row;
    } else {
        title.textContent = 'Добавить запись';
    }

    let html = '';
    for (let col of currentColumns) {
        const val = record[col] ?? '';
        html += `<div class="form-group"><label>${col}</label><input name="${col}" value="${escapeHtml(val)}"></div>`;
    }
    fieldsDiv.innerHTML = html;
    document.getElementById('kbModalForm').dataset.id = id || '';
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('kbModalOverlay').style.display = 'none';
}

async function saveRecord(e) {
    e.preventDefault();
    const id = document.getElementById('kbModalForm').dataset.id;
    const formData = new FormData(e.target);
    const action = id ? 'update' : 'add';
    formData.append('table', currentTable);
    formData.append('action', action);
    if (id) formData.append(currentColumns[0], id);

    const resp = await fetch('?ajax=kb', { method: 'POST', body: formData });
    const data = await resp.json();
    if (data.success) {
        showToast(id ? 'Запись обновлена' : 'Запись добавлена', 'success');
        closeModal();
        loadTableData();
    } else {
        showToast(data.error || 'Ошибка', 'error');
    }
}

async function deleteRecord(id) {
    if (!confirm('Удалить запись?')) return;
    const formData = new FormData();
    formData.append('table', currentTable);
    formData.append('action', 'delete');
    formData.append(currentColumns[0], id);
    const resp = await fetch('?ajax=kb', { method: 'POST', body: formData });
    const data = await resp.json();
    if (data.success) {
        showToast('Запись удалена', 'success');
        loadTableData();
    } else {
        showToast(data.error || 'Ошибка', 'error');
    }
}

function exportTable() {
    const params = new URLSearchParams({
        table: currentTable,
        action: 'list',
        search: state.search,
        sort_field: state.sortField || '',
        sort_order: state.sortOrder,
        // все строки
        page: 1,
        per_page: 10000
    });
    fetch(`?ajax=kb&${params.toString()}`)
        .then(r => r.json())
        .then(data => {
            const rows = data.rows || [];
            let csv = '\uFEFF' + currentColumns.join(';') + '\n';
            rows.forEach(row => {
                csv += currentColumns.map(col => `"${String(row[col] ?? '').replace(/"/g, '""')}"`).join(';') + '\n';
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `${currentTable}.csv`;
            a.click();
        });
}

function debounce(fn, delay) {
    let t;
    return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), delay);
    };
}