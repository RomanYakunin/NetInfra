<!-- Модальное окно подробной информации об оборудовании -->
<div class="add-form-modal" id="equipmentDetailsModal">
    <div class="modal-content wide" style="max-width: 900px;">
        <h3 id="detailsTitle">Информация об устройстве</h3>
        <div class="equipment-dossier" id="detailsDossier">
            <!-- Заполняется через JS -->
        </div>
        <div class="modal-actions">
            <button class="btn secondary" onclick="closeEquipmentDetails()">Закрыть</button>
            <button class="btn" id="detailsEditBtn" onclick="editCurrentEquipment()" style="display:none;">Редактировать</button>
        </div>
    </div>
</div>