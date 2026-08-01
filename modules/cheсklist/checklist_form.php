<!-- Модальное окно добавления в чек-лист -->
<div class="add-form-modal" id="checklistModal">
    <div class="modal-content">
        <h3>Добавить в чек-лист</h3>
        <form id="checklistForm">
            <input type="hidden" name="equipment_id" id="cl-equipment-id">
            <div class="form-group">
                <label>Номер КУ</label>
                <input type="text" id="cl-ky" disabled>
            </div>
            <div class="form-group">
                <label>IP-адрес</label>
                <input type="text" id="cl-ip" disabled>
            </div>
            <div class="form-group">
                <label>Категория задачи</label>
                <select name="category_task_id" id="cl-category"></select>
            </div>
            <div class="form-group">
                <label>Тип задачи</label>
                <select name="type_task_id" id="cl-type"></select>
            </div>
            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" id="cl-desc"></textarea>
            </div>
            <div class="form-group">
                <label>Ответственный</label>
                <select name="responsible_user_id" id="cl-user"></select>
            </div>
            <div class="form-group">
                <label>Срок</label>
                <input type="date" name="deadline" id="cl-deadline">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeChecklistModal()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>