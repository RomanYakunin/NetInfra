// modules/nodes_page/buildings/buildings.js

function addBuildingModal() {
    window.__buildingEditMode = null;   // сбрасываем режим редактирования
    const modal = document.getElementById('addBuildingModal');
    const form = document.getElementById('addBuildingForm');
    const title = document.getElementById('buildingModalTitle');
    if (!modal || !form) return;
    form.reset();
    if (title) title.textContent = 'Добавить новое здание';
    showModal(modal);
}

function closeBuildingForm() {
    const modal = document.getElementById('addBuildingModal');
    if (modal) {
        const form = modal.querySelector('#addBuildingForm');
        if (form) form.reset();
        modal.classList.remove('visible');
    }
}

function showBuildingContextMenu(e, buildingId, buildingName) {
    e.preventDefault();
    const oldMenu = document.getElementById('buildingCtxMenu');
    if (oldMenu) oldMenu.remove();

    const menu = document.createElement('div');
    menu.id = 'buildingCtxMenu';
    menu.className = 'context-menu';
    menu.style.display = 'block';
    menu.style.left = e.clientX + 'px';
    menu.style.top = e.clientY + 'px';
    menu.innerHTML = `
        <div class="menu-item" data-action="edit">✏️ Редактировать</div>
        <div class="menu-item" data-action="delete">🗑️ Удалить</div>
    `;
    document.body.appendChild(menu);

    menu.querySelector('[data-action="edit"]').addEventListener('click', () => {
        menu.remove();
        editBuilding(buildingId, buildingName);
    });
    menu.querySelector('[data-action="delete"]').addEventListener('click', () => {
        menu.remove();
        deleteBuilding(buildingId);
    });

    const closeHandler = (ev) => {
        if (!menu.contains(ev.target)) {
            menu.remove();
            document.removeEventListener('click', closeHandler);
        }
    };
    setTimeout(() => document.addEventListener('click', closeHandler), 0);
}

function editBuilding(buildingId, currentName) {
    window.__buildingEditMode = buildingId;   // запоминаем ID редактируемого здания
    const modal = document.getElementById('addBuildingModal');
    const form = document.getElementById('addBuildingForm');
    const title = document.getElementById('buildingModalTitle');
    if (!modal || !form) return;

    const nameInput = form.querySelector('input[name="name"]');
    if (nameInput) nameInput.value = currentName;
    if (title) title.textContent = 'Редактировать здание';
    showModal(modal);
}

