<?php
// api/GetData/get_equipment_columns.php – список полей формы оборудования
// Адаптировано: удалено поле Groupe (группа теперь управляется отдельно через group_id)
header('Content-Type: application/json; charset=utf-8');

$fields = [
    ['name' => 'ip_address',      'label' => 'IP-адрес',        'type' => 'select', 'source' => 'ip_address_list'],
    ['name' => 'hostname',        'label' => 'Имя хоста',       'type' => 'text'],
    ['name' => 'Poe',             'label' => 'PoE',             'type' => 'switch'],
    ['name' => 'device_type_id',  'label' => 'Тип устройства',  'type' => 'select', 'source' => 'device_types_list'],
    ['name' => 'vendor_id',       'label' => 'Производитель',   'type' => 'select', 'source' => 'vendors_list'],
    ['name' => 'model_id',        'label' => 'Модель',          'type' => 'select', 'source' => 'device_models_list'],
    ['name' => 'serial_number',   'label' => 'Серийный номер',  'type' => 'text'],
    ['name' => 'mac_address',     'label' => 'MAC-адрес',       'type' => 'text'],
    ['name' => 'firmwares',       'label' => 'Прошивка',        'type' => 'select', 'source' => 'firmwares_list'],
    ['name' => 'id_rack',      'label' => 'Шкаф',            'type' => 'select', 'source' => 'racks_list'],
    ['name' => 'unit_position',   'label' => 'Юнит',            'type' => 'number'],
    ['name' => 'Annotation',      'label' => 'Примечание',      'type' => 'textarea'],
];

echo json_encode($fields);