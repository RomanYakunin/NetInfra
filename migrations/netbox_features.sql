-- migrations/netbox_features.sql
-- Пять функций по образцу NetBox: журнал изменений с полями, шаблоны
-- моделей, интерфейсы оборудования, IPAM (подсети и VLAN), кабельный
-- журнал с трассировкой.
--
-- Миграция идемпотентна настолько, насколько это позволяет MySQL 5.7/8:
-- CREATE TABLE IF NOT EXISTS безопасен, ALTER TABLE ADD COLUMN — нет,
-- поэтому повторный запуск ALTER выдаст ошибку «Duplicate column».
-- Это не страшно: остальные операции уже применились.

-- =====================================================================
-- 1. ЖУРНАЛ ИЗМЕНЕНИЙ: снимок изменённых полей
-- =====================================================================
-- details остаётся для произвольного текста, changes хранит только
-- {"поле": {"from": "...", "to": "..."}} — по нему строится «что менялось».
ALTER TABLE logs ADD COLUMN changes TEXT NULL AFTER details;


-- =====================================================================
-- 2. ШАБЛОНЫ МОДЕЛЕЙ УСТРОЙСТВ
-- =====================================================================
-- При добавлении оборудования интерфейсы создаются по этим полям,
-- как component templates в NetBox.
ALTER TABLE device_models
    ADD COLUMN u_height           TINYINT  UNSIGNED NOT NULL DEFAULT 1,
    ADD COLUMN ports_count        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN port_type          VARCHAR(20)  NULL,
    ADD COLUMN port_name_pattern  VARCHAR(60)  NULL,
    ADD COLUMN uplink_count       TINYINT  UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN uplink_type        VARCHAR(20)  NULL,
    ADD COLUMN uplink_name_pattern VARCHAR(60) NULL;


-- =====================================================================
-- 3. ИНТЕРФЕЙСЫ ОБОРУДОВАНИЯ
-- =====================================================================
CREATE TABLE IF NOT EXISTS equipment_interfaces (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    equipment_id  INT UNSIGNED NOT NULL,
    name          VARCHAR(64)  NOT NULL,
    if_index      INT UNSIGNED NULL COMMENT 'ifIndex из SNMP',
    type          ENUM('copper','fiber','sfp','sfp+','qsfp','virtual','other')
                  NOT NULL DEFAULT 'copper',
    speed_mbps    INT UNSIGNED NULL,
    mac_address   VARCHAR(17)  NULL,
    description   VARCHAR(255) NULL,
    enabled       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'админ. состояние',
    oper_status   ENUM('up','down','unknown') NOT NULL DEFAULT 'unknown',
    last_polled   DATETIME     NULL,
    mode          ENUM('none','access','trunk') NOT NULL DEFAULT 'none',
    vlan_id       INT UNSIGNED NULL COMMENT 'нетегированный VLAN',
    is_uplink     TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_equipment_iface (equipment_id, name),
    KEY idx_iface_equipment (equipment_id),
    KEY idx_iface_vlan (vlan_id),
    CONSTRAINT fk_iface_equipment FOREIGN KEY (equipment_id)
        REFERENCES equipment (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================================
-- 4. IPAM: VLAN И ПОДСЕТИ
-- =====================================================================
CREATE TABLE IF NOT EXISTS vlans (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vid         SMALLINT UNSIGNED NOT NULL COMMENT 'номер VLAN 1-4094',
    name        VARCHAR(64)  NOT NULL,
    description VARCHAR(255) NULL,
    building_id INT UNSIGNED NULL COMMENT 'область действия, NULL = вся сеть',
    role        VARCHAR(60)  NULL,
    status      ENUM('активен','резерв','выведен') NOT NULL DEFAULT 'активен',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vlan_vid (vid, building_id),
    KEY idx_vlan_building (building_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Границы сети храним числами: так занятость считается одним запросом
-- через INET_ATON, без перебора адресов в PHP.
CREATE TABLE IF NOT EXISTS ip_prefixes (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prefix        VARCHAR(43)  NOT NULL COMMENT 'например 10.2.34.0/24',
    network_start INT UNSIGNED NOT NULL,
    network_end   INT UNSIGNED NOT NULL,
    mask_len      TINYINT UNSIGNED NOT NULL,
    description   VARCHAR(255) NULL,
    vlan_id       INT UNSIGNED NULL,
    building_id   INT UNSIGNED NULL,
    role          VARCHAR(60)  NULL,
    status        ENUM('используется','резерв','устарел') NOT NULL DEFAULT 'используется',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prefix (prefix),
    KEY idx_prefix_range (network_start, network_end),
    KEY idx_prefix_vlan (vlan_id),
    CONSTRAINT fk_prefix_vlan FOREIGN KEY (vlan_id)
        REFERENCES vlans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================================
-- 5. КАБЕЛЬНЫЙ ЖУРНАЛ
-- =====================================================================
-- Кабель соединяет две точки подключения. Точка — либо интерфейс
-- оборудования, либо лицевой порт патч-панели/кросса. Тыльная сторона
-- порта панели уже описана полями destination_* в passive_device_ports,
-- поэтому трассировка идёт: интерфейс → кабель → порт панели → тыл.
--
-- Один порт может иметь только один кабель. Ограничение проверяется в
-- PHP: двух UNIQUE-индексов не хватит, потому что порт может оказаться
-- концом A в одной строке и концом B в другой.
CREATE TABLE IF NOT EXISTS cables (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    label      VARCHAR(64) NULL,
    type       ENUM('cat5e','cat6','cat6a','ftp','om3','om4','os2','dac','power','other')
               NOT NULL DEFAULT 'cat5e',
    length_m   DECIMAL(6,2) NULL,
    color      VARCHAR(20) NULL,
    status     ENUM('подключён','запланирован','отключён') NOT NULL DEFAULT 'подключён',
    a_type     ENUM('interface','passive_port') NOT NULL,
    a_id       INT UNSIGNED NOT NULL,
    b_type     ENUM('interface','passive_port') NOT NULL,
    b_id       INT UNSIGNED NOT NULL,
    notes      TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cable_a (a_type, a_id),
    KEY idx_cable_b (b_type, b_id),
    KEY idx_cable_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
