<?php
// api/GetData/get_node_equipment_for_move.php
// Возвращает плоский список оборудования узла с полными данными для формы перемещения
// Адаптировано под новую схему БД (group_id, equipment_groups)

require_once dirname(__FILE__, 5) . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['node_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'node_id required']);
    exit;
}

$nodeId = (int)$_GET['node_id'];

try {
    $sql = "SELECT 
                e.id,
                e.hostname,
                e.serial_number,
                e.mac_address,
                e.group_id,
                e.Slot,
                ip.ip_address,
                dt.name AS device_type_name,
                v.name AS vendor_name,
                dm.name AS model_name,
                eg.hostname AS group_hostname,
                eg.ip_address_id AS group_ip_id
            FROM equipment e
            LEFT JOIN ip_address ip ON e.ip_address = ip.Id
            LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
            LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
            LEFT JOIN device_models dm ON e.model_id = dm.id
            LEFT JOIN equipment_groups eg ON e.group_id = eg.id
            WHERE e.id_node = ?
            ORDER BY e.group_id, e.Slot";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nodeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    $stackGroups = [];

    foreach ($rows as $row) {
        $groupId = $row['group_id'];
        $isStack = ($groupId !== null);

        if (!$isStack) {
            // Одиночное устройство
            $result[] = [
                'id' => $row['id'],
                'hostname' => $row['hostname'],
                'ip_address' => $row['ip_address'],
                'device_type_name' => $row['device_type_name'],
                'vendor_name' => $row['vendor_name'],
                'model_name' => $row['model_name'],
                'serial_number' => $row['serial_number'],
                'mac_address' => $row['mac_address'],
                'is_stack' => false,
                '_stackMembers' => null
            ];
        } else {
            // Устройство принадлежит стеку
            $key = (string)$groupId;
            if (!isset($stackGroups[$key])) {
                $stackGroups[$key] = [
                    'group_hostname' => $row['group_hostname'] ?? $row['hostname'],
                    'group_ip' => $row['ip_address'], // или IP из equipment_groups при желании
                    'members' => []
                ];
            }
            $stackGroups[$key]['members'][] = $row;
        }
    }

    // Обрабатываем стеки
    foreach ($stackGroups as $groupId => $stack) {
        $members = $stack['members'];
        usort($members, function($a, $b) {
            return ($a['Slot'] ?? 0) - ($b['Slot'] ?? 0);
        });

        $first = $members[0];
        $stackMembers = array_map(function($m) {
            return [
                'id' => $m['id'],
                'hostname' => $m['hostname'],
                'ip_address' => $m['ip_address'],
                'device_type_name' => $m['device_type_name'],
                'vendor_name' => $m['vendor_name'],
                'model_name' => $m['model_name'],
                'serial_number' => $m['serial_number'],
                'mac_address' => $m['mac_address']
            ];
        }, $members);

        // Используем общий IP стека (если он есть в группе, то можно взять из $stack['group_ip'] либо из первого члена)
        $stackIp = $stack['group_ip'] ?? $first['ip_address'];

        $result[] = [
            'id' => $first['id'],
            'hostname' => $stack['group_hostname'] ?? $first['hostname'],
            'ip_address' => $stackIp,
            'device_type_name' => $first['device_type_name'],
            'vendor_name' => $first['vendor_name'],
            'model_name' => $first['model_name'],
            'serial_number' => $first['serial_number'],
            'mac_address' => $first['mac_address'],
            'is_stack' => true,
            '_stackMembers' => $stackMembers
        ];
    }

    echo json_encode($result);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}