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
        <div class="nav-section" data-section="monitoring">
            <button type="button" class="nav-section-title" aria-expanded="true">
                <span class="section-icon">📊</span>
                <span class="section-label">Мониторинг</span>
                <span class="section-chevron">▾</span>
            </button>
            <div class="nav-section-body">
                <a href="?page=dashboard" class="nav-item <?= $page=='dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">📈</span> <span class="nav-label">Дашборд</span>
                </a>
                <a href="?page=monitoring" class="nav-item <?= $page=='monitoring' ? 'active' : '' ?>">
                    <span class="nav-icon">🚨</span> <span class="nav-label">Панель</span>
                </a>
                <a href="?page=nodes" class="nav-item <?= $page=='nodes' ? 'active' : '' ?>">
                    <span class="nav-icon">🖧</span> <span class="nav-label">Узлы</span>
                </a>
            </div>
        </div>

        <!-- Секция 2: Инвентаризация -->
        <div class="nav-section" data-section="inventory">
            <button type="button" class="nav-section-title" aria-expanded="true">
                <span class="section-icon">📦</span>
                <span class="section-label">Инвентаризация</span>
                <span class="section-chevron">▾</span>
            </button>
            <div class="nav-section-body">
                <a href="?page=warehouse" class="nav-item <?= $page=='warehouse' ? 'active' : '' ?>">
                    <span class="nav-icon">🏬</span> <span class="nav-label">Склад</span>
                </a>
                <a href="?page=phones" class="nav-item <?= $page=='phones' ? 'active' : '' ?>">
                    <span class="nav-icon">📞</span> <span class="nav-label">Телефоны</span>
                </a>
                <a href="?page=checklist" class="nav-item <?= $page=='checklist' ? 'active' : '' ?>">
                    <span class="nav-icon">✅</span> <span class="nav-label">Чек-лист</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Нижний блок -->
    <div class="sidebar-footer">
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <!-- Администрирование (только для админа) -->
        <div class="nav-section" data-section="admin">
            <button type="button" class="nav-section-title" aria-expanded="true">
                <span class="section-icon">⚙️</span>
                <span class="section-label">Администрирование</span>
                <span class="section-chevron">▾</span>
            </button>
            <div class="nav-section-body">
                <a href="?page=database_manager" class="nav-item <?= $page=='database_manager' ? 'active' : '' ?>">
                    <span class="nav-icon">🗄️</span> <span class="nav-label">База знаний</span>
                </a>
                <a href="?page=users" class="nav-item <?= $page=='users' ? 'active' : '' ?>">
                    <span class="nav-icon">👥</span> <span class="nav-label">Пользователи</span>
                </a>
                <a href="?page=journal" class="nav-item <?= $page=='journal' ? 'active' : '' ?>">
                    <span class="nav-icon">📋</span> <span class="nav-label">Журнал</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php
            $sidebarLogin = $_SESSION['login'] ?? '';
            $sidebarRole  = $_SESSION['role'] ?? '';
            $sidebarRoleLabel = $sidebarRole === 'admin' ? 'Администратор' : 'Пользователь';
        ?>
        <div class="user-block">
            <div class="user-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($sidebarLogin, 0, 1))) ?></div>
            <div class="user-details">
                <div class="user-name"><?= htmlspecialchars($sidebarRoleLabel) ?></div>
                <div class="user-role"><?= htmlspecialchars($sidebarLogin) ?></div>
            </div>
            <button onclick="location.href='?logout=1'">Выйти</button>
        </div>
        <!-- ============================================================
             БЛОК «СОВРЕМЕННЫЙ СТИЛЬ» — можно удалить целиком вместе с
             modules/sidebar/modern.css и обработчиком #modernThemeBtn
             в sidebar.js, остальной интерфейс это не затронет.
             ============================================================ -->
        <button type="button" class="modern-theme-btn" id="modernThemeBtn"
                title="Переключить современный стиль">
            <span class="modern-theme-icon">🎨</span>
            <span class="modern-theme-label">Современный стиль</span>
        </button>
        <!-- ==================== /БЛОК «СОВРЕМЕННЫЙ СТИЛЬ» ==================== -->

        <div class="theme-switch">
            <div class="theme-option active" data-theme="dark">🌙</div>
            <div class="theme-option" data-theme="light">☀️</div>
            <div class="theme-option" data-theme="neutral">⛅</div>
        </div>
    </div>
</aside>