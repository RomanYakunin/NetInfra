<aside class="sidebar" id="sidebar">
    <!-- Верхний блок -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <span class="logo-icon">N</span>
            <span class="logo-text">NetInfra</span>
        </div>
        <button class="icon-btn pin-btn" id="pinSidebarBtn" title="Зафиксировать панель">
        <span class="pin-icon">📌</span>
    </button>
    </div>

    <!-- Поиск -->
    <div class="sidebar-search">
        <span class="search-icon">🔍</span>
        <input type="text" placeholder="Поиск по разделам..." autocomplete="off" />
    </div>

    <!-- Навигационное меню -->
    <nav class="sidebar-nav" id="sidebarNav">
        <!-- Секция 1: Мониторинг -->
        <div class="nav-section">
            <div class="nav-section-title">
                <span class="section-icon">📊</span> 
                <span class="section-label">Мониторинг</span>
            </div>
            <a href="?page=dashboard" class="nav-item <?= $page=='dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📈</span> <span class="nav-label">Дашборд</span>
            </a>
            <a href="?page=nodes" class="nav-item <?= $page=='nodes' ? 'active' : '' ?>">
                <span class="nav-icon">🖧</span> <span class="nav-label">Узлы</span>
            </a>
        </div>

        <!-- Секция 2: Инвентаризация -->
        <div class="nav-section">
            <div class="nav-section-title">
                <span class="section-icon">📦</span> 
                <span class="section-label">Инвентаризация</span>
            </div>
            <a href="?page=warehouse" class="nav-item <?= $page=='warehouse' ? 'active' : '' ?>">
                <span class="nav-icon">🏬</span> <span class="nav-label">Склад</span>
            </a>
            <a href="?page=checklist" class="nav-item <?= $page=='checklist' ? 'active' : '' ?>">
                <span class="nav-icon">✅</span> <span class="nav-label">Чек-лист</span>
            </a>
        </div>
    </nav>

    <!-- Нижний блок -->
    <div class="sidebar-footer">
        <!-- Администрирование -->
        <div class="nav-section">
            <div class="nav-section-title">
                <span class="section-icon">⚙️</span> 
                <span class="section-label">Администрирование</span>
            </div>
            <a href="?page=database_manager" class="nav-item">
                <span class="nav-icon">🗄️</span> <span class="nav-label">База данных</span>
            </a>
        </div>

        <div class="user-block">
            <div class="user-avatar">A</div>
            <div class="user-details">
                <div class="user-name">Администратор</div>
                <div class="user-role">admin@netinfra</div>
            </div>
            <button onclick="location.href='?logout=1'">Выйти</button>
        </div>
        <div class="theme-switch">
            <div class="theme-option active" data-theme="dark">🌙</div>
            <div class="theme-option" data-theme="light">☀️</div>
            <div class="theme-option" data-theme="neutral">⛅</div>
        </div>
    </div>
</aside>