async function buildNodeForm(container, config, initialData, extraData, buildings, nodeTypes) {
    let locationBlock = null;
    let inLocationBlock = false;

    for (const field of config.fields) {
        // --- Блок "Расположение" (визуальная группировка) ---
        if (field.name === 'building_id') {
            locationBlock = document.createElement('div');
            locationBlock.className = 'location-block';
            locationBlock.style.border = '1px solid var(--border-color)';
            locationBlock.style.borderRadius = '8px';
            locationBlock.style.padding = '1rem';
            locationBlock.style.marginBottom = '1rem';

            const legend = document.createElement('div');
            legend.textContent = 'Расположение';
            legend.style.fontWeight = '600';
            legend.style.marginBottom = '0.8rem';
            legend.style.color = 'var(--accent)';
            locationBlock.appendChild(legend);
            container.appendChild(locationBlock);
            inLocationBlock = true;
        }

        const div = document.createElement('div');
        div.className = 'form-group';
        const label = document.createElement('label');
        label.textContent = field.label;

        // ================= SELECT =================
        if (field.type === 'select' && field.source) {
            const select = document.createElement('select');
            select.name = field.name;
            select.dataset.source = field.source;

            // Пустой пункт
            select.appendChild(new Option('-- не выбрано --', ''));

            if (field.source === 'buildings') {
    let buildingList = buildings;

    // Если список не передан или пуст, загружаем с сервера
    if (!Array.isArray(buildingList) || buildingList.length === 0) {
        try {
            const resp = await fetch('?ajax=get_buildings');
            if (resp.ok) buildingList = await resp.json();
            else buildingList = [];
        } catch (e) {
            buildingList = [];
        }
    }

    // Добавляем опции в select
    if (Array.isArray(buildingList) && buildingList.length > 0) {
        buildingList.forEach(b => select.appendChild(new Option(b.name, b.id)));
    }

    // Кнопка "Добавить..."
    const addOpt = new Option('Добавить...', '__add_new__');
    addOpt.style.fontStyle = 'italic';
    select.appendChild(addOpt);

    select.addEventListener('change', () => {
        if (select.value === '__add_new__') {
            select.value = '';
            addBuildingModal();   // или openBuildingForm(), если используется
        }
    });
} else if (field.source === 'node_types') {
                if (Array.isArray(nodeTypes) && nodeTypes.length > 0) {
                    nodeTypes.forEach(item => select.appendChild(new Option(item.name, item.id)));
                }
                const addOpt = new Option('Добавить...', '__add_new__');
                addOpt.style.fontStyle = 'italic';
                select.appendChild(addOpt);
                select.addEventListener('change', () => {
                    if (select.value === '__add_new__') {
                        select.value = '';
                        openNodeTypeForm();
                    }
                });
            }

            // Предустановка значения, если передан building_id
            if (!initialData && extraData && extraData.building_id && field.name === 'building_id') {
                select.value = String(extraData.building_id);
            }

            div.appendChild(label);
            div.appendChild(select);

            // SearchableSelect после того, как значение установлено
            new SearchableSelect(select);

            // Синхронизация текстового поля с выбранным значением
            if (select.searchableInstance && typeof select.searchableInstance.syncInputWithSelect === 'function') {
                select.searchableInstance.syncInputWithSelect();
            }
        }
        // ================= TEXTAREA =================
        else if (field.type === 'textarea') {
            const textarea = document.createElement('textarea');
            textarea.name = field.name;
            textarea.rows = 3;
            textarea.style.resize = 'vertical';
            textarea.style.minHeight = '60px';
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            div.appendChild(label);
            div.appendChild(textarea);
        }
        // ================= TEXT / NUMBER / DEFAULT =================
        else {
            const input = document.createElement('input');
            input.type = field.type;
            input.name = field.name;
            div.appendChild(label);
            div.appendChild(input);

            // Специальная валидация номера КУ
            if (field.name === 'KY_number') {
                const errorSpan = document.createElement('span');
                errorSpan.className = 'ky-error';
                errorSpan.style.color = 'var(--danger)';
                errorSpan.style.fontSize = '0.8rem';
                errorSpan.style.display = 'none';
                div.appendChild(errorSpan);

                input.addEventListener('input', function() {
                    const val = this.value.trim();
                    if (val !== '' && !/^\d+$/.test(val)) {
                        errorSpan.textContent = 'Введите строго числовое значение';
                        errorSpan.style.display = 'block';
                        this.classList.add('ky-invalid');
                        const submitBtn = document.querySelector('#universalAddForm button[type="submit"]');
                        if (submitBtn) submitBtn.disabled = true;
                    } else {
                        errorSpan.style.display = 'none';
                        this.classList.remove('ky-invalid');
                        const submitBtn = document.querySelector('#universalAddForm button[type="submit"]');
                        if (submitBtn) submitBtn.disabled = false;
                    }
                });
            }
        }

        // Добавляем в группу "Расположение" или в основной контейнер
        if (inLocationBlock) {
            locationBlock.appendChild(div);
        } else {
            container.appendChild(div);
        }

        // Закрываем блок после поля room (последнее в группе расположения)
        if (field.name === 'room') {
            inLocationBlock = false;
            locationBlock = null;
        }
    }

    // --- Блок "Шкаф(-ы)" ---
    // Строится ПОСЛЕ цикла по полям, а не внутри него: раньше он создавался
    // только вместе с полем room, поэтому при редактировании (и вообще при
    // любом наборе полей без room) секция не появлялась вовсе.
    buildRacksSection(container, initialData);

    // ================= ЗАПОЛНЕНИЕ ДАННЫХ ПРИ РЕДАКТИРОВАНИИ =================
    if (initialData) {
        for (const key of Object.keys(initialData)) {
            if (['id_node', 'status', 'device_count'].includes(key)) continue;

            let fieldName = key;
            if (key === 'id_location') fieldName = 'building_id';

            const field = container.querySelector(`[name="${fieldName}"]`);
            if (!field) continue;

            if (field.type === 'checkbox') {
                field.checked = initialData[key] == 1 || initialData[key] === true;
            } else if (field.tagName === 'SELECT') {
                field.value = initialData[key];
                if (field.searchableInstance) {
                    field.searchableInstance.syncInputWithSelect();
                }
            } else {
                field.value = initialData[key] !== undefined ? initialData[key] : '';
            }
        }
    }
}
/**
 * Строит сворачиваемый блок «Шкаф(-ы)» в форме узла.
 *
 * Вызывается один раз после построения всех полей — и при добавлении нового
 * узла, и при редактировании существующего. Для существующего узла сразу
 * подгружает привязанные шкафы (плитки отмечаются чекбоксами).
 *
 * @param {HTMLElement} container   Контейнер полей формы
 * @param {Object|null} initialData Данные узла при редактировании
 */