async function deleteBuilding(buildingId) {
    const element = document.querySelector(`[data-building-id="${buildingId}"]`);
    const buildingName = element ? element.textContent.trim() : '';

    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;display:flex;justify-content:center;align-items:center;';
    const dialog = document.createElement('div');
    dialog.style.cssText = 'background:var(--bg-card);padding:2rem;border-radius:8px;max-width:400px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.3);';
    dialog.innerHTML = `
        <h3 style="margin-bottom:1rem;">Удалить здание?</h3>
        <p style="margin-bottom:1.5rem;">${buildingName ? `«${buildingName}»` : 'Выбранное здание'}</p>
        <button id="confirmDeleteBtn" class="btn danger" style="margin-right:0.5rem;">Удалить</button>
        <button id="cancelDeleteBtn" class="btn secondary">Отмена</button>
    `;
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);

    document.getElementById('cancelDeleteBtn').onclick = () => overlay.remove();
    document.getElementById('confirmDeleteBtn').onclick = async () => {
        overlay.remove();
        try {
            const resp = await fetch(`?ajax=get_building_item&id=${buildingId}`);
            const info = await resp.json();
            if (info.node_count > 0) {
                showToast('В этом здании находятся активные коммутационные узлы', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('id', buildingId);
            const delResp = await fetch('?ajax=delete_building', { method: 'POST', body: formData });
            const data = await delResp.json();
            if (data.success) {
                if (element) element.remove();
                showToast(`Здание «${buildingName}» успешно удалено`, 'success');
                if (typeof currentBuildingFilter !== 'undefined' && currentBuildingFilter == buildingId) {
                    filterByBuilding(0);
                }
            } else {
                showToast(data.message || data.error || 'Ошибка удаления', 'error');
            }
        } catch (e) {
            showToast('Ошибка сети', 'error');
        }
    };
}

function addBuildingToSidebar(id, name) {
    const list = document.querySelector('.buildings-list');
    if (!list) return;
    const li = document.createElement('li');
    li.className = 'building-item';
    li.setAttribute('data-building-id', id);
    li.setAttribute('onclick', `filterByBuilding(${id})`);
    li.setAttribute('oncontextmenu', `showBuildingContextMenu(event, ${id}, '${name.replace(/'/g, "\\'")}')`);
    li.textContent = name;
    const addBtn = list.querySelector('.building-item:last-child');
    list.insertBefore(li, addBtn);
}

document.addEventListener('DOMContentLoaded', () => {
    const buildingForm = document.getElementById('addBuildingForm');
    if (buildingForm) {
        buildingForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = this;
            const nameInput = form.querySelector('input[name="name"]');
            const name = nameInput.value.trim();
            if (!name) { showToast('Название обязательно', 'warning'); return; }

            const formData = new FormData(form);
            const editId = window.__buildingEditMode;   // берём ID из глобальной переменной
            if (editId) {
                formData.append('id', editId);
            }

            const url = editId ? '?ajax=update_building' : '?ajax=add_building';
            try {
                const resp = await fetch(url, { method: 'POST', body: formData });
                const data = await resp.json();
                if (data.success) {
                    closeBuildingForm();
                    if (editId) {
                        // Редактирование
                        const el = document.querySelector(`[data-building-id="${editId}"]`);
                        const oldName = data.old_name || (el ? el.textContent.trim() : '');
                        if (el) el.childNodes[0].textContent = data.name;
                        showToast(`Здание «${oldName}» изменено на «${data.name}»`, 'success');
                        if (typeof filterByBuilding === 'function' && typeof currentBuildingFilter !== 'undefined' && currentBuildingFilter == editId) {
                            filterByBuilding(editId);
                        }
                        refreshBuildingsSidebar();
                        window.__buildingEditMode = null;   // сброс режима
                    } else {
                        // Добавление
                        addBuildingToSidebar(data.id, data.name);
                        refreshBuildingsSidebar(); // полное обновление, чтобы гарантировать актуальность
                        showToast(`Здание «${data.name}» успешно добавлено`, 'success');
                    }
                } else {
                    showToast(data.message || data.error || 'Ошибка', 'error');
                }
            } catch (err) { showToast('Ошибка сети', 'error'); }
        });
    }
});

async function refreshBuildingsSidebar() {
    const list = document.querySelector('.buildings-list');
    if (!list) return;

    // Загружаем здания с сервера
    let buildings = [];
    try {
        const resp = await fetch('?ajax=get_buildings');
        if (resp.ok) buildings = await resp.json();
    } catch (e) {
        console.error('Не удалось обновить список зданий', e);
        return;
    }

    // Очищаем список, оставляя только кнопку "Добавить здание" (последний элемент)
    const addBtn = list.querySelector('.building-item:last-child');
    list.innerHTML = '';
    if (addBtn) list.appendChild(addBtn);

    // Заполняем заново
    buildings.forEach(b => {
        const li = document.createElement('li');
        li.className = 'building-item';
        li.setAttribute('data-building-id', b.id);
        li.setAttribute('onclick', `filterByBuilding(${b.id})`);
        li.setAttribute('oncontextmenu', `showBuildingContextMenu(event, ${b.id}, '${b.name.replace(/'/g, "\\'")}')`);
        li.textContent = b.name;
        list.insertBefore(li, addBtn); // вставляем перед кнопкой "Добавить"
    });

    // Восстанавливаем активный элемент, если есть currentBuildingFilter
    if (typeof currentBuildingFilter !== 'undefined' && currentBuildingFilter > 0) {
        const activeEl = list.querySelector(`[data-building-id="${currentBuildingFilter}"]`);
        if (activeEl) activeEl.classList.add('active');
    }
}