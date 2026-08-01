// modules/header/ping.js

let vlanPresets = [];

// Загрузка пресетов из localStorage
(function() {
    const saved = localStorage.getItem('vlanPresets');
    if (saved) {
        try { vlanPresets = JSON.parse(saved); } catch(e) { vlanPresets = []; }
    } else {
        vlanPresets = [
            { name: 'Vlan 119', start: '192.168.119.2', end: '192.168.119.254' },
            { name: 'Vlan 114', start: '192.168.114.2', end: '192.168.114.254' },
            { name: 'Vlan 234', start: '10.2.34.2',   end: '10.2.34.254' },
            { name: 'Vlan 231', start: '10.10.231.2', end: '10.10.231.254' }
        ];
        saveVlanPresets();
    }
})();

function saveVlanPresets() {
    localStorage.setItem('vlanPresets', JSON.stringify(vlanPresets));
}

// Открытие/закрытие модалки
function openPingModal() {
    const modal = document.getElementById('pingModal');
    if (!modal) return;
    renderVlanCheckboxes();
    document.getElementById('pingProgress').style.display = 'none';
    document.getElementById('pingResults').style.display = 'none';
    document.getElementById('pingResultsBody').innerHTML = '';
    document.getElementById('pingSaveBtn').style.display = 'none';
    modal.classList.add('visible');
}
function closePingModal() {
    const modal = document.getElementById('pingModal');
    if (modal) modal.classList.remove('visible');
}

// Переключение вкладок
function switchPingTab(tab) {
    document.querySelectorAll('.ping-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.ping-tab-content').forEach(c => c.style.display = 'none');
    const activeTab = document.querySelector(`.ping-tab[data-tab="${tab}"]`);
    if (activeTab) activeTab.classList.add('active');
    const activeContent = document.getElementById(`ping-${tab}`);
    if (activeContent) activeContent.style.display = 'block';
}

// Рендер чекбоксов с плиткой добавления и контекстным меню
function renderVlanCheckboxes() {
    const container = document.getElementById('vlan-checkboxes');
    if (!container) return;
    container.className = 'vlan-tile-group';
    container.innerHTML = vlanPresets.map((p, i) => `
        <label class="vlan-tile-label" oncontextmenu="showVlanContextMenu(event, ${i})">
            <input type="checkbox" value="${i}" class="vlan-tile-checkbox">
            <span class="vlan-tile-content">
                <span class="vlan-tile-name">${p.name}</span>
                <span class="vlan-tile-range">${p.start}–${p.end}</span>
            </span>
        </label>
    `).join('');
    const addBtn = document.createElement('div');
    addBtn.className = 'vlan-tile-label';
    addBtn.innerHTML = '<span class="vlan-tile-content vlan-tile-add" onclick="openAddVlanPresetForm()"><span>+</span></span>';
    container.appendChild(addBtn);
}

// Контекстное меню для VLAN
function showVlanContextMenu(e, index) {
    e.preventDefault();
    const menu = document.getElementById('vlanContextMenu');
    if (!menu) return;
    menu.innerHTML = `
        <div class="menu-item" onclick="editVlanPreset(${index}); document.getElementById('vlanContextMenu').style.display='none'">✏️ Редактировать</div>
        <div class="menu-item" onclick="deleteVlanPreset(${index}); document.getElementById('vlanContextMenu').style.display='none'">🗑️ Удалить</div>
    `;
    menu.style.display = 'block';
    menu.style.left = e.clientX + 'px';
    menu.style.top = e.clientY + 'px';
}
document.addEventListener('click', () => {
    const menu = document.getElementById('vlanContextMenu');
    if (menu) menu.style.display = 'none';
});

function editVlanPreset(index) {
    openAddVlanPresetForm(vlanPresets[index]);
}
function deleteVlanPreset(index) {
    vlanPresets.splice(index, 1);
    saveVlanPresets();
    renderVlanCheckboxes();
}

// Форма добавления/редактирования пресета
function openAddVlanPresetForm(preset = null) {
    const modal = document.getElementById('addVlanPresetModal');
    const form = document.getElementById('addVlanPresetForm');
    if (!modal || !form) return;
    form.reset();
    if (preset) {
        form.preset_name.value = preset.name;
        form.start_ip.value = preset.start;
        form.end_ip.value = preset.end;
        form.dataset.editIndex = vlanPresets.indexOf(preset);
    } else {
        form.dataset.editIndex = -1;
    }
    modal.classList.add('visible');
}
function closeAddVlanPresetForm() {
    const modal = document.getElementById('addVlanPresetModal');
    if (modal) modal.classList.remove('visible');
    document.getElementById('addVlanPresetForm').reset();
}

