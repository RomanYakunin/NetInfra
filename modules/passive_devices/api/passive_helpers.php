<?php
/**
 * modules/passive_devices/api/passive_helpers.php
 * Общая валидация для обработчиков пассивного оборудования.
 */

/** Допустимые значения перечислений — совпадают с ENUM в БД. */
function passiveAllowed()
{
    return [
        'type'      => ['patch_panel', 'optical_panel', 'sfp_module', 'psu_module', 'terminal', 'other'],
        'port_type' => ['RJ45', 'LC', 'SC', 'FC', 'ST', 'SFP', 'other'],
        'status'    => ['в эксплуатации', 'на складе', 'обслуживается', 'демонтирован'],
    ];
}

/** Русские названия типов — для интерфейса и журнала. */
function passiveTypeLabels()
{
    return [
        'patch_panel'   => 'Патч-панель',
        'optical_panel' => 'Оптический кросс',
        'sfp_module'    => 'SFP-модуль',
        'psu_module'    => 'Блок питания',
        'terminal'      => 'Терминал ВКС',
        'other'         => 'Прочее',
    ];
}

/**
 * Разбирает «4» или «4-8» в канонический вид.
 * Возвращает null для пустого значения, false — если формат неверен.
 */
function passiveNormalizeUnit($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $raw, $m)) {
        $from = (int)$m[1]; $to = (int)$m[2];
        if ($from > $to) { [$from, $to] = [$to, $from]; }
        return $from === $to ? (string)$from : $from . '-' . $to;
    }
    if (preg_match('/^\d+$/', $raw)) return $raw;
    return false;
}

/**
 * Проверяет и приводит входные данные формы.
 * При ошибке сам отдаёт JSON и завершает выполнение.
 *
 * @param array $src обычно $_POST
 * @return array готовые к записи значения
 */
function passiveValidateInput(array $src)
{
    $allowed = passiveAllowed();

    $type = trim($src['type'] ?? '');
    if (!in_array($type, $allowed['type'], true)) {
        echo json_encode(['error' => 'Не указан или неверен тип устройства']);
        exit;
    }

    $name = trim($src['name'] ?? '');
    if ($name === '') {
        echo json_encode(['error' => 'Укажите наименование']);
        exit;
    }
    if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);

    $portType = trim($src['port_type'] ?? 'RJ45');
    if (!in_array($portType, $allowed['port_type'], true)) $portType = 'RJ45';

    $status = trim($src['status'] ?? 'в эксплуатации');
    if (!in_array($status, $allowed['status'], true)) $status = 'в эксплуатации';

    $portsCount = (int)($src['ports_count'] ?? 0);
    if ($portsCount < 0)   $portsCount = 0;
    if ($portsCount > 255) $portsCount = 255;   // TINYINT UNSIGNED

    $portRows = (int)($src['port_rows'] ?? 1);
    if ($portRows < 1) $portRows = 1;
    if ($portRows > 4) $portRows = 4;

    $unit = passiveNormalizeUnit($src['unit_position'] ?? '');
    if ($unit === false) {
        echo json_encode(['error' => 'Юнит указывается числом (4) или диапазоном (4-8)']);
        exit;
    }

    // Устройство не может одновременно стоять в шкафу и лежать на складе
    $rackId      = !empty($src['rack_id'])      ? (int)$src['rack_id']      : null;
    $warehouseId = !empty($src['warehouse_id']) ? (int)$src['warehouse_id'] : null;
    if ($rackId && $warehouseId) {
        echo json_encode(['error' => 'Устройство не может одновременно находиться в шкафу и на складе']);
        exit;
    }

    return [
        'type'          => $type,
        'name'          => $name,
        'vendor_id'     => !empty($src['vendor_id']) ? (int)$src['vendor_id'] : null,
        'model'         => trim($src['model'] ?? '') ?: null,
        'ports_count'   => $portsCount,
        'port_type'     => $portType,
        'port_rows'     => $portRows,
        'rack_id'       => $rackId,
        'unit_position' => $unit,
        'warehouse_id'  => $warehouseId,
        'node_id'       => !empty($src['node_id']) ? (int)$src['node_id'] : null,
        'status'        => $status,
        'serial_number' => trim($src['serial_number'] ?? '') ?: null,
        'notes'         => trim($src['notes'] ?? '') ?: null,
    ];
}
