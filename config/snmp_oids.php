<?php
/**
 * config/snmp_oids.php — конфигурация SNMP-опросов.
 *
 * Здесь задаются пути к утилитам Net-SNMP и карта OID по типам запросов
 * и производителям. Ничего не сохраняется в БД: результат опроса только
 * отображается в интерфейсе.
 *
 * Установка Net-SNMP на Windows описана в конце файла.
 */

// ---------- Пути к утилитам ----------
// Если утилиты добавлены в PATH, достаточно 'snmpget' / 'snmpwalk'.
// Иначе укажите полный путь к .exe.
if (!defined('SNMPGET_PATH')) {
    define('SNMPGET_PATH', 'C:\\usr\\bin\\snmpget.exe');
}
if (!defined('SNMPWALK_PATH')) {
    define('SNMPWALK_PATH', 'C:\\usr\\bin\\snmpwalk.exe');
}

// Таймаут одного запроса (сек) и число повторов — чтобы недоступное
// устройство не подвешивало страницу
if (!defined('SNMP_TIMEOUT')) define('SNMP_TIMEOUT', 3);
if (!defined('SNMP_RETRIES')) define('SNMP_RETRIES', 1);

/**
 * Карта OID.
 *
 * Ключ верхнего уровня — тип запроса, далее:
 *   mode    — get | walk
 *   label   — заголовок для интерфейса
 *   columns — колонки результирующей таблицы
 *   oid     — OID по умолчанию
 *   vendors — переопределение OID для конкретных производителей
 *   parser  — имя функции разбора (см. snmp_query.php)
 */
function snmpOidMap()
{
    return [
        'sysName' => [
            'mode'    => 'get',
            'label'   => 'Системное имя',
            'oid'     => '1.3.6.1.2.1.1.5.0',
            'columns' => ['Параметр', 'Значение'],
            'parser'  => 'snmpParseScalar',
        ],
        'sysDescr' => [
            'mode'    => 'get',
            'label'   => 'Описание системы',
            'oid'     => '1.3.6.1.2.1.1.1.0',
            'columns' => ['Параметр', 'Значение'],
            'parser'  => 'snmpParseScalar',
        ],
        'serial' => [
            'mode'    => 'walk',
            'label'   => 'Серийный номер',
            // ENTITY-MIB entPhysicalSerialNum — работает у большинства вендоров
            'oid'     => '1.3.6.1.2.1.47.1.1.1.1.11',
            'vendors' => [
                'cisco'  => '1.3.6.1.2.1.47.1.1.1.1.11',
                'huawei' => '1.3.6.1.2.1.47.1.1.1.1.11',
                'eltex'  => '1.3.6.1.2.1.47.1.1.1.1.11',
            ],
            'columns' => ['Индекс', 'Серийный номер'],
            'parser'  => 'snmpParseIndexed',
        ],
        'mac' => [
            'mode'    => 'walk',
            'label'   => 'MAC-адреса интерфейсов',
            // IF-MIB ifPhysAddress
            'oid'     => '1.3.6.1.2.1.2.2.1.6',
            'columns' => ['Интерфейс (индекс)', 'MAC-адрес'],
            'parser'  => 'snmpParseIndexed',
        ],
        'lldp' => [
            'mode'    => 'walk',
            'label'   => 'LLDP-соседи',
            // LLDP-MIB lldpRemTable
            'oid'     => '1.0.8802.1.1.2.1.4.1',
            'columns' => ['Локальный порт', 'Имя соседа', 'Порт соседа'],
            'parser'  => 'snmpParseLldp',
        ],
        'ports' => [
            'mode'    => 'walk',
            'label'   => 'Конфигурация портов',
            // IF-MIB ifDescr
            'oid'     => '1.3.6.1.2.1.2.2.1.2',
            'columns' => ['Индекс', 'Порт'],
            'parser'  => 'snmpParseIndexed',
        ],
        'ports_status' => [
            'mode'    => 'walk',
            'label'   => 'Активность портов',
            // IF-MIB ifTable: обходим всю таблицу, парсер соберёт
            // имя (ifDescr), админ-статус (ifAdminStatus), опер-статус
            // (ifOperStatus) и скорость (ifSpeed) в одну строку на порт
            'oid'     => '1.3.6.1.2.1.2.2.1',
            'columns' => ['Порт', 'Состояние', 'Админ. статус', 'Скорость'],
            'parser'  => 'snmpParsePortStatus',
            'limit'   => 2000,
        ],
        'all' => [
            'mode'    => 'walk',
            'label'   => 'Все данные (полный обход)',
            'oid'     => '1.3.6.1.2.1',
            'columns' => ['OID', 'Значение'],
            'parser'  => 'snmpParseRaw',
            'limit'   => 500,   // ограничиваем вывод, чтобы не залить интерфейс
        ],
    ];
}

/**
 * Нормализует название производителя к ключу карты OID.
 */
function snmpNormalizeVendor($vendor)
{
    $v = mb_strtolower(trim((string)$vendor));
    if ($v === '') return '';
    // strpos, а не str_contains: рабочий PHP — 7.4, там str_contains нет
    if (strpos($v, 'cisco')  !== false) return 'cisco';
    if (strpos($v, 'huawei') !== false) return 'huawei';
    if (strpos($v, 'eltex')  !== false) return 'eltex';
    return $v;
}

/*
===============================================================================
УСТАНОВКА NET-SNMP НА WINDOWS (сеть изолирована — дистрибутив принести вручную)

1. Скачать установщик Net-SNMP для Windows с https://sourceforge.net/projects/net-snmp/
   (файл вида net-snmp-5.x.x-x.win32.exe или win64) на машине с интернетом
   и перенести на сервер.
2. Установить. По умолчанию утилиты попадают в C:\usr\bin\ — этот путь уже
   прописан в SNMPGET_PATH / SNMPWALK_PATH выше.
3. Если выбран другой каталог — либо поправить константы в этом файле,
   либо добавить каталог в системный PATH и оставить в константах
   просто 'snmpget' и 'snmpwalk'.
4. Проверить из консоли:
       C:\usr\bin\snmpget.exe -v2c -c public 10.0.0.1 1.3.6.1.2.1.1.5.0
   Должно вернуться sysName устройства.
5. Убедиться, что на сетевом оборудовании включён SNMPv2c и заданная
   community-строка разрешена для IP сервера (ACL).
6. Перезапустить Apache, чтобы PHP увидел изменения PATH (если меняли PATH).

Проверка доступности утилит выполняется в snmp_query.php — при их отсутствии
интерфейс покажет понятное сообщение, а не пустой результат.
===============================================================================
*/
