<?php
require_once '../../config/db.php';

if (!isset($_GET['equipment_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'equipment_id required']);
    exit;
}
$equipId = (int)$_GET['equipment_id'];

try {
    $eq = $pdo->query("SELECT * FROM equipment WHERE id = $equipId")->fetch();
    if (!$eq) { echo json_encode(['error' => 'Not found']); exit; }

    $cabinetName = $eq['cabinet'];
    $cabinetType = $eq['cabinet_type'];
    preg_match('/(\d+)/', $cabinetType, $m);
    $units = isset($m[1]) ? (int)$m[1] : 0;

    $nodeId = $eq[$equipmentFkColumn];
    $stmt = $pdo->prepare("SELECT * FROM equipment WHERE `$equipmentFkColumn` = ? AND cabinet = ?");
    $stmt->execute([$nodeId, $cabinetName]);
    $cabEquipment = $stmt->fetchAll();

    $unitMap = [];
    foreach ($cabEquipment as $item) {
        if ($item['unit']) {
            $unitMap[$item['unit']] = [
                'hostname' => $item['hostname'],
                'ip'       => $item['ip_address'],
                'active'   => $item['is_active']
            ];
        }
    }
    $cabinetRows = [];
    for ($u = 1; $u <= $units; $u++) {
        $cabinetRows[] = $unitMap[$u] ?? null;
    }
    echo json_encode(['cabinet' => $cabinetName, 'type' => $cabinetType, 'units' => $cabinetRows]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
