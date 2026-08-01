<div class="dashboard">
    <!-- Верхняя панель дашборда -->
    <div class="dashboard-header">
        <div class="breadcrumbs">🏠 Главная / <strong>Статистика</strong></div>
        <div class="header-actions">
            <input type="text" placeholder="Поиск...">
            <span class="user-badge">👤 Super-Admin</span>
        </div>
    </div>

    

    <!-- Карточки метрик -->
    <div class="metric-cards">
        <div class="metric-card blue" data-type="nodes" oncontextmenu="return showMetricContextMenu(event, 'nodes')">
            <div class="metric-icon"><?= icon('assets/icons/rack.png', ['size' => 'medium']) ?></div>
            <div class="metric-value"><?= $totalNodes ?></div>
            <div class="metric-label">Узлов</div>
        </div>
        <div class="metric-card orange" data-type="equipment" oncontextmenu="return showMetricContextMenu(event, 'equipment')">
            <div class="metric-icon"><?= icon('assets/icons/nodes.png', ['size' => 'medium']) ?></div>
            <div class="metric-value"><?= $totalEquipment ?></div>
            <div class="metric-label">Устройств</div>
        </div>
        <div class="metric-card green" data-type="ups" oncontextmenu="return showMetricContextMenu(event, 'ups')">
            <div class="metric-icon">🔋</div>
            <div class="metric-value"><?= $totalUps ?></div>
            <div class="metric-label">ИБП</div>
        </div>
        <div class="metric-card purple" data-type="phones" oncontextmenu="return showMetricContextMenu(event, 'phones')">
            <div class="metric-icon">📞</div>
            <div class="metric-value"><?= $totalPhones ?></div>
            <div class="metric-label">Телефонов</div>
        </div>
        <div class="metric-card cyan" data-type="printers" oncontextmenu="return showMetricContextMenu(event, 'printers')">
            <div class="metric-icon">🖨️</div>
            <div class="metric-value"><?= $totalPrinters ?></div>
            <div class="metric-label">Принтеров</div>
        </div>
    </div>

    <!-- Графики -->
    <div class="charts-grid">
        <div class="chart-card">
            <h3>Узлы по статусу</h3>
            <canvas id="chart-nodes-status"></canvas>
        </div>
        <div class="chart-card">
            <h3>Устройства по типу</h3>
            <canvas id="chart-devices-type"></canvas>
        </div>
        <div class="chart-card">
    <h3>Устройства по статусу</h3>
    <canvas id="chart-equipment-status"></canvas>
</div>
        <div class="chart-card">
            <h3>Телефоны по статусу</h3>
            <canvas id="chart-phones-status"></canvas>
        </div>
        <div class="chart-card">
            <h3>Оборудование по производителям</h3>
            <canvas id="chart-devices-vendor"></canvas>
        </div>
    </div>
</div>