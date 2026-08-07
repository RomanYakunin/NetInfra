/* ============================================================
   modules/scanner/scanner.js

   Сканирование документов и настройки хранилища.

   Сканирование идёт через WIA на стороне сервера, поэтому в списке
   видны сканеры той машины, где работает NetInfra. Браузер к
   TWAIN/WIA доступа не имеет — из страницы напрямую отсканировать
   нельзя ни в одном браузере.
   ============================================================ */
(function () {
    'use strict';

    // Куда привязать полученный документ
    let ctx = { phoneId: null, boxId: null, intoInput: null, onSaved: null };
    // Устройства держим списком: в select кладём индекс, а не идентификатор,
    // потому что у TWAIN идентификатор — это имя источника с пробелами
    let deviceList = [];
    let devicesLoaded = false;

    function esc(s) {
        if (s === null || s === undefined) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') showToast(msg, type);
    }

    function setState(id, kind, html) {
        const el = document.getElementById(id);
        if (!el) return;
        el.className = 'scan-state ' + (kind || '');
        el.innerHTML = html;
        el.style.display = html ? '' : 'none';
    }

    // ---------------- Сканирование ----------------

    /**
     * Открывает окно сканирования.
     * @param {Object} opts phoneId | boxId — к чему привязать, onSaved — колбэк
     */
    window.openScanModal = async function (opts) {
        opts = opts || {};
        const modal = document.getElementById('scanModal');
        if (!modal) return;

        ctx.phoneId = opts.phoneId || null;
        ctx.boxId   = opts.boxId || null;
        // Объекта ещё нет (форма добавления) — скан кладём в поле файла,
        // и он уйдёт на сервер вместе с формой
        ctx.intoInput = opts.intoInput || null;
        ctx.onSaved = typeof opts.onSaved === 'function' ? opts.onSaved : null;

        setState('scanState', '', '');
        document.getElementById('scanProgress').style.display = 'none';
        document.getElementById('scanStartBtn').disabled = false;

        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');

        await loadDevices();
    };

    window.closeScanModal = function () {
        document.getElementById('scanModal')?.classList.remove('visible');
    };

    async function loadDevices() {
        const select = document.getElementById('scanDevice');
        const hint = document.getElementById('scanDeviceHint');
        select.innerHTML = '<option>загрузка…</option>';
        hint.textContent = '';

        try {
            const data = await (await fetch('?ajax=scan_devices')).json();

            if (!data.success) {
                select.innerHTML = '<option value="">—</option>';
                setState('scanState', 'error', '<b>Сканирование недоступно</b><p>' + esc(data.error) + '</p>');
                document.getElementById('scanStartBtn').disabled = true;
                return;
            }

            const devices = data.devices || [];
            if (!devices.length) {
                select.innerHTML = '<option value="">сканеры не найдены</option>';
                setState('scanState', 'warning',
                    '<b>Сканеры не найдены</b><p>' + esc(data.hint) + '</p>');
                document.getElementById('scanStartBtn').disabled = true;
                return;
            }

            select.innerHTML = '';
            devices.forEach((d, i) => {
                // Протокол показываем явно: одно устройство может отвечать
                // и по WIA, и по TWAIN, и вести себя по-разному
                const proto = d.backend === 'naps2' ? 'TWAIN' : 'WIA';
                const label = d.name + (d.port ? ' (' + d.port + ')' : '') + ' · ' + proto;
                const opt = new Option(label, String(i));
                select.appendChild(opt);
            });
            deviceList = devices;
            hint.textContent = 'Видны устройства компьютера, где работает NetInfra';

            const def = data.defaults || {};
            if (def.dpi)    document.getElementById('scanDpi').value = String(def.dpi);
            if (def.color)  document.getElementById('scanColor').value = def.color;
            if (def.format) document.getElementById('scanFormat').value = def.format;

            devicesLoaded = true;
            setState('scanState', '', '');
            document.getElementById('scanStartBtn').disabled = false;
        } catch (e) {
            select.innerHTML = '<option value="">—</option>';
            setState('scanState', 'error', '<p>Не удалось получить список сканеров</p>');
            document.getElementById('scanStartBtn').disabled = true;
        }
    }

    /**
     * Кладёт полученный скан в <input type="file"> формы.
     * Значение такого поля программно задаётся только через DataTransfer —
     * присвоить input.value нельзя, браузер это запрещает.
     */
    function putScanIntoInput(data) {
        const input = ctx.intoInput ? document.getElementById(ctx.intoInput) : null;
        if (!input) return false;

        try {
            const bin = atob(data.content);
            const bytes = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);

            const file = new File([bytes], data.filename, { type: data.mime });
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        } catch (e) {
            return false;
        }
    }

    async function startScan() {
        const btn = document.getElementById('scanStartBtn');
        const progress = document.getElementById('scanProgress');

        btn.disabled = true;
        progress.style.display = '';
        setState('scanState', '', '');

        const fd = new FormData();
        if (ctx.phoneId) fd.append('phone_id', ctx.phoneId);
        if (ctx.boxId)   fd.append('box_id', ctx.boxId);

        const dev = deviceList[parseInt(document.getElementById('scanDevice').value, 10)] || {};
        fd.append('device_id', dev.id || '');
        fd.append('backend',   dev.backend || 'wia');
        fd.append('driver',    dev.driver || 'wia');
        fd.append('dpi',       document.getElementById('scanDpi').value);
        fd.append('color',     document.getElementById('scanColor').value);
        fd.append('format',    document.getElementById('scanFormat').value);
        fd.append('doc_type',  document.getElementById('scanDocType').value);
        fd.append('doc_number', document.getElementById('scanDocNumber').value.trim());
        fd.append('doc_date',   document.getElementById('scanDocDate').value);

        try {
            const data = await (await fetch('?ajax=scan_document', { method: 'POST', body: fd })).json();
            progress.style.display = 'none';
            btn.disabled = false;

            if (data.error) {
                setState('scanState', 'error', '<p>' + esc(data.error) + '</p>');
                return;
            }

            // Форма добавления: объекта ещё нет, поэтому скан пришёл файлом.
            // Кладём его в поле выбора файла — дальше он уйдёт на сервер
            // вместе с формой, тем же путём, что и выбранный вручную.
            if (data.preview) {
                if (!putScanIntoInput(data)) {
                    setState('scanState', 'error',
                        '<p>Скан получен, но подставить его в форму не удалось</p>');
                    return;
                }
                toast('Документ отсканирован и подставлен в форму', 'success');
            } else if (data.duplicate) {
                toast(data.message || 'Такой скан уже был', 'info');
            } else {
                toast('Документ отсканирован и сохранён', 'success');
            }

            const cb = ctx.onSaved;
            window.closeScanModal();
            if (cb) cb(data);
        } catch (e) {
            progress.style.display = 'none';
            btn.disabled = false;
            setState('scanState', 'error', '<p>Ошибка сети при сканировании</p>');
        }
    }

    /**
     * Диагностика: показывает, что сервер знает об устройствах.
     * Нужна потому, что вопрос «почему не видно мой принтер» почти всегда
     * упирается в одно из трёх — устройство подключено к другому
     * компьютеру, у него только TWAIN-драйвер, либо выключено com_dotnet.
     */
    async function showDiagnostics() {
        setState('scanState', '', '<p>Опрашиваю устройства…</p>');
        try {
            const resp = await (await fetch('?ajax=scan_diagnose')).json();
            if (!resp.success) {
                setState('scanState', 'error', '<p>' + esc(resp.error || 'Ошибка') + '</p>');
                return;
            }
            const d = resp.data;
            const list = a => (a && a.length)
                ? '<ul class="scan-diag-list">' + a.map(x => '<li>' + esc(x) + '</li>').join('') + '</ul>'
                : '<p class="scan-diag-none">— нет</p>';

            let html = '<b>Диагностика сканирования</b>';
            html += '<p>Сервер: <code>' + esc(d.server_host) + '</code></p>';
            html += '<p>Расширение com_dotnet: <b>' + (d.com.loaded ? 'включено' : 'выключено') + '</b>'
                 + (d.com.reason ? '<br><small>' + esc(d.com.reason) + '</small>' : '') + '</p>';

            html += '<p>WIA-сканеры:</p>' + list((d.wia.devices || []).map(x => x.name));
            if (d.wia.error) html += '<p><small>' + esc(d.wia.error) + '</small></p>';

            html += '<p>TWAIN через NAPS2: <b>' + (d.naps2.available ? 'доступен' : 'нет утилиты') + '</b>'
                 + (d.naps2.path ? '<br><small>' + esc(d.naps2.path) + '</small>' : '')
                 + (d.naps2.reason ? '<br><small>' + esc(d.naps2.reason) + '</small>' : '') + '</p>';
            html += list((d.naps2.devices || []).map(x => x.name));
            if (d.naps2.error) html += '<p><small>' + esc(d.naps2.error) + '</small></p>';

            html += '<p>Служба WIA: <code>' + esc(d.windows.wia_service || '—') + '</code></p>';
            html += '<p>Принтеры на сервере:</p>' + list(d.windows.printers);
            html += '<p>Устройства обработки изображений:</p>' + list(d.windows.image_devices);
            html += '<p>Файлы TWAIN-источников:</p>' + list(d.windows.twain_sources);

            html += '<p><b>Вывод</b></p>' + list(d.verdict);

            setState('scanState', d.can_scan ? 'warning' : 'error', html);
        } catch (e) {
            setState('scanState', 'error', '<p>Не удалось выполнить диагностику</p>');
        }
    }

    /** Доступно ли сканирование — по этому признаку показываем кнопки. */
    window.scannerAvailable = async function () {
        try {
            const data = await (await fetch('?ajax=scan_devices')).json();
            return !!(data.success && (data.devices || []).length);
        } catch (e) {
            return false;
        }
    };

    // ---------------- Настройки хранилища ----------------

    function renderStorageStatus(st) {
        if (!st) return;
        if (!st.configured) {
            setState('stStatus', 'warning', '<p>Папка хранения не назначена. '
                + 'Пока её нет, документы сохраняются внутри проекта, в uploads/phone_docs.</p>');
            return;
        }
        if (!st.exists) {
            setState('stStatus', 'error', '<b>Папка недоступна</b><p>' + esc(st.error) + '</p>');
            return;
        }
        if (!st.writable) {
            setState('stStatus', 'error', '<b>Нет прав на запись</b><p>' + esc(st.error) + '</p>');
            return;
        }
        const folders = st.folders || [];
        setState('stStatus', 'ok',
            '<b>Папка доступна</b><p><code>' + esc(st.root) + '</code></p>'
            + (folders.length
                ? '<p>Вложенных папок: ' + folders.length + ' — ' + esc(folders.slice(0, 12).join(', '))
                  + (folders.length > 12 ? ' …' : '') + '</p>'
                : '<p>Папка пуста. Нажмите «Создать папки подразделений».</p>'));
    }

    // ---------------- Обзор папок ----------------
    // Системный диалог выбора папки браузеру недоступен: страница не должна
    // узнавать структуру дисков. Поэтому проводник рисуем сами — сервер
    // перечисляет каталоги, пользователь ходит по ним мышью.
    let fbPath = '';        // где сейчас находимся
    let fbWritable = false; // можно ли писать в текущую папку

    window.openFolderBrowser = function () {
        const modal = document.getElementById('folderBrowserModal');
        if (!modal) return;
        setState('fbState', '', '');
        // Стартуем от уже назначенной папки, если она задана
        const current = (document.getElementById('stDocsRoot').value || '').trim();
        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');
        fbLoad(current);
    };

    window.closeFolderBrowser = function () {
        document.getElementById('folderBrowserModal')?.classList.remove('visible');
    };

    async function fbLoad(path) {
        const list = document.getElementById('fbList');
        list.innerHTML = '<div class="fb-empty">Загрузка…</div>';
        setState('fbState', '', '');

        try {
            const data = await (await fetch('?ajax=storage_browse&path='
                + encodeURIComponent(path || ''))).json();

            if (!data.success) {
                list.innerHTML = '<div class="fb-empty">Каталог недоступен</div>';
                setState('fbState', 'error', '<p>' + esc(data.error) + '</p>');
                document.getElementById('fbSelectBtn').disabled = true;
                return;
            }

            fbPath = data.path || '';
            fbWritable = !!data.writable;
            document.getElementById('fbPath').value = fbPath;
            document.getElementById('fbUpBtn').disabled = !data.parent && !fbPath;
            // Диски выбирать нельзя — только каталог внутри
            document.getElementById('fbSelectBtn').disabled = fbPath === '';

            const items = data.is_root
                ? (data.drives || []).map(d => ({ ...d, icon: '💽' }))
                : (data.folders || []).map(f => ({ ...f, icon: '📁' }));

            if (!items.length) {
                list.innerHTML = '<div class="fb-empty">Вложенных папок нет'
                    + (fbPath ? ' — можно выбрать эту' : '') + '</div>';
            } else {
                list.innerHTML = '';
                items.forEach(it => {
                    const row = document.createElement('div');
                    row.className = 'fb-item';
                    row.innerHTML = '<span class="fb-icon">' + it.icon + '</span>'
                        + '<span class="fb-name">' + esc(it.name) + '</span>'
                        + (it.writable ? '' : '<span class="fb-ro">только чтение</span>');
                    row.onclick = () => fbLoad(it.path);
                    list.appendChild(row);
                });
            }

            if (fbPath && !fbWritable) {
                setState('fbState', 'warning',
                    '<p>В эту папку веб-сервер писать не может. Выбрать её можно, '
                  + 'но сохранить документ не получится.</p>');
            }
        } catch (e) {
            list.innerHTML = '<div class="fb-empty">Ошибка загрузки</div>';
            setState('fbState', 'error', '<p>Не удалось получить список папок</p>');
        }
    }

    async function fbMkdir() {
        if (!fbPath) {
            setState('fbState', 'warning', '<p>Сначала зайдите в папку, где создать новую</p>');
            return;
        }
        const name = prompt('Название новой папки:');
        if (!name) return;

        const fd = new FormData();
        fd.append('name', name);
        try {
            const data = await (await fetch('?ajax=storage_browse&action=mkdir&path='
                + encodeURIComponent(fbPath), { method: 'POST', body: fd })).json();
            if (!data.success) {
                setState('fbState', 'error', '<p>' + esc(data.error) + '</p>');
                return;
            }
            toast(data.existed ? 'Такая папка уже есть' : 'Папка создана', 'success');
            fbLoad(fbPath);
        } catch (e) {
            setState('fbState', 'error', '<p>Ошибка сети</p>');
        }
    }

    window.openStorageSettings = async function () {
        const modal = document.getElementById('storageSettingsModal');
        if (!modal) return;
        setState('stStatus', '', '');

        try {
            const data = await (await fetch('?ajax=storage_settings')).json();
            const c = data.data || {};
            document.getElementById('stDocsRoot').value = c.docs_root || '';
            document.getElementById('stUseDepartments').checked = !!c.use_departments;
            document.getElementById('stUnsorted').value = c.unsorted_folder || '';
            document.getElementById('stScanEnabled').checked = !!c.scan_enabled;
            document.getElementById('stScanReason').textContent =
                c.scan_available ? '' : (c.scan_reason || '');
            renderStorageStatus(c.status);
        } catch (e) {
            setState('stStatus', 'error', '<p>Не удалось прочитать настройки</p>');
        }

        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');
    };

    window.closeStorageSettings = function () {
        document.getElementById('storageSettingsModal')?.classList.remove('visible');
    };

    async function saveStorage() {
        const fd = new FormData();
        fd.append('docs_root', document.getElementById('stDocsRoot').value.trim());
        fd.append('use_departments', document.getElementById('stUseDepartments').checked ? '1' : '0');
        fd.append('unsorted_folder', document.getElementById('stUnsorted').value.trim());
        fd.append('scan_enabled', document.getElementById('stScanEnabled').checked ? '1' : '0');

        setState('stStatus', '', '<p>Сохраняю…</p>');
        try {
            const data = await (await fetch('?ajax=storage_settings', { method: 'POST', body: fd })).json();
            if (!data.success) {
                setState('stStatus', 'error', '<p>' + esc(data.error) + '</p>');
                return;
            }
            toast('Настройки хранилища сохранены', 'success');
            renderStorageStatus(data.status);
        } catch (e) {
            setState('stStatus', 'error', '<p>Ошибка сети</p>');
        }
    }

    async function createFolders() {
        setState('stStatus', '', '<p>Создаю папки…</p>');
        const fd = new FormData();
        fd.append('action', 'create_folders');
        try {
            const data = await (await fetch('?ajax=storage_settings', { method: 'POST', body: fd })).json();
            if (!data.success) {
                setState('stStatus', 'error', '<p>' + esc(data.error) + '</p>');
                return;
            }
            const c = data.created || [], ex = data.existed || [], f = data.failed || [];
            let html = '<b>Готово</b><p>Создано: ' + c.length + ', уже было: ' + ex.length;
            if (f.length) html += ', не удалось: ' + f.length;
            html += '</p>';
            if (c.length) html += '<p>' + esc(c.join(', ')) + '</p>';
            if (f.length) html += '<p>Не удалось создать: ' + esc(f.join(', ')) + '</p>';
            setState('stStatus', f.length ? 'warning' : 'ok', html);
            toast('Папки подразделений созданы', 'success');
        } catch (e) {
            setState('stStatus', 'error', '<p>Ошибка сети</p>');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('scanStartBtn')?.addEventListener('click', startScan);
        document.getElementById('scanDiagBtn')?.addEventListener('click', showDiagnostics);
        document.getElementById('stSaveBtn')?.addEventListener('click', saveStorage);

        // Обзор папок
        document.getElementById('stBrowseBtn')?.addEventListener('click', () => openFolderBrowser());
        document.getElementById('fbUpBtn')?.addEventListener('click', async () => {
            const data = await (await fetch('?ajax=storage_browse&path='
                + encodeURIComponent(fbPath))).json();
            fbLoad(data.parent || '');
        });
        document.getElementById('fbGoBtn')?.addEventListener('click',
            () => fbLoad(document.getElementById('fbPath').value.trim()));
        document.getElementById('fbPath')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); fbLoad(e.target.value.trim()); }
        });
        document.getElementById('fbMkdirBtn')?.addEventListener('click', fbMkdir);
        document.getElementById('fbSelectBtn')?.addEventListener('click', () => {
            // Путь показываем в привычном для Windows виде
            document.getElementById('stDocsRoot').value = fbPath.replace(/\//g, '\\');
            closeFolderBrowser();
        });
        document.getElementById('stCreateFoldersBtn')?.addEventListener('click', createFolders);

        // Клик по затемнению закрывает окно
        ['scanModal', 'storageSettingsModal'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', function (e) {
                if (e.target === this) this.classList.remove('visible');
            });
        });
    });
})();
