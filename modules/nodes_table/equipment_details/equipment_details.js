// ======================== Подробная информация об оборудовании ========================
async function showEquipmentDetails(equipId) {
    const modal = document.getElementById('equipmentDetailsModal');
    const dossier = document.getElementById('detailsDossier');
    const title = document.getElementById('detailsTitle');
    if (!modal || !dossier || !title) return;

    // Показываем индикатор загрузки
    dossier.innerHTML = '<div style="text-align:center; padding:2rem; color: var(--text-secondary);">Загрузка...</div>';
    title.textContent = 'Загрузка...';
    modal.classList.add('visible');

    try {
        const resp = await fetch(`?ajax=get_equipment_details&id=${equipId}`);
        const eq = await resp.json();
        if (eq.error) { alert(eq.error); return; }

        const hostname = eq.hostname || 'Без имени';
        const ip = eq.ip_address_display || '—';
        title.textContent = `📋 ${hostname} — Подробная информация`;

        let html = '';

        // Принадлежность к стеку (упрощённо)
        if (eq.Groupe == 2 && eq.id_node) {
            html += `
            <div class="dossier-section stack-info" style="background: #f0f4ff; border-color: #667eea;">
                <h4>📦 В составе стека (узел ${eq.id_node})</h4>
            </div>`;
        }

        // Досье
        const fields = [
            ['Имя хоста', eq.hostname || '—'],
            ['Тип', eq.device_type_name || '—'],
            ['Модель', eq.model_name || '—'],
            ['Производитель', eq.vendor_name || '—'],
            ['Серийный номер', eq.serial_number || '—'],
            ['MAC-адрес', eq.mac_address || '—'],
            ['IP-адрес', ip],
            ['Прошивка', eq.firmware_name || '—'],
            ['Шкаф', eq.cabinet_id ? `Шкаф №${eq.cabinet_id} (${eq.cabinet_height || '—'}U)` : '—'],
            ['Юнит', eq.unit_position || '—'],
            ['Статус', eq.status === 'active' ? '<span class="status-badge status-ok">Активен</span>' : '<span class="status-badge status-cancelled">Неактивен</span>'],
            ['Примечание', eq.Annotation || '—'],
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
                            <div class="module-name">🔌 ${m.name || 'SFP'}</div>
                            <div class="module-details">
                                ${m.type ? `<span>📡 ${m.type}</span>` : ''}
                                ${m.serial ? `<span>🔢 ${m.serial}</span>` : ''}
                                ${m.wavelength ? `<span>🌈 ${m.wavelength}</span>` : ''}
                                ${m.distance ? `<span>📏 ${m.distance}</span>` : ''}
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
                            <div class="module-name">⚡ ${m.name || 'БП'}</div>
                            <div class="module-details">
                                ${m.type ? `<span>📡 ${m.type}</span>` : ''}
                                ${m.serial ? `<span>🔢 ${m.serial}</span>` : ''}
                                ${m.status ? `<span>${m.status === 'ok' ? '✅ OK' : '⚠️ ' + m.status}</span>` : ''}
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