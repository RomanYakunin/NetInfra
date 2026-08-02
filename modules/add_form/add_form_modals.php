<!-- Универсальное модальное окно (узел / устройство) -->
<div class="add-form-modal" id="universalAddModal">
    <div class="modal-content">
        <h3 id="addFormTitle"></h3>
        <form id="universalAddForm">
            <div id="addFormFields"></div>
            <div id="addFormHiddenFields"></div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeAddForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Форма добавления локации -->
<div class="add-form-modal" id="addLocationModal">
    <div class="modal-content">
        <h3>Добавить новую локацию</h3>
        <form id="addLocationForm">
            <div class="form-group">
                <label>Здание</label>
                <select name="building_id" id="buildingSelect"></select>
            </div>
            <div class="form-group"><label>Цех</label><input type="text" name="workshop"></div>
            <div class="form-group"><label>Этаж</label><input type="text" name="floor"></div>
            <div class="form-group"><label>Комната</label><input type="text" name="room"></div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeLocationForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>


<!-- Форма добавления типа узла -->
<div class="add-form-modal" id="addNodeTypeModal">
    <div class="modal-content">
        <h3>Добавить новый тип узла</h3>
        <form id="addNodeTypeForm">
            <div class="form-group">
                <label>Название</label>
                <input type="text" name="name" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeNodeTypeForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Мета‑модалка для справочников и столбцов -->
<div class="add-form-modal" id="metaAddModal">
    <div class="modal-content">
        <h3 id="metaAddTitle">Добавить</h3>
        <form id="metaAddForm">
            <div class="form-group">
                <label>Название</label>
                <input type="text" id="metaAddName" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeMetaForm()">Отмена</button>
                <button type="submit" class="btn" id="metaAddSubmit">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Специальная форма для моделей (с выбором производителя) -->
<div class="add-form-modal" id="metaAddModel">
    <div class="modal-content">
        <h3 id="modelMetaTitle">Добавить модель</h3>
        <form id="modelMetaForm" autocomplete="off">
            <div class="form-group">
                <label for="modelVendorSelect">Производитель</label>
                <select id="modelVendorSelect">
                    <option value="">-- не выбрано --</option>
                </select>
            </div>
            <div class="form-group">
                <label for="modelMetaName">Название модели</label>
                <input type="text" id="modelMetaName" placeholder="Введите название модели" required>
            </div>
            <div class="modal-actions" style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button type="submit" id="modelMetaSubmit" class="btn">Сохранить</button>
                <button type="button" class="btn secondary" onclick="closeModelMetaForm()">Отмена</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно настроек оповещений -->
<div class="add-form-modal" id="alertSettingsModal">
    <div class="modal-content">
        <h3>Настройки оповещений</h3>
        <h4><em> В разработке...</em></h4>
        <form id="alertSettingsForm">
            <div class="alert-row">
                <div class="alert-info">
                    <span class="alert-label">Статус</span>
                    <span class="alert-desc">Уведомление при изменении состояния системы (включено/выключено)</span>
                </div>
                <label class="switch">
                    <input type="checkbox" name="alert_status" checked>
                    <span class="slider round"></span>
                </label>
            </div>
            <div class="alert-row">
                <div class="alert-info">
                    <span class="alert-label">Использование CPU</span>
                    <span class="alert-desc">Срабатывает, если средняя загрузка превышает <strong id="cpu-value">80%</strong> в течение 5 минут</span>
                </div>
                <div class="alert-controls">
                    <label class="switch">
                        <input type="checkbox" name="alert_cpu" checked>
                        <span class="slider round"></span>
                    </label>
                    <input type="range" name="cpu_threshold" min="10" max="100" value="80" step="5"
                           oninput="document.getElementById('cpu-value').textContent = this.value + '%'">
                </div>
            </div>
            <div class="alert-row">
                <div class="alert-info">
                    <span class="alert-label">Использование памяти</span>
                    <span class="alert-desc">Срабатывает, если использование превышает <strong id="mem-value">90%</strong> в течение 10 минут</span>
                </div>
                <div class="alert-controls">
                    <label class="switch">
                        <input type="checkbox" name="alert_memory" checked>
                        <span class="slider round"></span>
                    </label>
                    <input type="range" name="memory_threshold" min="10" max="100" value="90" step="5"
                           oninput="document.getElementById('mem-value').textContent = this.value + '%'">
                </div>
            </div>
            <div class="alert-row">
                <div class="alert-info">
                    <span class="alert-label">Использование диска</span>
                    <span class="alert-desc">Уведомление при превышении <strong id="disk-value">70%</strong> в течение 20 минут</span>
                </div>
                <div class="alert-controls">
                    <label class="switch">
                        <input type="checkbox" name="alert_disk" checked>
                        <span class="slider round"></span>
                    </label>
                    <input type="range" name="disk_threshold" min="10" max="100" value="70" step="5"
                           oninput="document.getElementById('disk-value').textContent = this.value + '%'">
                </div>
            </div>
            <div class="alert-row">
                <div class="alert-info">
                    <span class="alert-label">Пропускная способность</span>
                    <span class="alert-desc">Срабатывает при превышении комбинированного порога входящего и исходящего трафика</span>
                </div>
                <div class="alert-controls">
                    <label class="switch">
                        <input type="checkbox" name="alert_bandwidth">
                        <span class="slider round"></span>
                    </label>
                    <input type="range" name="bandwidth_threshold" min="10" max="1000" value="100" step="10" disabled>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeAlertSettings()">Закрыть</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>
