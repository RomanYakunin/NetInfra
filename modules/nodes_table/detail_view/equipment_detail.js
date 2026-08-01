// modules/nodes_table/detail_view/equipment_detail.js

// Локальные данные для модулей (временная замена API)
const sfpModules = [
    { name: 'SFP-10G-SR', type: '10G SFP+', serial: 'SFP' + Math.random().toString(36).substring(2,8).toUpperCase(), wavelength: '850nm', distance: '300m' },
    { name: 'SFP-10G-LR', type: '10G SFP+', serial: 'SFP' + Math.random().toString(36).substring(2,8).toUpperCase(), wavelength: '1310nm', distance: '10km' },
    { name: 'GLC-SX-MMD', type: '1G SFP', serial: 'SFP' + Math.random().toString(36).substring(2,8).toUpperCase(), wavelength: '850nm', distance: '550m' },
    { name: 'GLC-LH-SMD', type: '1G SFP', serial: 'SFP' + Math.random().toString(36).substring(2,8).toUpperCase(), wavelength: '1310nm', distance: '10km' },
    { name: 'SFP-25G-SR', type: '25G SFP28', serial: 'SFP' + Math.random().toString(36).substring(2,8).toUpperCase(), wavelength: '850nm', distance: '100m' },
];
const psuModules = [
    { name: 'PWR-C1-350WAC', type: 'AC 350W', serial: 'PSU' + Math.random().toString(36).substring(2,8).toUpperCase(), status: 'OK' },
    { name: 'PWR-C1-715WAC', type: 'AC 715W', serial: 'PSU' + Math.random().toString(36).substring(2,8).toUpperCase(), status: 'OK' },
    { name: 'PWR-C1-1100WAC', type: 'AC 1100W', serial: 'PSU' + Math.random().toString(36).substring(2,8).toUpperCase(), status: 'Warning' },
    { name: 'PWR-C6-600WAC', type: 'AC 600W', serial: 'PSU' + Math.random().toString(36).substring(2,8).toUpperCase(), status: 'OK' },
];

// Хранилище модулей (заглушка, пока нет API)
const equipmentModules = {};

