<?php
// modules/knowledge_base/knowledge_base_template.php — разметка страницы «База знаний»
require_once __DIR__ . '/../../includes/acl.php';
if (!isAdmin()) {
    echo '<p style="padding:2rem; text-align:center;">Доступ запрещён</p>';
    return;
}
?>
<link rel="stylesheet" href="modules/knowledge_base/knowledge_base.css">

<div class="kb-page">
    <!-- Левая панель: список таблиц -->
    <aside class="kb-sidebar">
        <div class="kb-sidebar-header">
            <span>🗄️ Таблицы</span>
        </div>
        <input type="text" id="kbTableFilter" class="kb-table-filter" placeholder="Фильтр таблиц...">
        <ul class="kb-table-list" id="kbTableList">
            <li class="kb-muted">Загрузка…</li>
        </ul>
    </aside>

    <!-- Правая область -->
    <section class="kb-main" id="kbMain">
        <div class="kb-empty" id="kbEmptyState">
            Выберите таблицу слева, чтобы посмотреть её структуру и данные
        </div>

        <div class="kb-content" id="kbContent" style="display:none;">
            <div class="kb-header">
                <h2 id="kbTableName"></h2>
                <span class="kb-muted" id="kbTableMeta"></span>
            </div>

            <!-- Вкладки -->
            <div class="kb-tabs">
                <button type="button" class="kb-tab active" data-tab="data">Данные</button>
                <button type="button" class="kb-tab" data-tab="structure">Структура</button>
            </div>

            <!-- ---------- Вкладка «Данные» ---------- -->
            <div class="kb-tab-content" id="kbTabData">
                <div class="kb-toolbar">
                    <input type="text" id="kbSearch" class="kb-search" placeholder="Поиск по всем столбцам...">
                    <select id="kbPerPage" class="kb-select">
                        <option value="10">10 строк</option>
                        <option value="25" selected>25 строк</option>
                        <option value="50">50 строк</option>
                        <option value="100">100 строк</option>
                        <option value="200">200 строк</option>
                    </select>
                    <button type="button" class="btn" id="kbAddRowBtn">+ Добавить запись</button>
                    <button type="button" class="btn secondary" id="kbExportBtn">Экспорт CSV</button>
                </div>

                <div class="kb-table-wrapper">
                    <table class="kb-data-table" id="kbDataTable">
                        <thead id="kbTableHead"></thead>
                        <tbody id="kbTableBody"></tbody>
                    </table>
                </div>

                <div class="kb-pagination" id="kbPagination"></div>
            </div>

            <!-- ---------- Вкладка «Структура» ---------- -->
            <div class="kb-tab-content" id="kbTabStructure" style="display:none;">
                <div class="kb-toolbar">
                    <button type="button" class="btn" id="kbAddColumnBtn">+ Добавить столбец</button>
                </div>
                <div class="kb-table-wrapper">
                    <table class="kb-data-table" id="kbStructureTable">
                        <thead>
                            <tr>
                                <th>Столбец</th>
                                <th>Тип</th>
                                <th>NULL</th>
                                <th>Ключ</th>
                                <th>По умолчанию</th>
                                <th>Extra</th>
                                <th style="width:1%;">Действия</th>
                            </tr>
                        </thead>
                        <tbody id="kbStructureBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Модальное окно записи (добавление / редактирование) -->
<div class="add-form-modal" id="kbRowModal">
    <div class="modal-content">
        <h3 id="kbRowModalTitle">Запись</h3>
        <form id="kbRowForm" autocomplete="off">
            <div id="kbRowFields"></div>
            <div class="form-error" id="kbRowError" style="display:none;"></div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="kbCloseRowModal()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно столбца (добавление / изменение) -->
<div class="add-form-modal" id="kbColumnModal">
    <div class="modal-content">
        <h3 id="kbColumnModalTitle">Столбец</h3>
        <form id="kbColumnForm" autocomplete="off">
            <input type="hidden" id="kbColumnOriginalName">

            <div class="form-group">
                <label>Имя столбца</label>
                <input type="text" id="kbColumnName" required
                       pattern="[A-Za-z_][A-Za-z0-9_]*"
                       title="Латиница, цифры и подчёркивание; не начинается с цифры">
            </div>

            <div class="form-group">
                <label>Тип</label>
                <select id="kbColumnType">
                    <option value="INT">INT</option>
                    <option value="BIGINT">BIGINT</option>
                    <option value="TINYINT(1)">TINYINT(1) — логический</option>
                    <option value="VARCHAR(100)" selected>VARCHAR(100)</option>
                    <option value="VARCHAR(255)">VARCHAR(255)</option>
                    <option value="TEXT">TEXT</option>
                    <option value="DATE">DATE</option>
                    <option value="DATETIME">DATETIME</option>
                    <option value="TIMESTAMP">TIMESTAMP</option>
                    <option value="DECIMAL(10,2)">DECIMAL(10,2)</option>
                    <option value="FLOAT">FLOAT</option>
                    <option value="JSON">JSON</option>
                </select>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="kbColumnNullable" checked>
                    Разрешить NULL
                </label>
            </div>

            <div class="form-group">
                <label>Значение по умолчанию</label>
                <input type="text" id="kbColumnDefault" placeholder="оставьте пустым, если не нужно">
            </div>

            <div class="form-error" id="kbColumnError" style="display:none;"></div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="kbCloseColumnModal()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script src="modules/knowledge_base/knowledge_base.js"></script>
