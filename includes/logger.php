<?php
/**
 * includes/logger.php — журналирование действий пользователей.
 *
 * Подключается в обработчиках, изменяющих данные:
 *     require_once dirname(__FILE__, N) . '/includes/logger.php';
 *     logAction($pdo, 'add_node', 'node', $newId, 'КУ-15');
 *
 * Пользователь, логин и IP берутся из сессии/запроса автоматически.
 * Журнал доступен только на чтение (страница ?page=journal).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Определяет IP-адрес клиента.
 * Учитываем прокси предприятия, но берём только первый адрес из X-Forwarded-For.
 */
function clientIp()
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

/**
 * Записывает событие в таблицu logs.
 *
 * Ошибки записи журнала намеренно проглатываются: сбой логирования
 * не должен ломать основную операцию пользователя.
 *
 * @param PDO         $pdo
 * @param string      $action     add_node, edit_node, delete_node, login, logout, ...
 * @param string|null $objectType node | equipment | stack | building | warehouse | rack | user
 * @param int|null    $objectId   ID изменённого объекта
 * @param string|null $objectName Человекочитаемое название объекта
 * @param mixed       $details    Строка либо массив (будет сохранён как JSON)
 * @return bool                   true, если запись добавлена
 */
function logAction(PDO $pdo, $action, $objectType = null, $objectId = null, $objectName = null, $details = '', $changes = null)
{
    try {
        // Массивы/объекты (например, старые и новые значения) кладём как JSON
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_array($changes) || is_object($changes)) {
            $changes = $changes
                ? json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
        }

        $stmt = $pdo->prepare("
            INSERT INTO logs (user_id, username, ip_address, action, object_type, object_id, object_name, details, changes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['login'] ?? 'system',
            clientIp(),
            $action,
            $objectType ?: null,
            $objectId !== null && $objectId !== '' ? (int)$objectId : null,
            $objectName !== null && $objectName !== '' ? mb_substr((string)$objectName, 0, 255) : null,
            $details !== '' ? $details : null,
            $changes ?: null,
        ]);
        return true;
    } catch (PDOException $e) {
        // Журнал не должен мешать основной операции
        return false;
    }
}

/**
 * Русские подписи полей для журнала. Без них diff читается как дамп БД.
 * Ключи — реальные имена колонок из разных таблиц.
 */
function logFieldLabels()
{
    return [
        'hostname'        => 'Имя хоста',
        'ip_address'      => 'IP-адрес',
        'status'          => 'Статус',
        'serial_number'   => 'Серийный номер',
        'mac_address'     => 'MAC-адрес',
        'Poe'             => 'PoE',
        'Slot'            => 'Слот в стеке',
        'id_node'         => 'Узел',
        'id_rack'         => 'Шкаф',
        'unit_position'   => 'Юнит',
        'warehouse_id'    => 'Склад',
        'device_type_id'  => 'Тип устройства',
        'vendor_id'       => 'Производитель',
        'model_id'        => 'Модель',
        'firmwares'       => 'Прошивка',
        'group_id'        => 'Стек',
        'Annotation'      => 'Примечание',
        'KY_number'       => 'Номер КУ',
        'location_id'     => 'Расположение',
        'node_type_id'    => 'Тип узла',
        'name'            => 'Название',
        'model'           => 'Модель',
        'ports_count'     => 'Количество портов',
        'port_type'       => 'Тип портов',
        'port_rows'       => 'Рядов портов',
        'notes'           => 'Примечание',
        'rack_id'         => 'Шкаф',
        'node_id'         => 'Узел',
        'type'            => 'Тип',
        'description'     => 'Описание',
        'vlan_id'         => 'VLAN',
        'prefix'          => 'Подсеть',
        'speed_mbps'      => 'Скорость, Мбит/с',
        'enabled'         => 'Включён',
        'mode'            => 'Режим порта',
        'label'           => 'Метка',
        'length_m'        => 'Длина, м',
        'color'           => 'Цвет',
    ];
}

/**
 * Сравнивает состояние объекта до и после и возвращает только изменённое.
 *
 * Сравнение нестрогое по строковому представлению: из формы приходят
 * строки, из БД — числа, и «1» против 1 изменением считать нельзя.
 *
 * @param array $before  снимок до изменения (обычно строка из БД)
 * @param array $after   новые значения
 * @param array $ignore  поля, которые не показывать (пароли, служебные)
 * @return array|null    ['поле' => ['from'=>..,'to'=>..,'label'=>..]] или null
 */
function diffFields(array $before, array $after, array $ignore = [])
{
    $labels = logFieldLabels();
    $ignore = array_merge(['password', 'password_hash', 'updated_at', 'created_at'], $ignore);
    $changes = [];

    foreach ($after as $field => $newVal) {
        if (in_array($field, $ignore, true)) continue;
        if (!array_key_exists($field, $before)) continue;

        $oldVal = $before[$field];

        // NULL и пустая строка для пользователя — одно и то же «не заполнено»
        $oldCmp = $oldVal === null ? '' : (string)$oldVal;
        $newCmp = $newVal === null ? '' : (string)$newVal;
        if ($oldCmp === $newCmp) continue;

        // Числовые поля: «10» и «10.00» — не изменение
        if (is_numeric($oldCmp) && is_numeric($newCmp) && (float)$oldCmp === (float)$newCmp) continue;

        $changes[$field] = [
            'label' => $labels[$field] ?? $field,
            'from'  => $oldCmp === '' ? null : $oldCmp,
            'to'    => $newCmp === '' ? null : $newCmp,
        ];
    }

    return $changes ?: null;
}

/**
 * Читает строку таблицы по первичному ключу — снимок «до» для diffFields.
 * Имя таблицы и ключа подставляются в SQL, поэтому вызывать только
 * с константами из кода, не с данными пользователя.
 */
function snapshotRow(PDO $pdo, $table, $pk, $id)
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Удаляет записи журнала старше указанного количества дней.
 * Вызывать вручную или по расписанию — из интерфейса журнал не редактируется.
 */
function purgeOldLogs(PDO $pdo, $days = 90)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([(int)$days]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}
