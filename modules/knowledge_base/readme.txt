modules/
├── knowledge_base/
│   ├── knowledge_base.php          // подключается при ?page=knowledge_base
│   ├── knowledge_base_template.php // HTML основной страницы
│   ├── knowledge_base.js           // вся клиентская логика
│   ├── knowledge_base.css          // стили (опционально, можно добавить в общий)
│   └── api/
│       ├── get_tables.php          // список таблиц и их колонок
│       ├── get_rows.php            // данные конкретной таблицы (с пагинацией/поиском)
│       ├── delete_row.php          // универсальное удаление строки
│       └── vendors/                // пример для таблицы vendors
│           ├── add.php
│           ├── update.php
│           └── delete.php