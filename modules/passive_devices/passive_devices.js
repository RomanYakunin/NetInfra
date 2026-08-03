/* ============================================================
   modules/passive_devices/passive_devices.js
   Формы пассивного оборудования (патч-панели, кроссы, модули,
   терминалы) и настройка направления портов.
   ============================================================ */
(function () {
    'use strict';

    // Контекст текущей формы: куда вернуться после сохранения
    let ctx = { onSaved: null };
    let portCtx = { onSaved: null };

    // Значения по умолчанию под каждый тип: избавляет от лишних кликов
    const TYPE_DEFAULTS = {
        patch_panel:   { name: 'Патч-панель',      ports: 24, portType: 'RJ45', rows: 1, showPorts: true },
        optical_panel: { name: 'Оптический кросс', ports: 12, portType: 'LC',   rows: 1, showPorts: true },
        sfp_module:    { name: 'SFP-модуль',       ports: 0,  portType: 'SFP',  rows: 1, showPorts: false },
        psu_module:    { name: 'Блок питания',     ports: 0,  portType: 'other', rows: 1, showPorts: false },
        terminal:      { name: 'Терминал ВКС',     ports: 0,  portType: 'other', rows: 1, showPorts: false },
        other:         { name: '',                 ports: 0,  portType: 'other', rows: 1, showPorts: false },
    };

    const TYPE_TITLES = {
        patch_panel:   'патч-панели',
        optical_panel: 'оптического кросса',
        sfp_module:    'SFP-модуля',
        psu_module:    'блока питания',
        terminal:      'терминала ВКС',
        other:         'устройства',
    };

    function esc(s) {
        if (s === null || s === undefined) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type);
    }

    function showErr(id, msg) {
        const el = document.getElementById(id);
        if (el) { el.textContent = msg; el.style.display = ''; }
    }
    function hideErr(id) {
        const el = document.getElementById(id);
        if (el) { el.textContent = ''; el.style.display = 'none'; }
    }

    /** Заполняет select значениями {id, name}, сохраняя выбранное. */
    function fillSelect(select, items, selected) {
        if (!select) return;
        select.innerHTML = '<option value="">-- не выбрано --</option>';
        (items || []).forEach(it => select.appendChild(new Option(it.name, it.id)));
        if (selected) select.value = String(selected);
    }

    // ---------- Форма устройства ----------

    /**
     * Открывает форму пассивного оборудования.
     *
     * @param {Object} opts
     *   id            — при редактировании
     *   type          — предустановленный тип (из меню шкафа)
     *   node_id, rack_id, unit_position — контекст шкафа
     *   warehouse_id  — если добавляем на склад
     *   onSaved       — колбэк после успешного сохранения
     */
    window.openPassiveDeviceForm = async function (opts) {
        opts = opts || {};
        const modal = document.getElementById('passiveDeviceModal');
        const form = document.getElementById('passiveDeviceForm');
        if (!modal || !form) return;

        // Полный сброс: иначе поисковые селекты дублируются (см. utils.js)
        if (typeof resetModalForm === 'function') resetModalForm(modal);
        hideErr('passiveFormError');
        ctx.onSaved = typeof opts.onSaved === 'function' ? opts.onSaved : null;

        document.getElementById('passiveId').value = opts.id || '';
        document.getElementById('passiveNodeId').value = opts.node_id || '';
        document.getElementById('passiveRackId').value = opts.rack_id || '';
        document.getElementById('passiveWarehouseId').value = opts.warehouse_id || '';
        document.getElementById('passiveUnit').value = opts.unit_position || '';

        // На складе юнит не нужен — устройство не в шкафу
        const unitGroup = document.getElementById('passiveUnitGroup');
        if (unitGroup) unitGroup.style.display = opts.warehouse_id ? 'none' : '';

        const typeSelect = document.getElementById('passiveType');
        typeSelect.value = opts.type || 'patch_panel';

        // Производители
        try {
            const resp = await fetch('?ajax=get_list&list=vendors');
            const data = await resp.json();
            fillSelect(document.getElementById('passiveVendor'), data.data || []);
        } catch (e) { /* без списка форма всё равно работает */ }

        if (opts.id) {
            // Редактирование: подтягиваем текущие значения
            await loadDeviceIntoForm(opts.id);
            document.getElementById('passiveFormTitle').textContent = 'Редактирование устройства';
        } else {
            applyTypeDefaults(typeSelect.value, true);
            document.getElementById('passiveFormTitle').textContent =
                'Добавление ' + (TYPE_TITLES[typeSelect.value] || 'устройства');
            document.getElementById('passiveStatus').value = opts.warehouse_id ? 'на складе' : 'в эксплуатации';
        }

        // Поисковый селект для производителя — как в остальных формах
        const vendorSel = document.getElementById('passiveVendor');
        if (vendorSel && typeof SearchableSelect === 'function') new SearchableSelect(vendorSel);

        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');
    };

    window.closePassiveDeviceForm = function () {
        if (typeof closeModalAndReset === 'function') closeModalAndReset('passiveDeviceModal');
        else document.getElementById('passiveDeviceModal')?.classList.remove('visible');
        ctx.onSaved = null;
    };

    /** Подставляет значения по умолчанию для выбранного типа. */
    function applyTypeDefaults(type, setName) {
        const d = TYPE_DEFAULTS[type] || TYPE_DEFAULTS.other;

        const portsBlock = document.getElementById('passivePortsBlock');
        if (portsBlock) portsBlock.style.display = d.showPorts ? '' : 'none';

        // Ряды имеют смысл только для оптики
        const rowsGroup = document.getElementById('passiveRowsGroup');
        if (rowsGroup) rowsGroup.style.display = (type === 'optical_panel') ? '' : 'none';

        if (d.showPorts) {
            const pc = document.getElementById('passivePortsCount');
            if (pc) pc.value = String(d.ports);
            const pt = document.getElementById('passivePortType');
            if (pt) pt.value = d.portType;
            const pr = document.getElementById('passivePortRows');
            if (pr) pr.value = String(d.rows);
        }

        if (setName) {
            const nameInput = document.getElementById('passiveName');
            if (nameInput && !nameInput.value.trim()) {
                nameInput.value = d.showPorts ? (d.name + ' ' + d.ports + ' портов') : d.name;
            }
        }
    }

    /** Загружает устройство в форму при редактировании. */
    async function loadDeviceIntoForm(id) {
        try {
            const resp = await fetch('?ajax=get_passive_devices');
            const data = await resp.json();
            const dev = (data.data || []).find(d => d.id === parseInt(id, 10));
            if (!dev) return;

            document.getElementById('passiveType').value = dev.type;
            applyTypeDefaults(dev.type, false);

            document.getElementById('passiveName').value = dev.name || '';
            document.getElementById('passiveModel').value = dev.model || '';
            document.getElementById('passiveSerial').value = dev.serial_number || '';
            document.getElementById('passiveNotes').value = dev.notes || '';
            document.getElementById('passiveStatus').value = dev.status || 'в эксплуатации';
            document.getElementById('passiveUnit').value = dev.unit_position || '';
            document.getElementById('passiveNodeId').value = dev.node_id || '';
            document.getElementById('passiveRackId').value = dev.rack_id || '';
            document.getElementById('passiveWarehouseId').value = dev.warehouse_id || '';

            // Склад или шкаф выясняется только после загрузки — уточняем поле юнита
            const unitGroup = document.getElementById('passiveUnitGroup');
            if (unitGroup) unitGroup.style.display = dev.warehouse_id ? 'none' : '';

            const pc = document.getElementById('passivePortsCount');
            if (pc) {
                // Количество может не совпасть с пресетом — добавляем опцию
                if (!Array.from(pc.options).some(o => o.value === String(dev.ports_count))) {
                    pc.appendChild(new Option(String(dev.ports_count), String(dev.ports_count)));
                }
                pc.value = String(dev.ports_count);
            }
            const pt = document.getElementById('passivePortType');
            if (pt) pt.value = dev.port_type || 'RJ45';
            const pr = document.getElementById('passivePortRows');
            if (pr) pr.value = String(dev.port_rows || 1);

            const vendorSel = document.getElementById('passiveVendor');
            if (vendorSel && dev.vendor_id) vendorSel.value = String(dev.vendor_id);
        } catch (e) { /* оставляем форму как есть */ }
    }

    async function submitPassiveForm(e) {
        e.preventDefault();
        hideErr('passiveFormError');

        const form = e.target;
        const fd = new FormData(form);

        // Поисковый селект скрывает исходный <select> — берём значение явно
        const vendorSel = document.getElementById('passiveVendor');
        if (vendorSel) fd.set('vendor_id', vendorSel.value || '');

        // Для типов без портов количество не отправляем
        const type = document.getElementById('passiveType').value;
        if (!(TYPE_DEFAULTS[type] || {}).showPorts) fd.set('ports_count', '0');

        const id = document.getElementById('passiveId').value;
        const url = id ? '?ajax=update_passive_device' : '?ajax=add_passive_device';

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const data = await (await fetch(url, { method: 'POST', body: fd })).json();
            if (data.success) {
                const cb = ctx.onSaved;
                window.closePassiveDeviceForm();
                toast(id ? 'Устройство обновлено' : 'Устройство добавлено', 'success');
                if (cb) cb();
            } else {
                showErr('passiveFormError', data.error || 'Ошибка сохранения');
            }
        } catch (err) {
            showErr('passiveFormError', 'Ошибка сети');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    // ---------- Форма порта ----------

    /**
     * Открывает форму настройки порта.
     * @param {number}   deviceId
     * @param {number}   portNumber
     * @param {Function} onSaved
     */
    window.openPassivePortForm = async function (deviceId, portNumber, onSaved) {
        const modal = document.getElementById('passivePortModal');
        if (!modal) return;

        if (typeof resetModalForm === 'function') resetModalForm(modal);
        hideErr('passivePortError');
        portCtx.onSaved = typeof onSaved === 'function' ? onSaved : null;

        document.getElementById('portDeviceId').value = deviceId;
        document.getElementById('portNumber').value = portNumber;
        document.getElementById('passivePortTitle').textContent = 'Порт ' + portNumber;

        // Справочники для направления
        try {
            const [bResp, nResp] = await Promise.all([
                fetch('?ajax=get_list&list=buildings'),
                fetch('?ajax=get_nodes_list')
            ]);
            const bData = await bResp.json();
            fillSelect(document.getElementById('portBuilding'), bData.data || []);

            const nData = await nResp.json();
            const nodes = (Array.isArray(nData) ? nData : (nData.nodes || [])).map(n => ({
                id: n.id_node,
                name: n.KY_number ? ('КУ-' + n.KY_number) : ('Узел ' + n.id_node)
            }));
            fillSelect(document.getElementById('portNode'), nodes);
        } catch (e) { /* без справочников можно заполнить метку вручную */ }

        // Текущие значения порта
        try {
            const data = await (await fetch('?ajax=get_passive_ports&device_id=' + deviceId)).json();
            const port = (data.data || []).find(p => p.port_number === parseInt(portNumber, 10));
            if (port) {
                document.getElementById('portLabel').value = port.label || '';
                document.getElementById('portNotes').value = port.notes || '';
                if (port.destination_building_id) document.getElementById('portBuilding').value = String(port.destination_building_id);
                if (port.destination_node_id)     document.getElementById('portNode').value = String(port.destination_node_id);
                if (port.fiber_type)              document.getElementById('portFiber').value = port.fiber_type;
            }
        } catch (e) { /* новый порт — поля пустые */ }

        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');
    };

    window.closePassivePortForm = function () {
        if (typeof closeModalAndReset === 'function') closeModalAndReset('passivePortModal');
        else document.getElementById('passivePortModal')?.classList.remove('visible');
        portCtx.onSaved = null;
    };

    async function submitPortForm(e, clear) {
        if (e) e.preventDefault();
        hideErr('passivePortError');

        const fd = new FormData();
        fd.append('device_id', document.getElementById('portDeviceId').value);
        fd.append('port_number', document.getElementById('portNumber').value);

        // «Освободить порт» отправляет пустые значения — сервер снимет is_connected
        if (!clear) {
            fd.append('label', document.getElementById('portLabel').value.trim());
            fd.append('destination_building_id', document.getElementById('portBuilding').value);
            fd.append('destination_node_id', document.getElementById('portNode').value);
            fd.append('fiber_type', document.getElementById('portFiber').value);
            fd.append('notes', document.getElementById('portNotes').value.trim());
        }

        try {
            const data = await (await fetch('?ajax=update_passive_port', { method: 'POST', body: fd })).json();
            if (data.success) {
                const cb = portCtx.onSaved;
                window.closePassivePortForm();
                toast(clear ? 'Порт освобождён' : 'Порт сохранён', 'success');
                if (cb) cb();
            } else {
                showErr('passivePortError', data.error || 'Ошибка сохранения');
            }
        } catch (err) {
            showErr('passivePortError', 'Ошибка сети');
        }
    }

    // ---------- Инициализация ----------
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('passiveDeviceForm')?.addEventListener('submit', submitPassiveForm);
        document.getElementById('passivePortForm')?.addEventListener('submit', (e) => submitPortForm(e, false));
        document.getElementById('portClearBtn')?.addEventListener('click', () => submitPortForm(null, true));

        // Смена типа перестраивает форму под него
        document.getElementById('passiveType')?.addEventListener('change', function () {
            applyTypeDefaults(this.value, true);
            const titleEl = document.getElementById('passiveFormTitle');
            if (titleEl && !document.getElementById('passiveId').value) {
                titleEl.textContent = 'Добавление ' + (TYPE_TITLES[this.value] || 'устройства');
            }
        });
    });
})();
