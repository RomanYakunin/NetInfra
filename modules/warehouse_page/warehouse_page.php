<?php
// modules/warehouse_page/warehouse_page.php

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
        } elseif ($tab === 'Прочее') {
            // Пассивное оборудование на складах: патч-панели, кроссы, модули, терминалы
            require_once dirname(__FILE__, 3) . '/modules/passive_devices/api/passive_helpers.php';
            $labels  = passiveTypeLabels();
            $allowed = passiveAllowed();

            $typeFilter = trim($_GET['ptype'] ?? '');
            if ($typeFilter !== '' && !in_array($typeFilter, $allowed['type'], true)) {
                $typeFilter = '';
            }

            // Общие условия для таблицы и для счётчиков слева
            $base   = " FROM passive_devices pd
                        LEFT JOIN vendors v ON pd.vendor_id = v.id_vendor
                        WHERE pd.warehouse_id IS NOT NULL";
            $common = '';
            $commonParams = [];

            if ($warehouseId !== 'all' && intval($warehouseId) > 0) {
                $common .= " AND pd.warehouse_id = ?";
                $commonParams[] = intval($warehouseId);
            }
            if ($search !== '') {
                $like = '%' . $search . '%';
                $common .= " AND (pd.name LIKE ? OR pd.model LIKE ? OR pd.serial_number LIKE ?
                             OR pd.notes LIKE ? OR v.name LIKE ?)";
                $commonParams = array_merge($commonParams, array_fill(0, 5, $like));
            }

            $items = [];
            $counts = [];
            $tableMissing = false;
            try {
                // Счётчики по типам — не зависят от выбранного слева типа,
                // иначе панель фильтров схлопывалась бы после первого клика
                $cStmt = $pdo->prepare("SELECT pd.type, COUNT(*) AS cnt" . $base . $common . " GROUP BY pd.type");
                $cStmt->execute($commonParams);
                foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $counts[$row['type']] = intval($row['cnt']);
                }

                $sql = "SELECT pd.id, pd.type, pd.name, pd.model, pd.ports_count, pd.port_type,
                               pd.status, pd.notes, pd.serial_number,
                               v.name AS vendor_name"
                       . $base . $common;
                $params = $commonParams;
                if ($typeFilter !== '') {
                    $sql .= " AND pd.type = ?";
                    $params[] = $typeFilter;
                }
                $sql .= " ORDER BY pd.type, v.name, pd.model, pd.name";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Таблица появляется миграцией passive_devices.sql — без неё
                // вкладка не должна ронять страницу
                $tableMissing = true;
            }

            $html = '';
            if ($tableMissing) {
                $html = '<tr><td colspan="7" style="text-align:center; padding:2rem;">'
                      . 'Таблица пассивного оборудования не создана. Примените migrations/passive_devices.sql'
                      . '</td></tr>';
            } elseif (empty($items)) {
                $html = '<tr><td colspan="7" style="text-align:center; padding:2rem;">Нет устройств</td></tr>';
            } else {
                foreach ($items as $pd) {
                    // Наименование и серийник нет в колонках — показываем подсказкой
                    $tip = $pd['name'];
                    if (!empty($pd['serial_number'])) $tip .= ' · S/N ' . $pd['serial_number'];
                    $html .= '<tr class="passive-device-row" data-passive-id="' . intval($pd['id']) . '"'
                           . ' title="' . htmlspecialchars($tip) . '">';
                    $html .= '<td>' . htmlspecialchars($labels[$pd['type']] ?? $pd['type']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($pd['vendor_name'] ?: '—') . '</td>';
                    $html .= '<td>' . htmlspecialchars($pd['model'] ?: '—') . '</td>';
                    $html .= '<td>' . (intval($pd['ports_count']) > 0 ? intval($pd['ports_count']) : '—') . '</td>';
                    $html .= '<td>' . (intval($pd['ports_count']) > 0 ? htmlspecialchars($pd['port_type']) : '—') . '</td>';
                    $html .= '<td>' . htmlspecialchars($pd['status'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars($pd['notes'] ?? '') . '</td>';
                    $html .= '</tr>';
                }
            }

            // Панель фильтров слева
            $filters = [['key' => '', 'label' => 'Все типы', 'count' => array_sum($counts)]];
            foreach ($allowed['type'] as $t) {
                $filters[] = [
                    'key'   => $t,
                    'label' => $labels[$t] ?? $t,
                    'count' => $counts[$t] ?? 0,
                ];
            }

            $result['html']         = $html;
            $result['total_count']  = count($items);
            $result['columns']      = ['Тип', 'Производитель', 'Модель', 'Кол-во портов', 'Тип портов', 'Статус', 'Примечание'];
            $result['type_filters'] = $filters;
            $result['active_type']  = $typeFilter;
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