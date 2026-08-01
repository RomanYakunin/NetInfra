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
    'get_node'               => 'api/GetData/get_node.php',
    'get_nodes_list'         => 'api/GetData/get_nodes_list.php',
    'get_node_item'          => 'api/GetData/get_node_item.php',
    'get_node_columns'       => 'api/GetData/get_node_columns.php',
    'add_node'               => 'api/AddData/add_node.php',
    'update_node'            => 'api/UpdateData/update_node.php',
    // delete_node обрабатывается прямо в index.php (встроенный SQL)

    // ----------------------------- ЗДАНИЯ -----------------------------
    'get_buildings'          => 'modules/nodes_page/buildings/api/GetData/get_buildings.php',
    'get_building_item'      => 'modules/nodes_page/buildings/api/GetData/get_building_item.php',
    'add_building'           => 'modules/nodes_page/buildings/api/AddData/add_building.php',
    'update_building'        => 'modules/nodes_page/buildings/api/UpdateData/update_building.php',
    'delete_building'        => 'modules/nodes_page/buildings/api/DeleteData/delete_building.php',

    // ----------------------------- ОБОРУДОВАНИЕ -----------------------------
    'get_equipment'          => 'api/GetData/get_equipment.php',
    'get_equipment_item'     => 'api/GetData/get_equipment_item.php',
    'get_equipment_columns'  => 'api/GetData/get_equipment_columns.php',
    'add_equipment'          => 'api/AddData/add_equipment.php',
    'update_equipment'       => 'api/UpdateData/update_equipment.php',
    // delete_equipment – встроенный обработчик в index.php (прямой SQL)

    // ----------------------------- СТЕК -----------------------------
    'save_stack_device'      => 'modules/nodes_page/stack/api/AddData/save_stack_device.php',
    'add_stack'              => 'modules/nodes_page/stack/api/AddData/add_stack.php',
    'get_stack_devices'      => 'modules/nodes_page/stack/api/GetData/get_stack_devices.php',
    'get_stack_info'         => 'modules/nodes_page/stack/api/GetData/get_stack_info.php',
    'get_stack_members'      => 'api/GetData/get_stack_members.php',  // пока не перенесён в модуль
    'save_stack_group'       => 'modules/nodes_page/stack/api/UpdateData/save_stack_group.php',
    'update_stack'           => 'modules/nodes_page/stack/api/UpdateData/update_stack.php',
    'delete_stack_device'    => 'modules/nodes_page/stack/api/DeleteData/delete_stack_device.php',

    // ----------------------------- ПЕРЕМЕЩЕНИЕ -----------------------------
    'move_equipment'         => 'api/MoveData/move_equipment.php',
    'get_node_equipment_for_move' => 'api/GetData/get_node_equipment_for_move.php',

    // ----------------------------- ЛОКАЦИИ / ТИПЫ УЗЛОВ -----------------------------
    'get_locations'          => 'api/GetData/get_locations.php',
    'get_node_types'         => 'api/GetData/get_node_types.php',
    'add_location'           => 'api/AddData/add_location.php',
    'add_node_type'          => 'api/AddData/add_node_type.php',

    // ----------------------------- ОБЩИЕ МЕТА-ДАННЫЕ (справочники) -----------------------------
    'get_list'               => 'api/GetData/get_list.php',
    'get_list_models'        => 'api/GetData/get_list_models.php',   // если не существует, создать
    'add_meta'               => 'api/AddData/add_equipment_meta.php',
    'add_column'             => 'api/AddData/add_column.php',

    // ----------------------------- ПРОЧЕЕ -----------------------------
    // 'check_mac'              => 'api/GetData/check_mac.php',
    'search_hostname' => 'api/GetData/search_hostname.php',
    'check_unique'           => 'api/GetData/check_unique.php',      // может отсутствовать, удалить если нет
];