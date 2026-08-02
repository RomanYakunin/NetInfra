/* ============================================================
   modules/nodes_page/nodes_page_pagination.js
   Таблица узлов: пагинация, фильтр по зданию, поиск.

   Состояние хранится в window.nodesTableState, поэтому фильтр,
   поиск и выбор «записей на странице» не сбрасывают друг друга.
   ============================================================ */
(function () {
    'use strict';

    window.nodesTableState = {
        buildingId: 0,
        search: '',
        page: 1,
        perPage: 10
    };

    /**
     * Загружает узлы с сервера и перерисовывает таблицу + пагинацию.
     * @param {number|null} page    Номер страницы (null — текущая)
     * @param {number|null} perPage Записей на странице (null — текущее значение)
     */
    async function loadNodesTable(page = null, perPage = null) {
        const st = window.nodesTableState;
        if (page !== null) st.page = page;
        if (perPage !== null) st.perPage = perPage;

        const tableBody = document.querySelector('#nodesTable tbody');
        if (!tableBody) return;

        const params = new URLSearchParams({ page: st.page, per_page: st.perPage });
        if (st.buildingId) params.set('building_id', st.buildingId);
        if (st.search) params.set('search', st.search);

        try {
            const response = await fetch('?ajax=get_nodes_list&' + params.toString());
            const data = await response.json();

            // При переданном per_page сервер отдаёт объект с метаданными
            const nodes      = Array.isArray(data) ? data : (data.nodes || []);
            const total      = Array.isArray(data) ? nodes.length : (data.total ?? nodes.length);
            const curPage    = Array.isArray(data) ? 1 : (data.page ?? st.page);
            const totalPages = Array.isArray(data) ? 1 : (data.total_pages ?? 1);
            const whMatch    = Array.isArray(data) ? null : data.warehouse_match;
            st.page = curPage;

            renderNodesRows(nodes, tableBody, whMatch);
            renderPagination(total, curPage, st.perPage, totalPages);
        } catch (err) {
            console.error('Ошибка загрузки узлов:', err);
            if (typeof showToast === 'function') showToast('Ошибка загрузки списка узлов', 'error');
        }
    }

    /** Отрисовка строк таблицы узлов. */
    function renderNodesRows(nodes, tableBody, warehouseMatch) {
        tableBody.innerHTML = '';

        if (!nodes.length) {
            const td = document.createElement('td');
            td.colSpan = 10;
            td.style.cssText = 'text-align:center; padding:2rem;';
            if (warehouseMatch) {
                // Искомое устройство нашлось не в узлах, а на складе
                const display = warehouseMatch.hostname || warehouseMatch.serial_number || warehouseMatch.mac_address;
                td.textContent = `Устройство (${display}) находится на складе «${warehouseMatch.warehouse}»`;
            } else {
                td.textContent = 'Ничего не найдено';
            }
            const tr = document.createElement('tr');
            tr.appendChild(td);
            tableBody.appendChild(tr);
            return;
        }

        nodes.forEach(node => {
            const nid = node.id_node;
            const status = node.status || 'inactive';
            const dotClass = status === 'active' ? 'active' : (status === 'partial' ? 'partial' : 'inactive');
            const statusText = status === 'active' ? 'Активен' : (status === 'partial' ? 'Частично' : 'Не активен');
            const kyDisplay = node.KY_number ? 'КУ-' + node.KY_number : '—';
            const deviceCount = node.device_count || 0;

            const tr = document.createElement('tr');
            tr.className = 'data-row';
            tr.setAttribute('data-node-id', nid);
            tr.setAttribute('data-status', status);
            tr.setAttribute('data-ky', node.KY_number || 0);
            tr.setAttribute('data-location', node.location_display || '');
            tr.setAttribute('data-nodetype', node.node_type_name || '');
            tr.setAttribute('data-devicecount', deviceCount);
            tr.onclick = function () { toggleNodeEquipment(this, nid); };
            tr.oncontextmenu = function (e) {
                if (typeof showNodeContextMenu === 'function') {
                    showNodeContextMenu(e, nid, node.KY_number || '');
                }
            };

            const tdStatus = document.createElement('td');
            tdStatus.innerHTML = '<span class="blink-dot ' + dotClass + '"></span> ' + statusText;
            tr.appendChild(tdStatus);

            [kyDisplay, node.location_display || '', node.node_type_name || ''].forEach(val => {
                const td = document.createElement('td');
                td.textContent = val;
                tr.appendChild(td);
            });

            const tdCount = document.createElement('td');
            tdCount.innerHTML = '<span class="equipment-count"></span>';
            tdCount.querySelector('.equipment-count').textContent = deviceCount + ' шт.';
            tr.appendChild(tdCount);

            const tdArrow = document.createElement('td');
            tdArrow.className = 'expand-cell';
            tdArrow.innerHTML = '<span class="expand-arrow">▶</span>';
            tr.appendChild(tdArrow);

            tableBody.appendChild(tr);

            // Строка-контейнер для вложенной таблицы оборудования
            const detailRow = document.createElement('tr');
            detailRow.className = 'equipment-detail-row';
            detailRow.id = 'equip-row-' + nid;
            const detailTd = document.createElement('td');
            detailTd.colSpan = 10;
            const container = document.createElement('div');
            container.className = 'nested-container';
            container.id = 'equip-container-' + nid;
            detailTd.appendChild(container);
            detailRow.appendChild(detailTd);
            tableBody.appendChild(detailRow);
        });
    }

    /** Рисует блок пагинации: «Всего записей», стрелки и номера страниц. */
    function renderPagination(total, page, perPage, totalPages) {
        const el = document.getElementById('nodesPagination');
        if (!el) return;

        totalPages = totalPages || Math.max(1, Math.ceil(total / perPage));

        let html = '<span class="page-info">Всего записей: ' + total + '</span>';

        if (totalPages > 1) {
            const btn = (p, label, disabled, active) =>
                '<button type="button" class="page-btn' + (active ? ' active' : '') +
                '" data-page="' + p + '"' + (disabled ? ' disabled' : '') + '>' + label + '</button>';

            html += '<span class="page-controls">';
            html += btn(page - 1, '‹ Предыдущая', page <= 1, false);

            // Окно из пяти номеров вокруг текущей страницы
            const from = Math.max(1, page - 2);
            const to = Math.min(totalPages, from + 4);
            if (from > 1) {
                html += btn(1, '1', false, false);
                if (from > 2) html += '<span class="page-gap">…</span>';
            }
            for (let p = from; p <= to; p++) html += btn(p, String(p), false, p === page);
            if (to < totalPages) {
                if (to < totalPages - 1) html += '<span class="page-gap">…</span>';
                html += btn(totalPages, String(totalPages), false, false);
            }

            html += btn(page + 1, 'Следующая ›', page >= totalPages, false);
            html += '<span class="page-current">стр. ' + page + ' из ' + totalPages + '</span>';
            html += '</span>';
        }

        el.innerHTML = html;

        el.querySelectorAll('.page-btn').forEach(b => {
            b.addEventListener('click', () => {
                const p = parseInt(b.dataset.page, 10);
                if (p >= 1 && p <= totalPages && p !== page) loadNodesTable(p);
            });
        });
    }

    // filterByBuilding / searchNodes / toggleBuildingsSidebar намеренно НЕ
    // определяются здесь: они живут в nodes_page.js (там же хранятся
    // currentBuildingFilter и currentBuildingName) и делегируют загрузку
    // в loadNodesTable(). Дублировать их нельзя — nodes_page.js грузится
    // позже и перекрыл бы объявления из этого файла.

    // ---------- Инициализация ----------
    document.addEventListener('DOMContentLoaded', () => {
        const perPageSelect = document.getElementById('nodesPerPage');
        if (perPageSelect) {
            perPageSelect.value = String(window.nodesTableState.perPage);
            perPageSelect.addEventListener('change', () => {
                const val = parseInt(perPageSelect.value, 10) || 10;
                loadNodesTable(1, val);   // при смене размера страницы — на первую
            });
        }

        // Первая загрузка через AJAX, чтобы серверная разметка и пагинация совпали
        if (document.querySelector('#nodesTable tbody')) {
            loadNodesTable(1);
        }
    });

    // ---------- Экспорт (используются из разметки и других модулей) ----------
    window.loadNodesTable = loadNodesTable;
    window.renderPagination = renderPagination;
    window.renderNodesRows = renderNodesRows;
})();
