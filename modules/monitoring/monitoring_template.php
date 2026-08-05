<?php
// modules/monitoring/monitoring_template.php
// Страница «Панель» — активные проблемы из Zabbix в привычном по
// мониторингу виде: плитки по уровням важности и таблица аварий.
?>
<link rel="stylesheet" href="modules/monitoring/monitoring.css">

<div class="mon-page">
    <div class="mon-toolbar">
        <div class="mon-search">
            <span class="search-icon">🔍</span>
            <input type="text" id="monSearch" placeholder="Поиск по узлу или проблеме..." autocomplete="off">
        </div>

        <label class="mon-check">
            <input type="checkbox" id="monUnackOnly">
            <span>Только неподтверждённые</span>
        </label>

        <label class="mon-check">
            <input type="checkbox" id="monAutoRefresh" checked>
            <span>Автообновление</span>
        </label>

        <button class="btn small" id="monRefreshBtn">⟳ Обновить</button>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <button class="btn small secondary" id="monSettingsBtn" title="Подключение к Zabbix">⚙ Zabbix</button>
        <?php endif; ?>
        <span class="mon-updated" id="monUpdated"></span>
    </div>

    <!-- Плитки по уровням важности: клик фильтрует таблицу -->
    <div class="mon-severity-tiles" id="monSeverityTiles"></div>

    <div class="mon-state" id="monState" style="display:none;"></div>

    <div class="mon-table-wrap">
        <table class="mon-table">
            <thead>
                <tr>
                    <th style="width:150px;">Время</th>
                    <th style="width:150px;">Важность</th>
                    <th style="width:210px;">Узел</th>
                    <th>Проблема</th>
                    <th style="width:110px;">Длительность</th>
                    <th style="width:110px;">Подтверждено</th>
                    <th style="width:160px;">Оборудование</th>
                </tr>
            </thead>
            <tbody id="monTableBody">
                <tr><td colspan="7" class="mon-empty">Загрузка…</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mon-footer" id="monFooter"></div>
</div>

<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<!-- Настройки подключения. Пишутся в config/zabbix.php: пароль в базе
     был бы виден на странице «База знаний», которая листает любые таблицы. -->
<div class="add-form-modal" id="zabbixSettingsModal">
    <div class="modal-content" style="max-width: 560px;">
        <h3>Подключение к Zabbix</h3>
        <form id="zabbixSettingsForm" autocomplete="off">
            <div class="form-group">
                <label class="mon-check" style="font-size:0.9rem;">
                    <input type="checkbox" name="enabled" id="zbxEnabled">
                    <span>Использовать данные Zabbix</span>
                </label>
            </div>

            <div class="form-group">
                <label>Адрес API</label>
                <input type="text" name="url" id="zbxUrl" placeholder="http://10.10.90.90/api_jsonrpc.php">
                <small class="zbx-hint">Если Zabbix установлен в подкаталог, адрес будет
                    <code>/zabbix/api_jsonrpc.php</code></small>
            </div>

            <div class="form-group">
                <label>Учётная запись</label>
                <input type="text" name="user" id="zbxUser" autocomplete="off">
            </div>

            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" id="zbxPassword" autocomplete="new-password">
                <small class="zbx-hint" id="zbxPasswordHint"></small>
            </div>

            <div class="form-row" style="display:flex; gap:0.8rem;">
                <div class="form-group" style="flex:1;">
                    <label>Таймаут ответа, с</label>
                    <input type="number" name="timeout" id="zbxTimeout" min="1" max="120" value="10">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Таймаут связи, с</label>
                    <input type="number" name="connect_timeout" id="zbxConnectTimeout" min="1" max="60" value="4">
                </div>
            </div>

            <div class="form-group">
                <label class="mon-check" style="font-size:0.9rem;">
                    <input type="checkbox" name="verify_ssl" id="zbxVerifySsl">
                    <span>Проверять сертификат (снимите при самоподписанном)</span>
                </label>
            </div>

            <div class="mon-state" id="zbxSettingsResult" style="display:none;"></div>

            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeZabbixSettings()">Отмена</button>
                <button type="button" class="btn secondary" id="zbxTestBtn">Проверить связь</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="modules/monitoring/monitoring.js"></script>
