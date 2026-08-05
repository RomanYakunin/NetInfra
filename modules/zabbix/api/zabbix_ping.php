<?php
// modules/zabbix/api/zabbix_ping.php  (маршрут zabbix_ping)
// Проверка связи с Zabbix: версия, авторизация, число узлов и проблем.
// Диагностика показывает настройки, поэтому доступна только администратору.
require_once dirname(__FILE__, 4) . '/includes/acl.php';
requireAdmin();
require_once dirname(__FILE__, 3) . '/zabbix/ZabbixClient.php';
header('Content-Type: application/json; charset=utf-8');

$client = new ZabbixClient();
$result = $client->diagnose();

// Пароль наружу не отдаём ни при каких обстоятельствах
$cfg = ZabbixClient::loadConfig();
$result['user'] = $cfg['user'] ?? '';
$result['config_file_exists'] = is_file(dirname(__FILE__, 4) . '/config/zabbix.php');

echo json_encode(['success' => empty($result['error']), 'data' => $result], JSON_UNESCAPED_UNICODE);
