<?php
// api/GetData/get_warehouses.php
// Возвращает список складов с отформатированным названием (Название, Здание)

$sql = "SELECT w.id, w.name, b.Name_Building 
        FROM warehouses w 
        LEFT JOIN Buildings b ON w.building = b.Id 
        ORDER BY b.Name_Building, w.name";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($rows as $row) {
    $parts = [];
    if (!empty($row['name'])) {
        $parts[] = $row['name'];
    }
    if (!empty($row['Name_Building'])) {
        $parts[] = $row['Name_Building'];
    }
    $display = $parts ? implode(', ', $parts) : 'Склад #' . $row['id'];
    $data[] = [
        'id' => $row['id'],
        'display' => $display
    ];
}

echo json_encode(['data' => $data]);
exit;