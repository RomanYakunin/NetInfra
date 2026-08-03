<?php
// modules/warehouse_page/warehouse_page_template.php
?>
<link rel="stylesheet" href="modules/warehouse_page/warehouse_page.css">

<div class="warehouse-page">
    <!-- Поиск -->
    <div class="warehouse-top-bar">
        <div class="warehouse-search">
            <span class="search-icon">🔍</span>
            <input type="text" id="warehouseSearch" placeholder="Поиск..." autocomplete="off">
        </div>
        <div class="warehouse-actions">
            <button class="btn small" id="addDeviceBtn" onclick="warehouseAddClick()">➕ Добавить устройство</button>
        </div>
        <div class="warehouse-actions">
            <button class="btn" onclick="warehouseBuildStack()">🧱 Собрать стек</button>
        </div>
    </div>

    <!-- Кнопки складов -->
    <div class="warehouse-building-filter" id="warehouseFilter">
        <span class="label">🏢 Склад:</span>
        <button class="warehouse-building-btn active" data-warehouse="all" onclick="applyWarehouseFilter('all')">Все склады</button>
        <?php foreach ($warehouses as $wh): ?>
            <button class="warehouse-building-btn" data-warehouse="<?= $wh['id'] ?>" onclick="applyWarehouseFilter('<?= $wh['id'] ?>')">
                <?= htmlspecialchars($wh['building_name'] . ' (' . $wh['name'] . ')') ?>
            </button>
        <?php endforeach; ?>
        <button class="warehouse-building-btn add-warehouse-btn" onclick="openAddWarehouseForm()">+</button>
    </div>

    <!-- Вкладки -->
    <div class="warehouse-tabs" id="warehouseTabs">
        <a href="#" data-tab="Оборудование" class="active" onclick="switchTab('Оборудование'); return false;">Оборудование</a>
        <a href="#" data-tab="Телефоны" onclick="switchTab('Телефоны'); return false;">Телефоны</a>
        <a href="#" data-tab="Прочее" onclick="switchTab('Прочее'); return false;">Прочее</a>
    </div>

    <!-- Таблица и боковая панель типов (панель только на вкладке «Прочее») -->
    <div class="warehouse-content">
        <aside class="warehouse-type-panel" id="warehouseTypePanel" style="display:none;">
            <div class="type-panel-title">Тип устройства</div>
            <div id="warehouseTypeList"></div>
        </aside>

        <div class="warehouse-table">
            <table>
                <thead id="warehouseTableHead"></thead>
                <tbody id="warehouseTableBody">
                    <!-- AJAX -->
                </tbody>
            </table>
        </div>
    </div>

    <div class="warehouse-footer" id="warehouseFooter">
        <span>Всего устройств на складе: 0</span>
    </div>
</div>

<!-- Модальное окно добавления склада -->
<div class="add-form-modal" id="warehouseModal">
    <div class="modal-content">
        <h3>Добавить склад</h3>
        <form id="warehouseForm">
            <div class="form-group">
                <label>Здание</label>
                <select name="building_id" id="warehouseBuildingSelect"></select>
            </div>
            <div class="form-group">
                <label>Помещение</label>
                <input type="text" name="name">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeWarehouseModal()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно добавления оборудования на склад (автономная форма) -->
<!-- warehouse_page_template.php – обновлённая форма -->
<!-- Модальное окно добавления оборудования на склад -->
<div class="add-form-modal" id="warehouseEquipmentModal">
    <div class="modal-content">
        <div class="modal-title-row">
            <h3 id="warehouseEquipmentTitle">Добавить оборудование на склад</h3>
            <button class="modal-expand-btn" id="warehouseExpandBtn" type="button" title="Расширить форму">⛶</button>
        </div>
        <form id="warehouseEquipmentForm">
            <div id="warehouseFormFields"></div>
            <div id="warehouseFormHiddenFields"></div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeWarehouseEquipmentForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>
</div>
<div id="stackWizardModal" class="add-form-modal">
    <div class="modal-content" style="max-width: 650px; overflow: hidden;">
        <h3>Собрать стек</h3>
        <div class="wizard-steps" style="display: flex; transition: transform 0.4s ease; width: 300%;">
            <!-- Шаг 1: Выбор устройств -->
            <div class="wizard-step" style="width: 33.33%; flex-shrink: 0;">
                <h4>Выберите устройства</h4>
                <div class="form-group">
    <input type="text" id="wizard-search" placeholder="Поиск по MAC-адресу, хосту, модели...">
</div>
<div id="wizard-equipment-list" style="max-height:400px; overflow-y:auto; border:1px solid var(--border-color); border-radius:4px; padding:0.5rem;"></div>
                <div class="modal-actions">
                    <button class="btn secondary" onclick="closeStackWizard()">Отмена</button>
                    <button class="btn" id="wizard-next-1">Далее</button>
                </div>
            </div>
            <!-- Шаг 2: Настройка слотов, IP, hostname -->
            <div class="wizard-step" style="width: 33.33%; flex-shrink: 0;">
                <h4>Настройка</h4>
                <div id="wizard-config-list"></div>
                <div class="form-group">
                    <label>IP-адрес (только свободные)</label>
                    <select id="wizard-ip-select"></select>
                    <div id="wizard-ip-warning" style="color: var(--danger); display: none;"></div>
                </div>
                <div class="form-group">
                    <label>Имя хоста</label>
                    <input type="text" id="wizard-hostname">
                    <div id="wizard-hostname-warning" style="color: var(--danger); display: none;"></div>
                </div>
                <div class="modal-actions">
                    <button class="btn secondary" id="wizard-back-2">Назад</button>
                    <button class="btn" id="wizard-next-2">Далее</button>
                </div>
            </div>
            <!-- Шаг 3: Выбор КУ -->
            <div class="wizard-step" style="width: 33.33%; flex-shrink: 0;">
                <h4>Стек собран. Куда переместить?</h4>
                <div class="form-group">
                    <select id="wizard-node-select"></select>
                </div>
                <div class="modal-actions">
                    <button class="btn secondary" id="wizard-back-3">Назад</button>
                    <button class="btn" id="wizard-finish">Переместить</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Контекстное меню -->
<div id="ctxMenu" class="context-menu"></div>
<script src="modules/warehouse_page/warehouse_page.js"></script>