<!-- Форма добавления устройства в стек -->
<!-- Модальное окно добавления/редактирования устройства стека -->
<div class="add-form-modal" id="addStackDeviceModal">
    <div class="modal-content">
        <h3 id="stackDeviceFormTitle">Добавление устройства в стек</h3>
        <form id="addStackDeviceForm" autocomplete="off">
            <div id="stackDeviceFormFields"></div>
<div id="stackDeviceFormHiddenFields"></div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeStackDeviceForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно добавления шкафа -->
<div class="add-form-modal" id="addRackModal">
    <div class="modal-content">
        <h3>Добавить шкаф</h3>
        <form id="addRackForm" autocomplete="off">
            <!-- Производитель -->
            <div class="form-group">
                <label>Производитель</label>
                <select name="vendor_id" id="rack-vendor-select"></select>
            </div>
            <!-- Модель -->
            <div class="form-group">
                <label>Модель</label>
                <select name="model_id" id="rack-model-select"></select>
            </div>
            <!-- Имя -->
            <div class="form-group">
                <label>Имя шкафа</label>
                <input type="text" name="name" class="dossier-input" required>
            </div>
            <div class="form-group" style="border:1px solid var(--border-color); border-radius:8px; padding:1rem; margin-bottom:1rem;">
    <div style="font-weight:600; margin-bottom:0.8rem; color:var(--accent);">Расположение</div>
    <div class="form-group">
        <label>Здание</label>
        <select name="building_id" id="rack-building-select"></select>
    </div>
    <div class="form-group">
        <label>Цех</label>
        <input type="text" name="workshop" class="dossier-input">
    </div>
    <div class="form-group">
        <label>Этаж</label>
        <input type="text" name="floor" class="dossier-input">
    </div>
    <div class="form-group">
        <label>Комната</label>
        <input type="text" name="room" class="dossier-input">
    </div>
</div>
            <!-- Статус -->
            <div class="form-group">
                <label>Статус</label>
                <select name="status">
                    <option value="в эксплуатации" selected>В эксплуатации</option>
                    <option value="на складе">На складе</option>
                    <option value="обслуживается">Обслуживается</option>
                    <option value="демонтирован">Демонтирован</option>
                </select>
            </div>
            <!-- Примечание -->
            <div class="form-group">
                <label>Примечание</label>
                <textarea name="notes" rows="2" class="dossier-input"></textarea>
            </div>
            <!-- Скрытые поля -->
            <input type="hidden" name="id_node" id="rack-node-id">
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeAddRackForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно добавления новой модели шкафа -->
<div class="add-form-modal" id="addRackModelModal">
    <div class="modal-content">
        <h3>Добавить модель шкафа</h3>
        <form id="addRackModelForm" autocomplete="off">
            <div class="form-group">
                <label>Производитель</label>
                <select name="vendor_id" id="rack-model-vendor-select" required></select>
            </div>
            <div class="form-group">
                <label>Название модели</label>
                <input type="text" name="model_name" class="dossier-input" required>
            </div>
            <div class="form-group">
                <label>Тип</label>
                <select name="form_factor">
                    <option value="напольный" selected>Напольный</option>
                    <option value="настенный">Настенный</option>
                </select>
            </div>
            <div class="form-group">
                <label>Высота, U</label>
                <input type="number" name="height_u" class="dossier-input" min="1" required>
            </div>
            <div class="form-group">
                <label>Ширина, мм</label>
                <input type="number" name="width_mm" class="dossier-input" min="1" required>
            </div>
            <div class="form-group">
                <label>Глубина, мм</label>
                <input type="number" name="depth_mm" class="dossier-input" min="1" required>
            </div>
            <div class="form-group">
                <label>Тип двери</label>
                <select name="door_type">
                    <option value="перфорированная" selected>Перфорированная</option>
                    <option value="стеклянная">Стеклянная</option>
                    <option value="глухая металлическая">Глухая металлическая</option>
                    <option value="сетчатая">Сетчатая</option>
                    <option value="комбинированная">Комбинированная</option>
                </select>
            </div>
            <div class="form-group">
                <label>Класс защиты IP</label>
                <input type="text" name="ip_rating" class="dossier-input" value="IP20">
            </div>
            <div class="form-group">
                <label>Макс. нагрузка, кг</label>
                <input type="number" name="max_load_kg" class="dossier-input" min="0">
            </div>
            <div class="form-group">
                <label>Примечание</label>
                <textarea name="notes" rows="2" class="dossier-input"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closeAddRackModelForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно инструкции по сервису -->
<div class="add-form-modal" id="serviceInstructionModal">
    <div class="modal-content">
        <h3 id="instr-title">Инструкция</h3>
        <div id="instr-body">
            <!-- Заполняется динамически -->
        </div>
        <div class="modal-actions">
            <button type="button" class="btn secondary" onclick="closeServiceInstruction()">Закрыть</button>
        </div>
    </div>
</div>
<!-- Модальное окно импорта LLDP -->
<div class="add-form-modal" id="lldpImportModal">
    <div class="modal-content">
        <h3>📡 Импорт LLDP соседей</h3>
        <div class="form-group">
            <label>Вывод команды (sh lldp neighbor / dis lldp neighbor brief)</label>
            <textarea id="lldpOutput" rows="12" style="font-family:monospace; white-space:pre;"></textarea>
        </div>
        <div id="lldpResult" style="margin-top:1rem; max-height:300px; overflow-y:auto;"></div>
        <div class="modal-actions">
            <button type="button" class="btn secondary" onclick="closeLLDPImport()">Закрыть</button>
            <button type="button" class="btn" id="analyzeLLDPBtn">Анализировать</button>
        </div>
    </div>
</div>