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
        document.getElementById('detailsEditBtn').style.display = 'inline-block';

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