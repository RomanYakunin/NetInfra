<!-- modules/passive_devices/passive_modals.php -->
<!-- Формы пассивного оборудования: патч-панели, кроссы, модули, терминалы -->

<div class="add-form-modal" id="passiveDeviceModal">
    <div class="modal-content" style="max-width: 620px;">
        <h3 id="passiveFormTitle">Пассивное оборудование</h3>
        <form id="passiveDeviceForm" autocomplete="off">
            <input type="hidden" name="id" id="passiveId">
            <input type="hidden" name="node_id" id="passiveNodeId">
            <input type="hidden" name="rack_id" id="passiveRackId">
            <input type="hidden" name="warehouse_id" id="passiveWarehouseId">

            <div class="form-group">
                <label>Тип устройства</label>
                <select name="type" id="passiveType" required>
                    <option value="patch_panel">Патч-панель</option>
                    <option value="optical_panel">Оптический кросс</option>
                    <option value="sfp_module">SFP-модуль</option>
                    <option value="psu_module">Блок питания</option>
                    <option value="terminal">Терминал ВКС</option>
                    <option value="other">Прочее</option>
                </select>
            </div>

            <div class="form-group">
                <label>Наименование</label>
                <input type="text" name="name" id="passiveName" required
                       placeholder="Например: Патч-панель 24 порта">
            </div>

            <div class="form-group">
                <label>Производитель</label>
                <select name="vendor_id" id="passiveVendor">
                    <option value="">-- не выбрано --</option>
                </select>
            </div>

            <div class="form-group">
                <label>Модель</label>
                <input type="text" name="model" id="passiveModel" placeholder="Например: PP-24-RJ45">
            </div>

            <!-- Блок портов: скрывается для модулей и терминалов -->
            <div id="passivePortsBlock">
                <div class="form-group">
                    <label>Количество портов</label>
                    <select name="ports_count" id="passivePortsCount">
                        <option value="8">8</option>
                        <option value="12">12</option>
                        <option value="16">16</option>
                        <option value="24" selected>24</option>
                        <option value="48">48</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Тип портов</label>
                    <select name="port_type" id="passivePortType">
                        <option value="RJ45">RJ45 (медь)</option>
                        <option value="LC">LC (оптика)</option>
                        <option value="SC">SC (оптика)</option>
                        <option value="FC">FC (оптика)</option>
                        <option value="ST">ST (оптика)</option>
                        <option value="SFP">SFP</option>
                        <option value="other">Прочее</option>
                    </select>
                </div>

                <!-- Ряды нужны прежде всего оптическим кроссам -->
                <div class="form-group" id="passiveRowsGroup">
                    <label>Количество рядов портов</label>
                    <select name="port_rows" id="passivePortRows">
                        <option value="1" selected>1 ряд</option>
                        <option value="2">2 ряда</option>
                    </select>
                </div>
            </div>

            <!-- Позиция в шкафу: не показывается при добавлении на склад -->
            <div class="form-group" id="passiveUnitGroup">
                <label>Юнит в шкафу</label>
                <input type="text" name="unit_position" id="passiveUnit"
                       placeholder="например 4 или 4-8"
                       pattern="^\s*\d+\s*(-\s*\d+\s*)?$"
                       title="Номер юнита или диапазон, например 4 или 4-8">
            </div>

            <div class="form-group">
                <label>Статус</label>
                <select name="status" id="passiveStatus">
                    <option value="в эксплуатации">в эксплуатации</option>
                    <option value="на складе">на складе</option>
                    <option value="обслуживается">обслуживается</option>
                    <option value="демонтирован">демонтирован</option>
                </select>
            </div>

            <div class="form-group">
                <label>Серийный номер</label>
                <input type="text" name="serial_number" id="passiveSerial">
            </div>

            <div class="form-group">
                <label>Примечание</label>
                <textarea name="notes" id="passiveNotes" rows="2"></textarea>
            </div>

            <div class="form-error" id="passiveFormError" style="display:none;"></div>

            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closePassiveDeviceForm()">Отмена</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>


<!-- Настройка порта: куда идёт линия -->
<div class="add-form-modal" id="passivePortModal">
    <div class="modal-content" style="max-width: 560px;">
        <h3 id="passivePortTitle">Порт</h3>
        <form id="passivePortForm" autocomplete="off">
            <input type="hidden" name="device_id" id="portDeviceId">
            <input type="hidden" name="port_number" id="portNumber">

            <div class="form-group">
                <label>Метка порта</label>
                <input type="text" name="label" id="portLabel" placeholder="Например: КУ-34, каб. 226">
            </div>

            <div class="form-group">
                <label>Здание назначения</label>
                <select name="destination_building_id" id="portBuilding">
                    <option value="">-- не выбрано --</option>
                </select>
            </div>

            <div class="form-group">
                <label>Узел назначения</label>
                <select name="destination_node_id" id="portNode">
                    <option value="">-- не выбрано --</option>
                </select>
            </div>

            <div class="form-group" id="portFiberGroup">
                <label>Тип волокна</label>
                <select name="fiber_type" id="portFiber">
                    <option value="">-- не указано --</option>
                    <option value="одномод">одномод</option>
                    <option value="многомод">многомод</option>
                </select>
            </div>

            <div class="form-group">
                <label>Примечание</label>
                <textarea name="notes" id="portNotes" rows="2"></textarea>
            </div>

            <div class="form-error" id="passivePortError" style="display:none;"></div>

            <div class="modal-actions">
                <button type="button" class="btn secondary" onclick="closePassivePortForm()">Отмена</button>
                <button type="button" class="btn secondary" id="portClearBtn">Освободить порт</button>
                <button type="submit" class="btn">Сохранить</button>
            </div>
        </form>
    </div>
</div>
