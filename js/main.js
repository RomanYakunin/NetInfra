const App = {
    currentTab: 'nodes',
    user: null,
    columnPrefs: {},

    async init() {
        await this.loadSession();
        if (this.user) {
            document.getElementById('loginScreen').style.display = 'none';
            document.getElementById('app').style.display = 'flex';
            await this.loadTab(this.currentTab);
        }
    },

    async loadSession() {
        const res = await fetch('/api/auth_api.php');
        if (res.ok) this.user = await res.json();
    },

    async loadTab(tab) {
        this.currentTab = tab;
        // Загрузка настроек столбцов для этой вкладки
        const prefsRes = await fetch(`/api/columns_api.php?table=${tab}`);
        this.columnPrefs[tab] = await prefsRes.json();

        // Загрузка данных
        const dataRes = await fetch(`/api/data_api.php?table=${tab}`);
        const data = await dataRes.json();

        this.renderTable(tab, data);
    },

    renderTable(tab, data) {
        const prefs = this.columnPrefs[tab];
        const visible = prefs.visible_columns;
        const order = prefs.column_order.filter(col => visible.includes(col));

        const thead = document.querySelector('#content thead');
        let html = '<tr>';
        order.forEach(col => html += `<th>${col}</th>`);
        html += '<th></th></tr>';
        thead.innerHTML = html;

        const tbody = document.querySelector('#content tbody');
        html = '';
        data.forEach(row => {
            html += '<tr>';
            order.forEach(col => html += `<td>${row[col] ?? ''}</td>`);
            html += '<td><button class="btn small">+</button></td></tr>';
        });
        tbody.innerHTML = html;
    },

    async addColumn() {
        const name = prompt('Имя столбца:');
        const type = prompt('Тип (VARCHAR(255), INT, TEXT):', 'VARCHAR(255)');
        if (!name) return;
        await fetch('/api/columns_api.php?action=add', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ table: this.currentTab, name, type })
        });
        this.loadTab(this.currentTab);
    },

    async deleteColumn(name) {
        if (!confirm(`Удалить столбец ${name}?`)) return;
        await fetch('/api/columns_api.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ table: this.currentTab, name })
        });
        this.loadTab(this.currentTab);
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());