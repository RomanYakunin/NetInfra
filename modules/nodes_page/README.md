# Страница «Узлы» (Nodes Page)

## Общая архитектура
Страница построена по модульному принципу: каждый функциональный блок (узлы, оборудование, перемещение и т.д.) вынесен в отдельную папку со своими PHP‑обработчиками, JavaScript и шаблонами.

## Структура папок
- **nodes/** – CRUD узлов.
- **equipment/** – оборудование в контексте узла (загрузка, отображение, редактирование).
- **move_equipment/** – диалог перемещения оборудования между узлами и складом.
- **stack/** – управление стеком (группой устройств).
- **equipment_details/** – подробная карточка устройства.
- **buildings/** – здания (боковая панель, фильтрация).
- **checklist/** – чек-листы (добавление задач).
- **alerts/** – настройки оповещений (заглушка).
- **rack_panel/** – панель стойки.
- **context_menu/** – общий модуль контекстного меню (используется также на странице склада).

## AJAX-маршруты
Все маршруты начинаются с `?ajax=`. Ниже перечислены основные действия и соответствующие файлы.

### Узлы
| Маршрут          | Файл                                                |
|------------------|-----------------------------------------------------|
| get_nodes_list   | nodes/api/GetData/get_nodes_list.php                |
| get_node         | nodes/api/GetData/get_node.php                      |
| add_node         | nodes/api/AddData/add_node.php                      |
| update_node      | nodes/api/UpdateData/update_node.php                |
| delete_node      | nodes/api/DeleteData/delete_node.php                |

### Оборудование
| Маршрут             | Файл                                                     |
|---------------------|----------------------------------------------------------|
| get_equipment       | equipment/api/GetData/get_equipment.php                  |
| get_equipment_item  | equipment/api/GetData/get_equipment_item.php             |
| add_equipment       | equipment/api/AddData/add_equipment.php                  |
| update_equipment    | equipment/api/UpdateData/update_equipment.php            |
| delete_equipment    | equipment/api/DeleteData/delete_equipment.php            |

... (аналогично для остальных модулей)

## Как добавить новый модуль
1. Создайте папку внутри `modules/nodes_page/`, например `new_feature/`.
2. Внутри разместите JS‑логику, шаблоны и подпапку `api/` с необходимыми обработчиками.
3. Зарегистрируйте маршруты в `index.php`.
4. Подключите скрипты и стили в `nodes_page.php`.
5. При необходимости инициализируйте модуль в `nodes_page.js`.

## Зависимости
- **PHP**: доступ к `$pdo` (глобальное подключение к БД).
- **JS**: глобальные утилиты `loadList`, `fetchJSON`, `showToast`, `SearchableSelect` (из `modules/common/`).
- **Контекстное меню**: функция `showContextMenu(x, y, items, callback)` – доступна глобально.