<?php
// Проверка прав доступа (только админ)
if ($_SESSION['role'] !== 'admin') {
    echo '<p style="padding:2rem; text-align:center;">Доступ запрещён</p>';
    return;
}
?>
<link rel="stylesheet" href="modules/knowledge_base/knowledge_base.css">

<div class="knowledge-base-page">
    <div class="kb-sidebar" id="kbSidebar">
        <div class="kb-sidebar-header">
            <span>📚 База знаний</span>
        </div>
        <ul id="kbTableList">
            <!-- Заполняется динамически -->
        </ul>
    </div>
    <div class="kb-main" id="kbMain">
        <div class="toolbar" id="kbToolbar">
            <input type="text" id="kbSearch" placeholder="Поиск...">
            <select id="kbPerPage">
                <option value="25">25 строк</option>
                <option value="50">50 строк</option>
                <option value="100">100 строк</option>
            </select>
            <button class="btn" id="kbAddBtn">Добавить запись</button>
            <button class="btn secondary" id="kbExportBtn">Экспорт CSV</button>
        </div>
        <div class="table-wrapper" id="kbTableContainer">
            <table id="kbDataTable">
                <thead id="kbTableHead"></thead>
                <tbody id="kbTableBody"></tbody>
            </table>
        </div>
        <div class="pagination" id="kbPagination"></div>
    </div>
</div>

<!-- Модальное окно для редактирования/добавления -->
<div class="modal-overlay" id="kbModalOverlay">
    <div class="modal" id="kbModalBox">
        <h3 id="kbModalTitle"></h3>
        <form id="kbModalForm">
            <div id="kbModalFields"></div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" id="kbModalCancel">Отмена</button>
                <button type="submit" class="btn" id="kbModalSave">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script src="modules/knowledge_base/knowledge_base.js"></script>