function randomItem(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

/**
 * Открывает модальное окно с подробной информацией об оборудовании.
 */
async function showEquipmentDetails(equipId) {
    try {
        const resp = await fetch(`?ajax=get_equipment_item&id=${equipId}`);
        const eq = await resp.json();
        if (eq.error) {
            alert('Ошибка загрузки данных: ' + eq.error);
            return;
        }

        // Заглушка модулей (пока нет API)
        if (!equipmentModules[equipId]) {
            equipmentModules[equipId] = {
                sfp: Array.from({length: Math.floor(Math.random() * 4) + 1}, () => ({...randomItem(sfpModules)})),
                psu: Array.from({length: Math.floor(Math.random() * 2) + 1}, () => ({...randomItem(psuModules)}))
            };
        }
        const modules = equipmentModules[equipId];

        renderDetailModal(eq, modules);
    } catch (err) {
        alert('Ошибка сети');
    }
}

function renderDetailModal(eq, modules) {
    let modal = document.getElementById('equipmentDetailModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'equipmentDetailModal';
        modal.className = 'add-form-modal';
        modal.innerHTML = '<div class="modal-content" style="max-width:900px;"></div>';
        document.body.appendChild(modal);
    }

    const modalContent = modal.querySelector('.modal-content');
    modalContent.innerHTML = buildDetailHTML(eq, modules);
    modal.classList.add('visible');

    document.getElementById('equipmentDetailClose')?.addEventListener('click', () => modal.classList.remove('visible'));
    document.getElementById('equipmentDetailEdit')?.addEventListener('click', () => {
        modal.classList.remove('visible');
        if (typeof openEditEquipmentForm === 'function') {
            openEditEquipmentForm(eq.id);
        }
    });
}

function buildDetailHTML(eq, modules) {
    const status = eq.status || 'inactive';
    const statusText = status === 'active' ? 'Активен' : 'Не активен';
    const statusClass = status === 'active' ? 'status-ok' : 'status-cancelled';
    const ip = eq.ip_address_text || eq.ip_address || '—';
    const nodeInfo = eq.KY_number ? `КУ-${eq.KY_number}` : (eq.id_node ? `Узел ${eq.id_node}` : 'Склад');
    const cabinet = eq.id_cabinet ? `${eq.cabinet_label || eq.id_cabinet} / ${eq.unit_position || '—'}` : '—';
    const services = [
        { name: 'Zabbix', connected: eq.status === 'active' && Math.random() > 0.3 },
        { name: 'NTP', connected: Math.random() > 0.2 },
        { name: 'Graylog', connected: eq.status === 'active' && Math.random() > 0.4 },
        { name: 'RADIUS', connected: Math.random() > 0.5 },
        { name: 'TACACS+', connected: Math.random() > 0.6 }
    ];

    return `
        <h3>📋 ${eq.hostname || 'Оборудование'} — Подробная информация</h3>
        <div class="equipment-dossier">
            <div class="dossier-section">
                <h4>📄 Досье устройства</h4>
                <div class="dossier-grid">
                    <div class="dossier-item"><div class="label">Имя хоста</div><div class="value">${eq.hostname || '—'}</div></div>
                    <div class="dossier-item"><div class="label">Тип</div><div class="value">${eq.device_type_name || '—'}</div></div>
                    <div class="dossier-item"><div class="label">Модель</div><div class="value">${eq.model_name || eq.model_id || '—'}</div></div>
                    <div class="dossier-item"><div class="label">Производитель</div><div class="value">${eq.vendor_name || eq.vendor_id || '—'}</div></div>
                    <div class="dossier-item"><div class="label">Серийный номер</div><div class="value">${eq.serial_number || '—'}</div></div>
                    <div class="dossier-item"><div class="label">MAC-адрес</div><div class="value">${eq.mac_address || '—'}</div></div>
                    <div class="dossier-item"><div class="label">IP-адрес</div><div class="value">${ip}</div></div>
                    <div class="dossier-item"><div class="label">Прошивка</div><div class="value">${eq.firmware_name || '—'}</div></div>
                    <div class="dossier-item"><div class="label">Узел</div><div class="value">${nodeInfo}</div></div>
                    <div class="dossier-item"><div class="label">Шкаф / Юнит</div><div class="value">${cabinet}</div></div>
                    <div class="dossier-item"><div class="label">Статус</div><div class="value"><span class="status-badge ${statusClass}">${statusText}</span></div></div>
                    <div class="dossier-item"><div class="label">Примечание</div><div class="value">${eq.Annotation || '—'}</div></div>
                </div>
            </div>
            <div class="dossier-section">
                <h4>🔧 Встроенные модули</h4>
                <div class="modules-container">
                    <div class="module-column">
                        <div class="module-column-header">🔌 SFP-модули</div>
                        <div class="module-list">
                            ${modules.sfp.map(m => `
                                <div class="module-tile">
                                    <div class="module-name">🔌 ${m.name}</div>
                                    <div class="module-details">
                                        <span>📡 ${m.type}</span>
                                        <span>🔢 ${m.serial}</span>
                                        <span>🌈 ${m.wavelength}</span>
                                        <span>📏 ${m.distance}</span>
                                    </div>
                                </div>
                            `).join('')}
                            <div class="add-module-tile" onclick="addModule(${eq.id}, 'sfp')">+ Добавить SFP модуль</div>
                        </div>
                    </div>
                    <div class="module-column">
                        <div class="module-column-header">⚡ Блоки питания</div>
                        <div class="module-list">
                            ${modules.psu.map(m => `
                                <div class="module-tile">
                                    <div class="module-name">⚡ ${m.name}</div>
                                    <div class="module-details">
                                        <span>📡 ${m.type}</span>
                                        <span>🔢 ${m.serial}</span>
                                        <span>${m.status === 'OK' ? '✅' : '⚠️'} ${m.status}</span>
                                    </div>
                                </div>
                            `).join('')}
                            <div class="add-module-tile" onclick="addModule(${eq.id}, 'psu')">+ Добавить блок питания</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dossier-section">
                <h4>🌐 Подключённые сервисы</h4>
                <div class="services-grid">
                    ${services.map(s => `
                        <div class="service-card">
                            <div class="service-name">${s.name}</div>
                            <div class="service-status ${s.connected ? 'service-connected' : 'service-disconnected'}">
                                ${s.connected ? '✓ Подключён' : '✗ Не подключён'}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn secondary" id="equipmentDetailClose">Закрыть</button>
            ${typeof openEditEquipmentForm === 'function' ? '<button class="btn" id="equipmentDetailEdit">Редактировать</button>' : ''}
        </div>
    `;
}

function addModule(equipId, type) {
    const modules = equipmentModules[equipId] || { sfp: [], psu: [] };
    if (type === 'sfp') {
        modules.sfp.push({...randomItem(sfpModules)});
    } else {
        modules.psu.push({...randomItem(psuModules)});
    }
    equipmentModules[equipId] = modules;
    showToast(`Модуль ${type === 'sfp' ? 'SFP' : 'БП'} добавлен`, 'success');
    showEquipmentDetails(equipId); // перерисовываем окно
}