// modules/sidebar/sidebar.js

function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('netinfra-theme', theme);
    document.querySelectorAll('.theme-option').forEach(opt =>
        opt.classList.toggle('active', opt.dataset.theme === theme)
    );
}

function initPinButton() {
    const pinBtn = document.getElementById('pinSidebarBtn');
    const sidebar = document.getElementById('sidebar');
    if (!pinBtn || !sidebar) return;

    function isPinned() {
        return localStorage.getItem('sidebarPinned') === 'true';
    }

    function setPinned(pinned) {
        localStorage.setItem('sidebarPinned', pinned);
        if (pinned) {
            document.body.classList.add('sidebar-pinned');
            document.body.classList.add('sidebar-expanded');
            pinBtn.classList.add('active');
            pinBtn.title = 'Открепить панель';
            document.body.classList.remove('sidebar-collapsing');
        } else {
            document.body.classList.remove('sidebar-pinned', 'sidebar-expanded');
            document.body.classList.add('sidebar-collapsing');
            pinBtn.classList.remove('active');
            pinBtn.title = 'Зафиксировать панель';

            setTimeout(() => {
                document.body.classList.remove('sidebar-collapsing');
            }, 300);
        }
    }

    pinBtn.addEventListener('click', () => setPinned(!isPinned()));

    if (isPinned()) {
        setPinned(true);
    }

    sidebar.addEventListener('mouseenter', () => {
        if (!isPinned()) {
            document.body.classList.add('sidebar-expanded');
        }
    });
    sidebar.addEventListener('mouseleave', () => {
        if (!isPinned()) {
            document.body.classList.remove('sidebar-expanded');
        }
    });
}

/**
 * Секции меню сворачиваются кликом по заголовку. Состояние хранится
 * в localStorage: разделов планируется много, и каждый раз проматывать
 * ненужные — лишняя работа.
 *
 * Секцию с текущей страницей не сворачиваем: иначе после перехода
 * активный пункт оказался бы спрятан.
 */
function initSidebarSections() {
    const STORAGE_KEY = 'netinfra-sidebar-sections';

    let collapsed;
    try {
        collapsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        if (!Array.isArray(collapsed)) collapsed = [];
    } catch (e) {
        collapsed = [];
    }

    const save = () => localStorage.setItem(STORAGE_KEY, JSON.stringify(collapsed));

    document.querySelectorAll('.nav-section[data-section]').forEach(section => {
        const key = section.dataset.section;
        const title = section.querySelector('.nav-section-title');
        if (!title) return;

        const hasActive = !!section.querySelector('.nav-item.active');
        const isCollapsed = collapsed.indexOf(key) !== -1 && !hasActive;

        section.classList.toggle('collapsed', isCollapsed);
        title.setAttribute('aria-expanded', String(!isCollapsed));

        // Активную секцию раскрыли принудительно — снимем её из сохранённых
        if (hasActive && collapsed.indexOf(key) !== -1) {
            collapsed = collapsed.filter(k => k !== key);
            save();
        }

        title.addEventListener('click', () => {
            const nowCollapsed = !section.classList.contains('collapsed');
            section.classList.toggle('collapsed', nowCollapsed);
            title.setAttribute('aria-expanded', String(!nowCollapsed));

            collapsed = collapsed.filter(k => k !== key);
            if (nowCollapsed) collapsed.push(key);
            save();
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initPinButton();
    initSidebarSections();

    const savedTheme = localStorage.getItem('netinfra-theme') || 'dark';
    setTheme(savedTheme);

    document.querySelectorAll('.theme-option').forEach(opt => {
        opt.addEventListener('click', () => setTheme(opt.dataset.theme));
    });

    const searchInput = document.querySelector('.sidebar-search input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            document.querySelectorAll('.nav-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = (!query || text.includes(query)) ? '' : 'none';
            });

            // Найденное в свёрнутой секции иначе осталось бы невидимым
            document.querySelectorAll('.nav-section[data-section]').forEach(section => {
                if (!query) {
                    section.classList.toggle('collapsed', section.dataset.wasCollapsed === '1');
                    return;
                }
                if (section.dataset.wasCollapsed === undefined) {
                    section.dataset.wasCollapsed = section.classList.contains('collapsed') ? '1' : '0';
                }
                const hasMatch = Array.from(section.querySelectorAll('.nav-item'))
                    .some(i => i.style.display !== 'none');
                section.classList.toggle('collapsed', !hasMatch);
            });
            if (!query) {
                document.querySelectorAll('.nav-section[data-section]')
                    .forEach(s => delete s.dataset.wasCollapsed);
            }
        });
    }
});
/* ============================================================
   БЛОК «СОВРЕМЕННЫЙ СТИЛЬ» (кнопка 🎨 в sidebar)
   Удаляется вместе с modules/sidebar/modern.css и разметкой
   #modernThemeBtn в sidebar_template.php.
   ============================================================ */
(function () {
    'use strict';

    const STORAGE_KEY = 'netinfra-modern-theme';

    /** Включает/выключает класс .modern-theme на <html> и запоминает выбор. */
    function applyModernTheme(enabled) {
        document.documentElement.classList.toggle('modern-theme', enabled);
        localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
        const btn = document.getElementById('modernThemeBtn');
        if (btn) {
            btn.classList.toggle('active', enabled);
            btn.title = enabled ? 'Вернуть стандартный стиль' : 'Переключить современный стиль';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Восстанавливаем сохранённый выбор
        const saved = localStorage.getItem(STORAGE_KEY) === '1';
        applyModernTheme(saved);

        document.getElementById('modernThemeBtn')?.addEventListener('click', () => {
            const nowEnabled = !document.documentElement.classList.contains('modern-theme');
            applyModernTheme(nowEnabled);
            if (typeof showToast === 'function') {
                showToast(nowEnabled ? 'Современный стиль включён' : 'Стандартный стиль', 'info');
            }
        });
    });
})();
