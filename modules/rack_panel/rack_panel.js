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
    // Окно модальное, поэтому открытие не сдвигает содержимое страницы.
    function toggleRackPanel(open) {
        const panel = document.getElementById('rightPanel');
        if (!panel) return;

        rackPanelOpen = open;
        if (open) {
            if (typeof showModal === 'function') showModal(panel);
            else panel.classList.add('visible');
        } else {
            panel.classList.remove('visible');
        }
    }

    /**
     * Открыть окно по конкретному оборудованию: показывается его шкаф,
     * а сам блок подсвечивается и прокручивается в зону видимости.
     */
    async function openRackPanel(equipId) {
        lastEquipId = equipId;
        highlightEquipId = equipId ? parseInt(equipId, 10) : null;
        await loadRackData({ equipment_id: equipId });
    }

    /** Открыть окно для узла целиком (без выделения конкретного устройства). */
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
        bindPassiveEvents();   // клик по порту и по блоку пассивного устройства
        bindResizeHandles();   // перетаскивание границ блока
        bindEmptySlotMenu();   // ПКМ по свободному юниту
        scrollToHighlighted();
    }

    /**
     * Стойка: вертикальный список юнитов сверху вниз (от большего номера к 1),
     * многоюнитовые устройства — одним блоком через grid-row.
     */
    function renderRack(rack) {
        const height = rack.height_u || 42;
        // Активное и пассивное оборудование рисуем в одной сетке: в шкафу
        // они одинаково занимают юниты. Отличаются флагом is_passive.
        const equipment = (rack.equipment || []).concat(rack.passive || []);

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

        // Состав стеков: id стека → участники, отсортированные по слоту.
        // Нужно, чтобы в блоке стека показать «Слот 1: host1 · Слот 2: host2».
        const stackMembers = {};
        equipment.forEach(eq => {
            if (eq.stack_id === null || eq.stack_id === undefined) return;
            if (!stackMembers[eq.stack_id]) stackMembers[eq.stack_id] = [];
            stackMembers[eq.stack_id].push(eq);
        });
        Object.keys(stackMembers).forEach(k => {
            stackMembers[k].sort((a, b) => (parseInt(a.Slot, 10) || 0) - (parseInt(b.Slot, 10) || 0));
        });

        // Корпус: верхняя крышка с общей высотой, нутро с направляющими, ножки
        let html = '<div class="rack-frame">';
        html += `<div class="rack-frame-cap">${esc(rack.name || 'ШКАФ')} · ${height}U</div>`;
        html += '<div class="rack-inner">';
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
            const isPassive = !!eq.is_passive;
            // Для пассивного оборудования тип берём из его собственного поля
            const typeName = isPassive
                ? (PASSIVE_LABELS[eq.type] || 'Пассивное оборудование')
                : eq.device_type_name;
            const c = colorFor(typeName);
            const isHighlight = !isPassive && highlightEquipId && eq.id === highlightEquipId;
            const isStack = !isPassive && !!eq.stack_id;

            const meta = [];
            if (typeName) meta.push(esc(typeName));
            if (eq.model_name || eq.model) meta.push(esc(eq.model_name || eq.model));

            const badges = [];
            if (isPassive) {
                // У панелей вместо статуса сети показываем занятость портов
                if (eq.ports_count > 0) {
                    badges.push('<span class="rack-badge ports" title="Занято портов">'
                              + eq.ports_connected + '/' + eq.ports_count + '</span>');
                }
            } else {
                if (eq.Poe) badges.push('<span class="rack-badge poe" title="PoE">⚡</span>');
                badges.push(eq.status === 'active'
                    ? '<span class="rack-badge on" title="Активно">●</span>'
                    : '<span class="rack-badge off" title="Не активно">●</span>');
            }
            if (size > 1) badges.push(`<span class="rack-badge size">${size}U</span>`);
            // Слот в стеке — без него не понять, какое из одинаковых
            // устройств стека перед тобой. Ноль означает «не назначен»:
            // слоты в стеке нумеруются с единицы.
            if (isStack && parseInt(eq.Slot, 10) > 0) {
                badges.push(`<span class="rack-badge slot" title="Слот в стеке">Слот ${esc(eq.Slot)}</span>`);
            }

            // Часть стека стоит в другом шкафу — подсказываем, где именно
            const otherRacks = Array.isArray(eq.other_racks) ? eq.other_racks : [];
            const otherHtml = otherRacks.length
                ? `<div class="rack-device-elsewhere" title="${esc('Другие устройства стека: ' + otherRacks.join('; '))}">↗ в другом шкафу: ${esc(otherRacks.join('; '))}</div>`
                : '';

            // Патч-панель и оптический кросс рисуем портами, а не текстом
            const portsHtml = renderPanelPorts(eq);
            // У пассивного оборудования имя в поле name, у активного — hostname
            const displayName = isPassive ? (eq.name || 'Без названия') : (eq.hostname || 'Без имени');

            html += `
                <div class="rack-device${isHighlight ? ' highlight' : ''}${isStack ? ' in-stack' : ''}${isPassive ? ' is-passive' : ''}${portsHtml ? ' is-panel' : ''}"
                     style="grid-row: ${rowStart} / span ${size}; --dev-accent:${c.border};"
                     ${isPassive ? `data-passive-id="${eq.id}"` : `data-equip-id="${eq.id}"`}
                     data-unit-start="${eq.unit_start}"
                     data-unit-size="${size}"
                     title="${esc(displayName)} — ${esc(typeName || '')}">
                    <div class="rack-resize-handle top" data-edge="top" title="Потяните, чтобы изменить занимаемые юниты"></div>
                    <div class="rack-device-main">
                        <span class="rack-device-name">${esc(displayName)}</span>
                        <span class="rack-device-badges">${badges.join('')}</span>
                    </div>
                    <div class="rack-device-sub">
                        ${eq.ip_address ? `<span class="rack-device-ip">${esc(eq.ip_address)}</span>` : ''}
                        ${meta.length ? `<span class="rack-device-meta">${meta.join(' · ')}</span>` : ''}
                    </div>
                    ${portsHtml}
                    ${isStack ? `<div class="rack-device-stack">📦 ${esc(eq.stack_name || 'стек')}</div>` : ''}
                    ${isStack ? renderStackSlots(stackMembers[eq.stack_id], eq.id) : ''}
                    ${otherHtml}
                    <div class="rack-resize-handle bottom" data-edge="bottom" title="Потяните, чтобы изменить занимаемые юниты"></div>
                </div>`;
        }

        html += '</div>';   // .rack-grid
        html += '</div>';   // .rack-inner
        html += '<div class="rack-frame-feet"><span></span><span></span></div>';
        html += '</div>';   // .rack-frame

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

    /**
     * Состав стека внутри блока: «Слот 1: host1 · Слот 2: host2».
     * Текущее устройство выделяем, чтобы было видно, какое из них это.
     *
     * @param {Array}  members  участники стека в этом шкафу (уже по слотам)
     * @param {number} selfId   id устройства, в блоке которого рисуем
     */
    function renderStackSlots(members, selfId) {
        if (!Array.isArray(members) || members.length < 2) return '';

        const parts = members.map(m => {
            const slot = parseInt(m.Slot, 10) > 0 ? 'Слот ' + esc(m.Slot) : '—';
            const name = esc(m.hostname || 'без имени');
            const cls = m.id === selfId ? ' class="self"' : '';
            return '<span' + cls + '>' + slot + ': ' + name + '</span>';
        });

        return '<div class="rack-device-slots" title="Состав стека">' + parts.join(' · ') + '</div>';
    }

    // ---------- Патч-панели и оптические кроссы ----------
    // Русские названия типов пассивного оборудования
    const PASSIVE_LABELS = {
        patch_panel:   'Патч-панель',
        optical_panel: 'Оптический кросс',
        sfp_module:    'SFP-модуль',
        psu_module:    'Блок питания',
        terminal:      'Терминал ВКС',
        other:         'Прочее',
    };

    /**
     * Медь — розетки RJ-45, оптика — синие адаптеры LC/SC/FC/ST.
     * Реальные панели различаются прежде всего этим, а не формой отверстия.
     */
    function portShapeFor(portType) {
        if (portType === 'RJ45' || !portType) return 'copper';
        if (portType === 'SFP') return 'sfp';
        return 'optic';
    }

    // Медные патч-панели: розетки идут блоками по 6 с промежутком —
    // по нему монтажник и отсчитывает номера
    const PORT_GROUP = 6;

    // Оптический кросс устроен иначе: в корпусе фиксированное число
    // посадочных секций, в каждую входит 8 адаптеров. Если в кроссе
    // 12 портов, они занимают первую секцию и половину второй, а
    // остальные позиции остаются пустыми заглушками — растягивать
    // 12 адаптеров на всю ширину неправильно.
    const OPTIC_SECTION = 8;
    const OPTIC_SECTION_LABELS = ['A', 'B', 'C', 'D', 'E', 'F'];

    /**
     * Лицевая панель патч-панели или оптического кросса.
     * Порты рисуются по данным passive_devices / passive_device_ports:
     * количество, тип коннектора, занятость и направление каждого.
     *
     * Раскладка повторяет настоящую панель: ряды сверху вниз, внутри ряда
     * блоки по 6 портов, над блоком — маркировочная полоса, под портом —
     * его номер.
     *
     * @param {Object} eq устройство (is_passive === true)
     */
    function renderPanelPorts(eq) {
        if (!eq.is_passive) return '';
        const total = parseInt(eq.ports_count, 10) || 0;
        if (total <= 0) return '';

        // Данные портов приходят массивом; раскладываем по номеру
        const byNumber = {};
        (eq.ports || []).forEach(p => { byNumber[p.port_number] = p; });

        const shape = portShapeFor(eq.port_type);
        const rows = Math.max(1, parseInt(eq.port_rows, 10) || 1);

        let html = '<div class="rack-panel-face ' + shape + '">';
        // Монтажное ухо с отверстиями — по нему панель узнаётся с первого взгляда
        html += '<span class="rack-panel-ear left"></span>';
        html += '<div class="rack-panel-rows">';
        html += shape === 'optic'
            ? renderOpticSections(eq, total, rows, byNumber)
            : renderCopperRows(eq, total, rows, byNumber);
        html += '</div>';
        html += '<span class="rack-panel-ear right"></span>';
        html += '</div>';
        return html;
    }

    /** Одна розетка/адаптер с подсказкой. */
    function renderPort(eq, number, portData) {
        const connected = portData && portData.is_connected;

        // Подсказка: номер, тип коннектора и куда идёт линия
        const tip = [];
        tip.push((eq.port_type || 'Порт') + ' ' + number);
        if (portData && portData.label)       tip.push(portData.label);
        if (portData && portData.destination) tip.push('→ ' + portData.destination);
        if (portData && portData.fiber_type)  tip.push(portData.fiber_type);
        if (!connected) tip.push('свободен');

        return '<span class="rack-port' + (connected ? ' connected' : '') + '"'
             + ' data-port="' + number + '" data-device-id="' + eq.id + '"'
             + ' title="' + esc(tip.join(' · ')) + '">'
             + '<i class="rack-port-body"></i>'
             + '<b class="rack-port-num">' + number + '</b>'
             + '</span>';
    }

    /** Медная патч-панель: ряды розеток блоками по 6 с маркировочной полосой. */
    function renderCopperRows(eq, total, rows, byNumber) {
        const perRow = Math.ceil(total / rows);
        let html = '';

        for (let r = 0; r < rows; r++) {
            const from = r * perRow + 1;
            if (from > total) break;
            const to = Math.min(total, from + perRow - 1);

            html += '<div class="rack-panel-row">';
            for (let start = from; start <= to; start += PORT_GROUP) {
                const end = Math.min(to, start + PORT_GROUP - 1);
                html += '<div class="rack-port-group">';
                html += '<span class="rack-port-strip"></span>';
                html += '<span class="rack-port-line">';
                for (let i = start; i <= end; i++) html += renderPort(eq, i, byNumber[i]);
                html += '</span></div>';
            }
            html += '</div>';
        }
        return html;
    }

    /**
     * Оптический кросс: корпус разбит на посадочные секции по 8 адаптеров.
     * Адаптеры ставятся по порядку с первой секции, незанятые позиции
     * остаются пустыми заглушками — как в реальном кроссе, где 12 портов
     * занимают полторы секции, а не растягиваются на всю ширину.
     *
     * 1U — три секции в ряд, 2U — по две секции в каждом из двух рядов
     * (маркируются A/B/C/D, как на ШКОС).
     */
    function renderOpticSections(eq, total, rows, byNumber) {
        const perRow = rows > 1 ? 2 : 3;
        // Секций всегда хватает под все порты, но не меньше типовой раскладки
        const needed = Math.ceil(total / OPTIC_SECTION);
        const sections = Math.max(perRow * rows, needed);
        const rowCount = Math.max(rows, Math.ceil(sections / perRow));
        const showLabels = sections > 1;

        let html = '';
        let placed = 0;

        for (let r = 0; r < rowCount; r++) {
            html += '<div class="rack-panel-row">';
            for (let c = 0; c < perRow; c++) {
                const idx = r * perRow + c;
                if (idx >= sections) {
                    // Ряд короче остальных — держим ширину пустышкой
                    html += '<div class="rack-port-group is-spacer"></div>';
                    continue;
                }

                const filled = Math.max(0, Math.min(OPTIC_SECTION, total - placed));
                html += '<div class="rack-port-group' + (filled ? '' : ' is-empty') + '">';
                html += '<span class="rack-port-line">';
                for (let k = 0; k < OPTIC_SECTION; k++) {
                    if (k < filled) {
                        const number = placed + k + 1;
                        html += renderPort(eq, number, byNumber[number]);
                    } else {
                        // Пустое посадочное место: тёмный проём без адаптера
                        html += '<span class="rack-port-blank" title="Свободное место под адаптер"></span>';
                    }
                }
                html += '</span>';
                if (showLabels) {
                    html += '<span class="rack-section-label">'
                          + (OPTIC_SECTION_LABELS[idx] || (idx + 1)) + '</span>';
                }
                html += '</div>';
                // Прижимной винт между секциями — характерная деталь кросса
                if (c < perRow - 1) html += '<span class="rack-panel-screw"></span>';
                placed += filled;
            }
            html += '</div>';
        }
        return html;
    }

    // ---------- Изменение занимаемых юнитов перетаскиванием ----------
    let resizeState = null;
    // После отпускания границы браузер шлёт click на блок устройства.
    // Метка времени гасит именно этот клик, чтобы не открывалось досье.
    let suppressClickUntil = 0;

    function bindResizeHandles() {
        // Пользователю без прав менять размещение нельзя
        if (typeof canEdit === 'function' && !canEdit()) {
            document.querySelectorAll('#panelBody .rack-resize-handle')
                .forEach(h => { h.style.display = 'none'; });
            return;
        }

        document.querySelectorAll('#panelBody .rack-resize-handle').forEach(handle => {
            handle.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const block = handle.closest('.rack-device');
                const grid = document.querySelector('#panelBody .rack-grid');
                if (!block || !grid) return;

                // Высота одного юнита нужна, чтобы переводить пиксели в юниты
                const rack = (rackData.racks || []).find(r => r.id_rack === activeRackId);
                const height = rack ? rack.height_u : 42;
                const unitPx = grid.getBoundingClientRect().height / height;

                resizeState = {
                    block: block,
                    edge: handle.dataset.edge,
                    equipId: parseInt(block.dataset.equipId, 10),
                    startY: e.clientY,
                    origStart: parseInt(block.dataset.unitStart, 10),
                    origSize: parseInt(block.dataset.unitSize, 10),
                    unitPx: unitPx > 0 ? unitPx : 28,
                    height: height,
                    curStart: parseInt(block.dataset.unitStart, 10),
                    curSize: parseInt(block.dataset.unitSize, 10),
                    conflict: false
                };
                document.body.classList.add('rack-resizing');
            });
        });
    }

    /** Занят ли диапазон другим устройством этого шкафа. */
    function rangeConflicts(from, to, exceptId) {
        const rack = (rackData.racks || []).find(r => r.id_rack === activeRackId);
        if (!rack) return false;
        return (rack.equipment || []).some(other => {
            if (other.id === exceptId || !other.unit_start) return false;
            const oFrom = other.unit_start;
            const oTo = other.unit_start + (other.unit_size || 1) - 1;
            return from <= oTo && oFrom <= to;
        });
    }

    function onResizeMove(e) {
        if (!resizeState) return;
        const st = resizeState;

        const deltaUnits = Math.round((e.clientY - st.startY) / st.unitPx);
        let newStart = st.origStart;
        let newSize = st.origSize;

        if (st.edge === 'bottom') {
            newSize = st.origSize + deltaUnits;
        } else {
            // Тянем верхнюю границу: сдвигается начало, нижний край на месте
            newStart = st.origStart + deltaUnits;
            newSize = st.origSize - deltaUnits;
        }

        // Держим блок в пределах шкафа
        if (newSize < 1) newSize = 1;
        if (newStart < 1) { newSize -= (1 - newStart); newStart = 1; }
        if (newStart + newSize - 1 > st.height) newSize = st.height - newStart + 1;
        if (newSize < 1) newSize = 1;

        st.curStart = newStart;
        st.curSize = newSize;

        // Предпросмотр прямо в сетке + красная рамка при наложении
        st.block.style.gridRow = newStart + ' / span ' + newSize;
        const bad = rangeConflicts(newStart, newStart + newSize - 1, st.equipId);
        st.block.classList.toggle('resize-conflict', bad);
        st.conflict = bad;
    }

    async function onResizeEnd() {
        if (!resizeState) return;
        const st = resizeState;
        resizeState = null;
        document.body.classList.remove('rack-resizing');
        st.block.classList.remove('resize-conflict');

        // Гасим click, который браузер пришлёт сразу после mouseup
        suppressClickUntil = Date.now() + 400;

        // Ничего не изменилось — тихо выходим
        if (st.curStart === st.origStart && st.curSize === st.origSize) return;

        if (st.conflict) {
            toast('Юниты заняты другим устройством', 'warning');
            st.block.style.gridRow = st.origStart + ' / span ' + st.origSize;
            return;
        }

        const fd = new FormData();
        fd.append('equip_id', st.equipId);
        fd.append('unit_from', st.curStart);
        fd.append('unit_to', st.curStart + st.curSize - 1);

        try {
            const resp = await fetch('?ajax=update_rack_unit', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                toast('Юниты обновлены: ' + data.unit_position, 'success');
                await loadRackData({ node_id: rackData.node_id });
            } else {
                toast(data.error || 'Не удалось изменить юниты', 'error');
                st.block.style.gridRow = st.origStart + ' / span ' + st.origSize;
            }
        } catch (e) {
            toast('Ошибка сети', 'error');
            st.block.style.gridRow = st.origStart + ' / span ' + st.origSize;
        }
    }

    // Слушатели вешаем один раз на документ, а не на каждую перерисовку
    document.addEventListener('mousemove', onResizeMove);
    document.addEventListener('mouseup', onResizeEnd);

    // ---------- Пассивное оборудование: клики по портам и блоку ----------
    function bindPassiveEvents() {
        // Клик по порту — форма назначения направления
        document.querySelectorAll('#panelBody .rack-port').forEach(port => {
            port.addEventListener('click', (e) => {
                e.stopPropagation();
                if (Date.now() < suppressClickUntil) return;   // клик после перетаскивания
                if (typeof canEdit === 'function' && !canEdit()) return;

                const deviceId = parseInt(port.dataset.deviceId, 10);
                const portNum  = parseInt(port.dataset.port, 10);
                if (!deviceId || !portNum) return;

                if (typeof openPassivePortForm === 'function') {
                    openPassivePortForm(deviceId, portNum, () => refreshRackPanel());
                } else {
                    toast('Форма настройки порта недоступна', 'warning');
                }
            });
        });

        // Клик по блоку пассивного устройства (мимо портов) — редактирование
        document.querySelectorAll('#panelBody .rack-device.is-passive').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target.closest('.rack-port')) return;      // порт обработан выше
                if (e.target.closest('.rack-resize-handle')) return;
                e.stopPropagation();
                if (Date.now() < suppressClickUntil) return;

                const id = parseInt(el.dataset.passiveId, 10);
                if (!id) return;
                if (typeof openPassiveDeviceForm === 'function') {
                    openPassiveDeviceForm({ id: id, onSaved: () => refreshRackPanel() });
                }
            });

            // ПКМ по пассивному устройству — удаление
            el.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (typeof canEdit === 'function' && !canEdit()) return;
                const id = parseInt(el.dataset.passiveId, 10);
                const name = el.querySelector('.rack-device-name')?.textContent || '';
                showPassiveContextMenu(e, id, name);
            });
        });
    }

    /** Меню действий над пассивным устройством. */
    function showPassiveContextMenu(e, id, name) {
        const menu = document.getElementById('ctxMenu');
        if (!menu || !id) return;

        const items = [
            { text: '✎ Редактировать', run: () => {
                if (typeof openPassiveDeviceForm === 'function') {
                    openPassiveDeviceForm({ id: id, onSaved: () => refreshRackPanel() });
                }
            }},
            { text: '🗑 Удалить', run: async () => {
                if (!confirm('Удалить «' + name + '»? Порты и их назначения будут удалены.')) return;
                const fd = new FormData();
                fd.append('id', id);
                try {
                    const data = await (await fetch('?ajax=delete_passive_device', { method: 'POST', body: fd })).json();
                    if (data.success) { toast('Устройство удалено', 'success'); refreshRackPanel(); }
                    else toast(data.error || 'Ошибка удаления', 'error');
                } catch (err) { toast('Ошибка сети', 'error'); }
            }}
        ];

        const ul = document.createElement('ul');
        const head = document.createElement('li');
        head.className = 'ctx-header';
        head.textContent = name;
        ul.appendChild(head);
        items.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item.text;
            li.addEventListener('click', () => { menu.style.display = 'none'; item.run(); });
            ul.appendChild(li);
        });

        menu.innerHTML = '';
        menu.appendChild(ul);
        menu.style.display = 'block';
        menu.style.left = Math.min(e.clientX, window.innerWidth - 230) + 'px';
        menu.style.top = Math.min(e.clientY, window.innerHeight - 180) + 'px';

        const close = () => { menu.style.display = 'none'; document.removeEventListener('click', close); };
        setTimeout(() => document.addEventListener('click', close), 0);
    }

    /** Перерисовка панели по текущему узлу — после любых изменений. */
    function refreshRackPanel() {
        if (rackData && rackData.node_id) {
            return loadRackData({ node_id: rackData.node_id });
        }
    }

    // ---------- Контекстное меню на свободном юните ----------
    function bindEmptySlotMenu() {
        if (typeof canEdit === 'function' && !canEdit()) return;

        document.querySelectorAll('#panelBody .rack-slot.empty').forEach(slot => {
            slot.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                e.stopPropagation();
                showEmptySlotMenu(e, parseInt(slot.dataset.unit, 10));
            });
        });
    }

    /**
     * Меню «Добавить» с выбором того, что именно поставить в свободный юнит.
     * Шкаф и юнит передаются в форму заранее заполненными.
     */
    function showEmptySlotMenu(e, unit) {
        const menu = document.getElementById('ctxMenu');
        if (!menu) return;

        const nodeId = rackData ? rackData.node_id : null;
        const rackId = activeRackId;

        // Активное оборудование — обычная форма
        const openEquipmentForm = () => {
            if (typeof openAddForm !== 'function') {
                toast('Форма добавления недоступна', 'warning');
                return;
            }
            openAddForm('equipment', nodeId, null, {
                node_id: nodeId,
                id_rack: rackId,
                unit_position: String(unit)
            });
        };

        // Пассивное оборудование — своя форма и своя таблица
        const openPassive = (type) => {
            if (typeof openPassiveDeviceForm !== 'function') {
                toast('Форма пассивного оборудования недоступна', 'warning');
                return;
            }
            openPassiveDeviceForm({
                type: type,
                node_id: nodeId,
                rack_id: rackId,
                unit_position: String(unit),
                onSaved: () => refreshRackPanel()
            });
        };

        const items = [
            { text: '➕ Оборудование',      run: openEquipmentForm },
            { text: '🔌 Патч-панель',       run: () => openPassive('patch_panel') },
            { text: '🔵 Оптическую панель', run: () => openPassive('optical_panel') },
            { text: '⚡ Блок питания',      run: () => openPassive('psu_module') },
            { text: '📺 Терминал ВКС',      run: () => openPassive('terminal') }
        ];

        const ul = document.createElement('ul');
        const head = document.createElement('li');
        head.className = 'ctx-header';
        head.textContent = 'Юнит ' + unit + ' — добавить:';
        ul.appendChild(head);

        items.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item.text;
            li.addEventListener('click', () => { menu.style.display = 'none'; item.run(); });
            ul.appendChild(li);
        });

        menu.innerHTML = '';
        menu.appendChild(ul);
        menu.style.display = 'block';
        menu.style.left = Math.min(e.clientX, window.innerWidth - 230) + 'px';
        menu.style.top = Math.min(e.clientY, window.innerHeight - 180) + 'px';

        const close = () => { menu.style.display = 'none'; document.removeEventListener('click', close); };
        setTimeout(() => document.addEventListener('click', close), 0);
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

            // Клик — подробная карточка оборудования.
            // После перетаскивания границы браузер шлёт click на блок,
            // из-за чего открывалось досье. Такой клик гасим.
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                if (Date.now() < suppressClickUntil) return;
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

    // ---------- Кнопки окна ----------
    // Escape обрабатывается централизованно в modules/add_form/esc_handler.js:
    // окно стойки — обычная модалка, свой обработчик спорил бы с ним.
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('panelClose')?.addEventListener('click', () => toggleRackPanel(false));

        // Клик по затемнению закрывает окно, клик внутри — нет
        document.getElementById('rightPanel')?.addEventListener('click', function (e) {
            if (e.target === this) toggleRackPanel(false);
        });
    });

    // ---------- Экспорт ----------
    window.openRackPanel = openRackPanel;
    window.openRackPanelForNode = openRackPanelForNode;
    window.toggleRackPanel = toggleRackPanel;
    window.closeRackPanel = () => toggleRackPanel(false);
})();
