<?php
require_once __DIR__ . '/../includes/db.php';

function addEquipment($data) {
    $pdo = Database::getConnection();
    // Динамически формируем запрос на основе переданных полей
    $fields = ['node_id', 'location_type', 'type', 'hostname', 'ip_address', 'vendor', 'model', 'serial_number', 'mac_address', 'firmware', 'physical_location', 'is_active', 'cabinet', 'cabinet_type', 'unit'];
    $values = [];
    $placeholders = [];
    foreach ($fields as $f) {
        if (isset($data[$f])) {
            $placeholders[] = "`$f`";
            $values[] = $data[$f];
        }
    }
    // Добавляем создание, если нет
    if (!isset($data['created_at'])) {
        $placeholders[] = "`created_at`";
        $values[] = date('Y-m-d H:i:s');
    }
    $sql = "INSERT INTO equipment (" . implode(',', $placeholders) . ") VALUES (" . implode(',', array_fill(0, count($values), '?')) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    return $pdo->lastInsertId();
}

// Аналогично updateEquipment и deleteEquipment