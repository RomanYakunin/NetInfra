// assets/js/scripts.js – общие функции

// Единственная функция переключения темы
function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('netinfra-theme', theme);

    // Обновляем активную кнопку в сайдбаре
    document.querySelectorAll('.theme-option').forEach(function(opt) {
        opt.classList.toggle('active', opt.dataset.theme === theme);
    });
}

// После загрузки DOM навешиваем обработчики и синхронизируем кнопки
document.addEventListener('DOMContentLoaded', function() {
    var current = document.documentElement.getAttribute('data-theme') || 'dark';
    document.querySelectorAll('.theme-option').forEach(function(opt) {
        opt.classList.toggle('active', opt.dataset.theme === current);
        opt.addEventListener('click', function() {
            setTheme(this.dataset.theme);
        });
    });
});

// Остальные общие функции (toggleSidebar, selectMenuItem, …) остаются без изменений
// При загрузке DOM активируем переключатели темы
document.addEventListener('DOMContentLoaded', () => {
    // Устанавливаем активную кнопку в соответствии с текущей темой
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark'||'light'||'neutral';
    document.querySelectorAll('.theme-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.theme === currentTheme);
        opt.addEventListener('click', function() {
            setTheme(this.dataset.theme);
        });
    });
});



// Контекстное меню для карточек дашборда (добавлено ранее)
function showMetricContextMenu(event, type) {
    event.preventDefault();
    const menu = document.getElementById('ctxMenu');
    if (!menu) return;

    menu.innerHTML = `
        <div class="menu-item" onclick="refreshMetric('${type}')">Обновить данные</div>
        <div class="menu-item" onclick="exportMetric('${type}')">Выгрузка данных в Excel</div>
        <div class="menu-item" style="font-style:italic; color:gray;">В разработке</div>
    `;
    menu.style.display = 'block';
    menu.style.left = event.clientX + 'px';
    menu.style.top = event.clientY + 'px';
}

async function refreshMetric(type) {
    document.getElementById('ctxMenu').style.display = 'none';
    try {
        const response = await fetch('?ajax=get_dashboard_stats&type=' + type);
        const data = await response.json();
        const card = document.querySelector(`.metric-card[data-type="${type}"] .metric-value`);
        if (card) card.textContent = data.count;
    } catch (e) {}
}

function exportMetric(type) {
    document.getElementById('ctxMenu').style.display = 'none';
    window.open('?ajax=export_dashboard&type=' + type, '_blank');
}