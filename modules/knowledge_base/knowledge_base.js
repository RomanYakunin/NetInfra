// modules/knowledge_base/knowledge_base.js — страница «База знаний»
(function () {
    'use strict';

    // ---------- Состояние ----------
    let tables = [];            // список таблиц БД
    let currentTable = null;    // выбранная таблица
    let columns = [];           // имена столбцов текущей таблицы
    let structure = [];         // полное описание столбцов (SHOW FULL COLUMNS)
    let primaryKey = null;      // имя первичного ключа
    let rows = [];              // текущая страница данных
    let page = 1;
    let perPage = 25;
    let totalPages = 1;
    let total = 0;
    let sortCol = '';
    let sortDir = 'ASC';
    let searchQuery = '';
    let searchTimer = null;
    let editingPk = null;       // PK редактируемой записи (null — добавление)

    function esc(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type);
        else alert(msg);
    }

    async function apiGet(action, query = '') {
        const resp = await fetch(`?ajax=${action}${query}`);
        return resp.json();
    }

    async function apiPost(action, payload) {
        const resp = await fetch(`?ajax=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        return resp.json();
    }

    // ---------- Список таблиц ----------
    async function loadTables() {
        const list = document.getElementById('kbTableList');
        if (!list) return;
        try {
            const data = await apiGet('kb_get_tables');
            if (data.error) {
                list.innerHTML = `<li class="kb-muted kb-error">${esc(data.error)}</li>`;
                return;
            }
            tables = data.data || [];
            renderTableList();
        } catch (e) {
            list.innerHTML = '<li class="kb-muted kb-error">Ошибка загрузки</li>';
        }
    }

    function renderTableList() {
        const list = document.getElementById('kbTableList');
        if (!list) return;

        const filter = (document.getElementById('kbTableFilter')?.value || '').toLowerCase();
        const visible = tables.filter(t => !filter || t.name.toLowerCase().includes(filter));

        if (!visible.length) {
            list.innerHTML = '<li class="kb-muted">Ничего не найдено</li>';
            return;
        }

        list.innerHTML = visible.map(t => `
            <li class="kb-table-item${t.name === currentTable ? ' active' : ''}" data-table="${esc(t.name)}">
                <span class="kb-table-name">${esc(t.name)}</span>
                <span class="kb-table-rows">${t.rows === null ? '—' : t.rows}</span>
            </li>`).join('');

        list.querySelectorAll('.kb-table-item').forEach(li => {
            li.addEventListener('click', () => selectTable(li.dataset.table));
        });
    }

    // ---------- Выбор таблицы ----------
    async function selectTable(tableName) {
        currentTable = tableName;
        page = 1;
        sortCol = '';
        sortDir = 'ASC';
        searchQuery = '';
        const searchInput = document.getElementById('kbSearch');
        if (searchInput) searchInput.value = '';

        document.getElementById('kbEmptyState').style.display = 'none';
        document.getElementById('kbContent').style.display = '';
        document.getElementById('kbTableName').textContent = tableName;

        renderTableList();
        await loadStructure();
        await loadRows();
    }

    // ---------- Структура ----------
    async function loadStructure() {
        if (!currentTable) return;
        try {
            const data = await apiGet('kb_get_columns', `&table=${encodeURIComponent(currentTable)}`);
            if (data.error) { toast(data.error, 'error'); return; }
            structure = data.data || [];
            primaryKey = data.primary_key || null;
            renderStructure();
        } catch (e) { toast('Ошибка загрузки структуры', 'error'); }
    }

    function renderStructure() {
        const tbody = document.getElementById('kbStructureBody');
        if (!tbody) return;

        tbody.innerHTML = structure.map(col => {
            const isPk = primaryKey && col.Field === primaryKey;
            return `
            <tr data-column="${esc(col.Field)}">
                <td class="kb-strong">${esc(col.Field)}${isPk ? ' <span class="kb-pk" title="Первичный ключ">🔑</span>' : ''}</td>
                <td><code>${esc(col.Type)}</code></td>
                <td>${col.Null === 'YES' ? 'да' : 'нет'}</td>
                <td>${esc(col.Key || '—')}</td>
                <td>${col.Default === null ? '<span class="kb-muted">NULL</span>' : esc(col.Default)}</td>
                <td class="kb-muted">${esc(col.Extra || '—')}</td>
                <td class="kb-row-actions">
                    <button type="button" class="btn small" data-act="edit-col" ${isPk ? 'disabled title="Первичный ключ изменять нельзя"' : ''}>✎</button>
                    <button type="button" class="btn small danger" data-act="del-col" ${isPk ? 'disabled title="Первичный ключ удалить нельзя"' : ''}>🗑</button>
                </td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('tr[data-column]').forEach(tr => {
            const colName = tr.dataset.column;
            tr.querySelector('[data-act="edit-col"]')?.addEventListener('click', () => openColumnModal(colName));
            tr.querySelector('[data-act="del-col"]')?.addEventListener('click', () => deleteColumn(colName));
        });
    }

    // ---------- Данные ----------
    async function loadRows() {
        if (!currentTable) return;
        const tbody = document.getElementById('kbTableBody');
        if (tbody) tbody.innerHTML = '<tr><td class="kb-muted" style="padding:1.5rem;">Загрузка…</td></tr>';

        const params = new URLSearchParams({ table: currentTable, page: page, per_page: perPage });
        if (searchQuery) params.set('search', searchQuery);
        if (sortCol) { params.set('sort', sortCol); params.set('order', sortDir); }

        try {
            const data = await apiGet('kb_get_rows', '&' + params.toString());
            if (data.error) {
                if (tbody) tbody.innerHTML = `<tr><td class="kb-muted kb-error" style="padding:1.5rem;">${esc(data.error)}</td></tr>`;
                return;
            }
            columns = data.columns || [];
            primaryKey = data.primary_key || null;
            rows = data.data || [];
            total = data.total || 0;
            page = data.page || 1;
            totalPages = data.total_pages || 1;

            document.getElementById('kbTableMeta').textContent =
                `${total} записей · ${columns.length} столбцов` +
                (primaryKey ? ` · PK: ${primaryKey}` : ' · без первичного ключа');

            renderRows();
            renderPagination();
        } catch (e) {
            if (tbody) tbody.innerHTML = '<tr><td class="kb-muted kb-error" style="padding:1.5rem;">Ошибка загрузки</td></tr>';
        }
    }

    function renderRows() {
        const thead = document.getElementById('kbTableHead');
        const tbody = document.getElementById('kbTableBody');
        if (!thead || !tbody) return;

        // Шапка с сортировкой
        thead.innerHTML = '<tr>' + columns.map(c => {
            const active = c === sortCol;
            const arrow = active ? (sortDir === 'ASC' ? ' ▲' : ' ▼') : '';
            return `<th data-col="${esc(c)}" class="kb-sortable${active ? ' sorted' : ''}">${esc(c)}${arrow}</th>`;
        }).join('') + '<th style="width:1%;"></th></tr>';

        thead.querySelectorAll('.kb-sortable').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.col;
                if (sortCol === col) sortDir = sortDir === 'ASC' ? 'DESC' : 'ASC';
                else { sortCol = col; sortDir = 'ASC'; }
                page = 1;
                loadRows();
            });
        });

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="${columns.length + 1}" class="kb-muted" style="padding:1.5rem; text-align:center;">Нет данных</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map((row, idx) => {
            const pkVal = primaryKey ? row[primaryKey] : null;
            const cells = columns.map(c => {
                const v = row[c];
                const display = v === null
                    ? '<span class="kb-muted">NULL</span>'
                    : esc(String(v).length > 120 ? String(v).slice(0, 120) + '…' : String(v));
                return `<td title="${esc(v === null ? 'NULL' : String(v))}">${display}</td>`;
            }).join('');

            return `
            <tr data-idx="${idx}" data-pk="${esc(pkVal === null ? '' : String(pkVal))}">
                ${cells}
                <td class="kb-row-actions">
                    <button type="button" class="btn small" data-act="edit-row" ${primaryKey ? '' : 'disabled title="Нет первичного ключа"'}>✎</button>
                    <button type="button" class="btn small danger" data-act="del-row" ${primaryKey ? '' : 'disabled title="Нет первичного ключа"'}>🗑</button>
                </td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('tr[data-idx]').forEach(tr => {
            const idx = parseInt(tr.dataset.idx, 10);
            tr.querySelector('[data-act="edit-row"]')?.addEventListener('click', () => openRowModal(rows[idx]));
            tr.querySelector('[data-act="del-row"]')?.addEventListener('click', () => deleteRow(rows[idx]));
        });
    }

    function renderPagination() {
        const el = document.getElementById('kbPagination');
        if (!el) return;
        if (totalPages <= 1) { el.innerHTML = ''; return; }

        const btn = (p, label, disabled, active) =>
            `<button type="button" class="kb-page-btn${active ? ' active' : ''}" data-page="${p}" ${disabled ? 'disabled' : ''}>${label}</button>`;

        let html = btn(page - 1, '‹', page <= 1, false);

        // Окно из пяти страниц вокруг текущей
        const from = Math.max(1, page - 2);
        const to = Math.min(totalPages, from + 4);
        if (from > 1) html += btn(1, '1', false, false) + (from > 2 ? '<span class="kb-page-gap">…</span>' : '');
        for (let p = from; p <= to; p++) html += btn(p, String(p), false, p === page);
        if (to < totalPages) html += (to < totalPages - 1 ? '<span class="kb-page-gap">…</span>' : '') + btn(totalPages, String(totalPages), false, false);

        html += btn(page + 1, '›', page >= totalPages, false);
        html += `<span class="kb-page-info">стр. ${page} из ${totalPages}</span>`;
        el.innerHTML = html;

        el.querySelectorAll('.kb-page-btn').forEach(b => {
            b.addEventListener('click', () => {
                const p = parseInt(b.dataset.page, 10);
                if (p >= 1 && p <= totalPages && p !== page) { page = p; loadRows(); }
            });
        });
    }

    // ---------- Модалка записи ----------
    function openRowModal(row = null) {
        const modal = document.getElementById('kbRowModal');
        const fields = document.getElementById('kbRowFields');
        if (!modal || !fields) return;

        editingPk = row && primaryKey ? row[primaryKey] : null;
        document.getElementById('kbRowModalTitle').textContent = row ? 'Редактировать запись' : 'Добавить запись';
        hideError('kbRowError');

        fields.innerHTML = structure.map(col => {
            const name = col.Field;
            const isPk = primaryKey && name === primaryKey;
            const isAuto = (col.Extra || '').includes('auto_increment');
            const value = row ? (row[name] === null ? '' : row[name]) : '';
            // Автоинкрементный ключ при добавлении не показываем
            if (isAuto && !row) return '';

            const longText = /text|json|blob/i.test(col.Type);
            const input = longText
                ? `<textarea name="${esc(name)}" rows="3" ${isPk ? 'readonly' : ''}>${esc(value)}</textarea>`
                : `<input type="text" name="${esc(name)}" value="${esc(value)}" ${isPk ? 'readonly' : ''}>`;

            return `
                <div class="form-group">
                    <label>${esc(name)} <span class="kb-muted">${esc(col.Type)}${col.Null === 'YES' ? '' : ' · NOT NULL'}</span></label>
                    ${input}
                </div>`;
        }).join('');

        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');
    }

    window.kbCloseRowModal = function () {
        document.getElementById('kbRowModal')?.classList.remove('visible');
        hideError('kbRowError');
    };

    async function submitRow(e) {
        e.preventDefault();
        hideError('kbRowError');
        if (!currentTable) return;

        const values = {};
        e.target.querySelectorAll('input[name], textarea[name]').forEach(el => {
            if (el.readOnly) return;   // первичный ключ не отправляем
            values[el.name] = el.value;
        });

        const isEdit = editingPk !== null;
        const payload = { table: currentTable, values };
        if (isEdit) payload.pk_value = editingPk;

        try {
            const data = await apiPost(isEdit ? 'kb_update_row' : 'kb_add_row', payload);
            if (data.success) {
                window.kbCloseRowModal();
                toast(isEdit ? 'Запись обновлена' : 'Запись добавлена', 'success');
                await loadRows();
                await loadTables();   // обновляем счётчик строк слева
            } else {
                showError('kbRowError', data.error || 'Ошибка сохранения');
            }
        } catch (err) { showError('kbRowError', 'Ошибка сети'); }
    }

    async function deleteRow(row) {
        if (!primaryKey || !row) return;
        const pkVal = row[primaryKey];
        if (!confirm(`Удалить запись ${primaryKey} = ${pkVal}? Действие необратимо.`)) return;

        try {
            const data = await apiPost('kb_delete_row', { table: currentTable, pk_value: pkVal });
            if (data.success) {
                toast('Запись удалена', 'success');
                await loadRows();
                await loadTables();
            } else {
                toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) { toast('Ошибка сети', 'error'); }
    }

    // ---------- Модалка столбца ----------
    function openColumnModal(columnName = null) {
        const modal = document.getElementById('kbColumnModal');
        if (!modal) return;
        hideError('kbColumnError');

        const isEdit = columnName !== null;
        const col = isEdit ? structure.find(c => c.Field === columnName) : null;

        document.getElementById('kbColumnModalTitle').textContent =
            isEdit ? `Изменить столбец «${columnName}»` : 'Добавить столбец';
        document.getElementById('kbColumnOriginalName').value = isEdit ? columnName : '';
        document.getElementById('kbColumnName').value = isEdit ? columnName : '';
        document.getElementById('kbColumnNullable').checked = col ? col.Null === 'YES' : true;
        document.getElementById('kbColumnDefault').value = (col && col.Default !== null) ? col.Default : '';

        // Текущий тип столбца может отсутствовать в списке — добавим его отдельной опцией
        const typeSelect = document.getElementById('kbColumnType');
        typeSelect.querySelectorAll('option[data-current]').forEach(o => o.remove());
        if (col) {
            const match = Array.from(typeSelect.options)
                .find(o => o.value.toUpperCase() === col.Type.toUpperCase());
            if (match) {
                typeSelect.value = match.value;
            } else {
                const opt = new Option(col.Type + ' (текущий)', col.Type);
                opt.dataset.current = '1';
                typeSelect.add(opt, 0);
                typeSelect.value = col.Type;
            }
        } else {
            typeSelect.value = 'VARCHAR(100)';
        }

        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');
    }

    window.kbCloseColumnModal = function () {
        document.getElementById('kbColumnModal')?.classList.remove('visible');
        hideError('kbColumnError');
    };

    async function submitColumn(e) {
        e.preventDefault();
        hideError('kbColumnError');
        if (!currentTable) return;

        const original = document.getElementById('kbColumnOriginalName').value;
        const isEdit = original !== '';

        const payload = {
            table: currentTable,
            type: document.getElementById('kbColumnType').value,
            nullable: document.getElementById('kbColumnNullable').checked,
            default: document.getElementById('kbColumnDefault').value
        };
        if (isEdit) {
            payload.column = original;
            payload.new_name = document.getElementById('kbColumnName').value.trim();
        } else {
            payload.column = document.getElementById('kbColumnName').value.trim();
        }

        try {
            const data = await apiPost(isEdit ? 'kb_update_column' : 'kb_add_column', payload);
            if (data.success) {
                window.kbCloseColumnModal();
                toast(isEdit ? 'Столбец изменён' : 'Столбец добавлен', 'success');
                await loadStructure();
                await loadRows();
            } else {
                showError('kbColumnError', data.error || 'Ошибка');
            }
        } catch (err) { showError('kbColumnError', 'Ошибка сети'); }
    }

    async function deleteColumn(columnName) {
        if (!confirm(`Удалить столбец «${columnName}» вместе со всеми его данными? Действие необратимо.`)) return;
        try {
            const data = await apiPost('kb_delete_column', { table: currentTable, column: columnName });
            if (data.success) {
                toast('Столбец удалён', 'success');
                await loadStructure();
                await loadRows();
            } else {
                toast(data.error || 'Ошибка', 'error');
            }
        } catch (e) { toast('Ошибка сети', 'error'); }
    }

    // ---------- Экспорт CSV (текущая страница) ----------
    function exportCsv() {
        if (!rows.length) { toast('Нет данных для экспорта', 'info'); return; }
        const quote = v => '"' + String(v === null ? '' : v).replace(/"/g, '""') + '"';
        const lines = [columns.map(quote).join(';')];
        rows.forEach(r => lines.push(columns.map(c => quote(r[c])).join(';')));

        // BOM — чтобы Excel корректно распознал UTF-8
        const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${currentTable}_page${page}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    // ---------- Вспомогательное ----------
    function showError(id, msg) {
        const el = document.getElementById(id);
        if (el) { el.textContent = msg; el.style.display = ''; }
    }
    function hideError(id) {
        const el = document.getElementById(id);
        if (el) { el.textContent = ''; el.style.display = 'none'; }
    }

    // ---------- Инициализация ----------
    function init() {
        if (!document.querySelector('.kb-page')) return;   // не наша страница

        document.getElementById('kbTableFilter')?.addEventListener('input', renderTableList);

        document.getElementById('kbSearch')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            const val = e.target.value.trim();
            searchTimer = setTimeout(() => {
                searchQuery = val;
                page = 1;
                loadRows();
            }, 300);
        });

        document.getElementById('kbPerPage')?.addEventListener('change', (e) => {
            perPage = parseInt(e.target.value, 10) || 25;
            page = 1;
            loadRows();
        });

        document.getElementById('kbAddRowBtn')?.addEventListener('click', () => openRowModal(null));
        document.getElementById('kbAddColumnBtn')?.addEventListener('click', () => openColumnModal(null));
        document.getElementById('kbExportBtn')?.addEventListener('click', exportCsv);

        document.getElementById('kbRowForm')?.addEventListener('submit', submitRow);
        document.getElementById('kbColumnForm')?.addEventListener('submit', submitColumn);

        // Переключение вкладок «Данные» / «Структура»
        document.querySelectorAll('.kb-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.kb-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const target = tab.dataset.tab;
                document.getElementById('kbTabData').style.display = target === 'data' ? '' : 'none';
                document.getElementById('kbTabStructure').style.display = target === 'structure' ? '' : 'none';
            });
        });

        loadTables();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
