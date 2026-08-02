<!-- Модальное окно подробной информации об оборудовании -->
<div class="add-form-modal" id="equipmentDetailsModal">
    <div class="modal-content wide" style="max-width: 900px;">
        <h3 id="detailsTitle">Информация об устройстве</h3>
        <div class="equipment-dossier" id="detailsDossier">
            <!-- Заполняется через JS -->
        </div>
        <div class="modal-actions">
            <button class="btn secondary" onclick="closeEquipmentDetails()">Закрыть</button>
            <button class="btn secondary" id="detailsHistoryBtn" onclick="openEquipmentHistory()">📜 История изменений</button>
            <button class="btn" id="detailsEditBtn" onclick="editCurrentEquipment()" style="display:none;">Редактировать</button>
        </div>
    </div>
</div>

<!-- Модальное окно истории изменений объекта -->
<div class="add-form-modal" id="historyModal">
    <div class="modal-content wide" style="max-width: 900px;">
        <h3 id="historyTitle">История изменений</h3>
        <div class="history-wrapper">
            <table class="history-table" id="historyTable">
                <thead>
                    <tr>
                        <th style="width:1%;">Дата/время</th>
                        <th>Пользователь</th>
                        <th>Действие</th>
                        <th>Объект</th>
                        <th>Подробности</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <tr><td colspan="5" class="history-empty">Загрузка…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="history-pagination" id="historyPagination"></div>
        <div class="modal-actions">
            <button class="btn secondary" onclick="closeEquipmentHistory()">Закрыть</button>
        </div>
    </div>
</div>