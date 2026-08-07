// modules/phones_page/phones_page.js — страница «Телефоны».
//
// Три раздела в одном модуле: аппараты, поставки/коробки и доп. панели
// быстрого набора. Телефоны и панели приходят коробками по 9-10 штук,
// поэтому склад ведётся на уровне коробки, а карточка аппарата всегда
// показывает, из какой коробки он пришёл.
(function () {
    'use strict';

    const isAdmin = window.phIsAdmin === true;

    // Справочники грузим один раз и переиспользуем во всех формах
    let refs = null;

    const phState = { page: 1, perPage: 25, sort: 'phone_number', order: 'ASC', rows: [] };
    const expState = { page: 1, perPage: 25, sort: '', order: 'ASC', rows: [] };
    let deliveriesCache = { deliveries: [], loose_boxes: [] };

    // ------------------------------------------------------------------
    //  Мелкие помощники
    // ------------------------------------------------------------------
    function esc(str) {
        if (str === null || str === undefined) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type);
        else alert(msg);
    }

    function el(id) { return document.getElementById(id); }

    function dash(v) {
        return (v === null || v === undefined || v === '') ? '<span class="ph-muted">—</span>' : esc(v);
    }

    function fmtDate(d) {
        if (!d) return '';
        const s = String(d).slice(0, 10).split('-');
        return s.length === 3 ? `${s[2]}.${s[1]}.${s[0]}` : String(d);
    }

    function showErr(boxId, msg) {
        const box = el(boxId);
        if (!box) return;
        box.textContent = msg || '';
        box.style.display = msg ? 'block' : 'none';
    }

    function debounce(fn, ms) {
        let t;
        return function () {
            clearTimeout(t);
            const args = arguments;
            t = setTimeout(() => fn.apply(null, args), ms);
        };
    }

    /** POST формы/объекта на ajax-роут. Ответ всегда JSON. */
    async function post(action, data) {
        const fd = data instanceof FormData ? data : (() => {
            const f = new FormData();
            Object.keys(data || {}).forEach(k => {
                if (data[k] !== undefined && data[k] !== null) f.append(k, data[k]);
            });
            return f;
        })();
        const resp = await fetch('?ajax=' + action, { method: 'POST', body: fd });
        const text = await resp.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Ответ не JSON для', action, text);
            return { error: 'Сервер вернул некорректный ответ' };
        }
    }

    async function get(action, params) {
        const qs = new URLSearchParams(params || {});
        qs.set('ajax', action);
        const resp = await fetch('?' + qs.toString());
        const text = await resp.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Ответ не JSON для', action, text);
            return { error: 'Сервер вернул некорректный ответ' };
        }
    }

    /**
     * Наполняет <select> и вешает поисковый селект.
     * Списки моделей и подразделений после импорта большие — обычный
     * select в них не найти.
     */
    function fillSelect(select, items, opts) {
        if (!select) return;
        opts = opts || {};
        if (select.searchableInstance) select.searchableInstance.destroy();

        select.innerHTML = '';
        select.appendChild(new Option(opts.placeholder || '-- не выбрано --', ''));
        (items || []).forEach(it => {
            select.appendChild(new Option(it.name, it.id));
        });
        if (opts.value) select.value = String(opts.value);
        if (opts.searchable !== false && (items || []).length > 8) {
            new SearchableSelect(select);
        }
    }

    // ------------------------------------------------------------------
    //  Справочники
    // ------------------------------------------------------------------
    async function loadRefs(force) {
        if (refs && !force) return refs;
        const data = await get('get_phone_refs');
        if (data.error) {
            toast(data.error, 'error');
            refs = { phone_models: [], expansion_models: [], departments: [], vendors: [],
                     boxes: [], deliveries: [], switches: [], statuses: [] };
            return refs;
        }
        refs = data;
        syncFilterSelects();
        return refs;
    }

    /**
     * Пересобирает выпадающие фильтры из справочников.
     *
     * Списки в панели фильтров отрисованы на сервере при загрузке страницы,
     * поэтому заведённая только что коробка (или модель, или подразделение)
     * в них не появлялась до перезагрузки. Вызывается после каждого
     * обновления справочников.
     */
    function syncFilterSelects() {
        const rebuild = (id, items, label) => {
            const sel = el(id);
            if (!sel) return;
            const keep = sel.value;
            sel.innerHTML = '';
            sel.appendChild(new Option(label, ''));
            items.forEach(it => sel.appendChild(new Option(it.name, it.id)));
            // Если выбранное значение исчезло (коробку удалили) — сбрасываем фильтр
            sel.value = keep;
            if (sel.value !== keep) sel.value = '';
        };

        const boxLabel = b => '№' + (b.box_number || b.id) + (b.model_name ? ' · ' + b.model_name : '');

        rebuild('phFilterDept', (refs.departments || []).map(d => ({ id: d.id, name: d.name })),
            'Все подразделения');
        rebuild('phFilterModel', (refs.phone_models || []).map(m => ({ id: m.id, name: m.name })),
            'Все модели');
        rebuild('phFilterBox', boxesOfType('phone').map(b => ({ id: b.id, name: boxLabel(b) })),
            'Все коробки');
        rebuild('phExpFilterBox', boxesOfType('expansion').map(b => ({ id: b.id, name: boxLabel(b) })),
            'Все коробки');
    }

    /** Коробки нужного типа — телефон нельзя положить в коробку от панелей. */
    function boxesOfType(type) {
        return (refs.boxes || []).filter(b => b.item_type === type);
    }

    // ==================================================================
    //  ВКЛАДКИ
    // ==================================================================
    function initTabs() {
        const tabs = document.querySelectorAll('.ph-tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                const name = tab.dataset.tab;
                document.querySelectorAll('.ph-panel').forEach(p => p.classList.remove('active'));
                const panel = el('phPanel' + name.charAt(0).toUpperCase() + name.slice(1));
                if (panel) panel.classList.add('active');

                // Грузим содержимое вкладки при первом открытии
                if (name === 'boxes' && !deliveriesCache.loaded) loadDeliveries();
                if (name === 'expansions' && !expState.loaded) loadExpansions();
            });
        });
    }

    // ==================================================================
    //  РАЗДЕЛ 1: ТЕЛЕФОНЫ
    // ==================================================================
    function phoneFilters() {
        return {
            page: phState.page,
            per_page: phState.perPage,
            sort: phState.sort,
            order: phState.order,
            search: el('phSearch') ? el('phSearch').value.trim() : '',
            department_id: el('phFilterDept') ? el('phFilterDept').value : '',
            model_id: el('phFilterModel') ? el('phFilterModel').value : '',
            status: el('phFilterStatus') ? el('phFilterStatus').value : '',
            is_issued: el('phFilterIssued') ? el('phFilterIssued').value : '',
            box_id: el('phFilterBox') ? el('phFilterBox').value : ''
        };
    }

    // Столбцов в таблице телефонов — держим в одном месте, чтобы
    // «Загрузка…» и «не найдено» не разъезжались при правках разметки
    const PH_COLS = 10;

    async function loadPhones() {
        const tbody = el('phTableBody');
        if (!tbody) return;
        tbody.innerHTML = `<tr><td colspan="${PH_COLS}" class="ph-empty">Загрузка…</td></tr>`;

        const data = await get('get_phones', phoneFilters());
        if (data.error) {
            tbody.innerHTML = `<tr><td colspan="${PH_COLS}" class="ph-empty ph-error">${esc(data.error)}</td></tr>`;
            return;
        }
        phState.rows = data.data || [];
        phState.page = data.page;
        renderPhones(data);
    }

    function renderPhones(data) {
        const tbody = el('phTableBody');
        if (!phState.rows.length) {
            tbody.innerHTML = `<tr><td colspan="${PH_COLS}" class="ph-empty">Телефоны не найдены</td></tr>`;
            renderPagination('phPagination', data, p => { phState.page = p; loadPhones(); });
            return;
        }

        tbody.innerHTML = phState.rows.map(p => {
            const netParts = [];
            if (p.ip_address) netParts.push(esc(p.ip_address));
            if (p.vlan) netParts.push('VLAN ' + esc(p.vlan));
            const net = netParts.length ? netParts.join('<br>') : '<span class="ph-muted">—</span>';

            // Коммутатор: сопоставленный из справочника либо строка из Excel
            const swName = p.switch_hostname || p.switch_raw || '';
            const sw = swName
                ? esc(swName) + (p.switch_port ? '<br><span class="ph-muted">' + esc(p.switch_port) + '</span>' : '')
                : '<span class="ph-muted">—</span>';

            const box = p.box_id
                ? `<button type="button" class="ph-link" data-act="box" data-box="${p.box_id}">№${esc(p.box_number || p.box_id)}</button>`
                : '<span class="ph-muted">—</span>';

            const expBadge = Number(p.expansions) > 0
                ? ` <span class="ph-badge ph-badge-exp" title="Подключено доп. панелей">+${p.expansions}</span>`
                : '';

            const flags = [];
            if (Number(p.is_issued)) flags.push('<span class="ph-badge ph-badge-ok">выдан</span>');
            if (Number(p.is_ready)) flags.push('<span class="ph-badge">готов</span>');

            return `
            <tr data-id="${p.id}">
                <td class="ph-number">${dash(p.phone_number)}${expBadge}</td>
                <td>${dash(p.user_fio)}</td>
                <td>${dash(p.department_name)}</td>
                <td>${dash(p.model_name ? ((p.vendor_name ? p.vendor_name + ' ' : '') + p.model_name) : '')}</td>
                <td class="ph-mono">${dash(p.serial_number)}</td>
                <td class="ph-mono">${dash(p.mac_address)}</td>
                <td>${net}</td>
                <td>${sw}</td>
                <td>${box}</td>
                <td><span class="ph-status ph-status-${statusClass(p.status)}">${esc(p.status)}</span>${flags.length ? '<br>' + flags.join(' ') : ''}</td>
            </tr>`;
        }).join('');

        renderPagination('phPagination', data, p => { phState.page = p; loadPhones(); });
    }

    // ------------------------------------------------------------------
    //  Меню действий по строке телефона
    //
    //  Кнопки в отдельном столбце занимали место в и без того широкой
    //  таблице, поэтому действия открываются меню по клику на строку.
    // ------------------------------------------------------------------
    let phMenu = null;

    function closePhoneMenu() {
        if (!phMenu) return;
        phMenu.remove();
        phMenu = null;
        document.removeEventListener('mousedown', onOutsideMenu, true);
        document.removeEventListener('keydown', onMenuKey, true);
        window.removeEventListener('resize', closePhoneMenu);
        window.removeEventListener('scroll', closePhoneMenu, true);
    }
    function onOutsideMenu(e) {
        if (phMenu && !phMenu.contains(e.target)) closePhoneMenu();
    }
    function onMenuKey(e) {
        if (e.key === 'Escape') { closePhoneMenu(); e.stopPropagation(); }
    }

    /**
     * Показывает меню действий у курсора.
     * @param {number} x,y      координаты клика
     * @param {Object} row      строка телефона из списка
     */
    function openPhoneMenu(x, y, row) {
        closePhoneMenu();

        const title = row.phone_number || row.serial_number || row.mac_address || ('#' + row.id);
        const items = [
            { act: 'detail', icon: '👁', label: 'Подробнее' }
        ];
        if (row.box_id) {
            items.push({ act: 'box', icon: '📦', label: 'Коробка №' + (row.box_number || row.box_id) });
        }
        if (isAdmin) {
            items.push({ act: 'edit',   icon: '✎', label: 'Редактировать' });
            items.push({ act: 'delete', icon: '🗑', label: 'Удалить', danger: true });
        }

        phMenu = document.createElement('div');
        phMenu.className = 'ph-menu';
        phMenu.innerHTML =
            `<div class="ph-menu-title">${esc(title)}</div>` +
            items.map(i =>
                `<button type="button" class="ph-menu-item${i.danger ? ' danger' : ''}" data-act="${i.act}">
                    <span class="ph-menu-icon">${i.icon}</span>${esc(i.label)}
                 </button>`).join('');
        document.body.appendChild(phMenu);

        // Не даём меню уехать за край экрана
        const rect = phMenu.getBoundingClientRect();
        const left = Math.min(x, window.innerWidth  - rect.width  - 8);
        const top  = Math.min(y, window.innerHeight - rect.height - 8);
        phMenu.style.left = Math.max(8, left) + 'px';
        phMenu.style.top  = Math.max(8, top)  + 'px';

        phMenu.querySelectorAll('.ph-menu-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const act = btn.dataset.act;
                closePhoneMenu();
                switch (act) {
                    case 'detail': openPhoneDetail(row.id); break;
                    case 'edit':   openPhoneForm(row.id); break;
                    case 'box':    viewBox(row.box_id); break;
                    case 'delete': deletePhone(row.id, title); break;
                }
            });
        });

        document.addEventListener('mousedown', onOutsideMenu, true);
        document.addEventListener('keydown', onMenuKey, true);
        window.addEventListener('resize', closePhoneMenu);
        window.addEventListener('scroll', closePhoneMenu, true);
    }

    function statusClass(status) {
        switch (status) {
            case 'в эксплуатации': return 'active';
            case 'на складе':      return 'stock';
            case 'в ремонте':      return 'repair';
            case 'списан':         return 'dead';
            default:               return 'stock';
        }
    }

    function renderPagination(containerId, data, onGo) {
        const box = el(containerId);
        if (!box) return;
        const total = data.total_pages || 1;
        const cur = data.page || 1;

        if (total <= 1) {
            box.innerHTML = `<span class="ph-page-info">Всего: ${data.total || 0}</span>`;
            return;
        }

        // Показываем не больше 7 номеров: остальные схлопываем в «…»
        const pages = [];
        const push = n => { if (!pages.includes(n) && n >= 1 && n <= total) pages.push(n); };
        push(1);
        for (let i = cur - 2; i <= cur + 2; i++) push(i);
        push(total);
        pages.sort((a, b) => a - b);

        let html = `<span class="ph-page-info">Всего: ${data.total}</span>`;
        html += `<button type="button" class="ph-page-btn" data-go="${cur - 1}" ${cur <= 1 ? 'disabled' : ''}>‹</button>`;
        let prev = 0;
        pages.forEach(n => {
            if (prev && n - prev > 1) html += '<span class="ph-page-gap">…</span>';
            html += `<button type="button" class="ph-page-btn ${n === cur ? 'active' : ''}" data-go="${n}">${n}</button>`;
            prev = n;
        });
        html += `<button type="button" class="ph-page-btn" data-go="${cur + 1}" ${cur >= total ? 'disabled' : ''}>›</button>`;
        box.innerHTML = html;

        box.querySelectorAll('.ph-page-btn[data-go]').forEach(btn => {
            btn.addEventListener('click', () => {
                const n = parseInt(btn.dataset.go, 10);
                if (n >= 1 && n <= total && n !== cur) onGo(n);
            });
        });
    }

    // ---------- Форма телефона ----------
    async function openPhoneForm(id) {
        await loadRefs();
        const modal = el('phoneFormModal');
        resetModalForm(modal);

        el('phoneFormTitle').textContent = id ? 'Редактировать телефон' : 'Добавить телефон';
        el('phoneFormId').value = id || '';
        el('phoneFormAllowDup').value = '';
        showErr('phoneFormError', '');

        // Проверка дубликатов: подсказки от прошлого открытия убираем,
        // обработчики вешаем один раз на поле
        clearDuplicateHints();
        bindDuplicateChecks();

        let phone = null;
        if (id) {
            const data = await get('get_phone_detail', { id: id });
            if (data.error) { toast(data.error, 'error'); return; }
            phone = data.phone;
        }

        fillSelect(el('phoneFormModel'), refs.phone_models, { value: phone && phone.model_id });
        fillSelect(el('phoneFormDept'), refs.departments, { value: phone && phone.department_id });
        fillSelect(el('phoneFormBox'), boxesOfType('phone'), {
            value: phone && phone.box_id, placeholder: '-- вне коробки --'
        });
        fillSelect(el('phoneFormSwitch'), refs.switches, { value: phone && phone.switch_id });

        if (phone) {
            el('phoneFormNumber').value   = phone.phone_number || '';
            el('phoneFormUser').value     = phone.user_fio || '';
            el('phoneFormSerial').value   = phone.serial_number || '';
            el('phoneFormMac').value      = phone.mac_address || '';
            el('phoneFormIp').value       = phone.ip_address || '';
            el('phoneFormVlan').value     = phone.vlan || '';
            el('phoneFormPort').value     = phone.switch_port || '';
            el('phoneFormSocket').value   = phone.socket || '';
            el('phoneFormFirmware').value = phone.firmware || '';
            el('phoneFormStatus').value   = phone.status || 'в эксплуатации';
            el('phoneFormPrevUser').value = phone.previous_user || '';
            el('phoneFormNotes').value    = phone.notes || '';
            el('phoneFormIssued').checked = Number(phone.is_issued) === 1;
            el('phoneFormReady').checked  = Number(phone.is_ready) === 1;
        }

        await prepareFormDocs(id, phone);

        // Курсор в поле намеренно не ставим: при автофокусе первое поле
        // оказывается активным для ввода, хотя пользователь его не выбирал
        showModal(modal);
    }

    /**
     * Блок «Накладная или расписка» в форме телефона.
     * При редактировании показывает уже прикреплённые документы,
     * при добавлении — только поля для нового файла.
     */
    async function prepareFormDocs(id, phone) {
        const file = el('phoneFormDocFile');
        if (!file) return;

        // Оформление поля и показ выбранного файла — на file_picker.js
        if (typeof enhanceFileInputs === 'function') enhanceFileInputs(el('phoneFormModal'));
        if (typeof resetFilePickers === 'function') resetFilePickers(el('phoneFormModal'));
        else file.value = '';
        el('phoneFormDocFields').hidden = true;
        el('phoneFormDocNumber').value = '';
        el('phoneFormDocDate').value = '';
        el('phoneFormDocWholeBox').checked = false;

        // «Ко всей коробке» имеет смысл, только когда коробка известна
        const boxId = phone ? phone.box_id : (el('phoneFormBox') ? el('phoneFormBox').value : '');
        const wrap = el('phoneFormDocWholeBoxWrap');
        wrap.hidden = !boxId;
        if (boxId && phone) {
            el('phoneFormDocWholeBoxLabel').textContent =
                'Ко всей коробке №' + (phone.box_number || boxId);
        }

        // Кнопка сканирования работает и при добавлении: телефона ещё нет,
        // поэтому скан не привязывается сразу, а подставляется в поле файла
        // и уходит вместе с формой — тем же путём, что и выбранный файл.
        const scanBtn = el('phoneFormScanBtn');
        if (scanBtn && typeof openScanModal === 'function') {
            scanBtn.hidden = false;
            scanBtn.onclick = () => openScanModal({
                // При редактировании привязываем сразу, при добавлении — в форму
                phoneId: id || null,
                intoInput: id ? null : 'phoneFormDocFile',
                onSaved: () => { if (id) prepareFormDocs(id, phone); }
            });
        }

        const list = el('phoneFormDocsList');
        list.innerHTML = '';
        if (!id) return;

        const resp = await get('get_phone_documents', { phone_id: id });
        const docs = resp.error ? [] : (resp.data || []);
        if (!docs.length) {
            list.innerHTML = '<div class="ph-muted ph-doc-attached-empty">Документов пока нет</div>';
            return;
        }
        list.innerHTML = docs.map(d => `
            <span class="ph-doc-chip" title="${esc(d.original_name)}">
                ${docIcon(d)} ${esc(d.title || d.original_name)}
                <span class="ph-muted">· ${esc(d.doc_type)}${d.link_kind === 'box' ? ' · по коробке' : ''}</span>
            </span>`).join('');
    }

    /**
     * Догружает выбранный в форме файл после сохранения телефона.
     * Ошибку загрузки показываем отдельно: сам телефон уже сохранён,
     * и терять это из-за проблемы с файлом нельзя.
     */
    async function uploadFormDoc(phoneId) {
        const file = el('phoneFormDocFile');
        if (!file || !file.files.length) return;

        const fd = new FormData();
        fd.append('phone_id', phoneId);
        fd.append('file', file.files[0]);
        fd.append('doc_type', el('phoneFormDocType').value);
        fd.append('doc_number', el('phoneFormDocNumber').value);
        fd.append('doc_date', el('phoneFormDocDate').value);
        if (el('phoneFormDocWholeBox').checked) fd.append('whole_box', '1');

        const res = await post('upload_phone_document', fd);
        if (res.error) {
            toast('Телефон сохранён, но документ не прикреплён: ' + res.error, 'error');
            return;
        }
        toast(res.covered > 1
            ? `Документ прикреплён, охвачено аппаратов: ${res.covered}`
            : 'Документ прикреплён', 'success');
    }

    window.closePhoneForm = function () {
        closeModalAndReset('phoneFormModal');
    };

    /* ============================================================
       Живая проверка полей на дубликаты

       Проверяем, пока пользователь заполняет, а не после «Сохранить»:
       узнать о занятом серийнике в конце длинной формы — обидно.
       Серверные проверки при сохранении остаются: клиентская подсказка
       их дополняет, а не заменяет.

       Поля, где повтор нормален (подразделение, модель, розетка), не
       проверяются — предупреждать о них было бы шумом.
       ============================================================ */
    const DUP_FIELDS = {
        phoneFormSerial: 'serial_number',
        phoneFormMac:    'mac_address',
        phoneFormNumber: 'phone_number',
        phoneFormIp:     'ip_address',
    };
    const dupTimers = {};
    const dupState  = {};   // id поля → 'error' | 'warning' | null

    /** Показывает подсказку под полем и красит его рамку. */
    function setDupHint(inputId, severity, message) {
        const input = el(inputId);
        if (!input) return;

        dupState[inputId] = severity || null;
        input.classList.toggle('ph-dup-error', severity === 'error');
        input.classList.toggle('ph-dup-warning', severity === 'warning');

        let hint = document.getElementById(inputId + 'DupHint');
        if (!message) { if (hint) hint.remove(); return; }

        if (!hint) {
            hint = document.createElement('div');
            hint.id = inputId + 'DupHint';
            hint.className = 'ph-dup-hint';
            input.parentNode.appendChild(hint);
        }
        hint.className = 'ph-dup-hint ' + (severity === 'error' ? 'is-error' : 'is-warning');
        hint.textContent = message;
    }

    async function checkDuplicate(inputId) {
        const field = DUP_FIELDS[inputId];
        const input = el(inputId);
        if (!field || !input) return;

        const value = input.value.trim();
        if (!value) { setDupHint(inputId, null, ''); return; }

        const params = new URLSearchParams({
            ajax: 'check_phone_duplicate',
            field: field,
            value: value,
        });
        const id = el('phoneFormId').value;
        if (id) params.set('id', id);

        try {
            const data = await (await fetch('?' + params.toString())).json();
            // Сбой проверки не должен мешать заполнять форму
            if (!data.success || !data.checked) { setDupHint(inputId, null, ''); return; }
            if (!data.taken) { setDupHint(inputId, null, ''); return; }
            setDupHint(inputId, data.severity, data.message);
        } catch (e) {
            setDupHint(inputId, null, '');
        }
    }

    /** Навешивает проверку на поля формы; вызывается при её открытии. */
    function bindDuplicateChecks() {
        Object.keys(DUP_FIELDS).forEach(inputId => {
            const input = el(inputId);
            if (!input || input.dataset.dupBound) return;
            input.dataset.dupBound = '1';

            input.addEventListener('input', () => {
                clearTimeout(dupTimers[inputId]);
                // Ждём паузу в наборе: иначе запрос уходил бы на каждый символ
                dupTimers[inputId] = setTimeout(() => checkDuplicate(inputId), 450);
            });
            input.addEventListener('blur', () => {
                clearTimeout(dupTimers[inputId]);
                checkDuplicate(inputId);
            });
        });
    }

    /** Сбрасывает подсказки — при открытии формы и после сохранения. */
    function clearDuplicateHints() {
        Object.keys(DUP_FIELDS).forEach(id => setDupHint(id, null, ''));
    }

    async function submitPhoneForm(e) {
        e.preventDefault();
        showErr('phoneFormError', '');

        // Жёсткие дубли (серийник, MAC) не пропускаем: сервер всё равно
        // откажет, но пользователю понятнее увидеть причину у поля
        const blocking = Object.keys(dupState).filter(k => dupState[k] === 'error');
        if (blocking.length) {
            showErr('phoneFormError',
                'Исправьте поля с дубликатами — они отмечены красным');
            el(blocking[0])?.focus();
            return;
        }

        const form = el('phoneForm');
        const fd = new FormData(form);
        const id = el('phoneFormId').value;
        const data = await post(id ? 'update_phone' : 'add_phone', fd);

        if (data.error) {
            // Дубль внутреннего номера — не ошибка, а повод переспросить:
            // номер действительно могут временно держать два аппарата
            if (data.confirm === 'allow_duplicate_number') {
                if (confirm(data.error + '.\n\nВсё равно сохранить?')) {
                    el('phoneFormAllowDup').value = '1';
                    const fd2 = new FormData(form);
                    const data2 = await post(id ? 'update_phone' : 'add_phone', fd2);
                    if (data2.error) { showErr('phoneFormError', data2.error); return; }
                    await finishPhoneSave(data2, id);
                }
                return;
            }
            showErr('phoneFormError', data.error);
            return;
        }
        await finishPhoneSave(data, id);
    }

    async function finishPhoneSave(data, wasEdit) {
        toast(wasEdit ? 'Телефон сохранён' : 'Телефон добавлен', 'success');

        // Файл догружаем после сохранения: у нового телефона id
        // появляется только сейчас
        await uploadFormDoc(data.id);

        window.closePhoneForm();
        loadPhones();
        loadRefs(true);   // коробки изменили занятость
    }

    async function deletePhone(id, label) {
        if (!confirm(`Удалить телефон «${label}»?`)) return;
        const data = await post('delete_phone', { id: id });
        if (data.error) { toast(data.error, 'error'); return; }
        toast(data.detached
            ? `Телефон удалён, доп. панелей возвращено на склад: ${data.detached}`
            : 'Телефон удалён', 'success');
        loadPhones();
        loadRefs(true);
    }

    // ==================================================================
    //  ДОКУМЕНТЫ: накладные, расписки, акты
    // ==================================================================

    /** Значок по формату файла — в списке так быстрее ориентироваться. */
    function docIcon(doc) {
        if (doc.is_image) return '🖼';
        if (doc.ext === 'pdf') return '📕';
        return '📄';
    }

    /**
     * Раздел документов.
     * @param docs список документов
     * @param meta ответ get_phone_documents (типы, лимит, сведения о коробке)
     * @param scope 'phone' — карточка телефона, 'box' — содержимое коробки
     */
    function renderDocuments(docs, meta, scope) {
        let html = `<h4 class="ph-detail-h">Документы (${docs.length})</h4>`;

        if (isAdmin) {
            const types = (meta.doc_types || ['накладная']).map(t =>
                `<option value="${esc(t)}">${esc(t)}</option>`).join('');

            // Накладную выписывают на коробку целиком — даём прикрепить сразу ко всем
            const wholeBox = (scope === 'phone' && meta.box_id) ? `
                <label class="ph-doc-wholebox">
                    <input type="checkbox" id="phDocWholeBox">
                    Прикрепить ко всей коробке №${esc(meta.box_number || meta.box_id)}
                    (${meta.box_phones} шт.)
                </label>` : '';

            html += `
            <div class="ph-doc-upload">
                <div class="ph-doc-file-row">
                    <input type="file" id="phDocFile"
                           data-fp-label="Прикрепить файл" data-fp-icon="📎"
                           data-max-mb="${meta.max_mb || 20}"
                           accept=".pdf,.doc,.docx,.rtf,.odt,.jpg,.jpeg,.png,.tif,.tiff">
                </div>
                <div class="ph-doc-upload-row">
                    <select id="phDocType" class="ph-doc-type">${types}</select>
                    <input type="text" id="phDocNumber" class="ph-doc-number" placeholder="№ документа">
                    <input type="date" id="phDocDate" class="ph-doc-date" title="Дата документа">
                    <input type="text" id="phDocTitle" class="ph-doc-title" placeholder="Подпись (необязательно)">
                    <button type="button" class="btn small" id="phDocUpload">Прикрепить</button>
                </div>
                ${wholeBox}
                <div class="ph-doc-hint">
                    PDF, Word (doc/docx), RTF, ODT и сканы (jpg, png, tiff). До ${meta.max_mb || 20} МБ.
                    PDF и изображения открываются прямо здесь, остальные форматы — скачиванием.
                    Один и тот же файл не занимает место дважды.
                </div>
                <div class="ph-form-error" id="phDocError" style="display:none;"></div>
            </div>`;
        }

        if (!docs.length) {
            html += '<div class="ph-muted ph-detail-empty">Документы не прикреплены</div>';
            return html;
        }

        html += '<div class="ph-doc-list">';
        docs.forEach(d => {
            const label = d.title || d.original_name;
            const info = [
                d.doc_number ? '№ ' + d.doc_number : '',
                d.format,
                d.size_text,
                d.doc_date ? 'от ' + fmtDate(d.doc_date) : '',
                d.uploaded_by_login ? 'загрузил ' + d.uploaded_by_login : ''
            ].filter(Boolean).join(' · ');

            // Документ, прикреплённый к коробке, виден у всех её аппаратов —
            // без пометки непонятно, почему его нельзя открепить отсюда
            const scopeBadge = (scope === 'phone' && d.link_kind === 'box')
                ? '<span class="ph-badge ph-badge-box" title="Прикреплён к коробке — виден у всех аппаратов из неё">📦 по коробке</span>'
                : '';
            const shared = Number(d.links_total) > 1
                ? `<span class="ph-badge" title="Документ прикреплён к нескольким объектам">общий: ${d.links_total}</span>`
                : '';

            // Показать можно всё, кроме форматов, которые не разобрать:
            // PDF и картинки — силами браузера, Word/RTF/ODT — разбором на сервере
            const officeExts = ['docx', 'odt', 'rtf', 'doc'];
            const canPreview = !d.missing && (d.inline || officeExts.indexOf(d.ext) !== -1);

            html += `
            <div class="ph-doc" data-doc="${d.id}" data-inline="${canPreview ? 1 : 0}"
                 data-native="${d.inline && !d.is_image ? 1 : 0}"
                 data-image="${d.is_image ? 1 : 0}" data-link="${esc(d.link_kind || 'phone')}">
                <div class="ph-doc-head">
                    <span class="ph-doc-icon">${docIcon(d)}</span>
                    <div class="ph-doc-main">
                        <div class="ph-doc-name">${esc(label)}</div>
                        <div class="ph-doc-meta">
                            <span class="ph-badge">${esc(d.doc_type)}</span>
                            ${scopeBadge} ${shared} ${esc(info)}
                            ${d.missing ? '<span class="ph-doc-missing">файл отсутствует на диске</span>' : ''}
                        </div>
                        ${d.notes ? `<div class="ph-doc-notes">${esc(d.notes)}</div>` : ''}
                    </div>
                    <div class="ph-doc-actions">
                        ${canPreview
                            ? `<button type="button" class="btn small secondary" data-act="view">Посмотреть</button>`
                            : ''}
                        <a class="btn small secondary" href="?ajax=get_phone_document&id=${d.id}&download=1"
                           download title="Скачать">⬇</a>
                        ${isAdmin ? `<button type="button" class="btn small danger" data-act="del"
                            title="${d.link_kind === 'box' && scope === 'phone'
                                ? 'Открепить от коробки' : 'Открепить документ'}">🗑</button>` : ''}
                    </div>
                </div>
                <div class="ph-doc-viewer"><div class="ph-doc-viewer-inner"></div></div>
            </div>`;
        });
        html += '</div>';
        return html;
    }

    /**
     * Плавно раскрывает просмотр документа под его строкой.
     * Содержимое вставляем только при первом открытии: иначе браузер
     * держал бы в памяти все PDF из списка сразу.
     */
    async function toggleDocViewer(docEl) {
        const viewer = docEl.querySelector('.ph-doc-viewer');
        const inner  = viewer.querySelector('.ph-doc-viewer-inner');
        const id     = docEl.dataset.doc;
        const isOpen = docEl.classList.contains('open');
        const btn    = docEl.querySelector('[data-act="view"]');

        if (isOpen) {
            viewer.style.maxHeight = '0px';
            docEl.classList.remove('open');
            // Останавливаем отрисовку закрытого документа
            setTimeout(() => { if (!docEl.classList.contains('open')) inner.innerHTML = ''; }, 350);
            if (btn) btn.textContent = 'Посмотреть';
            return;
        }

        if (!inner.innerHTML) {
            const url = '?ajax=get_phone_document&id=' + encodeURIComponent(id);

            if (docEl.dataset.image === '1') {
                inner.innerHTML = `<img class="ph-doc-image" src="${url}" alt="Скан документа">`;
            } else if (docEl.dataset.native === '1') {
                // PDF браузер рисует сам
                inner.innerHTML = `<iframe class="ph-doc-frame" src="${url}" title="Просмотр документа"></iframe>`;
            } else {
                // Word, RTF, ODT — содержимое разбирает сервер
                inner.innerHTML = '<div class="ph-doc-loading">Готовим предпросмотр…</div>';
                const res = await get('preview_phone_document', { id: id });
                inner.innerHTML = res.error
                    ? `<div class="ph-doc-fallback">
                           <div>${esc(res.error)}</div>
                           <a class="btn small secondary" href="${url}&download=1" download>Скачать файл</a>
                       </div>`
                    : `<div class="ph-doc-html">${res.html}</div>`;
            }
        }

        docEl.classList.add('open');
        viewer.style.maxHeight = '78vh';
        if (btn) btn.textContent = 'Свернуть';

        // Раскрывшийся документ должен оказаться на виду
        setTimeout(() => docEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 380);
    }

    /**
     * @param phoneId телефон, чья карточка открыта
     * @param meta    ответ get_phone_documents — нужен id коробки,
     *                чтобы открепить накладную, выписанную на неё
     */
    function bindDocuments(phoneId, meta) {
        const box = el('phoneDetailBody');

        // Раздел перерисовывается целиком, поэтому поле выбора файла
        // каждый раз новое — оформляем его заново
        if (typeof enhanceFileInputs === 'function') enhanceFileInputs(box);

        box.querySelectorAll('.ph-doc').forEach(docEl => {
            const head = docEl.querySelector('.ph-doc-head');
            head.addEventListener('click', e => {
                // Кнопки и ссылки обрабатываются отдельно
                if (e.target.closest('button, a')) return;
                if (docEl.dataset.inline === '1') toggleDocViewer(docEl);
            });

            const viewBtn = docEl.querySelector('[data-act="view"]');
            if (viewBtn) viewBtn.addEventListener('click', () => toggleDocViewer(docEl));

            const delBtn = docEl.querySelector('[data-act="del"]');
            if (delBtn) delBtn.addEventListener('click', async () => {
                const name = docEl.querySelector('.ph-doc-name').textContent;
                const byBox = docEl.dataset.link === 'box';

                // Накладную, выписанную на коробку, откреплять надо от коробки:
                // она общая для всех аппаратов из неё
                const question = byBox
                    ? `Документ «${name}» прикреплён к коробке целиком.\n\n` +
                      'Открепить его от коробки? Он перестанет быть виден у всех аппаратов из неё.'
                    : `Открепить документ «${name}» от этого телефона?`;
                if (!confirm(question)) return;

                const payload = { id: docEl.dataset.doc };
                if (byBox) {
                    if (!meta || !meta.box_id) {
                        toast('Не удалось определить коробку документа', 'error');
                        return;
                    }
                    payload.box_id = meta.box_id;
                } else {
                    payload.phone_id = phoneId;
                }

                const res = await post('delete_phone_document', payload);
                if (res.error) { toast(res.error, 'error'); return; }
                toast(res.file_removed
                    ? 'Документ удалён'
                    : 'Документ откреплён (остаётся у других объектов)', 'success');
                openPhoneDetail(phoneId);
            });
        });

        const upBtn = el('phDocUpload');
        if (!upBtn) return;
        upBtn.addEventListener('click', async () => {
            showErr('phDocError', '');
            const input = el('phDocFile');
            if (!input.files.length) {
                showErr('phDocError', 'Выберите файл');
                return;
            }
            const fd = new FormData();
            fd.append('phone_id', phoneId);
            fd.append('file', input.files[0]);
            fd.append('doc_type', el('phDocType').value);
            fd.append('doc_date', el('phDocDate').value);
            fd.append('title', el('phDocTitle').value);

            upBtn.disabled = true;
            const old = upBtn.textContent;
            upBtn.textContent = 'Загрузка…';
            const res = await post('upload_phone_document', fd);
            upBtn.disabled = false;
            upBtn.textContent = old;

            if (res.error) { showErr('phDocError', res.error); return; }
            toast('Документ прикреплён', 'success');
            openPhoneDetail(phoneId);
        });
    }

    // ---------- Карточка телефона ----------
    async function openPhoneDetail(id) {
        const data = await get('get_phone_detail', { id: id });
        if (data.error) { toast(data.error, 'error'); return; }
        const docsResp = await get('get_phone_documents', { phone_id: id });
        const docs = docsResp.error ? [] : (docsResp.data || []);

        const p = data.phone;
        el('phoneDetailTitle').textContent =
            'Телефон ' + (p.phone_number || p.serial_number || p.mac_address || '#' + p.id);

        // Сведения разложены по смыслу: сплошной список из двадцати строк
        // читать невозможно, а так взгляд сразу попадает в нужный блок
        const model = p.model_name ? ((p.vendor_name ? p.vendor_name + ' ' : '') + p.model_name) : '';

        const flags = [];
        flags.push(`<span class="ph-status ph-status-${statusClass(p.status)}">${esc(p.status)}</span>`);
        if (Number(p.is_issued)) flags.push('<span class="ph-badge ph-badge-ok">выдан</span>');
        if (Number(p.is_ready))  flags.push('<span class="ph-badge">готов</span>');
        if (data.expansions.length) {
            flags.push(`<span class="ph-badge ph-badge-exp">+${data.expansions.length} панель</span>`);
        }

        let html = `
        <div class="ph-hero">
            <div class="ph-hero-num">${esc(p.phone_number || '—')}</div>
            <div class="ph-hero-main">
                <div class="ph-hero-user">${esc(p.user_fio || 'Пользователь не указан')}</div>
                <div class="ph-hero-sub">
                    ${model ? esc(model) : '<span class="ph-muted">модель не указана</span>'}
                    ${p.department_name ? ' · ' + esc(p.department_name) : ''}
                </div>
            </div>
            <div class="ph-hero-flags">${flags.join(' ')}</div>
        </div>`;

        /** Блок «поле — значение»; пустые поля не показываем, чтобы не шуметь. */
        const card = (icon, title, rows, extraClass) => {
            const filled = rows.filter(r => r[1] !== null && r[1] !== undefined && r[1] !== '');
            if (!filled.length) return '';
            return `<section class="ph-card ${extraClass || ''}">
                <h5 class="ph-card-title"><span>${icon}</span>${esc(title)}</h5>
                <dl class="ph-card-list">`
                + filled.map(r =>
                    `<dt>${esc(r[0])}</dt><dd class="${r[2] || ''}">${esc(r[1])}</dd>`).join('')
                + '</dl></section>';
        };

        html += '<div class="ph-cards">';

        html += card('🔌', 'Связь', [
            ['IP-адрес', p.ip_address, 'ph-mono'],
            ['VLAN', p.vlan],
            ['Коммутатор', p.switch_hostname || p.switch_raw],
            ['Порт', p.switch_port, 'ph-mono'],
            ['Розетка', p.socket],
        ]);

        html += card('📱', 'Оборудование', [
            ['Модель', model],
            ['Серийный номер', p.serial_number, 'ph-mono'],
            ['MAC-адрес', p.mac_address, 'ph-mono'],
            ['Прошивка', p.firmware],
        ]);

        html += card('🏢', 'Размещение', [
            ['Подразделение', p.department_name],
            ['Пользователь', p.user_fio],
            ['Прежний пользователь', p.previous_user],
            ['Примечание', p.notes],
        ]);

        // Происхождение: из какой коробки и поставки пришёл аппарат
        const originRows = [];
        if (p.box_id) {
            originRows.push(['Коробка', '№' + (p.box_number || p.box_id)
                + (p.qty_declared ? ' (по ' + p.qty_declared + ' шт.)' : '')
                + (p.box_status ? ' · ' + p.box_status : '')]);
        }
        if (p.doc_number)    originRows.push(['Накладная', p.doc_number]);
        if (p.delivery_date) originRows.push(['Дата поставки', fmtDate(p.delivery_date)]);
        if (p.source_sheet)  originRows.push(['Лист Excel', p.source_sheet]);
        html += card('📦', 'Происхождение', originRows.length
            ? originRows
            : [['Происхождение', 'Сведений о поставке нет']]);

        html += '</div>';

        // Накладные, расписки и акты по этому аппарату
        html += renderDocuments(docs, docsResp, 'phone');

        // Доп. панели быстрого набора
        html += `<h4 class="ph-detail-h">Доп. панели быстрого набора (${data.expansions.length})</h4>`;
        if (!data.expansions.length) {
            html += '<div class="ph-muted ph-detail-empty">Панели не подключены</div>';
        } else {
            html += '<table class="ph-subtable"><thead><tr><th>Модель</th><th>Кнопок</th><th>Серийный №</th><th>MAC</th><th>Коробка</th><th>Статус</th>'
                 + (isAdmin ? '<th></th>' : '') + '</tr></thead><tbody>';
            data.expansions.forEach(x => {
                html += `<tr>
                    <td>${dash((x.vendor_name ? x.vendor_name + ' ' : '') + (x.model_name || ''))}</td>
                    <td>${dash(x.keys_count)}</td>
                    <td class="ph-mono">${dash(x.serial_number)}</td>
                    <td class="ph-mono">${dash(x.mac_address)}</td>
                    <td>${x.box_number ? '№' + esc(x.box_number) : '<span class="ph-muted">—</span>'}</td>
                    <td>${dash(x.status)}</td>
                    ${isAdmin ? `<td><button type="button" class="btn small secondary" data-detach="${x.id}">Снять</button></td>` : ''}
                </tr>`;
            });
            html += '</tbody></table>';
            if (isAdmin) {
                html += `<button type="button" class="btn small" id="phDetailAttachBtn">+ Подключить панель</button>`;
            }
        }

        // История переименований
        html += `<h4 class="ph-detail-h">История переименований (${data.renames.length})</h4>`;
        if (!data.renames.length) {
            html += '<div class="ph-muted ph-detail-empty">Записей нет</div>';
        } else {
            html += '<table class="ph-subtable"><thead><tr><th>Дата</th><th>Было</th><th>Стало</th><th>Основание</th></tr></thead><tbody>';
            data.renames.forEach(r => {
                html += `<tr>
                    <td>${dash(fmtDate(r.rename_date))}</td>
                    <td>${dash(r.old_name)}</td>
                    <td>${dash(r.new_name)}</td>
                    <td>${dash(r.reason)}</td>
                </tr>`;
            });
            html += '</tbody></table>';
        }

        // История замен аппарата
        html += `<h4 class="ph-detail-h">История замен (${data.replacements.length})</h4>`;
        if (!data.replacements.length) {
            html += '<div class="ph-muted ph-detail-empty">Записей нет</div>';
        } else {
            html += '<table class="ph-subtable"><thead><tr><th>Дата</th><th>Старое устройство</th><th>Новое устройство</th><th>Причина</th></tr></thead><tbody>';
            data.replacements.forEach(r => {
                const oldD = [r.old_model, r.old_serial, r.old_mac].filter(Boolean).join(' · ');
                const newD = [r.new_model, r.new_serial, r.new_mac].filter(Boolean).join(' · ');
                html += `<tr>
                    <td>${dash(fmtDate(r.replace_date))}</td>
                    <td>${dash(oldD)}</td>
                    <td>${dash(newD)}</td>
                    <td>${dash(r.reason)}</td>
                </tr>`;
            });
            html += '</tbody></table>';
        }

        // Журнал изменений
        if (data.logs && data.logs.length) {
            html += `<h4 class="ph-detail-h">Журнал изменений</h4>`;
            html += '<table class="ph-subtable"><thead><tr><th>Дата</th><th>Пользователь</th><th>Действие</th><th>Изменения</th></tr></thead><tbody>';
            data.logs.forEach(l => {
                let ch = '';
                if (l.changes) {
                    try {
                        const parsed = JSON.parse(l.changes);
                        ch = Object.keys(parsed).map(k =>
                            `${esc(parsed[k].label || k)}: ${esc(parsed[k].from || '—')} → ${esc(parsed[k].to || '—')}`
                        ).join('<br>');
                    } catch (e) { ch = esc(l.changes); }
                }
                html += `<tr>
                    <td>${esc(String(l.created_at).slice(0, 16).replace('T', ' '))}</td>
                    <td>${dash(l.username)}</td>
                    <td>${dash(actionLabel(l.action))}</td>
                    <td>${ch || dash(l.details)}</td>
                </tr>`;
            });
            html += '</tbody></table>';
        }

        el('phoneDetailBody').innerHTML = html;

        // Снятие панели прямо из карточки
        el('phoneDetailBody').querySelectorAll('[data-detach]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const data2 = await post('update_expansion', { id: btn.dataset.detach, phone_id: '' });
                if (data2.error) { toast(data2.error, 'error'); return; }
                toast('Панель снята с телефона', 'success');
                openPhoneDetail(id);
                loadPhones();
                if (expState.loaded) loadExpansions();
            });
        });

        const attachBtn = el('phDetailAttachBtn');
        if (attachBtn) attachBtn.addEventListener('click', () => attachExpansionTo(id));

        bindDocuments(id, docsResp);

        showModal(el('phoneDetailModal'));
    }

    window.closePhoneDetail = function () {
        closeModalAndReset('phoneDetailModal');
    };

    /** Подключение свободной панели к телефону из его карточки. */
    async function attachExpansionTo(phoneId) {
        const data = await get('get_expansions', { attached: '0', per_page: 200 });
        if (data.error) { toast(data.error, 'error'); return; }
        if (!data.data.length) {
            toast('Свободных доп. панелей нет — заведите их во вкладке «Доп. панели»', 'info');
            return;
        }
        const list = data.data.map((x, i) =>
            `${i + 1}. ${(x.model_name || 'без модели')} · ${x.serial_number || x.mac_address || '#' + x.id}`
        ).join('\n');
        const answer = prompt('Введите номер панели из списка:\n\n' + list);
        if (!answer) return;
        const idx = parseInt(answer, 10) - 1;
        if (isNaN(idx) || idx < 0 || idx >= data.data.length) {
            toast('Некорректный номер', 'error');
            return;
        }
        const res = await post('update_expansion', { id: data.data[idx].id, phone_id: phoneId });
        if (res.error) { toast(res.error, 'error'); return; }
        toast('Панель подключена', 'success');
        openPhoneDetail(phoneId);
        loadPhones();
        if (expState.loaded) loadExpansions();
    }

    const ACTION_LABELS = {
        add_phone: 'Добавление телефона', edit_phone: 'Изменение телефона', delete_phone: 'Удаление телефона',
        add_box: 'Добавление коробки', edit_box: 'Изменение коробки', delete_box: 'Удаление коробки',
        open_box: 'Вскрытие коробки',
        add_delivery: 'Добавление поставки', edit_delivery: 'Изменение поставки', delete_delivery: 'Удаление поставки',
        add_expansion: 'Добавление доп. панели', edit_expansion: 'Изменение доп. панели',
        delete_expansion: 'Удаление доп. панели',
        attach_expansion: 'Подключение доп. панели', detach_expansion: 'Снятие доп. панели',
        import_phones: 'Импорт из Excel'
    };
    function actionLabel(a) { return ACTION_LABELS[a] || a; }

    // ==================================================================
    //  РАЗДЕЛ 2: ПОСТАВКИ И КОРОБКИ
    // ==================================================================
    async function loadDeliveries() {
        const box = el('phDeliveries');
        if (!box) return;
        box.innerHTML = '<div class="ph-empty">Загрузка…</div>';

        const data = await get('get_deliveries');
        if (data.error) {
            box.innerHTML = `<div class="ph-empty ph-error">${esc(data.error)}</div>`;
            return;
        }
        deliveriesCache = data;
        deliveriesCache.loaded = true;
        renderDeliveries();
    }

    function renderDeliveries() {
        const box = el('phDeliveries');
        const query = (el('phBoxSearch') ? el('phBoxSearch').value : '').trim().toLowerCase();
        const fStatus = el('phBoxFilterStatus') ? el('phBoxFilterStatus').value : '';
        const fType = el('phBoxFilterType') ? el('phBoxFilterType').value : '';

        const boxMatches = b => {
            if (fStatus && b.status !== fStatus) return false;
            if (fType && b.item_type !== fType) return false;
            if (!query) return true;
            return [b.box_number, b.model_name, b.vendor_name, b.notes]
                .some(v => String(v || '').toLowerCase().includes(query));
        };

        let html = '';
        let shown = 0;

        deliveriesCache.deliveries.forEach(d => {
            const boxes = (d.boxes || []).filter(boxMatches);
            // Поставку показываем, если подходят её коробки либо она сама
            const dMatch = !query || [d.doc_number, d.vendor_name, d.notes]
                .some(v => String(v || '').toLowerCase().includes(query));
            if (!boxes.length && !(dMatch && !fStatus && !fType)) return;
            shown++;

            html += `<div class="ph-delivery" data-id="${d.id}">
                <div class="ph-delivery-head">
                    <div class="ph-delivery-title">
                        <span class="ph-delivery-doc">${esc(d.doc_number || 'Поставка #' + d.id)}</span>
                        ${d.delivery_date ? `<span class="ph-muted">от ${fmtDate(d.delivery_date)}</span>` : ''}
                        ${d.vendor_name ? `<span class="ph-muted">· ${esc(d.vendor_name)}</span>` : ''}
                    </div>
                    <div class="ph-delivery-meta">
                        Коробок: ${d.boxes_count} · разобрано ${d.items_used} из ${d.items_declared || '?'}
                    </div>
                    ${isAdmin ? `<div class="ph-delivery-actions">
                        <button type="button" class="btn small secondary" data-act="edit-delivery" data-id="${d.id}">✎</button>
                        <button type="button" class="btn small danger" data-act="delete-delivery" data-id="${d.id}">🗑</button>
                    </div>` : ''}
                </div>
                <div class="ph-box-grid">${boxes.map(renderBoxCard).join('') || '<div class="ph-muted ph-detail-empty">Коробок нет</div>'}</div>
            </div>`;
        });

        // Коробки без накладной — их тоже надо где-то показывать
        const loose = (deliveriesCache.loose_boxes || []).filter(boxMatches);
        if (loose.length) {
            shown++;
            html += `<div class="ph-delivery">
                <div class="ph-delivery-head">
                    <div class="ph-delivery-title"><span class="ph-delivery-doc">Без накладной</span></div>
                    <div class="ph-delivery-meta">Коробок: ${loose.length}</div>
                </div>
                <div class="ph-box-grid">${loose.map(renderBoxCard).join('')}</div>
            </div>`;
        }

        box.innerHTML = shown ? html : '<div class="ph-empty">Поставки и коробки не найдены</div>';
    }

    function renderBoxCard(b) {
        const declared = b.qty_declared !== null && b.qty_declared !== undefined ? Number(b.qty_declared) : null;
        const used = Number(b.used) || 0;
        const pct = declared ? Math.min(100, Math.round(used / declared * 100)) : 0;
        const typeLabel = b.item_type === 'expansion' ? 'Доп. панели' : 'Телефоны';

        return `<div class="ph-box-card ph-box-${b.status.replace(/\s/g, '')}" data-box="${b.id}">
            <div class="ph-box-top">
                <span class="ph-box-num">📦 №${esc(b.box_number || b.id)}</span>
                <span class="ph-box-status">${esc(b.status)}</span>
            </div>
            <div class="ph-box-model">${esc(b.model_name || 'модель не указана')}</div>
            <div class="ph-box-type">${typeLabel}</div>
            <div class="ph-box-bar"><span style="width:${pct}%"></span></div>
            <div class="ph-box-count">${used} из ${declared !== null ? declared : '?'} шт.</div>
            <div class="ph-box-actions">
                <button type="button" class="btn small secondary" data-act="view-box" data-id="${b.id}">Содержимое</button>
                ${isAdmin ? `
                <button type="button" class="btn small" data-act="open-box" data-id="${b.id}" ${b.status === 'разобрана' ? 'disabled' : ''}>Вскрыть</button>
                <button type="button" class="btn small secondary" data-act="edit-box" data-id="${b.id}">✎</button>
                <button type="button" class="btn small danger" data-act="delete-box" data-id="${b.id}">🗑</button>` : ''}
            </div>
        </div>`;
    }

    // ---------- Форма поставки ----------
    async function openDeliveryForm(id) {
        await loadRefs();
        const modal = el('deliveryFormModal');
        resetModalForm(modal);
        showErr('deliveryFormError', '');

        el('deliveryFormTitle').textContent = id ? 'Редактировать поставку' : 'Новая поставка';
        el('deliveryFormId').value = id || '';

        // При редактировании коробки уже созданы — блок массового создания не нужен
        el('deliveryBoxesBlock').style.display = id ? 'none' : '';

        let d = null;
        if (id) d = deliveriesCache.deliveries.find(x => String(x.id) === String(id));

        fillSelect(el('deliveryFormVendor'), refs.vendors, { value: d && d.vendor_id });
        fillSelect(el('deliveryFormModel'), refs.phone_models, {});

        if (d) {
            el('deliveryFormDate').value = d.delivery_date ? String(d.delivery_date).slice(0, 10) : '';
            el('deliveryFormDoc').value = d.doc_number || '';
            el('deliveryFormNotes').value = d.notes || '';
        } else {
            el('deliveryFormDate').value = new Date().toISOString().slice(0, 10);
            el('deliveryFormBoxCount').value = '0';
            el('deliveryFormPerBox').value = '10';
        }

        showModal(modal);
    }

    window.closeDeliveryForm = function () {
        closeModalAndReset('deliveryFormModal');
    };

    async function submitDeliveryForm(e) {
        e.preventDefault();
        showErr('deliveryFormError', '');
        const id = el('deliveryFormId').value;
        const data = await post(id ? 'update_delivery' : 'add_delivery', new FormData(el('deliveryForm')));
        if (data.error) { showErr('deliveryFormError', data.error); return; }

        toast(data.boxes_created
            ? `Поставка добавлена, коробок создано: ${data.boxes_created}`
            : (id ? 'Поставка сохранена' : 'Поставка добавлена'), 'success');
        window.closeDeliveryForm();
        loadDeliveries();
        loadRefs(true);
    }

    // ---------- Форма коробки ----------
    async function openBoxForm(id) {
        await loadRefs();
        const modal = el('boxFormModal');
        resetModalForm(modal);
        showErr('boxFormError', '');

        el('boxFormTitle').textContent = id ? 'Редактировать коробку' : 'Добавить коробку';
        el('boxFormId').value = id || '';

        let b = null;
        if (id) {
            const data = await get('get_box_detail', { id: id });
            if (data.error) { toast(data.error, 'error'); return; }
            b = data.box;
        }

        fillSelect(el('boxFormDelivery'), refs.deliveries, {
            value: b && b.delivery_id, placeholder: '-- без накладной --'
        });

        el('boxFormItemType').value = b ? b.item_type : 'phone';
        syncBoxModelSelect(b ? (b.item_type === 'expansion' ? b.expansion_model_id : b.phone_model_id) : null);

        if (b) {
            el('boxFormNumber').value = b.box_number || '';
            el('boxFormQty').value    = b.qty_declared || '';
            el('boxFormStatus').value = b.status;
            el('boxFormOpened').value = b.opened_at ? String(b.opened_at).slice(0, 10) : '';
            el('boxFormNotes').value  = b.notes || '';
        } else {
            el('boxFormQty').value = '10';
        }

        showModal(modal);
    }

    /** Список моделей в форме коробки зависит от того, что в ней лежит. */
    function syncBoxModelSelect(value) {
        const type = el('boxFormItemType').value;
        const items = type === 'expansion' ? refs.expansion_models : refs.phone_models;
        fillSelect(el('boxFormModel'), items, { value: value });
    }

    window.closeBoxForm = function () {
        closeModalAndReset('boxFormModal');
    };

    async function submitBoxForm(e) {
        e.preventDefault();
        showErr('boxFormError', '');
        const id = el('boxFormId').value;
        const data = await post(id ? 'update_box' : 'add_box', new FormData(el('boxForm')));
        if (data.error) { showErr('boxFormError', data.error); return; }

        toast(id ? 'Коробка сохранена' : 'Коробка добавлена', 'success');
        window.closeBoxForm();
        loadDeliveries();
        loadRefs(true);
    }

    async function deleteBox(id) {
        if (!confirm('Удалить коробку?')) return;
        const data = await post('delete_box', { id: id });
        if (data.error) { toast(data.error, 'error'); return; }
        toast('Коробка удалена', 'success');
        loadDeliveries();
        loadRefs(true);
    }

    async function deleteDelivery(id) {
        if (!confirm('Удалить поставку вместе с её пустыми коробками?')) return;
        const data = await post('delete_delivery', { id: id });
        if (data.error) { toast(data.error, 'error'); return; }
        toast(data.boxes_deleted
            ? `Поставка удалена, коробок удалено: ${data.boxes_deleted}`
            : 'Поставка удалена', 'success');
        loadDeliveries();
        loadRefs(true);
    }

    // ---------- Вскрытие коробки ----------
    async function openBoxDialog(id) {
        const data = await get('get_box_detail', { id: id });
        if (data.error) { toast(data.error, 'error'); return; }

        const b = data.box;
        const occ = data.occupancy;
        const isPhone = b.item_type !== 'expansion';

        if (!b.phone_model_id && !b.expansion_model_id) {
            toast('У коробки не указана модель — заполните её перед вскрытием', 'error');
            openBoxForm(id);
            return;
        }

        const modal = el('openBoxModal');
        resetModalForm(modal, { keepValues: true });
        showErr('openBoxError', '');

        el('openBoxTitle').textContent = 'Вскрыть коробку №' + (b.box_number || b.id);
        el('openBoxId').value = id;
        el('openBoxBlankMode').checked = false;

        el('openBoxHead').innerHTML = `
            <div><b>${esc(b.model_name || 'модель не указана')}</b> · ${isPhone ? 'телефоны' : 'доп. панели'}</div>
            <div class="ph-muted">
                Заявлено: ${occ.declared !== null ? occ.declared : '?'} шт. ·
                уже заведено: ${occ.used} ·
                свободно: ${occ.free !== null ? occ.free : '?'}
            </div>`;

        // По умолчанию — столько строк, сколько осталось в коробке
        const rowsCount = occ.free !== null ? occ.free : 10;
        const list = el('openBoxList');
        list.innerHTML = '';
        for (let i = 0; i < rowsCount; i++) addOpenBoxRow();

        showModal(modal);
    }

    function addOpenBoxRow() {
        const list = el('openBoxList');
        const n = list.children.length + 1;
        const row = document.createElement('div');
        row.className = 'ph-openbox-row';
        row.innerHTML = `
            <span class="ph-openbox-n">${n}</span>
            <input type="text" class="ph-openbox-serial" placeholder="Серийный номер" maxlength="100">
            <input type="text" class="ph-openbox-mac" placeholder="MAC-адрес" maxlength="20">
            <button type="button" class="ph-openbox-del" title="Убрать строку">✕</button>`;
        row.querySelector('.ph-openbox-del').addEventListener('click', () => {
            row.remove();
            renumberOpenBox();
        });
        list.appendChild(row);
    }

    function renumberOpenBox() {
        el('openBoxList').querySelectorAll('.ph-openbox-row').forEach((r, i) => {
            r.querySelector('.ph-openbox-n').textContent = i + 1;
        });
    }

    window.closeOpenBox = function () {
        closeModalAndReset('openBoxModal');
        el('openBoxList').innerHTML = '';
    };

    async function submitOpenBox(e) {
        e.preventDefault();
        showErr('openBoxError', '');

        const id = el('openBoxId').value;
        const blank = el('openBoxBlankMode').checked;

        const payload = { id: id };
        if (blank) {
            payload.count = 0;
        } else {
            const items = [];
            el('openBoxList').querySelectorAll('.ph-openbox-row').forEach(row => {
                const serial = row.querySelector('.ph-openbox-serial').value.trim();
                const mac = row.querySelector('.ph-openbox-mac').value.trim();
                if (serial || mac) items.push({ serial_number: serial, mac_address: mac });
            });
            if (!items.length) {
                showErr('openBoxError', 'Заполните хотя бы одну строку либо отметьте «просто вскрыть»');
                return;
            }
            payload.items = JSON.stringify(items);
        }

        const data = await post('open_box', payload);
        if (data.error) { showErr('openBoxError', data.error); return; }

        toast(`Коробка вскрыта, заведено: ${data.created} шт. (${data.status})`, 'success');
        window.closeOpenBox();
        loadDeliveries();
        loadPhones();
        loadRefs(true);
        if (expState.loaded) loadExpansions();
    }

    // ---------- Просмотр содержимого коробки ----------
    async function viewBox(id) {
        const data = await get('get_box_detail', { id: id });
        if (data.error) { toast(data.error, 'error'); return; }

        const b = data.box;
        const isPhone = b.item_type !== 'expansion';

        el('phoneDetailTitle').textContent = 'Коробка №' + (b.box_number || b.id);

        let html = `<div class="ph-detail-grid">
            <div class="ph-detail-key">Содержимое</div><div class="ph-detail-val">${isPhone ? 'Телефоны' : 'Доп. панели быстрого набора'}</div>
            <div class="ph-detail-key">Модель</div><div class="ph-detail-val">${dash((b.vendor_name ? b.vendor_name + ' ' : '') + (b.model_name || ''))}</div>
            <div class="ph-detail-key">Заявлено</div><div class="ph-detail-val">${dash(b.qty_declared)}</div>
            <div class="ph-detail-key">Заведено</div><div class="ph-detail-val">${data.occupancy.used}</div>
            <div class="ph-detail-key">Статус</div><div class="ph-detail-val">${dash(b.status)}</div>
            <div class="ph-detail-key">Дата вскрытия</div><div class="ph-detail-val">${dash(fmtDate(b.opened_at))}</div>
            <div class="ph-detail-key">Накладная</div><div class="ph-detail-val">${dash(b.doc_number)}${b.delivery_date ? ' от ' + fmtDate(b.delivery_date) : ''}</div>
            <div class="ph-detail-key">Примечание</div><div class="ph-detail-val">${dash(b.notes)}</div>
        </div>`;

        html += `<h4 class="ph-detail-h">Содержимое (${data.items.length})</h4>`;
        if (!data.items.length) {
            html += '<div class="ph-muted ph-detail-empty">Коробка ещё не разобрана</div>';
        } else if (isPhone) {
            html += '<table class="ph-subtable"><thead><tr><th>Серийный №</th><th>MAC</th><th>Номер</th><th>Пользователь</th><th>Подразделение</th><th>Статус</th></tr></thead><tbody>';
            data.items.forEach(p => {
                html += `<tr>
                    <td class="ph-mono">${dash(p.serial_number)}</td>
                    <td class="ph-mono">${dash(p.mac_address)}</td>
                    <td>${dash(p.phone_number)}</td>
                    <td>${dash(p.user_fio)}</td>
                    <td>${dash(p.department_name)}</td>
                    <td>${dash(p.status)}</td>
                </tr>`;
            });
            html += '</tbody></table>';
        } else {
            html += '<table class="ph-subtable"><thead><tr><th>Серийный №</th><th>MAC</th><th>Подключена к</th><th>Статус</th></tr></thead><tbody>';
            data.items.forEach(x => {
                const to = x.phone_id
                    ? esc([x.phone_number, x.user_fio].filter(Boolean).join(' — '))
                    : '<span class="ph-muted">свободна</span>';
                html += `<tr>
                    <td class="ph-mono">${dash(x.serial_number)}</td>
                    <td class="ph-mono">${dash(x.mac_address)}</td>
                    <td>${to}</td>
                    <td>${dash(x.status)}</td>
                </tr>`;
            });
            html += '</tbody></table>';
        }

        el('phoneDetailBody').innerHTML = html;
        showModal(el('phoneDetailModal'));
    }

    // ==================================================================
    //  РАЗДЕЛ 3: ДОП. ПАНЕЛИ
    // ==================================================================
    async function loadExpansions() {
        const tbody = el('phExpTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" class="ph-empty">Загрузка…</td></tr>';

        const data = await get('get_expansions', {
            page: expState.page,
            per_page: expState.perPage,
            sort: expState.sort,
            order: expState.order,
            search: el('phExpSearch') ? el('phExpSearch').value.trim() : '',
            attached: el('phExpFilterAttached') ? el('phExpFilterAttached').value : '',
            status: el('phExpFilterStatus') ? el('phExpFilterStatus').value : '',
            box_id: el('phExpFilterBox') ? el('phExpFilterBox').value : ''
        });

        if (data.error) {
            tbody.innerHTML = `<tr><td colspan="8" class="ph-empty ph-error">${esc(data.error)}</td></tr>`;
            return;
        }

        expState.rows = data.data || [];
        expState.page = data.page;
        expState.loaded = true;

        if (!expState.rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="ph-empty">Доп. панели не найдены</td></tr>';
        } else {
            tbody.innerHTML = expState.rows.map(x => {
                const attached = x.phone_id
                    ? esc([x.phone_number, x.user_fio].filter(Boolean).join(' — ') || '#' + x.phone_id)
                    : '<span class="ph-muted">свободна</span>';
                return `
                <tr data-id="${x.id}">
                    <td>${dash((x.vendor_name ? x.vendor_name + ' ' : '') + (x.model_name || ''))}</td>
                    <td>${dash(x.keys_count)}</td>
                    <td class="ph-mono">${dash(x.serial_number)}</td>
                    <td class="ph-mono">${dash(x.mac_address)}</td>
                    <td>${x.box_number ? '№' + esc(x.box_number) : '<span class="ph-muted">—</span>'}</td>
                    <td>${attached}</td>
                    <td><span class="ph-status ph-status-${statusClass(x.status)}">${esc(x.status)}</span></td>
                    <td class="ph-actions">
                        ${isAdmin ? `
                        ${x.phone_id ? `<button type="button" class="btn small secondary" data-act="detach" title="Снять с телефона">⤓</button>` : ''}
                        <button type="button" class="btn small" data-act="edit" title="Редактировать">✎</button>
                        <button type="button" class="btn small danger" data-act="delete" title="Удалить">🗑</button>` : ''}
                    </td>
                </tr>`;
            }).join('');
        }

        renderPagination('phExpPagination', data, p => { expState.page = p; loadExpansions(); });
    }

    async function openExpansionForm(id) {
        await loadRefs();
        const modal = el('expansionFormModal');
        resetModalForm(modal);
        showErr('expansionFormError', '');

        el('expansionFormTitle').textContent = id ? 'Редактировать доп. панель' : 'Добавить доп. панель';
        el('expansionFormId').value = id || '';

        const x = id ? expState.rows.find(r => String(r.id) === String(id)) : null;

        fillSelect(el('expansionFormModel'), refs.expansion_models, { value: x && x.model_id });
        fillSelect(el('expansionFormBox'), boxesOfType('expansion'), {
            value: x && x.box_id, placeholder: '-- вне коробки --'
        });

        // Телефоны для привязки грузим отдельно: их много и они меняются чаще справочников
        const phonesResp = await get('get_phones', { per_page: 200, sort: 'phone_number' });
        const phoneItems = (phonesResp.data || []).map(p => ({
            id: p.id,
            name: [p.phone_number, p.user_fio, p.model_name].filter(Boolean).join(' · ') || ('Телефон #' + p.id)
        }));
        fillSelect(el('expansionFormPhone'), phoneItems, {
            value: x && x.phone_id, placeholder: '-- не подключена --'
        });

        if (x) {
            el('expansionFormSerial').value = x.serial_number || '';
            el('expansionFormMac').value    = x.mac_address || '';
            el('expansionFormStatus').value = x.status || 'на складе';
            el('expansionFormNotes').value  = x.notes || '';
        }

        showModal(modal);
    }

    window.closeExpansionForm = function () {
        closeModalAndReset('expansionFormModal');
    };

    async function submitExpansionForm(e) {
        e.preventDefault();
        showErr('expansionFormError', '');
        const id = el('expansionFormId').value;
        const data = await post(id ? 'update_expansion' : 'add_expansion', new FormData(el('expansionForm')));
        if (data.error) { showErr('expansionFormError', data.error); return; }

        toast(id ? 'Панель сохранена' : 'Панель добавлена', 'success');
        window.closeExpansionForm();
        loadExpansions();
        loadPhones();
        loadRefs(true);
    }

    async function deleteExpansion(id) {
        if (!confirm('Удалить доп. панель?')) return;
        const data = await post('delete_expansion', { id: id });
        if (data.error) { toast(data.error, 'error'); return; }
        toast('Панель удалена', 'success');
        loadExpansions();
        loadRefs(true);
    }

    // ==================================================================
    //  ИМПОРТ ИЗ EXCEL
    // ==================================================================
    window.closePhoneImport = function () {
        closeModalAndReset('phoneImportModal');
        if (typeof resetFilePickers === 'function') resetFilePickers(el('phoneImportModal'));
        el('phoneImportResult').innerHTML = '';
        el('phoneImportCommitBtn').disabled = true;
    };

    async function runImport(mode) {
        showErr('phoneImportError', '');
        const fd = new FormData();
        fd.append('mode', mode);

        const file = el('phoneImportFile').files[0];
        const path = el('phoneImportPath').value.trim();
        if (file) fd.append('file', file);
        else if (path) fd.append('path', path);
        else {
            showErr('phoneImportError', 'Выберите файл или укажите путь');
            return;
        }

        const btn = mode === 'commit' ? el('phoneImportCommitBtn') : el('phoneImportPreviewBtn');
        const oldText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Обработка…';

        const data = await post('import_phones', fd);

        btn.disabled = false;
        btn.textContent = oldText;

        if (data.error) {
            showErr('phoneImportError', data.error);
            return;
        }

        const s = data.stats;
        let html = `<div class="ph-import-summary">
            <b>${mode === 'preview' ? 'Проверка' : 'Импорт завершён'}</b><br>
            Строк обработано: ${s.total} ·
            добавлено: ${s.inserted} ·
            обновлено: ${s.updated} ·
            переименований: ${s.renames} ·
            замен: ${s.replaces}
            ${s.boxes ? ' · коробок: ' + s.boxes : ''}
        </div>`;

        const sheets = Object.keys(s.sheets || {});
        if (sheets.length) {
            html += '<table class="ph-subtable"><thead><tr><th>Лист</th><th>Строк</th><th>Пропущено</th></tr></thead><tbody>';
            sheets.forEach(name => {
                const st = s.sheets[name];
                html += `<tr><td>${esc(name)}</td><td>${st.rows}</td><td>${st.skipped}</td></tr>`;
            });
            html += '</tbody></table>';
        }
        if (s.problems && s.problems.length) {
            html += '<div class="ph-import-problems"><b>Замечания:</b><ul>'
                 + s.problems.map(p => `<li>${esc(p)}</li>`).join('') + '</ul></div>';
        }

        el('phoneImportResult').innerHTML = html;
        el('phoneImportCommitBtn').disabled = (mode !== 'preview');

        if (mode === 'commit') {
            toast('Импорт завершён', 'success');
            loadPhones();
            loadRefs(true);
            deliveriesCache.loaded = false;
            expState.loaded = false;
        }
    }

    // ==================================================================
    //  ПРИВЯЗКА СОБЫТИЙ
    // ==================================================================
    function initPhonesTab() {
        const reload = () => { phState.page = 1; loadPhones(); };

        const search = el('phSearch');
        if (search) search.addEventListener('input', debounce(reload, 350));

        ['phFilterDept', 'phFilterModel', 'phFilterStatus', 'phFilterIssued', 'phFilterBox']
            .forEach(id => { const n = el(id); if (n) n.addEventListener('change', reload); });

        const perPage = el('phPerPage');
        if (perPage) perPage.addEventListener('change', () => {
            phState.perPage = parseInt(perPage.value, 10) || 25;
            phState.page = 1;
            loadPhones();
        });

        const resetBtn = el('phResetBtn');
        if (resetBtn) resetBtn.addEventListener('click', () => {
            ['phSearch', 'phFilterDept', 'phFilterModel', 'phFilterStatus', 'phFilterIssued', 'phFilterBox']
                .forEach(id => { const n = el(id); if (n) n.value = ''; });
            reload();
        });

        // Сортировка по клику на заголовок
        document.querySelectorAll('#phTable .ph-sortable').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (phState.sort === col) {
                    phState.order = phState.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    phState.sort = col;
                    phState.order = 'ASC';
                }
                document.querySelectorAll('#phTable .ph-sortable').forEach(o => o.classList.remove('sort-asc', 'sort-desc'));
                th.classList.add(phState.order === 'ASC' ? 'sort-asc' : 'sort-desc');
                phState.page = 1;
                loadPhones();
            });
        });

        // Клик по строке открывает меню действий у курсора
        const tbody = el('phTableBody');
        if (tbody) {
            const openFromEvent = e => {
                // Ссылка на коробку работает сама по себе
                if (e.target.closest('.ph-link')) return false;

                const tr = e.target.closest('tr[data-id]');
                if (!tr) return false;

                const row = phState.rows.find(r => String(r.id) === String(tr.dataset.id));
                if (!row) return false;

                openPhoneMenu(e.clientX, e.clientY, row);
                return true;
            };

            tbody.addEventListener('click', e => {
                const boxBtn = e.target.closest('.ph-link[data-act="box"]');
                if (boxBtn) { viewBox(boxBtn.dataset.box); return; }
                openFromEvent(e);
            });

            // Правая кнопка — привычный способ вызвать то же меню
            tbody.addEventListener('contextmenu', e => {
                if (openFromEvent(e)) e.preventDefault();
            });
        }

        const addBtn = el('phAddBtn');
        if (addBtn) addBtn.addEventListener('click', () => openPhoneForm(null));

        const form = el('phoneForm');
        if (form) form.addEventListener('submit', submitPhoneForm);

        // Поля документа раскрываются, только когда файл выбран.
        // Саму карточку файла рисует file_picker.js — здесь только логика формы.
        const docFile = el('phoneFormDocFile');
        if (docFile) {
            docFile.addEventListener('change', () => {
                el('phoneFormDocFields').hidden = docFile.files.length === 0;
            });
            // Коробку могли выбрать уже после открытия формы
            const boxSel = el('phoneFormBox');
            if (boxSel) boxSel.addEventListener('change', () => {
                el('phoneFormDocWholeBoxWrap').hidden = !boxSel.value;
                if (!boxSel.value) el('phoneFormDocWholeBox').checked = false;
            });
        }

        // Вкладки в примере листа: обычные данные и переименования
        const demo = el('phSheetDemo');
        if (demo) demo.addEventListener('click', e => {
            const tab = e.target.closest('.ph-sheet-tab');
            if (!tab) return;
            demo.querySelectorAll('.ph-sheet-tab').forEach(t => t.classList.remove('active'));
            demo.querySelectorAll('.ph-sheet-pane').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            const pane = demo.querySelector('.ph-sheet-pane[data-demo="' + tab.dataset.demo + '"]');
            if (pane) pane.classList.add('active');
        });

        const importBtn = el('phImportBtn');
        if (importBtn) importBtn.addEventListener('click', () => {
            showErr('phoneImportError', '');
            el('phoneImportResult').innerHTML = '';
            el('phoneImportCommitBtn').disabled = true;
            if (typeof enhanceFileInputs === 'function') enhanceFileInputs(el('phoneImportModal'));
            showModal(el('phoneImportModal'));
        });
        const prevBtn = el('phoneImportPreviewBtn');
        if (prevBtn) prevBtn.addEventListener('click', () => runImport('preview'));
        const commitBtn = el('phoneImportCommitBtn');
        if (commitBtn) commitBtn.addEventListener('click', () => {
            if (confirm('Записать разобранные данные в базу?')) runImport('commit');
        });
    }

    function initBoxesTab() {
        const reload = debounce(renderDeliveries, 250);
        const s = el('phBoxSearch');
        if (s) s.addEventListener('input', reload);
        ['phBoxFilterStatus', 'phBoxFilterType']
            .forEach(id => { const n = el(id); if (n) n.addEventListener('change', renderDeliveries); });

        const addD = el('phAddDeliveryBtn');
        if (addD) addD.addEventListener('click', () => openDeliveryForm(null));
        const addB = el('phAddBoxBtn');
        if (addB) addB.addEventListener('click', () => openBoxForm(null));

        const dForm = el('deliveryForm');
        if (dForm) dForm.addEventListener('submit', submitDeliveryForm);
        const bForm = el('boxForm');
        if (bForm) bForm.addEventListener('submit', submitBoxForm);
        const oForm = el('openBoxForm');
        if (oForm) oForm.addEventListener('submit', submitOpenBox);

        const itemType = el('boxFormItemType');
        if (itemType) itemType.addEventListener('change', () => syncBoxModelSelect(null));

        // Список моделей в форме поставки тоже зависит от типа содержимого
        const dItemType = el('deliveryFormItemType');
        if (dItemType) dItemType.addEventListener('change', () => {
            const items = dItemType.value === 'expansion' ? refs.expansion_models : refs.phone_models;
            fillSelect(el('deliveryFormModel'), items, {});
        });

        const addRow = el('openBoxAddRow');
        if (addRow) addRow.addEventListener('click', addOpenBoxRow);

        const blank = el('openBoxBlankMode');
        if (blank) blank.addEventListener('change', () => {
            el('openBoxList').style.display = blank.checked ? 'none' : '';
            el('openBoxAddRow').disabled = blank.checked;
        });

        // Делегирование действий по карточкам коробок и поставок
        const wrap = el('phDeliveries');
        if (wrap) wrap.addEventListener('click', e => {
            const btn = e.target.closest('button[data-act]');
            if (!btn) return;
            const id = btn.dataset.id;
            switch (btn.dataset.act) {
                case 'view-box':        viewBox(id); break;
                case 'open-box':        openBoxDialog(id); break;
                case 'edit-box':        openBoxForm(id); break;
                case 'delete-box':      deleteBox(id); break;
                case 'edit-delivery':   openDeliveryForm(id); break;
                case 'delete-delivery': deleteDelivery(id); break;
            }
        });
    }

    function initExpansionsTab() {
        const reload = () => { expState.page = 1; loadExpansions(); };

        const s = el('phExpSearch');
        if (s) s.addEventListener('input', debounce(reload, 350));
        ['phExpFilterAttached', 'phExpFilterStatus', 'phExpFilterBox']
            .forEach(id => { const n = el(id); if (n) n.addEventListener('change', reload); });

        const perPage = el('phExpPerPage');
        if (perPage) perPage.addEventListener('change', () => {
            expState.perPage = parseInt(perPage.value, 10) || 25;
            expState.page = 1;
            loadExpansions();
        });

        document.querySelectorAll('#phExpTable .ph-sortable').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (expState.sort === col) {
                    expState.order = expState.order === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    expState.sort = col;
                    expState.order = 'ASC';
                }
                document.querySelectorAll('#phExpTable .ph-sortable').forEach(o => o.classList.remove('sort-asc', 'sort-desc'));
                th.classList.add(expState.order === 'ASC' ? 'sort-asc' : 'sort-desc');
                expState.page = 1;
                loadExpansions();
            });
        });

        const tbody = el('phExpTableBody');
        if (tbody) tbody.addEventListener('click', async e => {
            const btn = e.target.closest('button[data-act]');
            if (!btn) return;
            const tr = btn.closest('tr');
            const id = tr && tr.dataset.id;
            if (!id) return;

            switch (btn.dataset.act) {
                case 'edit':   openExpansionForm(id); break;
                case 'delete': deleteExpansion(id); break;
                case 'detach': {
                    const data = await post('update_expansion', { id: id, phone_id: '' });
                    if (data.error) { toast(data.error, 'error'); return; }
                    toast('Панель снята с телефона', 'success');
                    loadExpansions();
                    loadPhones();
                    break;
                }
            }
        });

        const addBtn = el('phAddExpBtn');
        if (addBtn) addBtn.addEventListener('click', () => openExpansionForm(null));

        const form = el('expansionForm');
        if (form) form.addEventListener('submit', submitExpansionForm);
    }

    // ------------------------------------------------------------------
    //  Старт
    // ------------------------------------------------------------------
    function init() {
        if (!el('phTableBody')) return;   // не на странице телефонов
        initTabs();
        initPhonesTab();
        initBoxesTab();
        initExpansionsTab();
        loadPhones();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
