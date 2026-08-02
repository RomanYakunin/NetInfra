// ======================== Подробная информация об оборудовании ========================
async function showEquipmentDetails(equipId) {
    const modal = document.getElementById('equipmentDetailsModal');
    const dossier = document.getElementById('detailsDossier');
    const title = document.getElementById('detailsTitle');
    if (!modal || !dossier || !title) return;

    // Показываем индикатор загрузки
    dossier.innerHTML = '<div style="text-align:center; padding:2rem; color: var(--text-secondary);">Загрузка...</div>';
    title.textContent = 'Загрузка...';
    showModal(modal);

    try {
        const resp = await fetch(`?ajax=get_equipment_details&id=${equipId}`);
        const eq = await resp.json();
        if (eq.error) { alert(eq.error); return; }

        const hostname = eq.hostname || 'Без имени';
        const ip = eq.ip_address_display || '—';
        title.textContent = `📋 ${hostname} — Подробная информация`;

        let html = '';

        // Принадлежность к стеку
        if (eq.Groupe == 2) {
            const stackLabel = eq.stack_hostname ? `стека ${escapeHtml(eq.stack_hostname)}` : 'стека';
            const placeLabel = eq.KY_number ? ` (КУ-${eq.KY_number})` : '';
            html += `
            <div class="dossier-section stack-info" style="background: #f0f4ff; border-color: #667eea;">
                <h4>📦 В составе ${stackLabel}${placeLabel}${eq.Slot ? ` — слот ${eq.Slot}` : ''}</h4>
            </div>`;
        }

        // Расположение: узел (КУ) или склад
        let placement = '—';
        if (eq.KY_number) placement = `КУ-${eq.KY_number}`;
        else if (eq.warehouse_name) placement = `Склад: ${escapeHtml(eq.warehouse_name)}`;

        // Шкаф
        let rackLabel = '—';
        if (eq.rack_name || eq.rack_id) {
            rackLabel = escapeHtml(eq.rack_name || `№${eq.rack_id}`);
            const rackExtra = [];
            if (eq.rack_model_name) rackExtra.push(escapeHtml(eq.rack_model_name));
            if (eq.rack_height) rackExtra.push(`${eq.rack_height}U`);
            if (rackExtra.length) rackLabel += ` (${rackExtra.join(', ')})`;
        }

        // Досье
        const fields = [
            ['Имя хоста', escapeHtml(eq.hostname || '—')],
            ['Тип', escapeHtml(eq.device_type_name || '—')],
            ['Модель', escapeHtml(eq.model_name || '—')],
            ['Производитель', escapeHtml(eq.vendor_name || '—')],
            ['Серийный номер', escapeHtml(eq.serial_number || '—')],
            ['MAC-адрес', escapeHtml(eq.mac_address || '—')],
            ['IP-адрес', escapeHtml(ip)],
            ['PoE', eq.Poe == 1 ? 'Да' : 'Нет'],
            ['Прошивка', escapeHtml(eq.firmware_name || '—')],
            ['Расположение', escapeHtml(placement)],
            ['Шкаф', rackLabel],
            ['Юнит', escapeHtml(String(eq.unit_position || '—'))],
            ['Статус', eq.status === 'active' ? '<span class="status-badge status-ok">Активен</span>' : '<span class="status-badge status-cancelled">Неактивен</span>'],
            ['Локальный админ', eq.local_admin_login ? escapeHtml(eq.local_admin_login) : '—'],
            ['Примечание', escapeHtml(eq.Annotation || '—')],
        ];

        html += `
        <div class="dossier-section">
            <h4>📄 Досье устройства</h4>
            <div class="dossier-grid">
                ${fields.map(([label, value]) => `
                    <div class="dossier-item">
                        <div class="label">${label}</div>
                        <div class="value">${value}</div>
                    </div>`).join('')}
            </div>
        </div>`;

        // Модули
        html += '<div class="dossier-section"><h4>🔧 Встроенные модули</h4><div class="modules-container">';

        // SFP
        html += `
        <div class="module-column">
            <div class="module-column-header">🔌 SFP-модули</div>
            <div class="module-list">
                ${(eq.modules && eq.modules.sfp && eq.modules.sfp.length > 0)
                    ? eq.modules.sfp.map(m => `
                        <div class="module-tile">
                            <div class="module-name">🔌 ${escapeHtml(m.name || 'SFP')}</div>
                            <div class="module-details">
                                ${m.type ? `<span>📡 ${escapeHtml(m.type)}</span>` : ''}
                                ${m.serial_number ? `<span>🔢 ${escapeHtml(m.serial_number)}</span>` : ''}
                                ${m.wavelength ? `<span>🌈 ${escapeHtml(m.wavelength)}</span>` : ''}
                                ${m.distance ? `<span>📏 ${escapeHtml(m.distance)}</span>` : ''}
                                ${m.port ? `<span>🔟 порт ${escapeHtml(m.port)}</span>` : ''}
                            </div>
                        </div>`).join('')
                    : '<div style="color: var(--text-secondary); padding: 0.5rem;">Нет данных</div>'}
            </div>
        </div>`;

        // Блоки питания
        html += `
        <div class="module-column">
            <div class="module-column-header">⚡ Блоки питания</div>
            <div class="module-list">
                ${(eq.modules && eq.modules.psu && eq.modules.psu.length > 0)
                    ? eq.modules.psu.map(m => `
                        <div class="module-tile">
                            <div class="module-name">⚡ ${escapeHtml(m.name || 'БП')}</div>
                            <div class="module-details">
                                ${m.type ? `<span>📡 ${escapeHtml(m.type)}</span>` : ''}
                                ${m.serial_number ? `<span>🔢 ${escapeHtml(m.serial_number)}</span>` : ''}
                                ${m.status ? `<span>${m.status === 'ok' ? '✅ OK' : '⚠️ ' + escapeHtml(m.status)}</span>` : ''}
                            </div>
                        </div>`).join('')
                    : '<div style="color: var(--text-secondary); padding: 0.5rem;">Нет данных</div>'}
            </div>
        </div>`;

        html += '</div></div>';

        // Сервисы
        html += '<div class="dossier-section"><h4>🌐 Подключённые сервисы</h4><div class="services-grid">';
        const services = ['Zabbix', 'NTP', 'Graylog', 'RADIUS', 'TACACS+'];
        services.forEach(svc => {
            const connected = eq.services && eq.services[svc];
            html += `
            <div class="service-card">
                <div class="service-name">${svc}</div>
                <div class="service-status ${connected ? 'service-connected' : 'service-disconnected'}">
                    ${connected ? '✓ Подключён' : '✗ Не подключён'}
                </div>
            </div>`;
        });
        html += '</div></div>';

        // LLDP-соседи (только просмотр, без поля ввода)
        html += '<div class="dossier-section"><h4>📡 LLDP-соседи</h4>';
        const neighbors = Array.isArray(eq.lldp_neighbors) ? eq.lldp_neighbors : [];
        if (neighbors.length > 0) {
            html += `
            <table class="lldp-neighbors-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Локальный порт</th>
                        <th>Сосед</th>
                        <th>Порт соседа</th>
                        <th>В базе</th>
                    </tr>
                </thead>
                <tbody>
                    ${neighbors.map(n => {
                        let known = '—';
                        if (n.remote_equipment_id) {
                            const parts = [];
                            if (n.remote_hostname) parts.push(escapeHtml(n.remote_hostname));
                            if (n.remote_ip) parts.push(escapeHtml(n.remote_ip));
                            if (n.remote_ky_number) parts.push(`КУ-${n.remote_ky_number}`);
                            known = `<span class="status-badge status-ok">${parts.join(' · ') || 'найдено'}</span>`;
                        } else {
                            known = '<span class="status-badge status-update">не найдено</span>';
                        }
                        return `
                        <tr>
                            <td>${escapeHtml(n.local_port || '—')}</td>
                            <td>${escapeHtml(n.remote_device_id || '—')}</td>
                            <td>${escapeHtml(n.remote_port || '—')}</td>
                            <td>${known}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>`;
        } else {
            html += '<div style="color: var(--text-secondary); padding: 0.5rem;">Нет данных</div>';
        }
        html += '</div>';

        dossier.innerHTML = html;
        modal.dataset.equipId = equipId;
        // Контекст для SNMP-опроса: IP и производитель берём из загруженных данных
        // IP приходит как ip_address_display («—», если не задан)
        modal.dataset.equipIp = (ip && ip !== '—') ? ip : '';
        modal.dataset.equipVendor = eq.vendor_name || '';
        document.getElementById('detailsEditBtn').style.display = 'inline-block';

        // Кнопка SNMP имеет смысл только при заданном IP-адресе
        const snmpBtn = document.getElementById('detailsSnmpBtn');
        if (snmpBtn) snmpBtn.style.display = modal.dataset.equipIp ? 'inline-block' : 'none';

    } catch (err) {
        dossier.innerHTML = '<div class="alert alert-danger">Ошибка загрузки данных</div>';
        console.error(err);
    }
}

function closeEquipmentDetails() {
    const modal = document.getElementById('equipmentDetailsModal');
    if (modal) modal.classList.remove('visible');
}

function editCurrentEquipment() {
    const modal = document.getElementById('equipmentDetailsModal');
    const equipId = modal?.dataset.equipId;
    if (equipId) {
        closeEquipmentDetails();
        if (typeof openEditEquipmentForm === 'function') {
            openEditEquipmentForm(equipId);
        } else {
            alert('Функция редактирования не найдена');
        }
    }
}
/* ============================================================
   История изменений объекта (кнопка «📜 История изменений»).
   Данные берутся из таблицы logs через ?ajax=get_object_logs.
   ============================================================ */
(function () {
    'use strict';

    let historyState = { objectType: 'equipment', objectId: null, page: 1, perPage: 20, totalPages: 1 };

    // Человекочитаемые названия действий
    const ACTION_LABELS = {
        add_equipment: 'Добавлено', edit_equipment: 'Изменено', delete_equipment: 'Удалено',
        move: 'Перемещено', move_equipment: 'Перемещено',
        add_stack: 'Стек создан', edit_stack: 'Стек изменён',
        save_stack_device: 'Изменено в стеке', delete_stack_device: 'Выведено из стека',
        add_node: 'Узел добавлен', edit_node: 'Узел изменён', delete_node: 'Узел удалён',
        add_rack: 'Шкаф добавлен',
        login: 'Вход', logout: 'Выход'
    };

    function esc(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Открывает историю изменений.
     * Без аргументов берёт оборудование из открытой карточки досье.
     */
    window.openEquipmentHistory = function (objectType, objectId, titleText) {
        const detailsModal = document.getElementById('equipmentDetailsModal');
        historyState.objectType = objectType || 'equipment';
        historyState.objectId = objectId || detailsModal?.dataset.equipId || null;
        historyState.page = 1;

        if (!historyState.objectId) {
            if (typeof showToast === 'function') showToast('Объект не определён', 'warning');
            return;
        }

        const modal = document.getElementById('historyModal');
        if (!modal) return;

        const titleEl = document.getElementById('historyTitle');
        if (titleEl) {
            const name = titleText
                || document.getElementById('detailsTitle')?.textContent?.trim()
                || '';
            titleEl.textContent = name ? `История изменений — ${name}` : 'История изменений';
        }

        // Карточку досье не закрываем: история открывается поверх неё
        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');

        loadHistory(1);
    };

    window.closeEquipmentHistory = function () {
        document.getElementById('historyModal')?.classList.remove('visible');
    };

    async function loadHistory(page) {
        if (page) historyState.page = page;
        const tbody = document.getElementById('historyTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" class="history-empty">Загрузка…</td></tr>';

        const params = new URLSearchParams({
            object_type: historyState.objectType,
            object_id: historyState.objectId,
            page: historyState.page,
            per_page: historyState.perPage
        });

        try {
            const resp = await fetch('?ajax=get_object_logs&' + params.toString());
            const data = await resp.json();
            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="5" class="history-empty history-error">${esc(data.error)}</td></tr>`;
                return;
            }
            historyState.page = data.page || 1;
            historyState.totalPages = data.total_pages || 1;
            renderHistory(data.data || []);
            renderHistoryPagination(data.total || 0);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="5" class="history-empty history-error">Ошибка загрузки</td></tr>';
        }
    }

    function renderHistory(rows) {
        const tbody = document.getElementById('historyTableBody');
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="history-empty">Изменений пока не зафиксировано</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const dt = (r.created_at || '').replace('T', ' ');
            const action = ACTION_LABELS[r.action] || r.action;
            const details = r.details || '';
            const short = details.length > 80 ? details.slice(0, 80) + '…' : details;

            return `
            <tr>
                <td class="history-nowrap">${esc(dt)}</td>
                <td>${esc(r.username || 'system')}</td>
                <td>${esc(action)}</td>
                <td>${esc(r.object_name || '—')}</td>
                <td class="history-details"${details ? ' data-full="' + esc(details) + '" title="Нажмите, чтобы раскрыть"' : ''}>${esc(short) || '<span class="history-muted">—</span>'}</td>
            </tr>`;
        }).join('');

        // Раскрытие подробностей по клику
        tbody.querySelectorAll('.history-details[data-full]').forEach(td => {
            td.classList.add('history-expandable');
            td.addEventListener('click', function () {
                const full = this.dataset.full;
                if (this.classList.contains('expanded')) {
                    this.classList.remove('expanded');
                    this.textContent = full.length > 80 ? full.slice(0, 80) + '…' : full;
                } else {
                    this.classList.add('expanded');
                    this.textContent = full;
                }
            });
        });
    }

    function renderHistoryPagination(total) {
        const el = document.getElementById('historyPagination');
        if (!el) return;

        const totalPages = historyState.totalPages;
        let html = `<span class="history-total">Всего записей: ${total}</span>`;

        if (totalPages > 1) {
            const btn = (p, label, disabled, active) =>
                `<button type="button" class="history-page-btn${active ? ' active' : ''}" ` +
                `data-page="${p}"${disabled ? ' disabled' : ''}>${label}</button>`;

            html += '<span class="history-page-controls">';
            html += btn(historyState.page - 1, '‹', historyState.page <= 1, false);

            const from = Math.max(1, historyState.page - 2);
            const to = Math.min(totalPages, from + 4);
            for (let p = from; p <= to; p++) html += btn(p, String(p), false, p === historyState.page);

            html += btn(historyState.page + 1, '›', historyState.page >= totalPages, false);
            html += `<span class="history-page-current">стр. ${historyState.page} из ${totalPages}</span>`;
            html += '</span>';
        }

        el.innerHTML = html;
        el.querySelectorAll('.history-page-btn').forEach(b => {
            b.addEventListener('click', () => {
                const p = parseInt(b.dataset.page, 10);
                if (p >= 1 && p <= totalPages && p !== historyState.page) loadHistory(p);
            });
        });
    }
})();

