<?php
/**
 * Проверка заполненности критических полей узлов и оборудования
 */

function isFieldEmpty($value): bool
{
    if ($value === null) return true;
    if (is_string($value) && trim($value) === '') return true;
    if (is_string($value) && strtolower(trim($value)) === 'null') return true;
    return false;
}

function getNodeMissingFields(array $node): array
{
    $required = [
        'building_id'   => 'Здание',
        'node_type_id'  => 'Тип узла',
        // KY_number пока не считаем критичным
    ];
    $missing = [];
    foreach ($required as $field => $label) {
        if (isFieldEmpty($node[$field] ?? null)) {
            $missing[] = $label;
        }
    }
    return $missing;
}

function getEquipmentMissingFields(array $equip): array
{
    $required = [
        'ip_address'      => 'IP-адрес',
        'hostname'        => 'Имя хоста',
        'device_type_id'  => 'Тип устройства',
        'vendor_id'       => 'Производитель',
        'model_id'        => 'Модель',
    ];
    $missing = [];
    foreach ($required as $field => $label) {
        // В ответе get_equipment поля *_original, если есть, содержат реальные ID
        $originalField = $field . '_original';
        $value = $equip[$originalField] ?? $equip[$field] ?? null;
        if (isFieldEmpty($value)) {
            $missing[] = $label;
        }
    }
    return $missing;
}