// =================================================================
// scripts.js – Общие функции управления интерфейсом
// =================================================================

/**
 * Устанавливает тему оформления
 * @param {string} theme - 'dark', 'light' или 'neutral'
 */
function setTheme(theme) {
    // Меняем атрибут data-theme у корневого элемента
    document.documentElement.setAttribute('data-theme', theme);
    // Обновляем активную кнопку в переключателе тем
    document.querySelectorAll('.theme-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.theme === theme);
    });
}

// При загрузке DOM назначаем обработчики на кнопки выбора темы
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.theme-option').forEach(opt => {
        opt.addEventListener('click', () => setTheme(opt.dataset.theme));
    });
});

/**
 * Сворачивает / разворачивает боковое меню
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed'); // Переключаем класс collapsed
    const arrow = document.querySelector('.collapse-btn .arrow');
    // Меняем направление стрелки в зависимости от состояния
    if (sidebar.classList.contains('collapsed')) {
        arrow.textContent = '▶'; // Вправо (меню свёрнуто)
    } else {
        arrow.textContent = '◀'; // Влево (меню раскрыто)
    }
}

/**
 * Выбор пункта меню (навигация)
 * @param {Element} item - выбранный элемент меню
 */
function selectMenuItem(item) {
    // Снимаем класс active со всех пунктов
    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
    // Добавляем active на выбранный пункт
    item.classList.add('active');
    // Если меню раскрыто – сворачиваем его
    if (!document.getElementById('sidebar').classList.contains('collapsed')) {
        toggleSidebar();
    }
}

/**
 * Показывает / скрывает правую панель шкафа
 */
function toggleRightPanel() {
    const panel = document.getElementById('rightPanel');
    if (panel) panel.classList.toggle('hidden');
}

function showMetricContextMenu(event, type) {
    event.preventDefault();
    const menu = document.getElementById('ctxMenu');
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

