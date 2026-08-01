<!-- Модальное окно перемещения устройства -->
<div class="add-form-modal" id="moveModal">
    <div class="modal-content">
        <h3>Переместить устройство</h3>
        <div class="move-buttons">
            <button class="btn move-btn" id="move-to-other-ku">Другое КУ</button>
            <button class="btn move-btn" id="move-to-warehouse">Склад</button>
            <button class="btn move-btn" id="move-to-separate">Отдельно Без КУ</button>
        </div>
        <div id="move-options" style="margin-top:1rem;"></div>
        <div class="modal-actions">
            <button class="btn secondary" onclick="closeMoveModal()">Отмена</button>
            <button class="btn" id="move-submit-btn" style="display:none;" onclick="submitMove()">Переместить</button>
        </div>
    </div>
</div>