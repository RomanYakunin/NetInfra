async function buildEquipmentDossier(fieldsContainer, config, initialData, extraData) {
    const state = window.AppState;
    if (state.currentRelatedId) state.currentEquipmentNodeId = state.currentRelatedId;
    if (initialData && initialData.id_node) state.currentEquipmentNodeId = initialData.id_node;

    const dossier = document.createElement('div');
    dossier.className = 'equipment-dossier';

    // --- Основная сетка полей ---
    const dossierSection = document.createElement('div');
    dossierSection.className = 'dossier-section';
    const dossierGrid = document.createElement('div');
    dossierGrid.className = 'dossier-grid';

    const fieldMap = {};
    config.fields.forEach(f => { fieldMap[f.name] = f; });

    let dossierFields = [
        'hostname', 'device_type_id', 'vendor_id', 'model_id', 'serial_number',
        'mac_address', 'ip_address', 'firmwares', 'id_rack', 'unit_position',
        'Annotation', 'status'
    ];

    let deviceTypeSelect = null, poeGroup = null;

    dossierFields.forEach(name => {
        const field = fieldMap[name];
        if (!field) return;

        const item = document.createElement('div');
        item.className = 'dossier-item';
        if (name === 'Annotation') item.classList.add('annotation-item');
        item.innerHTML = `<div class="label">${field.label}</div><div class="value"></div>`;
        const valueDiv = item.querySelector('.value');

        if (name === 'device_type_id') {
            const flexContainer = document.createElement('div');
            flexContainer.style.display = 'flex';
            flexContainer.style.alignItems = 'center';
            flexContainer.style.gap = '0.6rem';

            const select = document.createElement('select');
            select.name = 'device_type_id';
            select.className = 'dossier-select';
            select.dataset.source = 'device_types_list';
            select.appendChild(new Option('-- не выбрано --', ''));
            select.style.flex = '1';

            const poe = document.createElement('div');
            poe.className = 'poe-inline-group';
            poe.style.display = 'none';
            poe.style.alignItems = 'center';
            poe.style.gap = '0.3rem';
            poe.innerHTML = `
                <label class="checkbox-google">
                    <input type="checkbox" name="Poe" ${initialData && initialData.Poe ? 'checked' : ''}>
                    <span class="checkbox-google-switch"></span>
                </label>
                <span style="font-size:0.75rem; color:var(--text-secondary); white-space:nowrap;">PoE</span>
            `;

            flexContainer.appendChild(select);
            flexContainer.appendChild(poe);
            valueDiv.appendChild(flexContainer);

            deviceTypeSelect = select;
            poeGroup = poe;
        } else if (field.type === 'select' && field.source) {
            const select = document.createElement('select');
            select.name = field.name;
            select.className = 'dossier-select';
            select.dataset.source = field.source;
            select.appendChild(new Option('-- не выбрано --', ''));
            valueDiv.appendChild(select);
            
        } else if (field.type === 'textarea') {
            const textarea = document.createElement('textarea');
            textarea.name = field.name;
            textarea.className = 'dossier-input';
            textarea.rows = 2;
            textarea.style.resize = 'vertical';
            valueDiv.appendChild(textarea);
        } else {
            const input = document.createElement('input');
            input.type = field.type === 'number' ? 'number' : 'text';
            input.name = field.name;
            input.className = 'dossier-input';
            if (field.name === 'mac_address') input.classList.add('mac-address');
            valueDiv.appendChild(input);
        }
        dossierGrid.appendChild(item);
    });

    dossierSection.appendChild(dossierGrid);
    dossier.appendChild(dossierSection);

    // --- Встроенные модули ---
const modulesSection = document.createElement('div');
modulesSection.className = 'dossier-section';
modulesSection.innerHTML = `
    <h4 onclick="toggleSection(this)">
        <span class="section-arrow">▶</span> 🔧 Встроенные модули
        <span class="add-module-tile" id="add-module-type-btn" style="margin-left: auto; cursor:pointer;" 
              onclick="event.stopPropagation(); const r=this.getBoundingClientRect(); openModuleTypeMenu(r.left, r.bottom);">+</span>
    </h4>
`;
const modulesBody = document.createElement('div');
modulesBody.className = 'section-body';
modulesBody.style.display = 'none'; // скрыто по умолчанию

const modulesContainer = document.createElement('div');
modulesContainer.className = 'modules-container';
modulesContainer.id = 'modules-container';
modulesContainer.innerHTML = `
    <div class="modules-empty-message" id="modules-empty-message">
        <p>Нет встроенных модулей</p>
    </div>
`;
modulesBody.appendChild(modulesContainer);
modulesSection.appendChild(modulesBody);
dossier.appendChild(modulesSection);

   // --- Сервисы ---
const servicesSection = document.createElement('div');
servicesSection.className = 'dossier-section';
servicesSection.innerHTML = '<h4 onclick="toggleSection(this)"><span class="section-arrow">▶</span> 🌐 Подключённые сервисы</h4>';
const servicesBody = document.createElement('div');
servicesBody.className = 'section-body';
servicesBody.style.display = 'none';

const servicesGrid = document.createElement('div');
servicesGrid.className = 'services-grid';
servicesGrid.id = 'services-grid';

const services = ['Zabbix', 'NTP', 'Graylog'];
services.forEach(svc => {
    const card = document.createElement('div');
    card.className = 'service-card';
    card.dataset.service = svc;
    card.setAttribute('onclick', 'toggleService(this)');
    card.innerHTML = `
        <div class="service-name">${svc}</div>
        <div class="service-status service-disconnected">✗ Не подключён</div>
    `;
    servicesGrid.appendChild(card);
});

const rtCard = document.createElement('div');
rtCard.className = 'service-card radius-tacacs';
rtCard.dataset.service = 'radius_tacacs';
rtCard.innerHTML = `
    <div class="service-name">RADIUS / TACACS+</div>
    <div class="service-status" id="radius-tacacs-status">
        <span class="service-disconnected">✗ Не подключён</span>
    </div>
    <div class="toggle-switch" onclick="toggleRadiusTacacs(event)">
        <div class="toggle-knob" style="left:0"></div>
    </div>
`;
servicesGrid.appendChild(rtCard);

// Контекстное меню для карточек сервисов
servicesGrid.querySelectorAll('.service-card').forEach(card => {
    card.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        const service = this.dataset.service;
        showServiceContextMenu(e.clientX, e.clientY, service);
    });
});

