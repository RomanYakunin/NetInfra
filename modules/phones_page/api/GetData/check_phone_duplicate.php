<?php
/**
 * modules/phones_page/api/GetData/check_phone_duplicate.php
 * (маршрут check_phone_duplicate)
 *
 * Живая проверка полей формы на дубликаты — пока пользователь заполняет,
 * а не после нажатия «Сохранить». Серверные проверки при сохранении
 * никуда не делись: клиентская подсказка их дополняет, а не заменяет.
 *
 * Проверяются только те поля, где повтор действительно означает ошибку
 * или требует внимания. Подразделение, модель, розетка и прочее
 * повторяются у сотен аппаратов — предупреждать о них бессмысленно.
 *
 * Строгость разная:
 *   серийный номер, MAC — ошибка: двух одинаковых аппаратов не бывает;
 *   внутренний номер     — предупреждение: номер переносят за человеком;
 *   IP-адрес            — предупреждение: адрес могли освободить и выдать.
 *
 * MAC и IP сверяем ещё и с таблицей оборудования: адресное пространство
 * общее, и телефон с MAC коммутатора — это ошибка учёта.
 */
require_once dirname(__FILE__, 5) . '/includes/acl.php';
requireAuth();
if (!isset($pdo)) {
    require_once dirname(__FILE__, 5) . '/config/db.php';
}
require_once dirname(__FILE__, 3) . '/phones_common.php';
header('Content-Type: application/json; charset=utf-8');

$field     = trim($_GET['field'] ?? '');
$value     = trim($_GET['value'] ?? '');
$exceptId  = !empty($_GET['id']) ? (int)$_GET['id'] : null;

$checkable = ['serial_number', 'mac_address', 'phone_number', 'ip_address'];
if (!in_array($field, $checkable, true)) {
    echo json_encode(['success' => true, 'checked' => false], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($value === '') {
    echo json_encode(['success' => true, 'checked' => true, 'taken' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Читаемое имя аппарата, занявшего значение. */
function dupPhoneLabel(array $row)
{
    $parts = [];
    if (!empty($row['phone_number'])) $parts[] = 'номер ' . $row['phone_number'];
    if (!empty($row['user_fio']))     $parts[] = $row['user_fio'];
    if (!empty($row['dept']))         $parts[] = $row['dept'];
    if (!$parts && !empty($row['serial_number'])) $parts[] = 'с/н ' . $row['serial_number'];
    return $parts ? implode(', ', $parts) : ('аппарат #' . $row['id']);
}

try {
    $result = ['success' => true, 'checked' => true, 'taken' => false];

    // ---------- Телефоны ----------
    if ($field === 'ip_address') {
        // Адрес хранится ссылкой на справочник, поэтому ищем через него
        $sql = "SELECT p.id, p.phone_number, p.user_fio, p.serial_number, d.name AS dept
                FROM phones p
                JOIN ip_address ip ON p.ip_address_id = ip.Id
                LEFT JOIN departments d ON p.department_id = d.id
                WHERE ip.ip_address = ?";
        $params = [$value];
        if ($exceptId) { $sql .= " AND p.id <> ?"; $params[] = $exceptId; }
        $st = $pdo->prepare($sql . " LIMIT 1");
        $st->execute($params);
    } else {
        $sql = "SELECT p.id, p.phone_number, p.user_fio, p.serial_number, d.name AS dept
                FROM phones p
                LEFT JOIN departments d ON p.department_id = d.id
                WHERE p.`$field` = ?";
        $params = [$value];
        if ($exceptId) { $sql .= " AND p.id <> ?"; $params[] = $exceptId; }
        $st = $pdo->prepare($sql . " LIMIT 1");
        $st->execute($params);
    }

    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $labels = [
            'serial_number' => ['error',   'Серийный номер уже у другого аппарата'],
            'mac_address'   => ['error',   'MAC-адрес уже у другого аппарата'],
            'phone_number'  => ['warning', 'Внутренний номер уже закреплён'],
            'ip_address'    => ['warning', 'IP-адрес уже назначен другому аппарату'],
        ];
        $result['taken']    = true;
        $result['severity'] = $labels[$field][0];
        $result['message']  = $labels[$field][1] . ': ' . dupPhoneLabel($row);
        $result['holder']   = ['type' => 'phone', 'id' => (int)$row['id']];
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---------- Оборудование: адресное пространство общее ----------
    if ($field === 'mac_address') {
        $st = $pdo->prepare("SELECT id, hostname FROM equipment WHERE mac_address = ? LIMIT 1");
        $st->execute([$value]);
        if ($eq = $st->fetch(PDO::FETCH_ASSOC)) {
            $result['taken']    = true;
            $result['severity'] = 'error';
            $result['message']  = 'MAC-адрес занят оборудованием: '
                                . ($eq['hostname'] ?: 'устройство #' . $eq['id']);
            $result['holder']   = ['type' => 'equipment', 'id' => (int)$eq['id']];
        }
    } elseif ($field === 'ip_address') {
        $st = $pdo->prepare("
            SELECT e.id, e.hostname FROM equipment e
            JOIN ip_address ip ON e.ip_address = ip.Id
            WHERE ip.ip_address = ? LIMIT 1
        ");
        $st->execute([$value]);
        if ($eq = $st->fetch(PDO::FETCH_ASSOC)) {
            $result['taken']    = true;
            $result['severity'] = 'warning';
            $result['message']  = 'IP-адрес назначен оборудованию: '
                                . ($eq['hostname'] ?: 'устройство #' . $eq['id']);
            $result['holder']   = ['type' => 'equipment', 'id' => (int)$eq['id']];
        }
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // Проверка вспомогательная — её сбой не должен мешать заполнять форму
    echo json_encode(['success' => false, 'error' => 'Ошибка БД'], JSON_UNESCAPED_UNICODE);
}
