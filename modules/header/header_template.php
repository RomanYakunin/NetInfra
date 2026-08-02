<?php
// Заголовок страницы по значению ?page=. Держим карту в одном месте,
// чтобы новые страницы не приходилось дописывать в цепочку тернарников.
$pageTitles = [
    'nodes'            => '🖧 Коммутационные узлы',
    'warehouse'        => '📦 Склады',
    'checklist'        => '✅ Чек-лист',
    'dashboard'        => '📊 Дашборд',
    'database_manager' => '🗄️ База знаний',
    'users'            => '👥 Пользователи',
    'journal'          => '📋 Журнал действий',
    'phones'           => '📞 Телефоны',
];
$pageTitle = $pageTitles[$page ?? ''] ?? 'NetInfra Manager';
?>
<header class="header">
    <div class="header-left">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>
    <div class="header-right">
        
        <!-- Блок открытых проблем чек-листа (показывается всегда) -->
         <?php if ($page === 'nodes'): ?>
            <!-- <div class="stat-block">
            <span class="stat-dot red"></span>
            <span class="stat-value"><?= $openProblems ?></span>
            <span class="stat-label">Проблем</span>
        </div> -->
        <!-- КНОПКА ОБНОВЛЕНИЯ СТАТУСОВ -->
        <button class="btn-icon refresh-status-btn" onclick="openPingModal()" title="Обновить статусы устройств">
            🔄 PingIP
        </button>
        <div class="stat-block"><span class="stat-dot green"></span><span class="stat-value"><?= $activeNodesByIp ?></span><span class="stat-label">Активных узлов</span></div>
            <div class="stat-block"><span class="stat-dot red"></span><span class="stat-value"><?= $inactiveNodesByIp ?></span><span class="stat-label">Не активных узлов</span></div>
            <div class="stat-block"><span class="stat-dot green"></span><span class="stat-value"><?= $activeDevices ?></span><span class="stat-label">Активных устройств</span></div>
            <div class="stat-block"><span class="stat-dot red"></span><span class="stat-value"><?= $inactiveDevices ?></span><span class="stat-label">Не активных устройств</span></div>
            <div class="stat-block"><span><?= icon('assets/icons/rack.png', ['class' => 'icon-md']) ?></span><span class="stat-value"><?= $allNodes ?></span><span class="stat-label">Всего узлов</span></div>
            <div class="stat-block"><?= icon('assets/icons/nodes.png', ['class' => 'icon-md']) ?></span><span class="stat-value"><?= $allDevices ?></span><span class="stat-label">Всего устройств</span></div>
        <?php elseif ($page === 'phones'): ?>
            <div class="stat-block"><span class="stat-dot green"></span><span class="stat-value"><?= $activePhones ?></span><span class="stat-label">Активных телефонов</span></div>
            <div class="stat-block"><span class="stat-dot red"></span><span class="stat-value"><?= $inactivePhones ?></span><span class="stat-label">Не активных телефонов</span></div>
        <?php endif; ?>
        <!-- <button class="btn-icon" onclick="toggleRightPanel()">🗄️</button> -->
    </div>
    <!-- Модальное окно для пинга -->
<div id="pingModal" class="add-form-modal">
    <div class="modal-content" style="max-width: 700px;">
        <h3>Сканирование сети</h3>
        <!-- Вкладки -->
        <div class="ping-tabs" style="display:flex; gap:0; margin-bottom:1rem; border-bottom:2px solid var(--border-color);">
            <button class="ping-tab active" data-tab="presets" onclick="switchPingTab('presets')">Подсети (VLAN)</button>
            <button class="ping-tab" data-tab="range" onclick="switchPingTab('range')">Произвольный диапазон</button>
            <button class="ping-tab" data-tab="single" onclick="switchPingTab('single')">Один IP</button>
        </div>

        <!-- Вкладка 1: Пресеты VLAN -->
        <div id="ping-presets" class="ping-tab-content" style="display:block;">
            <div class="form-group">
                <label>Выберите подсеть</label>
                <div id="vlan-checkboxes" style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.8rem;"></div>
                <!-- <button class="btn small" id="addVlanPresetBtn" onclick="openAddVlanPresetForm()" style="margin-top:0.5rem;">+ Добавить подсеть</button> -->
            </div>
            <div class="modal-actions" style="justify-content:flex-start;">
                <button class="btn" id="startPingPresets">Сканировать выбранные</button>
            </div>
        </div>

        <!-- Вкладка 2: Произвольный диапазон -->
        <div id="ping-range" class="ping-tab-content" style="display:none;">
            <div class="form-group">
                <label>Начальный IP:</label>
                <input type="text" id="pingStartIp" placeholder="Например, 192.168.1.1">
            </div>
            <div class="form-group">
                <label>Конечный IP:</label>
                <input type="text" id="pingEndIp" placeholder="Например, 192.168.1.254">
            </div>
            <div class="modal-actions" style="justify-content:flex-start;">
                <button class="btn" id="startPingRange">Сканировать диапазон</button>
            </div>
        </div>

        <!-- Вкладка 3: Одиночный IP -->
        <div id="ping-single" class="ping-tab-content" style="display:none;">
            <div class="form-group">
                <label>IP-адрес:</label>
                <input type="text" id="pingSingleIp" placeholder="192.168.1.1">
            </div>
            <div class="modal-actions" style="justify-content:flex-start;">
                <button class="btn" id="startPingSingle">Пинговать</button>
            </div>
        </div>

        <!-- Прогресс -->
        <div id="pingProgress" style="display:none; margin:1rem 0;">
            <progress id="pingProgressBar" value="0" max="100" style="width:100%; height:20px;"></progress>
            <span id="pingProgressText">0 / 0</span>
        </div>

        <!-- Таблица результатов -->
        <div id="pingResults" style="display:none; margin-top:1rem; max-height:300px; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead><tr><th>IP-адрес</th><th>Статус</th><th>Время отклика</th></tr></thead>
                <tbody id="pingResultsBody"></tbody>
            </table>
        </div>

        <div class="modal-actions" style="margin-top:1rem;">
    <button class="btn secondary" onclick="closePingModal()">Закрыть</button>
    <button class="btn" id="pingSaveBtn" style="display:none;" onclick="savePingResults()">Сохранить в БД</button>
</div>
    </div>
    <div id="vlanContextMenu" class="vlan-context-menu" style="display:none;"></div>
</div>

<!-- Мини-форма добавления пресета VLAN -->
<div id="addVlanPresetModal" class="add-form-modal" style="z-index:3001;">
    <div class="modal-content" style="max-width:400px;">
        <h3>Добавить подсеть</h3>
        <form id="addVlanPresetForm">
            <div class="form-group">
                <label>Название (например, Vlan 119)</label>
                <input type="text" name="preset_name" required>
            </div>
            <div class="form-group">
                <label>Начальный IP</label>
                <input type="text" name="start_ip" required>
            </div>
            <div class="form-group">
                <label>Конечный IP</label>
                <input type="text" name="end_ip" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeAddVlanPresetForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
    
</div>
</header>