// modules/common/acl_client.js
// Клиентская часть прав доступа. Сервер — единственный источник истины
// (includes/acl.php), здесь только прячем то, чем пользователь всё равно
// не сможет воспользоваться.
(function () {
    'use strict';

    // window.isAdmin выставляется в index.php из $_SESSION['role']
    const isAdmin = window.isAdmin === true;

    /** Разрешено ли текущему пользователю изменять данные. */
    window.canEdit = function () { return isAdmin; };

    /**
     * Фильтр пунктов контекстного меню.
     * Пользователю оставляем только просмотр.
     */
    const VIEWER_ACTIONS = ['detailed', 'show_rack'];
    window.filterContextItems = function (items) {
        if (isAdmin) return items;
        return (items || []).filter(i => VIEWER_ACTIONS.includes(i.action));
    };

    if (isAdmin) return;   // администратору ничего скрывать не нужно

    // Селекторы элементов, доступных только администратору
    const ADMIN_ONLY_SELECTORS = [
        '#addNodeBtn',
        '#addUserBtn',
        '#addWarehouseBtn',
        '#add-rack-tile',
        '.rack-tile-add',
        '.add-module-tile',
        '[data-admin-only]'
    ];

    function hideAdminControls(root) {
        const scope = root && root.querySelectorAll ? root : document;
        ADMIN_ONLY_SELECTORS.forEach(sel => {
            scope.querySelectorAll(sel).forEach(el => { el.style.display = 'none'; });
        });
    }

    /** Делает все поля формы недоступными для редактирования. */
    window.disableFormForViewer = function (formEl) {
        if (!formEl || isAdmin) return;
        formEl.querySelectorAll('input, select, textarea').forEach(el => { el.disabled = true; });
        formEl.querySelectorAll('button[type="submit"]').forEach(el => { el.style.display = 'none'; });
    };

    document.addEventListener('DOMContentLoaded', () => {
        hideAdminControls(document);

        // Формы, открывающиеся динамически, блокируем при появлении
        const observer = new MutationObserver(mutations => {
            for (const m of mutations) {
                // Модалка стала видимой — переводим её в режим просмотра
                if (m.type === 'attributes' && m.target.classList?.contains('visible')) {
                    const form = m.target.querySelector('form');
                    if (form) window.disableFormForViewer(form);
                }
                m.addedNodes?.forEach(node => {
                    if (node.nodeType === 1) hideAdminControls(node);
                });
            }
        });
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
    });
})();
