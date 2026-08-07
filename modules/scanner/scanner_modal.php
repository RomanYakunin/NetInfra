<!-- modules/scanner/scanner_modal.php -->
<!-- Окно сканирования и настроек хранилища документов.
     Подключается один раз на страницу: обе формы нужны и в карточке
     телефона, и в карточке коробки. -->

<div class="add-form-modal" id="scanModal">
    <div class="modal-content" style="max-width: 560px;">
        <h3>Отсканировать документ</h3>

        <div class="scan-state" id="scanState" style="display:none;"></div>

        <div class="form-group">
            <label>Сканер</label>
            <select id="scanDevice"></select>
            <small class="scan-hint" id="scanDeviceHint"></small>
        </div>

        <div class="scan-row">
            <div class="form-group">
                <label>Разрешение</label>
                <select id="scanDpi">
                    <option value="150">150 dpi — черновик</option>
                    <option value="200">200 dpi</option>
                    <option value="300" selected>300 dpi — документы</option>
                    <option value="600">600 dpi — мелкий текст</option>
                </select>
            </div>
            <div class="form-group">
                <label>Цветность</label>
                <select id="scanColor">
                    <option value="color">Цветной</option>
                    <option value="gray">Оттенки серого</option>
                    <option value="bw">Чёрно-белый</option>
                </select>
            </div>
            <div class="form-group">
                <label>Формат</label>
                <select id="scanFormat">
                    <option value="jpeg">JPEG</option>
                    <option value="png">PNG</option>
                    <option value="tiff">TIFF</option>
                </select>
            </div>
        </div>

        <div class="scan-row">
            <div class="form-group">
                <label>Тип документа</label>
                <select id="scanDocType">
                    <option value="накладная">накладная</option>
                    <option value="расписка">расписка</option>
                    <option value="акт">акт</option>
                    <option value="прочее">прочее</option>
                </select>
            </div>
            <div class="form-group">
                <label>№ документа</label>
                <input type="text" id="scanDocNumber">
            </div>
            <div class="form-group">
                <label>Дата документа</label>
                <input type="date" id="scanDocDate">
            </div>
        </div>

        <div class="scan-progress" id="scanProgress" style="display:none;">
            <span class="scan-spinner"></span>
            <span>Идёт сканирование. Не открывайте крышку до конца обработки…</span>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn secondary" onclick="closeScanModal()">Отмена</button>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <button type="button" class="btn secondary" id="scanDiagBtn"
                        title="Показать, что сервер знает об устройствах">Диагностика</button>
            <?php endif; ?>
            <button type="button" class="btn" id="scanStartBtn">Сканировать</button>
        </div>
    </div>
</div>


<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<!-- Настройки хранилища. Пишутся в config/storage.php, а не в базу:
     страница «База знаний» листает любые таблицы, и путь к сетевому
     ресурсу предприятия был бы виден там вместе со всем остальным. -->
<div class="add-form-modal" id="storageSettingsModal">
    <div class="modal-content" style="max-width: 620px;">
        <h3>Хранилище документов</h3>

        <div class="form-group">
            <label>Папка хранения</label>
            <input type="text" id="stDocsRoot" placeholder="\\fileserver\share\Документы связи">
            <small class="scan-hint">
                Сетевой или локальный путь. Папку можно оставить пустой — подпапки
                подразделений и годов создаются сами. Доступ к сетевому ресурсу
                нужен учётной записи, под которой работает веб-сервер: к сети он
                обращается от своего имени, а не от имени пользователя.
            </small>
        </div>

        <div class="form-group">
            <label class="scan-check">
                <input type="checkbox" id="stUseDepartments">
                <span>Раскладывать по папкам подразделений</span>
            </label>
        </div>

        <div class="form-group">
            <label>Папка для документов без подразделения</label>
            <input type="text" id="stUnsorted" placeholder="Без подразделения">
        </div>

        <div class="form-group">
            <label class="scan-check">
                <input type="checkbox" id="stScanEnabled">
                <span>Разрешить сканирование из приложения</span>
            </label>
            <small class="scan-hint" id="stScanReason"></small>
        </div>

        <div class="scan-state" id="stStatus" style="display:none;"></div>

        <div class="modal-actions">
            <button type="button" class="btn secondary" onclick="closeStorageSettings()">Закрыть</button>
            <button type="button" class="btn secondary" id="stCreateFoldersBtn">Создать папки подразделений</button>
            <button type="button" class="btn" id="stSaveBtn">Сохранить</button>
        </div>
    </div>
</div>
<?php endif; ?>
