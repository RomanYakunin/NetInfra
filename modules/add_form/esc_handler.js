(function() {
    'use strict';

    function getMaxZIndex() {
        const modals = document.querySelectorAll('.add-form-modal, .modal');
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

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;

        const visibleModals = document.querySelectorAll('.add-form-modal.visible, .modal.visible');
        if (visibleModals.length === 0) return;

        let topModal = null;
        let maxZ = -1;
        visibleModals.forEach(modal => {
            const z = parseInt(window.getComputedStyle(modal).zIndex) || 3000;
            if (z > maxZ) {
                maxZ = z;
                topModal = modal;
            }
        });

        if (!topModal) return;

        topModal.classList.remove('visible');

        switch (topModal.id) {
            case 'universalAddModal':     if (typeof closeAddForm === 'function') closeAddForm(); break;
            case 'moveModal':             if (typeof closeMoveModal === 'function') closeMoveModal(); break;
            case 'warehouseModal':        if (typeof closeWarehouseModal === 'function') closeWarehouseModal(); break;
            case 'warehouseEquipmentModal': if (typeof closeWarehouseEquipmentForm === 'function') closeWarehouseEquipmentForm(); break;
            case 'addStackDeviceModal':   if (typeof closeStackDeviceForm === 'function') closeStackDeviceForm(); break;
            case 'deleteConfirmModal':    if (typeof closeDeleteModal === 'function') closeDeleteModal(); break;
            case 'alertSettingsModal':    if (typeof closeAlertSettings === 'function') closeAlertSettings(); break;
            case 'checklistModal':        if (typeof closeChecklistModal === 'function') closeChecklistModal(); break;
            case 'addModelModal':         if (typeof closeAddModelModal === 'function') closeAddModelModal(); break;
            case 'metaAddModal':          if (typeof closeMetaForm === 'function') closeMetaForm(); break;
            case 'metaAddModel':          if (typeof closeModelMetaForm === 'function') closeModelMetaForm(); break;
            case 'addBuildingModal':      if (typeof closeBuildingForm === 'function') closeBuildingForm(); break;
            case 'addLocationModal':      if (typeof closeLocationForm === 'function') closeLocationForm(); break;
            case 'addNodeTypeModal':      if (typeof closeNodeTypeForm === 'function') closeNodeTypeForm(); break;
            case 'moduleAddModal':        if (typeof closeModuleDialog === 'function') closeModuleDialog(); break;
            case 'serviceInstructionModal': if (typeof closeServiceInstruction === 'function') closeServiceInstruction(); break;
            case 'addRackModal':          if (typeof closeAddRackForm === 'function') closeAddRackForm(); break;
            case 'addRackModelModal':     if (typeof closeAddRackModelForm === 'function') closeAddRackModelForm(); break;
            case 'lldpImportModal':       if (typeof closeLLDPImport === 'function') closeLLDPImport(); break;
            case 'userFormModal':         if (typeof closeUserForm === 'function') closeUserForm(); break;
            case 'kbRowModal':            if (typeof kbCloseRowModal === 'function') kbCloseRowModal(); break;
            case 'kbColumnModal':         if (typeof kbCloseColumnModal === 'function') kbCloseColumnModal(); break;
            case 'equipmentDetailsModal': if (typeof closeEquipmentDetails === 'function') closeEquipmentDetails(); break;
            case 'historyModal':          if (typeof closeEquipmentHistory === 'function') closeEquipmentHistory(); break;
            case 'snmpModal':             if (typeof closeSnmpModal === 'function') closeSnmpModal(); break;
        }

        // Гасим событие, чтобы Escape закрыл ровно одну (верхнюю) модалку
        e.stopPropagation();
    });
})();