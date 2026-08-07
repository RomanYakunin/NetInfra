-- migrations/document_storage.sql
-- Перенос документов из uploads/ в назначаемую сетевую папку.
--
-- Раньше файлы лежали плоско в uploads/phone_docs/ и имя на диске
-- полностью описывало путь. Теперь корень задаётся в настройках, а
-- внутри него документы раскладываются по подразделениям и годам,
-- поэтому одного имени мало — нужен путь относительно корня.
--
-- Старые записи остаются с NULL: для них путь по-прежнему вычисляется
-- как uploads/phone_docs/<stored_name>, и они продолжают открываться.

ALTER TABLE phone_documents
    ADD COLUMN rel_path VARCHAR(400) NULL
        COMMENT 'Путь относительно корня хранилища; NULL — файл в старом uploads/phone_docs/'
        AFTER stored_name,
    ADD COLUMN department_id INT UNSIGNED NULL
        COMMENT 'Подразделение, в папку которого положен документ'
        AFTER rel_path,
    ADD COLUMN source ENUM('upload','scan') NOT NULL DEFAULT 'upload'
        COMMENT 'Файл загрузили или отсканировали из приложения'
        AFTER department_id;

ALTER TABLE phone_documents
    ADD KEY idx_doc_department (department_id);
