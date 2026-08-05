// modules/add_form/esc_handler.js
//
// Единое закрытие модальных окон. Форма закрывается тремя способами:
// кнопкой «Отмена», клавишей Escape и щелчком по подложке вне формы.
// Все три пути ведут в одну и ту же close-функцию модалки, поэтому
// сброс полей и уничтожение поисковых селектов происходят одинаково.
(function() {
    'use strict';

    const MODAL_SELECTOR = '.add-form-modal, .modal';

    function getMaxZIndex() {
        const modals = document.querySelectorAll(MODAL_SELECTOR);
        let max = 3000;
        modals.forEach(m => {
            const z = parseInt(window.getComputedStyle(m).zIndex) || 3000;
            if (z > max) max = z;
        });
        return max;
    }

    window.bringToFront = function(modal) {
        if (!modal) return;
        modal.style.zIndex = getMaxZIndex() + 1;
    };

    window.showModal = function(modal) {
        if (!modal) return;
        bringToFront(modal);
        modal.classList.add('visible');
    };

    /**
     * Имя модалки → её функция закрытия.
     *
     * Собственная close-функция нужна каждой форме: она сбрасывает поля,
     * убирает поисковые селекты и чистит ошибки. Просто снять класс
     * visible недостаточно — при следующем открытии форма будет грязной.
     */
    const CLOSERS = {
        universalAddModal:      'closeAddForm',
        moveModal:              'closeMoveModal',
        warehouseModal:         'closeWarehouseModal',
        warehouseEquipmentModal:'closeWarehouseEquipmentForm',
        addStackDeviceModal:    'closeStackDeviceForm',
        deleteConfirmModal:     'closeDeleteModal',
        alertSettingsModal:     'closeAlertSettings',
        checklistModal:         'closeChecklistModal',
        addModelModal:          'closeAddModelModal',
        metaAddModal:           'closeMetaForm',
        metaAddModel:           'closeModelMetaForm',
        addBuildingModal:       'closeBuildingForm',
        addLocationModal:       'closeLocationForm',
        addNodeTypeModal:       'closeNodeTypeForm',
        moduleAddModal:         'closeModuleDialog',
        serviceInstructionModal:'closeServiceInstruction',
        addRackModal:           'closeAddRackForm',
        addRackModelModal:      'closeAddRackModelForm',
        lldpImportModal:        'closeLLDPImport',
        userFormModal:          'closeUserForm',
        kbRowModal:             'kbCloseRowModal',
        kbColumnModal:          'kbCloseColumnModal',
        equipmentDetailsModal:  'closeEquipmentDetails',
        historyModal:           'closeEquipmentHistory',
        snmpModal:              'closeSnmpModal',
        rightPanel:             'closeRackPanel',
        passiveDeviceModal:     'closePassiveDeviceForm',
        passivePortModal:       'closePassivePortForm',
        phoneFormModal:         'closePhoneForm',
        phoneDetailModal:       'closePhoneDetail',
        deliveryFormModal:      'closeDeliveryForm',
        boxFormModal:           'closeBoxForm',
        openBoxModal:           'closeOpenBox',
        expansionFormModal:     'closeExpansionForm',
        phoneImportModal:       'closePhoneImport',
        nodeImportModal:        'closeNodeImport',
        zabbixSettingsModal:    'closeZabbixSettings'
    };

    /**
     * Закрывает модалку её собственной функцией.
     * Если функции нет, просто убираем visible — окно всё равно закроется.
     */
    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('visible');

        const fnName = CLOSERS[modal.id];
        if (fnName && typeof window[fnName] === 'function') {
            window[fnName]();
        }
    }
    window.closeTopModalByElement = closeModal;

    /** Самая верхняя видимая модалка. */
    function topVisibleModal() {
        const visible = document.querySelectorAll('.add-form-modal.visible, .modal.visible');
        let top = null, maxZ = -1;
        visible.forEach(modal => {
            const z = parseInt(window.getComputedStyle(modal).zIndex) || 3000;
            if (z > maxZ) { maxZ = z; top = modal; }
        });
        return top;
    }

    // ---------------------------------------------------------------
    //  Escape — закрывает ровно одну, самую верхнюю модалку
    // ---------------------------------------------------------------
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;

        const top = topVisibleModal();
        if (!top) return;

        closeModal(top);
        e.stopPropagation();
    });

    // ---------------------------------------------------------------
    //  Щелчок вне формы
    //
    //  Закрываем, только если И нажатие, И отпускание кнопки пришлись
    //  на подложку. Иначе выделение текста внутри формы, законченное
    //  движением мыши за её край, закрывало бы форму вместе с введёнными
    //  данными — так и происходило раньше.
    // ---------------------------------------------------------------
    let pressStartedOn = null;

    /** Нажатие пришлось на подложку модалки, а не на её содержимое. */
    function backdropOf(target) {
        if (!target || !target.closest) return null;
        const modal = target.closest(MODAL_SELECTOR);
        if (!modal || !modal.classList.contains('visible')) return null;
        // Внутри окна — не подложка
        if (target.closest('.modal-content')) return null;
        return modal;
    }

    document.addEventListener('mousedown', function(e) {
        // Только основная кнопка мыши: правый клик открывает контекстное меню
        pressStartedOn = e.button === 0 ? backdropOf(e.target) : null;
    }, true);

    document.addEventListener('mouseup', function(e) {
        const started = pressStartedOn;
        pressStartedOn = null;
        if (!started || e.button !== 0) return;

        // Отпустили там же, где нажали, и это по-прежнему подложка
        if (backdropOf(e.target) !== started) return;

        // Пользователь мог выделять текст, начав с подложки, — не мешаем ему
        const selection = window.getSelection();
        if (selection && !selection.isCollapsed) return;

        // Закрываем только верхнюю модалку: подложка нижней перекрыта
        if (started !== topVisibleModal()) return;

        closeModal(started);
    }, true);

    // Перетаскивание за пределы окна браузера не должно оставлять
    // «взведённое» состояние до следующего отпускания кнопки
    window.addEventListener('blur', function() { pressStartedOn = null; });
})();
