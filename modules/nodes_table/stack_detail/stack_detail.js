// modules/nodes_table/stack_detail/stack_detail.js

/**
 * Открывает модальное окно с подробной информацией о стеке.
 * @param {number} equipId - ID любого устройства из стека.
 */
async function showStackDetails(equipId) {
    try {
        const resp = await fetch(`?ajax=get_stack_members&equip_id=${equipId}`);
        const data = await resp.json();
        const members = data.members || [];

        if (members.length === 0) {
            alert('Нет данных о стеке');
            return;
        }

        // Сортируем по слоту
        members.sort((a, b) => (a.Slot || 0) - (b.Slot || 0));

        renderStackDetailModal(members);
    } catch (err) {
        alert('Ошибка загрузки данных стека');
    }
}

/**
 * Отображает модальное окно с плитками устройств стека.
 * @param {Array} members - массив объектов устройств.
 */
function renderStackDetailModal(members) {
    let modal = document.getElementById('stackDetailModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'stackDetailModal';
        modal.className = 'add-form-modal';
        modal.innerHTML = '<div class="modal-content" style="max-width:800px;"></div>';
        document.body.appendChild(modal);
    }

    const content = modal.querySelector('.modal-content');
    content.innerHTML = buildStackDetailHTML(members);
    modal.classList.add('visible');

    const closeBtn = content.querySelector('#stackDetailClose');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => modal.classList.remove('visible'));
    }
}

/**
 * Генерирует HTML для модального окна стека.
 */
function buildStackDetailHTML(members) {
    const tilesHtml = members.map(m => {
        const hostname = m.hostname || '—';
        const ip = m.ip_address || m.ip || '—';
        const slot = m.Slot ?? '—';
        const serial = m.serial_number ?? '—';
        const mac = m.mac_address ?? '—';

        return `
            <div class="stack-device-tile">
                <div class="device-info">
                    <div class="device-name">🔌 ${hostname}</div>
                    <div class="device-details">
                        <span>📌 Слот ${slot}</span>
                        <span>🔢 ${serial}</span>
                        <span>📡 ${mac}</span>
                    </div>
                </div>
                <div class="device-ip">${ip}</div>
            </div>
        `;
    }).join('');

    return `
        <h3>📋 Стек устройств</h3>
        <div class="dossier-section">
            <div class="stack-devices-grid">
                ${tilesHtml}
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn secondary" id="stackDetailClose">Закрыть</button>
        </div>
    `;
}