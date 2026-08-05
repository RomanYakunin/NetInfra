/* ============================================================
   modules/monitoring/monitoring.js
   Страница «Панель»: активные проблемы из Zabbix.

   Данные берутся из мониторинга, а не опросом по SNMP: ACL на
   коммутаторах пускает только сервер Zabbix, и он эти OID уже
   опрашивает. Плюс здесь есть история и триггеры, которых у
   прямого опроса нет.
   ============================================================ */
(function () {
    'use strict';

    const SEVERITIES = [
        { id: 5, label: 'Чрезвычайная',       color: '#e45959' },
        { id: 4, label: 'Высокая',            color: '#e97659' },
        { id: 3, label: 'Средняя',            color: '#ffa059' },
        { id: 2, label: 'Предупреждение',     color: '#ffc859' },
        { id: 1, label: 'Информация',         color: '#7499ff' },
        { id: 0, label: 'Не классифицировано', color: '#97aab3' },
    ];

    const REFRESH_MS = 30000;

    let activeSeverities = new Set();   // пусто — показываем все
    let refreshTimer = null;
    let lastCounts = null;
    let inFlight = false;

    function esc(s) {
        if (s === null || s === undefined) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /** «2 ч 14 мин», «45 с» — как в списке проблем Zabbix. */
    function humanDuration(sec) {
        sec = Math.max(0, parseInt(sec, 10) || 0);
        const d = Math.floor(sec / 86400);
        const h = Math.floor((sec % 86400) / 3600);
        const m = Math.floor((sec % 3600) / 60);
        if (d) return d + ' сут ' + h + ' ч';
        if (h) return h + ' ч ' + m + ' мин';
        if (m) return m + ' мин';
        return sec + ' с';
    }

    function formatTime(unix) {
        const dt = new Date(unix * 1000);
        const pad = n => String(n).padStart(2, '0');
        return {
            date: pad(dt.getDate()) + '.' + pad(dt.getMonth() + 1) + '.' + dt.getFullYear(),
            time: pad(dt.getHours()) + ':' + pad(dt.getMinutes()) + ':' + pad(dt.getSeconds()),
        };
    }

    // ---------- Плитки уровней важности ----------
    function renderTiles(counts) {
        const wrap = document.getElementById('monSeverityTiles');
        if (!wrap) return;
        wrap.innerHTML = '';

        SEVERITIES.forEach(s => {
            const count = (counts && counts[s.id]) || 0;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mon-tile'
                + (activeSeverities.has(s.id) ? ' active' : '')
                + (count === 0 ? ' zero' : '');
            btn.style.setProperty('--tile-color', s.color);
            btn.dataset.severity = s.id;
            btn.innerHTML = '<span class="mon-tile-count">' + count + '</span>'
                          + '<span class="mon-tile-label">' + esc(s.label) + '</span>';
            btn.addEventListener('click', () => {
                if (activeSeverities.has(s.id)) activeSeverities.delete(s.id);
                else activeSeverities.add(s.id);
                load();
            });
            wrap.appendChild(btn);
        });
    }

    // ---------- Сообщения о состоянии ----------
    function showState(kind, title, html) {
        const el = document.getElementById('monState');
        if (!el) return;
        el.className = 'mon-state ' + kind;
        el.innerHTML = '<div class="mon-state-title">' + esc(title) + '</div>' + html;
        el.style.display = '';
    }
    function hideState() {
        const el = document.getElementById('monState');
        if (el) el.style.display = 'none';
    }

    function setBody(html) {
        const b = document.getElementById('monTableBody');
        if (b) b.innerHTML = html;
    }

    // ---------- Загрузка ----------
    async function load() {
        if (inFlight) return;
        inFlight = true;

        const search = (document.getElementById('monSearch') || {}).value || '';
        const unack = document.getElementById('monUnackOnly');
        const params = new URLSearchParams({ ajax: 'zabbix_problems', search: search.trim() });
        if (activeSeverities.size) params.set('severities', Array.from(activeSeverities).join(','));
        if (unack && unack.checked) params.set('unack', '1');

        try {
            const resp = await fetch('?' + params.toString());
            const data = await resp.json();

            if (!data.success) {
                renderTiles(lastCounts);
                if (data.disabled) {
                    showState('warning', 'Интеграция с Zabbix выключена',
                        '<p>Заполните <code>config/zabbix.php</code>: адрес API, учётную запись, '
                      + 'и переключите <code>enabled</code> в <code>true</code>.</p>'
                      + '<p>Для Zabbix 5.0 постоянных токенов нет — нужны логин и пароль '
                      + 'сервисной учётной записи с правом чтения нужных групп узлов.</p>');
                } else {
                    showState('error', 'Не удалось получить данные', '<p>' + esc(data.error || '') + '</p>');
                }
                setBody('<tr><td colspan="7" class="mon-empty">Нет данных</td></tr>');
                document.getElementById('monFooter').textContent = '';
                return;
            }

            hideState();
            lastCounts = data.counts;
            renderTiles(data.counts);
            renderProblems(data);
        } catch (e) {
            showState('error', 'Ошибка сети', '<p>Страница не смогла обратиться к серверу.</p>');
            if (typeof showToast === 'function') showToast('Ошибка загрузки проблем', 'error');
        } finally {
            inFlight = false;
            const upd = document.getElementById('monUpdated');
            if (upd) {
                const t = formatTime(Math.floor(Date.now() / 1000));
                upd.textContent = 'Обновлено в ' + t.time;
            }
        }
    }

    function renderProblems(data) {
        const rows = data.data || [];
        if (!rows.length) {
            setBody('<tr><td colspan="7" class="mon-empty">Активных проблем нет</td></tr>');
            document.getElementById('monFooter').textContent =
                'Zabbix ' + (data.version || '') + ' · проблем не найдено';
            return;
        }

        const sevById = {};
        SEVERITIES.forEach(s => { sevById[s.id] = s; });

        let html = '';
        rows.forEach(p => {
            const s = sevById[p.severity] || SEVERITIES[5];
            const t = formatTime(p.clock);

            const tags = (p.tags || []).slice(0, 4).map(tag =>
                '<span class="mon-tag">' + esc(tag.tag + (tag.value ? ': ' + tag.value : '')) + '</span>'
            ).join('');

            // Ссылка в досье появляется только если узел Zabbix
            // сопоставился с нашим оборудованием по IP
            const equip = p.equipment_id
                ? '<a href="#" class="mon-link" data-equipment="' + p.equipment_id + '">'
                  + (p.ky_number ? 'КУ-' + esc(p.ky_number) : 'Досье') + ' →</a>'
                  + (p.ip_address ? '<br><small>' + esc(p.ip_address) + '</small>' : '')
                : '<span class="mon-nomatch">нет в учёте</span>';

            html += '<tr data-sev="' + p.severity + '">'
                 + '<td class="mon-time">' + t.time + '<small>' + t.date + '</small></td>'
                 + '<td><span class="mon-sev-badge" style="background:' + s.color + '">'
                     + esc(s.label) + '</span></td>'
                 + '<td class="mon-host">' + esc(p.host_name || '—') + '</td>'
                 + '<td>' + esc(p.name) + (tags ? '<div class="mon-tags">' + tags + '</div>' : '') + '</td>'
                 + '<td class="mon-duration">' + humanDuration(p.duration_sec) + '</td>'
                 + '<td class="mon-ack ' + (p.acknowledged ? 'yes">Да' : 'no">Нет') + '</td>'
                 + '<td>' + equip + '</td>'
                 + '</tr>';
        });
        setBody(html);

        // Переход в досье оборудования прямо из аварии
        document.querySelectorAll('#monTableBody .mon-link[data-equipment]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const id = a.dataset.equipment;
                if (typeof showEquipmentDetails === 'function') showEquipmentDetails(id);
                else window.location.href = '?page=nodes&equipment_id=' + id;
            });
        });

        document.getElementById('monFooter').textContent =
            'Всего проблем: ' + data.total
            + ' · сопоставлено с оборудованием: ' + data.matched
            + (data.version ? ' · Zabbix ' + data.version : '');
    }

    // ---------- Автообновление ----------
    function applyAutoRefresh() {
        const cb = document.getElementById('monAutoRefresh');
        if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }
        if (cb && cb.checked) refreshTimer = setInterval(load, REFRESH_MS);
    }

    // ---------- Настройки подключения ----------
    /** Человекочитаемый итог проверки связи. */
    function describeDiagnosis(d) {
        if (!d) return '<p>Нет ответа</p>';
        if (!d.reachable) {
            return '<div class="mon-state-title">Сервер недоступен</div><p>' + esc(d.error) + '</p>';
        }
        if (!d.logged_in) {
            return '<div class="mon-state-title">Сервер отвечает, вход не удался</div>'
                 + '<p>Версия Zabbix: <code>' + esc(d.version) + '</code></p>'
                 + '<p>' + esc(d.error) + '</p>';
        }
        return '<div class="mon-state-title">Связь есть</div>'
             + '<p>Zabbix <code>' + esc(d.version) + '</code>, узлов под мониторингом: <b>'
             + d.hosts + '</b>, активных проблем: <b>' + (d.problems === null ? '—' : d.problems) + '</b></p>';
    }

    function setSettingsResult(kind, html) {
        const el = document.getElementById('zbxSettingsResult');
        if (!el) return;
        el.className = 'mon-state ' + kind;
        el.innerHTML = html;
        el.style.display = '';
    }

    window.openZabbixSettings = async function () {
        const modal = document.getElementById('zabbixSettingsModal');
        if (!modal) return;
        const res = document.getElementById('zbxSettingsResult');
        if (res) res.style.display = 'none';

        try {
            const data = await (await fetch('?ajax=zabbix_settings')).json();
            const c = data.data || {};
            document.getElementById('zbxEnabled').checked = !!c.enabled;
            document.getElementById('zbxUrl').value = c.url || '';
            document.getElementById('zbxUser').value = c.user || '';
            document.getElementById('zbxPassword').value = '';
            document.getElementById('zbxTimeout').value = c.timeout || 10;
            document.getElementById('zbxConnectTimeout').value = c.connect_timeout || 4;
            document.getElementById('zbxVerifySsl').checked = !!c.verify_ssl;
            document.getElementById('zbxPasswordHint').textContent = c.has_password
                ? 'Пароль сохранён. Оставьте поле пустым, чтобы не менять его.'
                : 'Пароль ещё не задан.';
            if (!c.writable) {
                setSettingsResult('error',
                    '<div class="mon-state-title">Файл настроек недоступен для записи</div>'
                  + '<p>Дайте право на запись <code>config/zabbix.php</code>, иначе сохранить не получится.</p>');
            }
        } catch (e) {
            setSettingsResult('error', '<p>Не удалось прочитать текущие настройки</p>');
        }

        if (typeof showModal === 'function') showModal(modal);
        else modal.classList.add('visible');
    };

    window.closeZabbixSettings = function () {
        document.getElementById('zabbixSettingsModal')?.classList.remove('visible');
    };

    function settingsFormData() {
        const fd = new FormData();
        fd.append('enabled', document.getElementById('zbxEnabled').checked ? '1' : '0');
        fd.append('url', document.getElementById('zbxUrl').value.trim());
        fd.append('user', document.getElementById('zbxUser').value.trim());
        fd.append('password', document.getElementById('zbxPassword').value);
        fd.append('timeout', document.getElementById('zbxTimeout').value);
        fd.append('connect_timeout', document.getElementById('zbxConnectTimeout').value);
        fd.append('verify_ssl', document.getElementById('zbxVerifySsl').checked ? '1' : '0');
        return fd;
    }

    async function saveSettings(e) {
        if (e) e.preventDefault();
        setSettingsResult('', '<p>Сохраняю…</p>');
        try {
            const data = await (await fetch('?ajax=zabbix_settings', {
                method: 'POST', body: settingsFormData()
            })).json();

            if (!data.success) {
                setSettingsResult('error', '<p>' + esc(data.error || 'Ошибка сохранения') + '</p>');
                return;
            }
            const d = data.diagnosis || {};
            setSettingsResult(d.logged_in ? 'warning' : 'error',
                '<div class="mon-state-title">Настройки сохранены</div>' + describeDiagnosis(d));
            if (typeof showToast === 'function') showToast('Настройки Zabbix сохранены', 'success');
            load();
        } catch (err) {
            setSettingsResult('error', '<p>Ошибка сети при сохранении</p>');
        }
    }

    /**
     * Проверка связи без сохранения невозможна: клиент читает параметры
     * из файла. Поэтому кнопка проверяет то, что уже сохранено, — и
     * честно об этом предупреждает, если в форме есть несохранённые правки.
     */
    async function testConnection() {
        setSettingsResult('', '<p>Проверяю связь…</p>');
        try {
            const data = await (await fetch('?ajax=zabbix_ping')).json();
            const d = data.data || {};
            setSettingsResult(d.logged_in ? 'warning' : 'error', describeDiagnosis(d)
                + '<p><small>Проверены сохранённые настройки. Изменения в форме учтутся после сохранения.</small></p>');
        } catch (e) {
            setSettingsResult('error', '<p>Ошибка сети при проверке</p>');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!document.querySelector('.mon-page')) return;

        renderTiles(null);
        load();
        applyAutoRefresh();

        document.getElementById('monRefreshBtn')?.addEventListener('click', load);
        document.getElementById('monUnackOnly')?.addEventListener('change', load);
        document.getElementById('monAutoRefresh')?.addEventListener('change', applyAutoRefresh);
        document.getElementById('monSettingsBtn')?.addEventListener('click', () => openZabbixSettings());
        document.getElementById('zabbixSettingsForm')?.addEventListener('submit', saveSettings);
        document.getElementById('zbxTestBtn')?.addEventListener('click', testConnection);

        let searchTimer;
        document.getElementById('monSearch')?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(load, 350);
        });
    });

    // Наружу — чтобы дашборд мог переиспользовать
    window.monitoringReload = load;
})();
