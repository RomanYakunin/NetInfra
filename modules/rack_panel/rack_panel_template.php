<!-- modules/rack_panel/rack_panel_template.php -->
<!-- Окно визуализации шкафов узла: наполняется modules/rack_panel/rack_panel.js.
     Раньше это была выдвижная панель справа — она сдвигала содержимое страницы.
     Внутренние идентификаторы (panelBody, panelTitle) сохранены: вся отрисовка
     стойки, портов и перетаскивания границ работает по ним без изменений. -->
<div class="add-form-modal rack-modal" id="rightPanel">
    <div class="modal-content">
        <div class="panel-header">
            <span id="panelTitle">Стойка</span>
            <span class="panel-close" id="panelClose" title="Закрыть">✕</span>
        </div>
        <div class="panel-body" id="panelBody">
            <div class="rack-placeholder">Выберите оборудование, чтобы увидеть шкаф</div>
        </div>
    </div>
</div>
