-- ============================================================
-- Пассивное оборудование: патч-панели, оптические кроссы,
-- SFP-модули, блоки питания, терминалы.
--
-- Хранится отдельно от equipment: у этих устройств нет IP,
-- прошивки, сервисов и прочего, что есть у активного оборудования.
--
-- Запуск: открыть в phpMyAdmin базу NetInfrastructure и выполнить,
-- либо в консоли:
--   mysql -u root NetInfrastructure < migrations/passive_devices.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS passive_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('patch_panel','optical_panel','sfp_module','psu_module','terminal','other') NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'Наименование (например, Патч-панель 24 порта)',
    vendor_id INT UNSIGNED DEFAULT NULL COMMENT 'Производитель (FK vendors)',
    model VARCHAR(100) DEFAULT NULL COMMENT 'Модель',
    ports_count TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Количество портов',
    port_type ENUM('RJ45','LC','SC','FC','ST','SFP','other') DEFAULT 'RJ45' COMMENT 'Тип портов',
    port_rows TINYINT UNSIGNED DEFAULT 1 COMMENT 'Количество рядов портов',
    rack_id INT UNSIGNED DEFAULT NULL COMMENT 'Шкаф (FK racks)',
    unit_position VARCHAR(20) DEFAULT NULL COMMENT 'Позиция в шкафу (число или диапазон)',
    warehouse_id INT UNSIGNED DEFAULT NULL COMMENT 'Склад (FK warehouses)',
    node_id INT UNSIGNED DEFAULT NULL COMMENT 'Узел (FK nodes)',
    status ENUM('в эксплуатации','на складе','обслуживается','демонтирован') DEFAULT 'в эксплуатации',
    serial_number VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_type (type),
    KEY idx_rack (rack_id),
    KEY idx_warehouse (warehouse_id),
    KEY idx_node (node_id),
    CONSTRAINT fk_pd_vendor    FOREIGN KEY (vendor_id)    REFERENCES vendors(id_vendor)  ON DELETE SET NULL,
    CONSTRAINT fk_pd_rack      FOREIGN KEY (rack_id)      REFERENCES racks(id_rack)      ON DELETE SET NULL,
    CONSTRAINT fk_pd_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)      ON DELETE SET NULL,
    CONSTRAINT fk_pd_node      FOREIGN KEY (node_id)      REFERENCES nodes(id_node)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Порты пассивного устройства с привязкой к назначению.
-- Нужны прежде всего оптическим кроссам: куда уходит каждое волокно.
CREATE TABLE IF NOT EXISTS passive_device_ports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL COMMENT 'passive_devices.id',
    port_number TINYINT UNSIGNED NOT NULL COMMENT 'Номер порта',
    label VARCHAR(100) DEFAULT NULL COMMENT 'Метка порта (например, «КУ-34»)',
    destination_building_id INT UNSIGNED DEFAULT NULL COMMENT 'Здание назначения',
    destination_location_id INT UNSIGNED DEFAULT NULL COMMENT 'Локация назначения',
    destination_node_id INT UNSIGNED DEFAULT NULL COMMENT 'Узел назначения',
    destination_equipment_id INT UNSIGNED DEFAULT NULL COMMENT 'Оборудование назначения',
    fiber_type ENUM('одномод','многомод') DEFAULT NULL,
    is_connected TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Порт занят',
    notes TEXT DEFAULT NULL,
    UNIQUE KEY uniq_device_port (device_id, port_number),
    KEY idx_dest_node (destination_node_id),
    CONSTRAINT fk_pdp_device    FOREIGN KEY (device_id)                REFERENCES passive_devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_pdp_building  FOREIGN KEY (destination_building_id)  REFERENCES Buildings(Id)       ON DELETE SET NULL,
    CONSTRAINT fk_pdp_location  FOREIGN KEY (destination_location_id)  REFERENCES locations(id_location) ON DELETE SET NULL,
    CONSTRAINT fk_pdp_node      FOREIGN KEY (destination_node_id)      REFERENCES nodes(id_node)      ON DELETE SET NULL,
    CONSTRAINT fk_pdp_equipment FOREIGN KEY (destination_equipment_id) REFERENCES equipment(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Отличия от исходного задания и почему:
--
-- 1. Столбец `rows` переименован в `port_rows`: ROWS — зарезервированное
--    слово MySQL 8, запрос вида «SELECT rows FROM ...» без обратных кавычек
--    падал бы с синтаксической ошибкой.
-- 2. Добавлены индексы по type / rack_id / warehouse_id / node_id —
--    выборки на странице склада и в панели шкафа идут именно по ним.
-- 3. Добавлен UNIQUE (device_id, port_number): один и тот же порт не должен
--    заводиться дважды.
-- 4. Добавлены FK на destination_node_id и destination_equipment_id
--    (в задании они были без внешних ключей) — чтобы при удалении узла или
--    оборудования ссылка обнулялась, а не висела мусором.
-- 5. Добавлен is_connected — по нему панель шкафа красит занятые порты.
-- 6. ports_count получил DEFAULT 0: у SFP-модуля и блока питания портов нет.
-- ------------------------------------------------------------
