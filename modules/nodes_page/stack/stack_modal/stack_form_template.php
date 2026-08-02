<!-- modules/nodes_page/stack/stack_modal/stack_form_template.php -->
<div class="dossier-section">
    <h4>📦 Основная информация стека</h4>
    <div class="dossier-grid">
        <div class="form-group">
            <label>IP-адрес</label>
            <select id="stack-ip" name="ip_address" class="dossier-select" data-source="ip_address_list"></select>
        </div>
        <div class="form-group">
            <label>Имя хоста</label>
            <input type="text" id="stack-hostname" name="hostname" class="dossier-input" placeholder="Введите общее имя">
        </div>
        <div class="form-group">
            <label>Производитель</label>
            <select id="stack-vendor" class="dossier-select" data-source="vendors_list"></select>
        </div>
        <div class="form-group">
            <label>Примечание</label>
            <textarea id="stack-note" class="dossier-input" rows="2"></textarea>
        </div>
    </div>
</div>

<div class="dossier-section">
    <h4>🔧 Устройства в стеке</h4>
    <div class="module-list" id="stack-devices-list">
        <div class="modules-empty-message" id="stack-empty-msg">Нет устройств в стеке</div>
        <div class="add-module-tile" id="add-stack-device-btn">+ Добавить устройство</div>
    </div>
</div>