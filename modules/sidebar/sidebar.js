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

document.addEventListener('DOMContentLoaded', () => {
    initPinButton();

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
        });
    }
});