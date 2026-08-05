/* ============================================================
   modules/checklist_table/checklist_table.js

   Автоматическая задача «Подключить Zabbix»: сверяет наше
   оборудование с узлами мониторинга и показывает то, чего в Zabbix
   нет. В таблице checklist эта задача не хранится — она всегда
   отражает текущее состояние, а не снимок на момент создания.
   ============================================================ */
(function () {
    'use strict';

    function esc(s) {
        if (s === null || s === undefined) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    let loaded = false;
    let data = null;

    function setNote(text) {
        const el = document.getElementById('zabbixTaskNote');
        if (el) el.textContent = text || '';
    }

    function renderBody() {
        const body = document.getElementById('zabbixTaskBody');
        if (!body || !data) return;

        const rows = data.missing || [];
        if (!rows.length) {
            body.innerHTML = '<div class="auto-task-ok">Всё оборудование заведено в Zabbix. '
                           + 'Проверено ' + data.covered + ' устройств.</div>';
            return;
        }

        let html = '<table class="auto-task-table"><thead><tr>'
                 + '<th>Имя хоста</th><th>IP-адрес</th><th>Тип</th>'
                 + '<th>Модель</th><th>Узел</th><th>Расположение</th>'
                 + '</tr></thead><tbody>';

        rows.forEach(r => {
            html += '<tr data-equipment="' + r.id + '">'
                 + '<td class="at-host">' + esc(r.hostname || '—') + '</td>'
                 + '<td class="at-ip">' + (r.ip_address
                        ? esc(r.ip_address)
                        : '<span class="at-warn">не задан</span>') + '</td>'
                 + '<td>' + esc(r.device_type || '—') + '</td>'
                 + '<td>' + esc(r.model || '—') + '</td>'
                 + '<td>' + (r.ky_number ? 'КУ-' + esc(r.ky_number) : '—') + '</td>'
                 + '<td>' + esc(r.location || '—') + '</td>'
                 + '</tr>';
        });
        html += '</tbody></table>';

        // Узлы, которые есть в мониторинге, но отсутствуют у нас в учёте —
        // обратная сторона той же сверки, тоже требует внимания
        if ((data.unknown_hosts || []).length) {
            html += '<div class="auto-task-sub">В Zabbix есть, но нет у нас в учёте ('
                 + data.unknown_hosts.length + '):</div><ul class="auto-task-list">';
            data.unknown_hosts.slice(0, 50).forEach(h => {
                html += '<li>' + esc(h.name) + ' <span class="at-dim">' + esc(h.ip) + '</span></li>';
            });
            html += '</ul>';
        }

        if (data.without_ip) {
            html += '<div class="auto-task-sub">Ещё ' + data.without_ip
                 + ' устройств без имени и адреса — сопоставить их с мониторингом не по чему.</div>';
        }

        body.innerHTML = html;

        // Клик по строке открывает досье устройства
        body.querySelectorAll('tr[data-equipment]').forEach(tr => {
            tr.addEventListener('click', () => {
                const id = tr.dataset.equipment;
                if (typeof showEquipmentDetails === 'function') showEquipmentDetails(id);
                else window.location.href = '?page=nodes&equipment_id=' + id;
            });
        });
    }

    async function loadMissing() {
        const task = document.getElementById('zabbixTask');
        const body = document.getElementById('zabbixTaskBody');
        if (!task) return;

        body.innerHTML = '<div class="auto-task-loading">Сверяю с Zabbix…</div>';
        try {
            const resp = await fetch('?ajax=zabbix_missing');
            const json = await resp.json();

            if (!json.success) {
                // Выключенная интеграция — не ошибка: задачу просто не показываем
                if (json.disabled) { task.style.display = 'none'; return; }
                task.style.display = '';
                document.getElementById('zabbixTaskCount').textContent = '!';
                setNote('не удалось свериться');
                body.innerHTML = '<div class="auto-task-error">' + esc(json.error || 'Ошибка') + '</div>';
                loaded = true;
                return;
            }

            data = json;
            loaded = true;

            const count = json.missing_count;
            task.style.display = '';
            task.classList.toggle('done', count === 0);

            const badge = document.getElementById('zabbixTaskCount');
            badge.textContent = count === 0 ? '✓' : count;
            setNote(count === 0
                ? 'всё оборудование под мониторингом'
                : 'устройств вне мониторинга из ' + (count + json.covered));

            renderBody();
        } catch (e) {
            task.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const head = document.getElementById('zabbixTaskHead');
        if (!head) return;

        // Считаем сразу: количество нужно видеть, не раскрывая задачу
        loadMissing();

        head.addEventListener('click', () => {
            const task = document.getElementById('zabbixTask');
            const body = document.getElementById('zabbixTaskBody');
            const open = body.style.display !== 'none';

            body.style.display = open ? 'none' : '';
            task.classList.toggle('open', !open);
            task.querySelector('.auto-task-arrow').textContent = open ? '▶' : '▼';

            if (!open && !loaded) loadMissing();
        });
    });
})();
