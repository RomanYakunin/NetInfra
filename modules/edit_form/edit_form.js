// modules/edit_form/edit_form.js — Открытие формы редактирования узла и оборудования

/**
 * Загружает данные узла по ID и открывает форму редактирования
 * @param {number|string} nodeId
 */
async function openEditNode(nodeId) {
    try {
        const res = await fetch(`?ajax=get_node&id=${encodeURIComponent(nodeId)}`);
        if (!res.ok) throw new Error('Ошибка сети');
        const data = await res.json();
        if (data.error) {
            alert(data.error);
            return;
        }

        // Формируем объект initialData для openAddForm
        const initialData = {
            id: data.id,                     // Первичный ключ узла (алиас из API)
            KY_number: data.KY_number,
            id_location: data.id_location,
            node_type_id: data.node_type_id,
            displayName: 'КУ-' + data.KY_number
            // Добавьте остальные поля узла, если они есть в таблице nodes
        };

        if (typeof window.openAddForm === 'function') {
            window.openAddForm('node', null, initialData);
        } else {
            alert('Функция openAddForm не найдена');
        }
    } catch (e) {
        console.error(e);
        alert('Не удалось загрузить данные узла');
    }
}

/**
 * Загружает данные оборудования по ID и открывает форму редактирования
 * @param {number|string} equipmentId
 */
async function openEditEquipment(equipmentId) {
    try {
        const res = await fetch(`?ajax=get_equipment_item&id=${encodeURIComponent(equipmentId)}`);
        if (!res.ok) throw new Error('Ошибка сети');
        const data = await res.json();
        if (data.error) {
            alert(data.error);
            return;
        }

        // Формируем объект initialData для openAddForm
        const initialData = {
            id: data.id,
            Groupe: data.Groupe,
            ip_address: data.ip_address,   // Это ID из таблицы ip_address
            hostname: data.hostname,
            device_type_id: data.device_type_id,
            vendor_id: data.vendor_id,
            model_id: data.model_id,
            serial_number: data.serial_number,
            mac_address: data.mac_address,
            firmwares: data.firmwares,     // ID прошивки
            id_cabinet: data.id_cabinet,
            unit_position: data.unit_position,
            id_node: data.id_node,
            Slot: data.Slot,
            displayName: data.hostname || ('Оборудование #' + data.id)
            // Если в форме есть другие поля, допишите их здесь
        };

        if (typeof window.openAddForm === 'function') {
            window.openAddForm('equipment', null, initialData);
        } else {
            alert('Функция openAddForm не найдена');
        }
    } catch (e) {
        console.error(e);
        alert('Не удалось загрузить данные оборудования');
    }
}