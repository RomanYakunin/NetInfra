<?php
// modules/journal/journal_template.php — разметка страницы «Журнал»
require_once __DIR__ . '/../../includes/acl.php';
if (!isAdmin()) {
    echo '<p style="padding:2rem; text-align:center;">Доступ запрещён</p>';
    return;
}
?>
<link rel="stylesheet" href="modules/journal/journal.css">

<div class="journal-page">
    <!-- Фильтры -->
    <div class="journal-filters">
        <div class="journal-filter">
            <label>Дата с</label>
            <input type="date" id="logDateFrom">
        </div>
        <div class="journal-filter">
            <label>Дата по</label>
            <input type="date" id="logDateTo">
        </div>
        <div class="journal-filter">
            <label>Пользователь</label>
            <select id="logUser">
                <option value="">Все</option>
                <?php foreach ($journalUsers as $u): ?>
                    <option value="<?= (int)$u['user_id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="journal-filter">
            <label>Действие</label>
            <select id="logAction">
                <option value="">Все</option>
                <?php foreach ($journalActions as $a): ?>
                    <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="journal-filter journal-filter-grow">
            <label>Поиск</label>
            <input type="text" id="logSearch" placeholder="Объект, пользователь, подробности…">
        </div>
        <div class="journal-filter">
            <label>На странице</label>
            <select id="logPerPage">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
        <div class="journal-filter">
            <label>&nbsp;</label>
            <button type="button" class="btn secondary" id="logResetBtn">Сбросить</button>
        </div>
    </div>

    <!-- Таблица логов -->
    <div class="journal-table-wrapper">
        <table class="journal-table" id="logsTable">
            <thead>
                <tr>
                    <th style="width:1%;">ID</th>
                    <th style="width:1%;">Дата/время</th>
                    <th>Пользователь</th>
                    <th>IP-адрес</th>
                    <th>Действие</th>
                    <th>Объект</th>
                    <th>Подробности</th>
                </tr>
            </thead>
            <tbody id="logsTableBody">
                <tr><td colspan="7" class="journal-empty">Загрузка…</td></tr>
            </tbody>
        </table>
    </div>

    <div class="journal-pagination" id="logsPagination"></div>
</div>

<!-- Модальное окно с полной информацией о записи -->
<div class="add-form-modal" id="logDetailModal">
    <div class="modal-content">
        <h3>Запись журнала</h3>
        <div id="logDetailBody" class="journal-detail"></div>
        <div class="modal-actions">
            <button type="button" class="btn secondary" onclick="closeLogDetail()">Закрыть</button>
        </div>
    </div>
</div>

<script src="modules/journal/journal.js"></script>