servicesBody.appendChild(servicesGrid);
servicesSection.appendChild(servicesBody);
dossier.appendChild(servicesSection);

    
        // --- LLDP-соседи ---
    const lldpSection = document.createElement('div');
lldpSection.className = 'dossier-section lldp-section';
lldpSection.innerHTML = '<h4 onclick="toggleSection(this)"><span class="section-arrow">▶</span> 📡 LLDP-соседи</h4>';
const lldpBody = document.createElement('div');
lldpBody.className = 'section-body';
lldpBody.style.display = 'none';
lldpBody.innerHTML = `
    <textarea id="lldp-textarea" class="dossier-input" rows="6" placeholder="Вставьте вывод команды..." style="font-family:monospace; resize:vertical;"></textarea>
    <div id="lldp-result-table" style="margin-top:0.8rem; display:none;"></div>
`;
lldpSection.appendChild(lldpBody);
dossier.appendChild(lldpSection);

            // --- Секция локального администратора ---
        const credentialsSection = document.createElement('div');
credentialsSection.className = 'dossier-section';
credentialsSection.innerHTML = '<h4 onclick="toggleSection(this)"><span class="section-arrow">▶</span> 🔐 Локальный администратор</h4>';
const credentialsBody = document.createElement('div');
credentialsBody.className = 'section-body';
credentialsBody.style.display = 'none';
credentialsBody.innerHTML = `
    <div class="dossier-grid">
        <div class="form-group">
            <label>Логин</label>
            <input type="text" name="local_admin_login" class="dossier-input" autocomplete="off">
        </div>
        <div class="form-group">
            <label>Пароль</label>
            <div style="position:relative;">
                <input type="password" name="local_admin_password" class="dossier-input password-blur" autocomplete="off">
                <span class="toggle-password-visibility" onclick="togglePasswordVisibility(this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; user-select:none;">👁️</span>
            </div>
        </div>
    </div>
`;
credentialsSection.appendChild(credentialsBody);
dossier.appendChild(credentialsSection);

    // --- Стек (при необходимости) ---
    const isStack = (extraData && extraData.force_stack) || 
                (initialData && initialData.Groupe == 2 && (!extraData || extraData.force_stack !== false));
    if (isStack && !extraData?.stack_mode) {
        const stackContainer = document.createElement('div');
        stackContainer.id = 'stack-form-container';
        dossier.prepend(stackContainer);
         // ---- синхронная установка node_id и group_id ----
    window.AppState.currentExtraData = extraData || {};
    window.AppState.currentExtraData.node_id = state.currentRelatedId || (initialData && initialData.id_node);
    if (initialData && initialData.Groupe == 2 && initialData.group_id) {
        window.AppState.currentExtraData.stack_group_id = initialData.group_id;
    }

        fetch('modules/nodes_page/stack/stack_modal/stack_form_template.php')
            .then(r => r.text())
            .then( async html => {
                stackContainer.innerHTML = html;

// Включаем проверку уникальности для полей hostname и ip стека
if (typeof setupEquipmentValidation === 'function') {
    setupEquipmentValidation(stackContainer, null, window.AppState.currentStackGroupId || null);
}

            const ipSelect = document.getElementById('stack-ip');
            const vendorSelect = document.getElementById('stack-vendor');
            if (ipSelect) await loadSearchableSelectOptions(ipSelect, '?ajax=get_list&list=ip_address', 'ip_address_list');
            if (vendorSelect) await loadSearchableSelectOptions(vendorSelect, '?ajax=get_list&list=vendors', 'vendors_list');

            

            if (typeof initStackForm === 'function') {
                initStackForm();  // без setTimeout
            }
            });

        Array.from(dossier.children).forEach(child => {
            if (child.classList.contains('dossier-section') && child !== stackContainer) {
                child.style.display = 'none';
            }
        });
    }

    // --- Добавляем dossier в DOM ---
    fieldsContainer.appendChild(dossier);

        // ========== Логика парсинга LLDP (теперь элементы в DOM) ==========
    const lldpTextarea = document.getElementById('lldp-textarea');
    const lldpResultTable = document.getElementById('lldp-result-table');
    if (lldpTextarea && lldpResultTable) {
        let lldpTimer;
        lldpTextarea.addEventListener('input', () => {
            clearTimeout(lldpTimer);
            lldpResultTable.style.display = 'none';
            const text = lldpTextarea.value.trim();
            if (!text) return;

            lldpTimer = setTimeout(async () => {
                const vendor = detectLLDPVendor(text);
                if (!vendor) {
                    lldpResultTable.innerHTML = '<div class="no-equipment">Не удалось определить производителя</div>';
                    lldpResultTable.style.display = 'block';
                    return;
                }
                const neighbors = parseLLDPOutput(text, vendor);

                if (neighbors.length === 0) {
                    lldpResultTable.innerHTML = '<div class="no-equipment">Соседей не найдено</div>';
                    lldpResultTable.style.display = 'block';
                    return;
                }

                // Для обрезанных имён ищем полное имя в БД
                const hostnamesToResolve = neighbors
                    .filter(n => n.neighbor_hostname.endsWith('....'))
                    .map(n => n.neighbor_hostname.replace(/\.+$/, ''));

                const resolvedMap = {};
                if (hostnamesToResolve.length > 0) {
                    try {
                        const resp = await fetch(`?ajax=search_hostnames&q=${encodeURIComponent(hostnamesToResolve.join(','))}`);
                        const data = await resp.json();
                        hostnamesToResolve.forEach(partial => {
                            const match = data.find(h => h.startsWith(partial));
                            if (match) resolvedMap[partial + '....'] = match;
                        });
                    } catch (e) {}
                }

                let html = '<table class="lldp-neighbors-table"><thead><tr><th>Локальный порт</th><th>Порт соседа</th><th>Имя соседа</th></tr></thead><tbody>';
                neighbors.forEach((n) => {
                    const displayHostname = resolvedMap[n.neighbor_hostname] || n.neighbor_hostname;
                    html += `
                        <tr>
                            <td><input type="text" class="dossier-input lldp-local-port" value="${escapeHtml(n.local_interface)}" readonly></td>
                            <td><input type="text" class="dossier-input lldp-neighbor-port" value="${escapeHtml(n.neighbor_interface)}" readonly></td>
                            <td><input type="text" class="dossier-input lldp-neighbor-hostname" value="${escapeHtml(displayHostname)}" data-original="${escapeHtml(n.neighbor_hostname)}"></td>
                        </tr>`;
                });
                html += '</tbody></table>';
                lldpResultTable.innerHTML = html;
                lldpResultTable.style.display = 'block';
            }, 4000);
        });
    }

    // --- Обработчики для уже добавленных элементов ---
    // const addTypeBtn = document.getElementById('add-module-type-btn');
    // if (addTypeBtn) {
    //     addTypeBtn.addEventListener('click', function(e) {
    //         e.stopPropagation();
    //         const rect = this.getBoundingClientRect();
    //         openModuleTypeMenu(rect.left, rect.bottom);
    //     });
    // }

    fieldsContainer.addEventListener('change', (e) => {
        const select = e.target;
        if (select.tagName !== 'SELECT') return;
        if (select.value === '__add_new__') {
            select.value = '';
            const source = select.dataset.source;
            if (!source) return;
            const listName = source.replace('_list', '');
            const labelEl = select.closest('.dossier-item')?.querySelector('.label');
            const labelText = labelEl ? labelEl.textContent.trim() : listName;
            openMetaForm(listName, labelText);
        }
    });

    // Загрузка опций для всех селектов
    const allSelects = fieldsContainer.querySelectorAll('select[data-source]');
    for (const select of allSelects) {
        const source = select.dataset.source;
        let listName = source.replace('_list', '');
        let items = [];
        try {
            if (source === 'vendors') listName = 'vendors';
            const resp = await fetchJSON(`?ajax=get_list&list=${listName}`);
            items = resp.data || [];
        } catch (e) {}
        select.innerHTML = '<option value="">-- не выбрано --</option>';
        items.forEach(item => select.appendChild(new Option(item.name, item.id)));
        const addOpt = new Option('Добавить...', '__add_new__');
        select.appendChild(addOpt);
        new SearchableSelect(select);

        select.addEventListener('change', function() {
            if (this.value === '__add_new__') {
                this.value = '';
                const sourceAttr = this.dataset.source;
                if (!sourceAttr) return;
                const list = sourceAttr.replace('_list', '');
                const labelEl = this.closest('.dossier-item')?.querySelector('.label');
                const labelText = labelEl ? labelEl.textContent.trim() : list;
                openMetaForm(list, labelText);
            }
        });
    }

    // PoE логика
    if (deviceTypeSelect) {
        const updatePoe = () => {
            if (!deviceTypeSelect) return;
            const selectedOption = deviceTypeSelect.options[deviceTypeSelect.selectedIndex];
            const text = selectedOption ? selectedOption.textContent.trim() : '';
            poeGroup.style.display = (text === 'Коммутатор') ? 'inline-flex' : 'none';
            if (text !== 'Коммутатор') {
                const checkbox = poeGroup.querySelector('input[name="Poe"]');
                if (checkbox) checkbox.checked = false;
            }
        };
        updatePoe();
        deviceTypeSelect.addEventListener('change', updatePoe);
    }

        // ========== Блокировка полей при добавлении в стек + поле слота ==========
    if (extraData?.stack_mode) {
        // Добавляем поле «Слот» в начало сетки
        const slotItem = document.createElement('div');
        slotItem.className = 'dossier-item';
        slotItem.innerHTML = `<div class="label">Слот</div><div class="value"><input type="number" name="Slot" class="dossier-input" min="0" value="${initialData?.Slot || ''}"></div>`;
        dossierGrid.prepend(slotItem);

        // Блокировка и заполнение производителя
        const vendorSelectEl = fieldsContainer.querySelector('select[name="vendor_id"]');
        if (vendorSelectEl) {
            vendorSelectEl.disabled = true;
            vendorSelectEl.value = extraData.stack_vendor_id || '';
            if (vendorSelectEl.searchableInstance) {
                vendorSelectEl.searchableInstance.syncInputWithSelect();
                const wrapper = vendorSelectEl.closest('.searchable-select');
                if (wrapper) {
                    const input = wrapper.querySelector('input');
                    if (input) input.disabled = true;
                }
            }
        }
        // Блокировка имени хоста
        const hostnameInput = fieldsContainer.querySelector('input[name="hostname"]');
        if (hostnameInput) {
            hostnameInput.disabled = true;
            hostnameInput.value = extraData.stack_hostname || '';
        }
        // Блокировка IP-адреса
        const ipSelect = fieldsContainer.querySelector('select[name="ip_address"]');
        if (ipSelect) {
            ipSelect.disabled = true;
            ipSelect.value = extraData.stack_ip || '';
            if (ipSelect.searchableInstance) {
                ipSelect.searchableInstance.syncInputWithSelect();
                const wrapper = ipSelect.closest('.searchable-select');
                if (wrapper) {
                    const input = wrapper.querySelector('input');
                    if (input) input.disabled = true;
                }
            }
        } else {
            const ipInput = fieldsContainer.querySelector('input[name="ip_address"]');
            if (ipInput) {
                ipInput.disabled = true;
                ipInput.value = extraData.stack_ip || '';
            }
        }
    }
    const vendorSelect = fieldsContainer.querySelector('select[name="vendor_id"]');
    const modelSelect = fieldsContainer.querySelector('select[name="model_id"]');

    if (vendorSelect && modelSelect) {
        vendorSelect.addEventListener('change', async () => {
    const vendorId = vendorSelect.value;
    reloadModelsForVendor(modelSelect, vendorId || null);

    // Автозаполнение учётных данных локального администратора
    const loginField = document.querySelector('input[name="local_admin_login"]');
    const passField  = document.querySelector('input[name="local_admin_password"]');
    if (loginField && passField && vendorId) {
        // Не перезаписывать, если пользователь уже вручную изменил поля
        if (!loginField.dataset.manuallyEdited && !passField.dataset.manuallyEdited) {
            try {
                const resp = await fetchJSON(`?ajax=get_vendor_default&vendor_id=${vendorId}&service=local_admin`);
                if (resp && resp.login) {
                    loginField.value = resp.login;
                    passField.value = resp.password || '';
                } else {
                    loginField.value = '';
                    passField.value = '';
                }
            } catch(e) {}
        }
    }
});
        if (initialData && initialData.vendor_id) {
            vendorSelect.value = initialData.vendor_id;
            await reloadModelsForVendor(modelSelect, initialData.vendor_id, initialData.model_id);
        } else if (!extraData?.stack_mode) {
            await reloadModelsForVendor(modelSelect, null);
        }
    }

    // Заполнение initialData
    if (initialData) {
        for (const [key, value] of Object.entries(initialData)) {
            const field = fieldsContainer.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = value == 1 || value === true;
                } else if (field.tagName === 'SELECT') {
                    field.value = value;
                    setTimeout(() => {
                        if (field.searchableInstance) field.searchableInstance.syncInputWithSelect();
                    }, 100);
                } else {
                    field.value = value;
                }
            }
        }

         if (initialData.local_admin_password) {
            const passField = fieldsContainer.querySelector('input[name="local_admin_password"]');
            if (passField) {
                passField.value = initialData.local_admin_password;
            }
        }

        if (initialData.services) {
            Object.entries(initialData.services).forEach(([svc, connected]) => {
                if (connected) {
                    const card = servicesGrid.querySelector(`.service-card[data-service="${svc}"]`);
                    if (card) toggleService(card, true);
                }
            });
            if (initialData.services.RADIUS) {
                const rtCard = servicesGrid.querySelector('.service-card[data-service="radius_tacacs"]');
                if (rtCard) {
                    const status = rtCard.querySelector('#radius-tacacs-status');
                    status.innerHTML = '<span class="service-connected">✓ RADIUS</span>';
                    rtCard.querySelector('.toggle-knob').style.left = '0';
                }
            } else if (initialData.services['TACACS+']) {
                const rtCard = servicesGrid.querySelector('.service-card[data-service="radius_tacacs"]');
                if (rtCard) {
                    const status = rtCard.querySelector('#radius-tacacs-status');
                    status.innerHTML = '<span class="service-connected">✓ TACACS+</span>';
                    rtCard.querySelector('.toggle-knob').style.left = '50%';
                }
            }
        }

        if (initialData.id) {
            fetch(`?ajax=get_equipment_modules&id=${initialData.id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.modules) {
                        Object.entries(data.modules).forEach(([type, mods]) => {
                            if (mods && mods.length > 0) addModuleColumn(type, mods);
                        });
                    }
                })
                .catch(() => {});
        }
        if (deviceTypeSelect && poeGroup) {
            const updatePoe = () => {
                const selectedOption = deviceTypeSelect.options[deviceTypeSelect.selectedIndex];
                const text = selectedOption ? selectedOption.textContent.trim() : '';
                poeGroup.style.display = (text === 'Коммутатор') ? 'inline-flex' : 'none';
                if (text !== 'Коммутатор') {
                    const checkbox = poeGroup.querySelector('input[name="Poe"]');
                    if (checkbox) checkbox.checked = false;
                }
            };
            updatePoe();
        }
    }

    // MAC-проверка
//     const macInput = fieldsContainer.querySelector('input[name="mac_address"]');
//     if (macInput) {
//         macInput.addEventListener('blur', async function() {
//             this.value = formatMacAddress(this.value);
//             const mac = this.value.trim();
//             const parent = this.parentElement;
//             const oldMsg = parent.querySelector('.mac-duplicate-msg');
//             if (mac === '') { if (oldMsg) oldMsg.remove(); return; }
//             if (oldMsg) { oldMsg.textContent = 'Проверка…'; }
//             else {
//                 const msgDiv = document.createElement('div');
//                 msgDiv.className = 'mac-duplicate-msg';
//                 msgDiv.style.cssText = 'color: var(--danger, #e63946); font-size: 0.85rem; margin-top: 0.3rem;';
//                 msgDiv.textContent = 'Проверка…';
//                 parent.appendChild(msgDiv);
//             }
//             try {
//                 const res = await fetch(`?ajax=check_mac&mac=${encodeURIComponent(mac)}`);
//                 const data = await res.json();
//                 const msg = parent.querySelector('.mac-duplicate-msg');
//                 if (!msg) return;
//                 if (data.exists) {
//     msg.textContent = data.message || `Оборудование с таким адресом уже существует`;
// } else {
//     msg.remove();
// }
//             } catch (e) { const msg = parent.querySelector('.mac-duplicate-msg'); if (msg) msg.remove(); }
//         });
//     }

            // Настройка кнопки переключения режима (она уже создана в заголовке)
    const modeBtn = document.getElementById('modeToggleBtn');
    if (modeBtn) {
        modeBtn.innerHTML = isStack ? 'Стек 🔄' : 'Одиночное 🔄';
        modeBtn.title = isStack ? 'Переключить на одиночное устройство' : 'Переключить на стек';
        modeBtn.onclick = function() {
    const currentlyStack = this.innerHTML.includes('Стек');
    const dossierChildren = Array.from(dossier.children);
    const stackContainer = document.getElementById('stack-form-container');

    // Проверка наличия данных
    const inputs = dossier.querySelectorAll('input:not([type="hidden"]), select, textarea');
    let hasData = false;
    inputs.forEach(el => {
        if (el.disabled) return;
        if (el.type === 'checkbox' || el.type === 'radio') return;
        if (el.tagName === 'SELECT') {
            if (el.value && el.value !== '__add_new__' && el.value !== '') hasData = true;
        } else if (el.value && el.value.trim() !== '') {
            hasData = true;
        }
    });

    if (hasData) {
        if (!confirm('При изменении типа оборудования все данные будут стёрты. Продолжить?')) {
            return;
        }
        // Сброс формы
        const form = document.getElementById('universalAddForm');
        if (form) form.reset();
        // Сброс поисковых селектов
        document.querySelectorAll('.searchable-select select').forEach(s => {
            if (s.searchableInstance) s.searchableInstance.syncInputWithSelect();
        });
        // Очистка модулей
        const modulesContainer = document.getElementById('modules-container');
        if (modulesContainer) {
            modulesContainer.innerHTML = '<div class="modules-empty-message"><p>Нет встроенных модулей</p></div>';
        }
        // Сброс сервисов
        document.querySelectorAll('.service-card .service-status').forEach(st => {
            st.classList.remove('service-connected');
            st.classList.add('service-disconnected');
            st.textContent = '✗ Не подключён';
        });
        const rtKnob = document.querySelector('.radius-tacacs .toggle-knob');
        if (rtKnob) rtKnob.style.left = '0';
        const rtStatus = document.querySelector('#radius-tacacs-status span');
        if (rtStatus) {
            rtStatus.className = 'service-disconnected';
            rtStatus.textContent = '✗ Не подключён';
        }
    }

    if (currentlyStack) {
        this.innerHTML = 'Одиночное 🔄';
        this.title = 'Переключить на стек';
        if (stackContainer) stackContainer.style.display = 'none';
        dossierChildren.forEach(child => {
            if (child !== stackContainer && (child.classList.contains('dossier-section') || child.classList.contains('module-column'))) {
                child.style.display = '';
            }
        });
    } else {
        this.innerHTML = 'Стек 🔄';
        this.title = 'Переключить на одиночное устройство';
        if (!stackContainer) {
            const newStackContainer = document.createElement('div');
            newStackContainer.id = 'stack-form-container';
            dossier.prepend(newStackContainer);
            fetch('modules/nodes_page/stack_form/stack_form_template.php')
                .then(r => r.text())
                .then(async html => {
                    newStackContainer.innerHTML = html;
                    const ipSelect = document.getElementById('stack-ip');
                    const vendorSelect = document.getElementById('stack-vendor');
                    if (ipSelect) await loadSearchableSelectOptions(ipSelect, '?ajax=get_list&list=ip_address', 'ip_address_list');
                    if (vendorSelect) await loadSearchableSelectOptions(vendorSelect, '?ajax=get_list&list=vendors', 'vendors_list');
                    if (typeof initStackForm === 'function') initStackForm();
                });
            dossierChildren.forEach(child => {
                if (child !== newStackContainer && (child.classList.contains('dossier-section') || child.classList.contains('module-column'))) {
                    child.style.display = 'none';
                }
            });
        } else {
            stackContainer.style.display = 'block';
            dossierChildren.forEach(child => {
                if (child !== stackContainer && (child.classList.contains('dossier-section') || child.classList.contains('module-column'))) {
                    child.style.display = 'none';
                }
            });
        }
    }
};
    }

    if (isStack && !extraData?.stack_mode) {
        const stackContainer = document.getElementById('stack-form-container');
        if (stackContainer) stackContainer.style.display = 'block';
    }
}

// ================== Вспомогательные функции для досье ==================
function addModuleColumn(moduleType, existingModules = []) {
    let container = document.getElementById('stack-modules-container');
    if (!container) container = document.getElementById('modules-container');
    if (!container) return;

    const emptyMsg = document.getElementById('modules-empty-message');
    if (emptyMsg) emptyMsg.style.display = 'none';

    if (container.querySelector(`.module-column[data-module-type="${moduleType}"]`)) return;

    const titles = {
        'sfp': '🔌 SFP-модули',
        'psu': '⚡ Блоки питания',
        'fan': '🌀 Вентиляторы',
        'linecard': '📦 Карты расширения',
        'supervisor': '🧠 Супервизор',
        'other': '📦 Другое'
    };
    const headerText = titles[moduleType] || 'Модуль';
    const listId = `module-list-${moduleType}`;

    const columnDiv = document.createElement('div');
    columnDiv.className = 'module-column';
    columnDiv.setAttribute('data-module-type', moduleType);

    let modulesHtml = '';
    if (existingModules.length > 0) {
        modulesHtml = existingModules.map(m => `
            <div class="module-tile" data-module-type="${moduleType}">
                <div class="module-name">${m.name || 'Модуль'}</div>
                <div class="module-details">
                    ${m.type ? `<span>📡 ${m.type}</span>` : ''}
                    ${m.serial ? `<span>🔢 ${m.serial}</span>` : ''}
                    <button class="btn small danger" onclick="this.closest('.module-tile').remove()">×</button>
                </div>
            </div>
        `).join('');
    }

    columnDiv.innerHTML = `
        <div class="module-column-header">
            <span>${headerText}</span>
            <span class="remove-module-column" style="cursor:pointer; color:var(--danger); margin-left:auto;">✕</span>
        </div>
        <div class="module-list" id="${listId}">
            ${modulesHtml}
            <div class="add-module-tile" data-module-type="${moduleType}" onclick="openModuleDialog('${moduleType}')">+ Добавить</div>
        </div>
    `;

    columnDiv.querySelector('.remove-module-column').addEventListener('click', () => {
        columnDiv.remove();
        const remaining = container.querySelectorAll('.module-column[data-module-type]');
        if (remaining.length === 0 && emptyMsg) {
            emptyMsg.style.display = '';
        }
    });

    container.appendChild(columnDiv);
}

function openModuleTypeMenu(x, y) {
    const oldMenu = document.querySelector('.module-type-menu');
    if (oldMenu) oldMenu.remove();

    const menu = document.createElement('div');
    menu.className = 'module-type-menu';
    menu.style.cssText = `
        position: fixed; left: ${x}px; top: ${y}px;
        background: var(--bg-card); border: 1px solid var(--border-color);
        border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10000; min-width: 220px; padding: 4px 0;
    `;

    const items = [
        { text: '🔌 SFP', type: 'sfp' },
        { text: '⚡ Блоки питания', type: 'psu' },
        { text: '🌀 Вентилятор', type: 'fan' },
        { text: '📦 Карта расширения', type: 'linecard' },
        { text: '🧠 Супервизор', type: 'supervisor' },
        { text: '📦 Другое', type: 'other' }
    ];

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'module-type-menu-item';
        div.style.padding = '8px 16px';
        div.style.cursor = 'pointer';
        div.textContent = item.text;
        div.addEventListener('click', () => {
            addModuleColumn(item.type);
            menu.remove();
        });
        menu.appendChild(div);
    });

    document.body.appendChild(menu);

    const closeHandler = (e) => {
        if (!menu.contains(e.target)) {
            menu.remove();
            document.removeEventListener('click', closeHandler);
        }
    };
    setTimeout(() => document.addEventListener('click', closeHandler), 0);
}

function toggleService(card, force = null) {
    const statusDiv = card.querySelector('.service-status');
    const isConnected = statusDiv.classList.contains('service-connected');
    const newState = force !== null ? force : !isConnected;
    statusDiv.classList.toggle('service-connected', newState);
    statusDiv.classList.toggle('service-disconnected', !newState);
    statusDiv.textContent = newState ? '✓ Подключён' : '✗ Не подключён';
}

function toggleRadiusTacacs(event) {
    event.stopPropagation();
    const card = event.target.closest('.service-card');
    const knob = card.querySelector('.toggle-knob');
    const status = card.querySelector('#radius-tacacs-status');
    const currentLeft = parseInt(knob.style.left);
    if (currentLeft === 0) {
        knob.style.left = '50%';
        status.innerHTML = '<span class="service-connected">✓ TACACS+</span>';
    } else {
        knob.style.left = '0';
        status.innerHTML = '<span class="service-connected">✓ RADIUS</span>';
    }
}

async function reloadModelsForVendor(modelSelect, vendorId, selectModelId = null) {
    if (!modelSelect) return;
    if (modelSelect.searchableInstance) {
        modelSelect.searchableInstance.options = [];
        modelSelect.searchableInstance.updateDropdown('');
        modelSelect.searchableInstance.syncInputWithSelect();
    }
    if (!vendorId) {
        modelSelect.innerHTML = '<option value="">-- выберите модель --</option>';
        const addOpt = new Option('+ Добавить модель', '__add_new__');
        addOpt.style.fontStyle = 'italic';
        modelSelect.appendChild(addOpt);
        if (modelSelect.searchableInstance) {
            modelSelect.searchableInstance.options = Array.from(modelSelect.options).filter(o => o.value !== '__add_new__');
            modelSelect.searchableInstance.updateDropdown('');
            modelSelect.searchableInstance.syncInputWithSelect();
        }
        return;
    }
    try {
        const resp = await fetchJSON(`?ajax=get_list_models&list=device_models&vendor_id=${vendorId}`);
        const models = resp.data || [];
        modelSelect.innerHTML = '<option value="">-- выберите модель --</option>';
        models.forEach(m => modelSelect.add(new Option(m.name, m.id)));
        const addOpt = new Option('+ Добавить модель', '__add_new__');
        addOpt.style.fontStyle = 'italic';
        modelSelect.appendChild(addOpt);
        if (selectModelId) modelSelect.value = selectModelId;
        if (modelSelect.searchableInstance) {
            modelSelect.searchableInstance.options = Array.from(modelSelect.options).filter(o => o.value !== '__add_new__');
            modelSelect.searchableInstance.updateDropdown('');
            modelSelect.searchableInstance.syncInputWithSelect();
        }
    } catch (e) {
        modelSelect.innerHTML = '<option value="">-- ошибка загрузки --</option>';
    }
}

function addModuleToDossier(moduleType, data = {}) {
    const listId = `module-list-${moduleType}`;
    let list = document.getElementById('stack-' + listId);
    if (!list) list = document.getElementById(listId);
    if (!list) return;

    const tile = document.createElement('div');
    tile.className = 'module-tile';
    tile.dataset.moduleType = moduleType;

    if (moduleType === 'sfp') {
        tile.innerHTML = `
            <div class="module-name">🔌 ${data.name || 'Новый SFP модуль'}</div>
            <div class="module-details">
                <span>📡 <input value="${data.type || ''}" placeholder="Тип" class="module-input" data-field="type"></span>
                <span>🔢 <input value="${data.serial || ''}" placeholder="Серийный" class="module-input" data-field="serial"></span>
                <span>🌈 <input value="${data.wavelength || ''}" placeholder="Длина волны" class="module-input" data-field="wavelength"></span>
                <span>📏 <input value="${data.distance || ''}" placeholder="Дистанция" class="module-input" data-field="distance"></span>
                <button class="btn small danger" onclick="this.closest('.module-tile').remove()">×</button>
            </div>
        `;
    } else if (moduleType === 'psu') {
        tile.innerHTML = `
            <div class="module-name">⚡ ${data.name || 'Новый блок питания'}</div>
            <div class="module-details">
                <span>📡 <input value="${data.type || ''}" placeholder="Тип" class="module-input" data-field="type"></span>
                <span>🔢 <input value="${data.serial || ''}" placeholder="Серийный" class="module-input" data-field="serial"></span>
                <button class="btn small danger" onclick="this.closest('.module-tile').remove()">×</button>
            </div>
        `;
    } else {
        tile.innerHTML = `
            <div class="module-name">${data.name || 'Модуль'}</div>
            <div class="module-details">
                <span>📡 <input value="${data.type || ''}" placeholder="Тип" class="module-input" data-field="type"></span>
                <span>🔢 <input value="${data.serial || ''}" placeholder="Серийный" class="module-input" data-field="serial"></span>
                <button class="btn small danger" onclick="this.closest('.module-tile').remove()">×</button>
            </div>
        `;
    }

    const addBtn = list.querySelector('.add-module-tile');
    list.insertBefore(tile, addBtn);
}
function toggleSection(headerElement) {
    const body = headerElement.nextElementSibling;
    if (body && body.classList.contains('section-body')) {
        const isHidden = body.style.display === 'none';
        body.style.display = isHidden ? '' : 'none';
        const arrow = headerElement.querySelector('.section-arrow');
        if (arrow) arrow.textContent = isHidden ? '▼' : '▶';
    }
}