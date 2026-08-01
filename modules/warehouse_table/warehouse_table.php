<?php
// modules/warehouse_table/warehouse_table.php

if (!isset($pdo)) {
    require_once dirname(__FILE__, 3) . '/config/db.php';
    if (!isset($pdo)) {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'error' => 'PDO не подключен']));
    }
}

// AJAX-обработчики
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    // Список зданий
    if ($action === 'get_buildings') {
        $stmt = $pdo->query("SELECT Id, Name_Building AS name FROM Buildings ORDER BY Name_Building");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    if ($action === 'get_warehouses') {
    $stmt = $pdo->query("
        SELECT w.id, w.name, b.Name_Building AS building_name,
               CONCAT(b.Name_Building, ' (', w.name, ')') AS display
        FROM warehouses w
        LEFT JOIN Buildings b ON w.building = b.Id
        ORDER BY b.Name_Building, w.name
    ");
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($warehouses);
    exit;
}



    // Добавление склада
    if ($action === 'add_warehouse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $buildingId = intval($_POST['building_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');

        if ($buildingId <= 0 || $name === '') {
            echo json_encode(['success' => false, 'error' => 'Здание и помещение обязательны']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO warehouses (building, name, device_count) VALUES (?, ?, 0)");
            $stmt->execute([$buildingId, $name]);
            $newId = $pdo->lastInsertId();

            $bStmt = $pdo->prepare("SELECT Name_Building FROM Buildings WHERE Id = ?");
            $bStmt->execute([$buildingId]);
            $buildingName = $bStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'id' => $newId,
                'name' => $name,
                'building_name' => $buildingName
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Ошибка БД: ' . $e->getMessage()]);
        }
        exit;
    }

    // Детали группы
    if ($action === 'get_warehouse_equipment_group') {
        $warehouseId = $_GET['warehouse_id'] ?? 'all';
        $deviceTypeId = intval($_GET['device_type_id'] ?? 0);
        $vendorId = intval($_GET['vendor_id'] ?? 0);
        $modelId = intval($_GET['model_id'] ?? 0);

        $sql = "SELECT e.*, 
                       dt.name AS device_type_name,
                       v.name AS vendor_name,
                       dm.name AS model_name,
                       fw.name AS firmware_name,
                       ip.ip_address AS ip_address_display
                FROM equipment e
                LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
                LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
                LEFT JOIN device_models dm ON e.model_id = dm.id
                LEFT JOIN firmwares fw ON e.firmwares = fw.id_firmware
                LEFT JOIN ip_address ip ON e.ip_address = ip.Id
                WHERE e.warehouse_id IS NOT NULL
                  AND e.device_type_id = ?
                  AND e.vendor_id = ?
                  AND e.model_id = ?";
        $params = [$deviceTypeId, $vendorId, $modelId];

        if ($warehouseId !== 'all' && intval($warehouseId) > 0) {
            $sql .= " AND e.warehouse_id = ?";
            $params[] = intval($warehouseId);
        }
        $sql .= " ORDER BY e.hostname";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '';
foreach ($items as $eq) {
    $html .= '<tr class="warehouse-equipment-row" data-equipment-id="' . $eq['id'] . '">';
    $html .= '<td>' . htmlspecialchars($eq['hostname'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($eq['ip_address_display'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($eq['serial_number'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($eq['mac_address'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($eq['firmware_name'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($eq['Annotation'] ?? '') . '</td>';
    $html .= '</tr>';
}

        echo json_encode(['html' => $html]);
        exit;
    }

    // Загрузка основной таблицы
    if ($action === 'load_table') {
        $tab = $_GET['tab'] ?? 'Оборудование';
        $warehouseId = $_GET['warehouse_id'] ?? 'all';
        $search = trim($_GET['search'] ?? '');

        $result = [
            'success' => true,
            'html' => '',
            'active_warehouse_id' => $warehouseId,
            'total_count' => 0,
            'columns' => []
        ];

        if ($tab === 'Оборудование' || $tab === 'Телефоны') {
            // Группировка по типу/вендору/модели
            $sql = "SELECT e.device_type_id, e.vendor_id, e.model_id,
                           dt.name AS device_type_name,
                           v.name AS vendor_name,
                           dm.name AS model_name,
                           COUNT(*) AS cnt
                    FROM equipment e
                    LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
                    LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
                    LEFT JOIN device_models dm ON e.model_id = dm.id
                    WHERE e.warehouse_id IS NOT NULL";
            $params = [];

            if ($tab === 'Телефоны') {
                $sql .= " AND e.device_type_id = 12";
            }

            if ($warehouseId !== 'all' && intval($warehouseId) > 0) {
                $sql .= " AND e.warehouse_id = ?";
                $params[] = intval($warehouseId);
            }

            if ($search !== '') {
                $like = '%' . $search . '%';
                $sql .= " AND (e.hostname LIKE ? OR e.serial_number LIKE ? OR e.mac_address LIKE ?
                          OR dt.name LIKE ? OR v.name LIKE ? OR dm.name LIKE ?)";
                $params = array_merge($params, array_fill(0, 6, $like));
            }

            $sql .= " GROUP BY e.device_type_id, e.vendor_id, e.model_id
                      ORDER BY dt.name, v.name, dm.name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Автопереключение склада, если поиск не дал результатов в текущем
            if (empty($groups) && $warehouseId !== 'all' && $search !== '') {
                $sqlAll = "SELECT e.warehouse_id FROM equipment e
                           LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
                           LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
                           LEFT JOIN device_models dm ON e.model_id = dm.id
                           WHERE e.warehouse_id IS NOT NULL
                             AND (e.hostname LIKE ? OR e.serial_number LIKE ? OR e.mac_address LIKE ?
                                  OR dt.name LIKE ? OR v.name LIKE ? OR dm.name LIKE ?)
                           GROUP BY e.warehouse_id ORDER BY e.warehouse_id LIMIT 1";
                $stmtAll = $pdo->prepare($sqlAll);
                $stmtAll->execute(array_fill(0, 6, $like));
                $firstWarehouseId = $stmtAll->fetchColumn();
                if ($firstWarehouseId) {
                    $result['active_warehouse_id'] = $firstWarehouseId;
                    $sql = "SELECT e.device_type_id, e.vendor_id, e.model_id,
                                   dt.name AS device_type_name,
                                   v.name AS vendor_name,
                                   dm.name AS model_name,
                                   COUNT(*) AS cnt
                            FROM equipment e
                            LEFT JOIN device_types dt ON e.device_type_id = dt.id_type_device
                            LEFT JOIN vendors v ON e.vendor_id = v.id_vendor
                            LEFT JOIN device_models dm ON e.model_id = dm.id
                            WHERE e.warehouse_id = ?";
                    $params = [$firstWarehouseId];
                    if ($tab === 'Телефоны') $sql .= " AND e.device_type_id = 12";
                    if ($search !== '') {
                        $sql .= " AND (e.hostname LIKE ? OR e.serial_number LIKE ? OR e.mac_address LIKE ?
                                  OR dt.name LIKE ? OR v.name LIKE ? OR dm.name LIKE ?)";
                        $params = array_merge($params, array_fill(0, 6, $like));
                    }
                    $sql .= " GROUP BY e.device_type_id, e.vendor_id, e.model_id
                              ORDER BY dt.name, v.name, dm.name";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            $html = '';
            if (empty($groups)) {
                $html = '<tr><td colspan="5" style="text-align:center; padding:2rem;">Нет устройств</td></tr>';
            } else {
                foreach ($groups as $grp) {
                    $groupId = implode('_', [$grp['device_type_id'], $grp['vendor_id'], $grp['model_id']]);
                    $html .= '<tr class="warehouse-group-row" data-group-id="' . $groupId . '"
                                  data-device-type="' . $grp['device_type_id'] . '"
                                  data-vendor="' . $grp['vendor_id'] . '"
                                  data-model="' . $grp['model_id'] . '"
                                  data-warehouse="' . $warehouseId . '"
                                  onclick="toggleWarehouseGroup(this)">';
                    $html .= '<td>' . htmlspecialchars($grp['device_type_name'] ?: '—') . '</td>';
                    $html .= '<td>' . htmlspecialchars($grp['vendor_name'] ?: '—') . '</td>';
                    $html .= '<td>' . htmlspecialchars($grp['model_name'] ?: '—') . '</td>';
                    $html .= '<td>' . $grp['cnt'] . ' шт.</td>';
                    $html .= '<td><span class="expand-arrow">▶</span></td>';
                    $html .= '</tr>';
                    $html .= '<tr class="warehouse-detail-row" id="warehouse-detail-' . $groupId . '" style="display:none;">';
                    $html .= '<td colspan="5"><div class="warehouse-detail-container" id="warehouse-detail-content-' . $groupId . '"></div></td>';
                    $html .= '</tr>';
                }
            }

            $result['html'] = $html;
            $result['total_count'] = array_sum(array_column($groups, 'cnt'));
            $result['columns'] = ($tab === 'Оборудование')
                ? ['Тип', 'Производитель', 'Модель', 'Кол-во', '']
                : ['Производитель', 'Модель', 'Кол-во', ''];
        } else {
            $result['html'] = '<tr><td colspan="5" style="text-align:center; padding:2rem;">Раздел в разработке</td></tr>';
        }

        echo json_encode($result);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown AJAX action: ' . $action]);
    exit;
}

// Обычная загрузка страницы
$warehouses = [];
try {
    $whStmt = $pdo->query("
        SELECT w.id, w.name, b.Name_Building AS building_name, w.building
        FROM warehouses w
        LEFT JOIN Buildings b ON w.building = b.Id
        ORDER BY b.Name_Building, w.name
    ");
    $warehouses = $whStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $warehouses = [];
}