function buildRacksSection(container, initialData) {
    if (!container) return;

    // Защита от повторного построения при пересоздании формы
    container.querySelectorAll('#racks-tile-group').forEach(el => {
        el.closest('.dossier-section')?.remove();
    });

    const racksSection = document.createElement('div');
    racksSection.className = 'dossier-section';
    racksSection.innerHTML = `
        <h4 onclick="toggleSection(this)">
            <span class="section-arrow">▶</span> 🗄️ Шкаф(-ы)
        </h4>
    `;

    const racksBody = document.createElement('div');
    racksBody.className = 'section-body';
    racksBody.style.display = 'none';

    const racksTileGroup = document.createElement('div');
    racksTileGroup.className = 'rack-tile-group';
    racksTileGroup.id = 'racks-tile-group';

    // Плитка «+ Добавить шкаф»
    const addRackLabel = document.createElement('label');
    addRackLabel.className = 'rack-tile-label rack-tile-add';
    addRackLabel.id = 'add-rack-tile';
    addRackLabel.innerHTML = `
        <div class="rack-tile-content">
            <div class="rack-tile-name">+</div>
            <div class="rack-tile-detail">Добавить шкаф</div>
        </div>
    `;
    addRackLabel.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (typeof openAddRackForm === 'function') {
            openAddRackForm();
        } else {
            console.error('Функция openAddRackForm не определена');
        }
    });

    racksTileGroup.appendChild(addRackLabel);
    racksBody.appendChild(racksTileGroup);
    racksSection.appendChild(racksBody);
    container.appendChild(racksSection);

    // Для существующего узла подтягиваем уже привязанные шкафы
    const nodeId = initialData && (initialData.id_node || initialData.id);
    if (nodeId && typeof loadNodeRacks === 'function') {
        loadNodeRacks(nodeId);
    }
}
