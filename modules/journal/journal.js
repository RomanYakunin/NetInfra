// modules/journal/journal.js — страница «Журнал» (просмотр логов)
(function () {
    'use strict';

    let page = 1;
    let perPage = 25;
    let totalPages = 1;
    let searchTimer = null;

    // Иконки действий (локальные эмодзи, без внешних библиотек — интранет)
    const ACTION_ICONS = {
        login: '🔑', logout: '🚪',
        add_node: '➕', edit_node: '✏️', delete_node: '🗑️',
        add_equipment: '➕', edit_equipment: '✏️', delete_equipment: '🗑️',
        add_stack: '➕', edit_stack: '✏️', delete_stack_device: '🗑️',
        add_building: '🏢', edit_building: '✏️', delete_building: '🗑️',
        add_warehouse: '📦', edit_warehouse: '✏️', delete_warehouse: '🗑️',
        add_rack: '🗄️',
        add_user: '👤', edit_user: '✏️', delete_user: '🗑️'
    };

    // Человекочитаемые названия типов объектов
    const OBJECT_LABELS = {
        node: 'Узел', equipment: 'Оборудование', stack: 'Стек',
        building: 'Здание', warehouse: 'Склад', rack: 'Шкаф', user: 'Пользователь'
    };

    function esc(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type);
    }

    /** Собирает текущие значения фильтров в query-строку. */
    function buildParams() {
        const params = new URLSearchParams({ page: page, per_page: perPage });
        const map = {
            date_from: 'logDateFrom',
            date_to:   'logDateTo',
            user_id:   'logUser',
            action:    'logAction',
            search:    'logSearch'
        };
        Object.keys(map).forEach(key => {
            const val = (document.getElementById(map[key])?.value || '').trim();
            if (val) params.set(key, val);
        });
        return params;
    }

    // ---------- Загрузка ----------
    async function loadLogs(toPage = null) {
        if (toPage !== null) page = toPage;
        const tbody = document.getElementById('logsTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="journal-empty">Загрузка…</td></tr>';

        try {
            const resp = await fetch('?ajax=get_logs&' + buildParams().toString());
            const data = await resp.json();
            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="7" class="journal-empty journal-error">${esc(data.error)}</td></tr>`;
                return;
            }
            page = data.page || 1;
            totalPages = data.total_pages || 1;
            renderLogs(data.data || []);
            renderPagination(data.total || 0);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="7" class="journal-empty journal-error">Ошибка загрузки</td></tr>';
        }
    }

    function renderLogs(rows) {
        const tbody = document.getElementById('logsTableBody');
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="journal-empty">Записей не найдено</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const icon = ACTION_ICONS[r.action] || '•';
            const objLabel = OBJECT_LABELS[r.object_type] || (r.object_type || '');
            const objText = [objLabel, r.object_name].filter(Boolean).join(': ');

            // Подробности обрезаем до 100 символов — полностью видно в модалке
            let details = r.details || '';
            const truncated = details.length > 100;
            if (truncated) details = details.slice(0, 100) + '…';

            const dt = (r.created_at || '').replace('T', ' ');

            return `
            <tr data-log-id="${r.id}" class="journal-row">
                <td class="journal-muted">${r.id}</td>
                <td class="journal-nowrap">${esc(dt)}</td>
                <td>${esc(r.username || 'system')}</td>
                <td class="journal-muted journal-nowrap">${esc(r.ip_address || '—')}</td>
                <td class="journal-nowrap"><span class="journal-action-icon">${icon}</span> ${esc(r.action)}</td>
                <td>${esc(objText) || '<span class="journal-muted">—</span>'}</td>
                <td class="journal-details">${esc(details) || '<span class="journal-muted">—</span>'}</td>
            </tr>`;
        }).join('');

        // Клик по строке — полная карточка записи
        tbody.querySelectorAll('tr[data-log-id]').forEach(tr => {
            tr.addEventListener('click', () => openLogDetail(tr.dataset.logId));
        });
    }

    function renderPagination(total) {
        const el = document.getElementById('logsPagination');
        if (!el) return;

        let html = `<span class="page-info">Всего записей: ${total}</span>`;

        if (totalPages > 1) {
            const btn = (p, label, disabled, active) =>
                `<button type="button" class="page-btn${active ? ' active' : ''}" ` +
                `data-page="${p}"${disabled ? ' disabled' : ''}>${label}</button>`;

            html += '<span class="page-controls">';
            html += btn(page - 1, '‹ Предыдущая', page <= 1, false);

            const from = Math.max(1, page - 2);
            const to = Math.min(totalPages, from + 4);
            if (from > 1) {
                html += btn(1, '1', false, false);
                if (from > 2) html += '<span class="page-gap">…</span>';
            }
            for (let p = from; p <= to; p++) html += btn(p, String(p), false, p === page);
            if (to < totalPages) {
                if (to < totalPages - 1) html += '<span class="page-gap">…</span>';
                html += btn(totalPages, String(totalPages), false, false);
            }

            html += btn(page + 1, 'Следующая ›', page >= totalPages, false);
            html += `<span class="page-current">стр. ${page} из ${totalPages}</span>`;
            html += '</span>';
        }

        el.innerHTML = html;
        el.querySelectorAll('.page-btn').forEach(b => {
            b.addEventListener('click', () => {
                const p = parseInt(b.dataset.page, 10);
                if (p >= 1 && p <= totalPages && p !== page) loadLogs(p);
            });
        });
    }

    // ---------- Карточка записи ----------
    async function openLogDetail(logId) {
        const modal = document.getElementById('logDetailModal');
        const body = document.getElementById('logDetailBody');
        if (!modal || !body) return;

        body.innerHTML = '<div class="journal-muted">Загрузка…</div>';
        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');

        try {
            const resp = await fetch(`?ajax=get_log_detail&id=${encodeURIComponent(logId)}`);
            const data = await resp.json();
            if (data.error) {
                body.innerHTML = `<div class="journal-error">${esc(data.error)}</div>`;
                return;
            }
            const r = data.data;

            const rows = [
                ['ID', r.id],
                ['Дата/время', (r.created_at || '').replace('T', ' ')],
                ['Пользователь', r.username || 'system'],
                ['IP-адрес', r.ip_address || '—'],
                ['Действие', r.action],
                ['Тип объекта', OBJECT_LABELS[r.object_type] || r.object_type || '—'],
                ['ID объекта', r.object_id ?? '—'],
                ['Название объекта', r.object_name || '—']
            ];

            let html = '<dl class="journal-detail-list">';
            rows.forEach(([k, v]) => {
                html += `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`;
            });
            html += '</dl>';

            // details: если это JSON — показываем построчно, иначе как текст
            if (r.details_parsed && typeof r.details_parsed === 'object') {
                html += '<div class="journal-detail-title">Подробности</div><dl class="journal-detail-list">';
                Object.keys(r.details_parsed).forEach(k => {
                    let v = r.details_parsed[k];
                    if (v === null) v = '—';
                    else if (typeof v === 'object') v = JSON.stringify(v);
                    html += `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`;
                });
                html += '</dl>';
            } else if (r.details) {
                html += '<div class="journal-detail-title">Подробности</div>';
                html += `<pre class="journal-detail-raw">${esc(r.details)}</pre>`;
            }

            body.innerHTML = html;
        } catch (e) {
            body.innerHTML = '<div class="journal-error">Ошибка загрузки</div>';
        }
    }

    window.closeLogDetail = function () {
        document.getElementById('logDetailModal')?.classList.remove('visible');
    };

    // ---------- Инициализация ----------
    function init() {
        if (!document.querySelector('.journal-page')) return;   // не наша страница

        ['logDateFrom', 'logDateTo', 'logUser', 'logAction'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => loadLogs(1));
        });

        document.getElementById('logSearch')?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadLogs(1), 300);
        });

        document.getElementById('logPerPage')?.addEventListener('change', (e) => {
            perPage = parseInt(e.target.value, 10) || 25;
            loadLogs(1);
        });

        document.getElementById('logResetBtn')?.addEventListener('click', () => {
            ['logDateFrom', 'logDateTo', 'logSearch'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            ['logUser', 'logAction'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            loadLogs(1);
        });

        loadLogs(1);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
