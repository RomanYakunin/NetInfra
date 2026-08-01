// modules/common/table_sort.js
// Глобальная функция, вызываемая из onclick заголовков таблицы узлов

function sortNodesBy(field, order) {
    const table = document.getElementById('nodesTable');
    if (!table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr.data-row'));
    if (rows.length < 2) return;

    // Если направление не передано – переключаем
    if (!order) {
        const header = table.querySelector(`th[onclick*="'${field}'"]`);
        if (header) {
            const icon = header.querySelector('.sort-icon');
            const currentOrder = icon ? icon.textContent.trim() : 'asc';
            order = (currentOrder === '↑') ? 'desc' : 'asc';
        } else {
            order = 'asc';
        }
    }

    // Обновляем стрелки в заголовках
    const headers = table.querySelectorAll('th[onclick]');
    headers.forEach(h => {
        const icon = h.querySelector('.sort-icon');
        if (!icon) return;
        const onclickAttr = h.getAttribute('onclick') || '';
        const headerField = onclickAttr.match(/'([^']+)'/)?.[1];
        if (headerField === field) {
            icon.textContent = order === 'asc' ? '↑' : '↓';
        } else {
            icon.textContent = '↕';
        }
    });

    // Сортируем
    rows.sort((a, b) => {
        let aVal, bVal;
        switch (field) {
            case 'status':
                const orderMap = { 'active': 1, 'partial': 2, 'inactive': 3 };
                aVal = orderMap[a.dataset.status] || 3;
                bVal = orderMap[b.dataset.status] || 3;
                break;
            case 'ky':
                aVal = parseInt(a.dataset.ky, 10) || 0;
                bVal = parseInt(b.dataset.ky, 10) || 0;
                break;
            case 'location':
                aVal = a.dataset.location || '';
                bVal = b.dataset.location || '';
                return (order === 'asc' ? 1 : -1) * aVal.localeCompare(bVal, 'ru');
            case 'nodetype':
                aVal = a.dataset.nodetype || '';
                bVal = b.dataset.nodetype || '';
                return (order === 'asc' ? 1 : -1) * aVal.localeCompare(bVal, 'ru');
            case 'devicecount':
                aVal = parseInt(a.dataset.devicecount, 10) || 0;
                bVal = parseInt(b.dataset.devicecount, 10) || 0;
                break;
            default:
                return 0;
        }
        return order === 'asc' ? aVal - bVal : bVal - aVal;
    });

    // Перемещаем строки вместе с их детальными строками оборудования
    rows.forEach(row => {
        const nodeId = row.dataset.nodeId;
        const detailRow = document.getElementById('equip-row-' + nodeId);
        tbody.appendChild(row);
        if (detailRow) tbody.appendChild(detailRow);
    });
}