/* ============================================================
   SNMP-опрос оборудования (кнопка «📡 SNMP-запросы» в досье).
   Результат нигде не сохраняется — только показывается в окне.
   ============================================================ */
(function () {
    'use strict';

    let snmpCtx = { ip: '', equipmentId: null, vendor: '' };

    /**
     * Открывает окно SNMP-опроса. Данные берутся из текущей карточки досье.
     */
    window.openSnmpModal = function (ip, equipmentId, vendor) {
        const detailsModal = document.getElementById('equipmentDetailsModal');
        snmpCtx.ip = ip || detailsModal?.dataset.equipIp || '';
        snmpCtx.equipmentId = equipmentId || detailsModal?.dataset.equipId || null;
        snmpCtx.vendor = vendor || detailsModal?.dataset.equipVendor || '';

        if (!snmpCtx.ip) {
            if (typeof showToast === 'function') showToast('У оборудования не указан IP-адрес', 'warning');
            return;
        }

        const modal = document.getElementById('snmpModal');
        if (!modal) return;

        document.getElementById('snmpIp').value = snmpCtx.ip;
        document.getElementById('snmpResult').innerHTML =
            '<div class="snmp-placeholder">Выберите запрос и нажмите «Подгрузить»</div>';
        document.getElementById('snmpCredentials').style.display = 'none';
        document.getElementById('snmpUser').value = '';
        document.getElementById('snmpPassword').value = '';
        syncSnmpVersionFields();

        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');
    };

    window.closeSnmpModal = function () {
        const modal = document.getElementById('snmpModal');
        if (!modal) return;
        modal.classList.remove('visible');
        // Сбрасываем поля: пароль не должен оставаться в DOM
        document.getElementById('snmpUser').value = '';
        document.getElementById('snmpPassword').value = '';
        document.getElementById('snmpCredentials').style.display = 'none';
        document.getElementById('snmpResult').innerHTML =
            '<div class="snmp-placeholder">Выберите запрос и нажмите «Подгрузить»</div>';
    };

    /** v2c — community, v3 — логин/пароль. */
    function syncSnmpVersionFields() {
        const version = document.getElementById('snmpVersion')?.value || '2c';
        const commGroup = document.getElementById('snmpCommunityGroup');
        const creds = document.getElementById('snmpCredentials');
        if (commGroup) commGroup.style.display = version === '2c' ? '' : 'none';
        if (creds && version === '3') {
            creds.style.display = '';
            document.getElementById('snmpCredentialsHint').textContent =
                'SNMPv3: логин и пароль подставятся из базы, либо введите их вручную';
        } else if (creds && version === '2c') {
            creds.style.display = 'none';
        }
    }

    /** Выполняет запрос и рисует результат. */
    async function runSnmp(withManualCredentials) {
        const btn = withManualCredentials
            ? document.getElementById('snmpGoBtn')
            : document.getElementById('snmpRunBtn');
        const box = document.getElementById('snmpResult');
        const version = document.getElementById('snmpVersion').value;

        const params = {
            ip_address:   snmpCtx.ip,
            equipment_id: snmpCtx.equipmentId,
            vendor:       snmpCtx.vendor,
            query_type:   document.getElementById('snmpQueryType').value,
            version:      version
        };
        if (version === '2c') {
            params.community = document.getElementById('snmpCommunity').value.trim() || 'public';
        }
        // Введённые вручную учётные данные (в браузере не сохраняются)
        const user = document.getElementById('snmpUser').value.trim();
        const pass = document.getElementById('snmpPassword').value;
        if (user) { params.snmp_user = user; params.snmp_password = pass; }

        const oldLabel = btn ? btn.textContent : '';
        if (btn) { btn.disabled = true; btn.textContent = 'Опрос…'; }
        box.innerHTML = '<div class="snmp-placeholder">Опрашиваем устройство…</div>';

        try {
            const data = await doSnmpQuery(params);

            if (data.error) {
                box.innerHTML = `<div class="snmp-placeholder snmp-error">${data.error}</div>`;
                if (typeof showToast === 'function') showToast(data.error, 'error');

                // Не подошли учётные данные или их нет — предлагаем ввести вручную
                if (data.auth_failed || data.need_credentials) {
                    const creds = document.getElementById('snmpCredentials');
                    if (creds) {
                        creds.style.display = '';
                        document.getElementById('snmpCredentialsHint').textContent =
                            data.auth_failed
                                ? 'Логин и пароль не подошли. Введите другие и нажмите «Вперёд».'
                                : 'Введите логин и пароль и нажмите «Вперёд».';
                        document.getElementById('snmpUser').focus();
                    }
                }
                return;
            }

            renderSnmpResult(box, data);
        } catch (e) {
            box.innerHTML = '<div class="snmp-placeholder snmp-error">Ошибка сети при обращении к серверу</div>';
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = oldLabel; }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('snmpRunBtn')?.addEventListener('click', () => runSnmp(false));
        document.getElementById('snmpGoBtn')?.addEventListener('click', () => runSnmp(true));
        document.getElementById('snmpVersion')?.addEventListener('change', syncSnmpVersionFields);
    });
})();