document.getElementById('addVlanPresetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const name = this.preset_name.value.trim();
    const start = this.start_ip.value.trim();
    const end = this.end_ip.value.trim();
    if (!name || !start || !end) return;
    const index = parseInt(this.dataset.editIndex);
    if (index >= 0) {
        vlanPresets[index] = { name, start, end };
    } else {
        vlanPresets.push({ name, start, end });
    }
    saveVlanPresets();
    renderVlanCheckboxes();
    closeAddVlanPresetForm();
});

// Вспомогательные функции IP
function ip2long(ip) {
    const parts = ip.split('.');
    return ((+parts[0] * 256 + +parts[1]) * 256 + +parts[2]) * 256 + +parts[3];
}
function long2ip(long) {
    return (long >>> 24) + '.' + (long >> 16 & 255) + '.' + (long >> 8 & 255) + '.' + (long & 255);
}

// Запуск пинга
let pingResultsData = [];

async function startPing(ipList) {
    const progressDiv = document.getElementById('pingProgress');
    const progressBar = document.getElementById('pingProgressBar');
    const progressText = document.getElementById('pingProgressText');
    const resultsDiv = document.getElementById('pingResults');
    const tbody = document.getElementById('pingResultsBody');
    const saveBtn = document.getElementById('pingSaveBtn');

    progressDiv.style.display = 'block';
    resultsDiv.style.display = 'block';
    tbody.innerHTML = '';
    saveBtn.style.display = 'none';
    pingResultsData = [];

    const chunkSize = 25;
    for (let i = 0; i < ipList.length; i += chunkSize) {
        const chunk = ipList.slice(i, i + chunkSize);
        progressText.textContent = `Пингуется ${i + 1}–${Math.min(i + chunkSize, ipList.length)} из ${ipList.length}`;
        progressBar.value = (i / ipList.length) * 100;

        try {
            const resp = await fetch('?ajax=ping_worker', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ips: chunk })
            });
            const data = await resp.json();
            if (data.success) {
                data.results.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${r.ip}</td>
                        <td><span class="poe-indicator ${r.alive ? 'active' : 'inactive'}"></span> ${r.alive ? 'Активен' : 'Неактивен'}</td>
                        <td>${r.time || '-'}</td>
                    `;
                    tbody.appendChild(tr);
                    pingResultsData.push(r);
                });
                if (typeof updateHeaderStats === 'function') {
                    updateHeaderStats(data.stats);
                }
            }
        } catch (err) {
            console.error('Ошибка при пинге чанка:', err);
        }
    }

    progressBar.value = 100;
    progressText.textContent = `Готово: ${ipList.length} IP проверено`;
    saveBtn.style.display = 'inline-block';
}

// Сохранение результатов в БД
async function savePingResults() {
    const saveBtn = document.getElementById('pingSaveBtn');
    if (!saveBtn) return;
    saveBtn.disabled = true;

    try {
        const resp = await fetch('?ajax=save_ping_results', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ results: pingResultsData })
        });
        const data = await resp.json();
        if (data.success) {
            showToast('Статусы обновлены', 'success');
            saveBtn.style.display = 'none';
            if (typeof updateHeaderStats === 'function') {
                updateHeaderStats(data.stats);
            }
        } else {
            showToast('Ошибка сохранения', 'error');
        }
    } catch (err) {
        showToast('Ошибка сети', 'error');
    }
    saveBtn.disabled = false;
}

// Обработчики кнопок
document.getElementById('startPingPresets')?.addEventListener('click', () => {
    const checkboxes = document.querySelectorAll('.vlan-tile-checkbox:checked');
    if (checkboxes.length === 0) { alert('Выберите подсети'); return; }
    const ips = [];
    checkboxes.forEach(cb => {
        const p = vlanPresets[cb.value];
        const start = ip2long(p.start);
        const end = ip2long(p.end);
        for (let i = start; i <= end; i++) ips.push(long2ip(i));
    });
    startPing(ips);
});

document.getElementById('startPingRange')?.addEventListener('click', () => {
    const start = document.getElementById('pingStartIp').value.trim();
    const end = document.getElementById('pingEndIp').value.trim();
    if (!start || !end) return;
    const startLong = ip2long(start);
    const endLong = ip2long(end);
    if (startLong === false || endLong === false || startLong > endLong) { alert('Неверный диапазон'); return; }
    const ips = [];
    for (let i = startLong; i <= endLong; i++) ips.push(long2ip(i));
    startPing(ips);
});

document.getElementById('startPingSingle')?.addEventListener('click', () => {
    const ip = document.getElementById('pingSingleIp').value.trim();
    if (!ip) return;
    startPing([ip]);
});