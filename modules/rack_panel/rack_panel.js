// modules/rack_panel/rack_panel.js
// Выдвижная панель визуализации шкафов узла.
// Открывается из контекстного меню оборудования («Отобразить стойку»)
// или кнопкой-ручкой у правого края экрана.

(function () {
    'use strict';

    // ---------- Состояние панели ----------
    let rackPanelOpen = false;       // открыта ли панель
    let rackData = null;             // последний ответ get_rack
    let activeRackId = null;         // выбранная вкладка (шкаф)
    let highlightEquipId = null;     // оборудование, ради которого открывали панель
    let lastEquipId = null;          // для повторного открытия по кнопке-ручке

    // Цвета блоков по типу устройства
    const DEVICE_COLORS = {
        'Коммутатор':               { bg: 'rgba(66,165,245,0.18)',  border: '#42a5f5' },
        'Маршрутизатор':            { bg: 'rgba(102,187,106,0.18)', border: '#66bb6a' },
        'Сервер':                   { bg: 'rgba(171,71,188,0.18)',  border: '#ab47bc' },
        'Дисковая полка':           { bg: 'rgba(255,167,38,0.18)',  border: '#ffa726' },
        'ИБП':                      { bg: 'rgba(239,83,80,0.18)',   border: '#ef5350' },
        'Инженерное оборудование':  { bg: 'rgba(236,64,122,0.18)',  border: '#ec407a' },
        'МСЭ(Межсетевой экран)':    { bg: 'rgba(38,166,154,0.18)',  border: '#26a69a' },
        'IP-Телефон':               { bg: 'rgba(120,144,156,0.18)', border: '#78909c' },
        'Принтер':                  { bg: 'rgba(141,110,99,0.18)',  border: '#8d6e63' }
    };
    const DEFAULT_COLOR = { bg: 'rgba(150,150,150,0.15)', border: '#9e9e9e' };

    function colorFor(typeName) {
        return DEVICE_COLORS[typeName] || DEFAULT_COLOR;
    }

    // Локальный экранировщик — на случай, если utils.js ещё не загружен
    function esc(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type);
    }

    // ---------- Открытие / закрытие ----------
    function toggleRackPanel(open) {
        const panel = document.getElementById('rightPanel');
        const tab = document.getElementById('panelTab');
        if (!panel) return;

        rackPanelOpen = open;
        panel.classList.toggle('open', open);
        if (tab) tab.classList.toggle('hidden', open);
    }

    /**
     * Открыть панель для оборудования. Повторный вызов для того же
     * оборудования — закрывает панель (поведение переключателя).
     */
    async function openRackPanel(equipId) {
        if (lastEquipId === equipId && rackPanelOpen) {
            toggleRackPanel(false);
            return;
        }
        lastEquipId = equipId;
        highlightEquipId = equipId ? parseInt(equipId, 10) : null;
        await loadRackData({ equipment_id: equipId });
    }

    /** Открыть панель для узла целиком (без выделения конкретного устройства). */
    async function openRackPanelForNode(nodeId) {
        lastEquipId = null;
        highlightEquipId = null;
        await loadRackData({ node_id: nodeId });
    }

    // ---------- Загрузка данных ----------
    async function loadRackData(params) {
        const panelBody = document.getElementById('panelBody');
        const panelTitle = document.getElementById('panelTitle');
        if (!panelBody) return;

        panelBody.innerHTML = '<div class="rack-placeholder">Загрузка…</div>';
        if (panelTitle) panelTitle.textContent = 'Стойка';
        toggleRackPanel(true);

        const qs = new URLSearchParams(params).toString();
        try {
            const resp = await fetch(`?ajax=get_rack&${qs}`);
            const data = await resp.json();

            if (data.error) {
                panelBody.innerHTML = `<div class="rack-placeholder rack-error">${esc(data.error)}</div>`;
                if (panelTitle) panelTitle.textContent = 'Стойка';
                return;
            }

            rackData = data;
            activeRackId = data.active_rack_id;
            if (data.active_equipment_id) highlightEquipId = parseInt(data.active_equipment_id, 10);

            renderPanel();
        } catch (e) {
            panelBody.innerHTML = '<div class="rack-placeholder rack-error">Ошибка загрузки данных шкафа</div>';
        }
    }

    // ---------- Отрисовка ----------
    function renderPanel() {
        const panelBody = document.getElementById('panelBody');
        const panelTitle = document.getElementById('panelTitle');
        if (!panelBody || !rackData) return;

        const racks = rackData.racks || [];
        const rack = racks.find(r => r.id_rack === activeRackId) || racks[0];
        if (!rack) {
            panelBody.innerHTML = '<div class="rack-placeholder">У этого узла нет шкафов</div>';
            return;
        }

        if (panelTitle) {
            panelTitle.textContent = `${rack.name || 'Шкаф'} · ${rack.height_u}U`;
        }

        let html = '';

        // --- Вкладки шкафов (если их несколько) ---
        if (racks.length > 1) {
            html += '<div class="rack-tabs">';
            racks.forEach(r => {
                const active = r.id_rack === rack.id_rack ? ' active' : '';
                html += `
                    <button type="button" class="rack-tab${active}" data-rack-id="${r.id_rack}">
                        <span class="rack-tab-name">${esc(r.name || 'Шкаф')}</span>
                        <span class="rack-tab-meta">${esc(r.location_display || '')}</span>
                    </button>`;
            });
            html += '</div>';
        }

        // --- Шапка с характеристиками шкафа ---
        const specs = [];
        if (rack.vendor_name) specs.push(esc(rack.vendor_name));
        if (rack.model_name) specs.push(esc(rack.model_name));
        if (rack.width_mm && rack.depth_mm) specs.push(`${rack.width_mm}×${rack.depth_mm} мм`);
        if (rack.form_factor) specs.push(esc(rack.form_factor));

        html += `
            <div class="rack-summary">
                <div class="rack-summary-line">${specs.join(' · ') || 'Модель не указана'}</div>
                ${rack.location_display ? `<div class="rack-summary-loc">📍 ${esc(rack.location_display)}</div>` : ''}
            </div>`;

        // --- Сама стойка ---
        html += renderRack(rack);

        // --- Легенда типов, встречающихся в этом шкафу ---
        html += renderLegend(rack);

        panelBody.innerHTML = html;

        bindTabs();
        bindDeviceEvents();
        scrollToHighlighted();
    }

    /**
     * Стойка: вертикальный список юнитов сверху вниз (от большего номера к 1),
     * многоюнитовые устройства — одним блоком через grid-row.
     */
    function renderRack(rack) {
        const height = rack.height_u || 42;
        const equipment = rack.equipment || [];

        // Карта: юнит → устройство, которое его занимает
        const occupied = {};
        equipment.forEach(eq => {
            if (!eq.unit_start) return;
            const size = eq.unit_size || 1;
            for (let u = eq.unit_start; u < eq.unit_start + size; u++) {
                occupied[u] = eq;
            }
        });

        // Оборудование без позиции — покажем отдельным списком под стойкой
        const unplaced = equipment.filter(eq => !eq.unit_start);

        let html = '<div class="rack-frame">';
        html += '<div class="rack-grid">';

        // Рисуем сверху вниз: юнит 1 наверху, юнит height внизу.
        // Номер юнита совпадает с номером строки grid — пересчёт не нужен.
        for (let u = 1; u <= height; u++) {
            const eq = occupied[u];

            // Номер юнита — всегда
            html += `<div class="rack-unit-no" style="grid-row: ${u};">${u}</div>`;

            if (!eq) {
                html += `<div class="rack-slot empty" style="grid-row: ${u};" data-unit="${u}"></div>`;
                continue;
            }

            // Блок устройства рисуем только на его первом (верхнем) юните
            const size = eq.unit_size || 1;
            if (u !== eq.unit_start) continue;

            const rowStart = eq.unit_start;
            const c = colorFor(eq.device_type_name);
            const isHighlight = highlightEquipId && eq.id === highlightEquipId;
            const isStack = !!eq.stack_id;

            const meta = [];
            if (eq.device_type_name) meta.push(esc(eq.device_type_name));
            if (eq.model_name) meta.push(esc(eq.model_name));

            const badges = [];
            if (eq.Poe) badges.push('<span class="rack-badge poe" title="PoE">⚡</span>');
            badges.push(eq.status === 'active'
                ? '<span class="rack-badge on" title="Активно">●</span>'
                : '<span class="rack-badge off" title="Не активно">●</span>');
            if (size > 1) badges.push(`<span class="rack-badge size">${size}U</span>`);
            // Слот в стеке — важен для идентификации устройства внутри стека
            if (isStack && eq.Slot !== null && eq.Slot !== undefined && eq.Slot !== '') {
                badges.push(`<span class="rack-badge slot" title="Слот в стеке">S${eq.Slot}</span>`);
            }

            html += `
                <div class="rack-device${isHighlight ? ' highlight' : ''}${isStack ? ' in-stack' : ''}"
                     style="grid-row: ${rowStart} / span ${size}; background:${c.bg}; border-color:${c.border};"
                     data-equip-id="${eq.id}"
                     data-unit="${eq.unit_start}"
                     title="${esc(eq.hostname || '')} — ${esc(eq.device_type_name || '')}">
                    <div class="rack-device-main">
                        <span class="rack-device-name">${esc(eq.hostname || 'Без имени')}</span>
                        <span class="rack-device-badges">${badges.join('')}</span>
                    </div>
                    <div class="rack-device-sub">
                        ${eq.ip_address ? `<span class="rack-device-ip">${esc(eq.ip_address)}</span>` : ''}
                        ${meta.length ? `<span class="rack-device-meta">${meta.join(' · ')}</span>` : ''}
                    </div>
                    ${isStack ? `<div class="rack-device-stack">📦 ${esc(eq.stack_name || 'стек')}</div>` : ''}
                </div>`;
        }

        html += '</div></div>';

        // Устройства этого шкафа без указанного юнита
        if (unplaced.length) {
            html += '<div class="rack-unplaced"><div class="rack-unplaced-title">Без указанного юнита</div>';
            unplaced.forEach(eq => {
                const c = colorFor(eq.device_type_name);
                html += `
                    <div class="rack-unplaced-item" data-equip-id="${eq.id}" style="border-left-color:${c.border};">
                        <span class="rack-device-name">${esc(eq.hostname || 'Без имени')}</span>
                        <span class="rack-device-meta">${esc(eq.device_type_name || '')}${eq.ip_address ? ' · ' + esc(eq.ip_address) : ''}</span>
                    </div>`;
            });
            html += '</div>';
        }

        return html;
    }

    function renderLegend(rack) {
        const types = [...new Set((rack.equipment || [])
            .map(e => e.device_type_name)
            .filter(Boolean))].sort();
        if (!types.length) return '';

        let html = '<div class="rack-legend">';
        types.forEach(t => {
            const c = colorFor(t);
            html += `<span class="rack-legend-item"><i style="background:${c.bg}; border-color:${c.border};"></i>${esc(t)}</span>`;
        });
        html += '</div>';
        return html;
    }

    // ---------- Обработчики ----------
    function bindTabs() {
        document.querySelectorAll('#panelBody .rack-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                activeRackId = parseInt(tab.dataset.rackId, 10);
                renderPanel();
            });
        });
    }

    function bindDeviceEvents() {
        const selector = '#panelBody .rack-device, #panelBody .rack-unplaced-item';
        document.querySelectorAll(selector).forEach(el => {
            const equipId = parseInt(el.dataset.equipId, 10);
            if (!equipId) return;

            // Клик — подробная карточка оборудования
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                if (typeof showEquipmentDetails === 'function') {
                    showEquipmentDetails(equipId);
                } else {
                    toast('Окно подробностей недоступно', 'warning');
                }
            });

            // ПКМ — контекстное меню (редактировать / переместить / удалить)
            el.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                e.stopPropagation();
                showRackContextMenu(e, equipId);
            });
        });
    }

    /**
     * Простое контекстное меню панели. Используем общий контейнер #ctxMenu,
     * но вешаем собственные обработчики — состояние nodes_page.js не трогаем.
     */
    function showRackContextMenu(e, equipId) {
        const menu = document.getElementById('ctxMenu');
        if (!menu) return;

        const items = [
            { text: 'Подробнее', icon: 'assets/icons/detailed.png', run: () => {
                if (typeof showEquipmentDetails === 'function') showEquipmentDetails(equipId);
            }},
            { text: 'Редактировать', icon: 'assets/icons/edit.png', run: () => {
                if (typeof openEditEquipmentForm === 'function') openEditEquipmentForm(equipId);
                else toast('Форма редактирования недоступна', 'warning');
            }},
            { text: 'Переместить', icon: 'assets/icons/move.png', run: () => {
                if (typeof openMoveDialog === 'function') openMoveDialog(equipId, null);
                else toast('Форма перемещения недоступна', 'warning');
            }},
            { text: 'Удалить', icon: 'assets/icons/delete.png', run: () => {
                if (typeof deleteEquipment === 'function') deleteEquipment(equipId);
                else toast('Удаление недоступно', 'warning');
            }}
        ];

        const ul = document.createElement('ul');
        items.forEach(item => {
            const li = document.createElement('li');
            li.innerHTML = `<img src="${item.icon}" width="16" height="16" alt=""> ${esc(item.text)}`;
            li.addEventListener('click', () => {
                menu.style.display = 'none';
                item.run();
            });
            ul.appendChild(li);
        });

        menu.innerHTML = '';
        menu.appendChild(ul);
        menu.style.display = 'block';
        menu.style.left = Math.min(e.clientX, window.innerWidth - 220) + 'px';
        menu.style.top = Math.min(e.clientY, window.innerHeight - 200) + 'px';

        const close = () => { menu.style.display = 'none'; document.removeEventListener('click', close); };
        setTimeout(() => document.addEventListener('click', close), 0);
    }

    // Прокручиваем к устройству, ради которого открыли панель
    function scrollToHighlighted() {
        if (!highlightEquipId) return;
        const el = document.querySelector(`#panelBody .rack-device[data-equip-id="${highlightEquipId}"]`);
        if (el && typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    // ---------- Кнопки панели ----------
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('panelClose')?.addEventListener('click', () => toggleRackPanel(false));

        document.getElementById('panelTab')?.addEventListener('click', () => {
            if (rackData) toggleRackPanel(true);
            else toast('Выберите оборудование и откройте «Отобразить стойку»', 'info');
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && rackPanelOpen) {
            // Не закрываем панель, если поверх открыта модалка
            if (document.querySelector('.add-form-modal.visible')) return;
            toggleRackPanel(false);
        }
    });

    // ---------- Экспорт ----------
    window.openRackPanel = openRackPanel;
    window.openRackPanelForNode = openRackPanelForNode;
    window.toggleRackPanel = toggleRackPanel;
})();
