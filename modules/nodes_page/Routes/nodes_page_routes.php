<?php
/**
 * Маршруты AJAX-обработчиков модуля Nodes Page
 *
 * Возвращает ассоциативный массив: ключ — значение параметра ajax,
 * значение — путь к файлу обработчика относительно корня проекта.
 *
 * Подключение в index.php: require_once __DIR__ . '/' . $routes[$action];
 */

return [
    // ----------------------------- УЗЛЫ -----------------------------
    'get_node'               => 'modules/nodes_page/api/GetData/get_node.php',
    'get_nodes_list'         => 'modules/nodes_page/api/GetData/get_nodes_list.php',
    'get_node_item'          => 'modules/nodes_page/api/GetData/get_node_item.php',
    'get_node_columns'       => 'modules/nodes_page/api/GetData/get_node_columns.php',
    'add_node'               => 'modules/nodes_page/api/AddData/add_node.php',
    'update_node'            => 'modules/nodes_page/api/UpdateData/update_node.php',
    // delete_node обрабатывается прямо в index.php (встроенный SQL)

    // ----------------------------- ЗДАНИЯ -----------------------------
    'get_buildings'          => 'modules/nodes_page/buildings/api/GetData/get_buildings.php',
    'get_building_item'      => 'modules/nodes_page/buildings/api/GetData/get_building_item.php',
    'add_building'           => 'modules/nodes_page/buildings/api/AddData/add_building.php',
    'update_building'        => 'modules/nodes_page/buildings/api/UpdateData/update_building.php',
    'delete_building'        => 'modules/nodes_page/buildings/api/DeleteData/delete_building.php',

    // ----------------------------- ОБОРУДОВАНИЕ -----------------------------
    'get_equipment'          => 'modules/nodes_page/api/GetData/get_equipment.php',
    'get_equipment_item'     => 'modules/nodes_page/api/GetData/get_equipment_item.php',
    'get_equipment_columns'  => 'modules/nodes_page/api/GetData/get_equipment_columns.php',
    'add_equipment'          => 'modules/nodes_page/api/AddData/add_equipment.php',
    'update_equipment'       => 'modules/nodes_page/api/UpdateData/update_equipment.php',
    // delete_equipment – встроенный обработчик в index.php (прямой SQL)

    // ----------------------------- СТЕК -----------------------------
    'save_stack_device'      => 'modules/nodes_page/stack/api/AddData/save_stack_device.php',
    'add_stack'              => 'modules/nodes_page/stack/api/AddData/add_stack.php',
    'get_stack_devices'      => 'modules/nodes_page/stack/api/GetData/get_stack_devices.php',
    'get_stack_info'         => 'modules/nodes_page/stack/api/GetData/get_stack_info.php',
    'get_stack_members'      => 'modules/nodes_page/api/GetData/get_stack_members.php',  // пока не перенесён в модуль (файл ещё не создан)
    'save_stack_group'       => 'modules/nodes_page/stack/api/UpdateData/save_stack_group.php',
    'update_stack'           => 'modules/nodes_page/stack/api/UpdateData/update_stack.php',
    'delete_stack_device'    => 'modules/nodes_page/stack/api/DeleteData/delete_stack_device.php',

    // ----------------------------- ПЕРЕМЕЩЕНИЕ -----------------------------
    'move_equipment'         => 'modules/nodes_page/api/MoveData/move_equipment.php',
    'get_node_equipment_for_move' => 'modules/nodes_page/api/GetData/get_node_equipment_for_move.php',

    // ----------------------------- ЛОКАЦИИ / ТИПЫ УЗЛОВ -----------------------------
    'get_locations'          => 'modules/nodes_page/buildings/api/GetData/get_locations.php',
    'get_node_types'         => 'modules/nodes_page/api/GetData/get_node_types.php',
    'add_location'           => 'modules/nodes_page/buildings/api/AddData/add_location.php',
    'add_node_type'          => 'modules/nodes_page/api/AddData/add_node_type.php',

    // ----------------------------- ОБЩИЕ МЕТА-ДАННЫЕ (справочники) -----------------------------
    'get_list'               => 'modules/nodes_page/api/GetData/get_list.php',
    'get_list_models'        => 'modules/nodes_page/api/GetData/get_list_models.php',   // если не существует, создать
    'add_meta'               => 'modules/nodes_page/api/AddData/add_equipment_meta.php',
    'add_column'             => 'modules/nodes_page/api/AddData/add_column.php',

    // ----------------------------- ШКАФЫ -----------------------------
    'add_rack'                => 'modules/nodes_page/api/AddData/add_rack.php',
    'get_node_racks'          => 'modules/nodes_page/api/GetData/get_node_racks.php',
    // Обработчик принимает и equipment_id, и node_id. Раньше вызывался только
    // из index.php по условию isset($_GET['equipment_id']), поэтому запрос с
    // node_id не доходил до него и получал «Unknown AJAX action».
    'get_rack'                => 'modules/nodes_page/api/GetData/get_rack.php',
    'get_equipment_lldp'      => 'modules/nodes_page/api/GetData/get_equipment_lldp.php',
    'snmp_query'              => 'modules/nodes_page/api/GetData/snmp_query.php',
    'update_rack_unit'        => 'modules/nodes_page/api/UpdateData/update_rack_unit.php',

    // ----------------------------- БАЗА ЗНАНИЙ (управление таблицами БД) -----------------------------
    'kb_get_tables'           => 'modules/knowledge_base/api/kb_get_tables.php',
    'kb_get_columns'          => 'modules/knowledge_base/api/kb_get_columns.php',
    'kb_get_rows'             => 'modules/knowledge_base/api/kb_get_rows.php',
    'kb_add_column'           => 'modules/knowledge_base/api/kb_add_column.php',
    'kb_update_column'        => 'modules/knowledge_base/api/kb_update_column.php',
    'kb_delete_column'        => 'modules/knowledge_base/api/kb_delete_column.php',
    'kb_add_row'              => 'modules/knowledge_base/api/kb_add_row.php',
    'kb_update_row'           => 'modules/knowledge_base/api/kb_update_row.php',
    'kb_delete_row'           => 'modules/knowledge_base/api/kb_delete_row.php',

    // ----------------------------- ЖУРНАЛ ДЕЙСТВИЙ -----------------------------
    'get_logs'                => 'modules/journal/api/get_logs.php',
    'get_log_detail'          => 'modules/journal/api/get_log_detail.php',
    'get_object_logs'         => 'modules/journal/api/get_object_logs.php',   // история одного объекта

    // ----------------------------- ПОЛЬЗОВАТЕЛИ -----------------------------
    'get_users'               => 'modules/users/api/GetData/get_users.php',
    'add_user'                => 'modules/users/api/AddData/add_user.php',
    'update_user'             => 'modules/users/api/UpdateData/update_user.php',
    'delete_user'             => 'modules/users/api/DeleteData/delete_user.php',
    'change_password'         => 'modules/users/api/UpdateData/change_password.php',

    // ----------------------------- ПРОЧЕЕ -----------------------------
    // 'check_mac'              => 'api/GetData/check_mac.php',
    'search_hostname' => 'modules/nodes_page/api/GetData/search_hostname.php',
    'check_unique'           => 'modules/nodes_page/api/GetData/check_unique.php',      // может отсутствовать, удалить если нет
];
