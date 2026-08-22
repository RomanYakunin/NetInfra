<?php
// Временно переключает config/zabbix.php на локальную заглушку для проверки.
$file = __DIR__ . '/config/zabbix.php';
$backup = __DIR__ . '/config/zabbix.backup.php';

if (($_GET['on'] ?? '') === '1') {
    if (!is_file($backup)) copy($file, $backup);
    $cfg = <<<'PHP'
<?php
return [
    'enabled'  => true,
    'url'      => 'http://netinfraphp/_zbxmock.php',
    'user'     => 'netinfra',
    'password' => 'secret',
    'timeout'  => 5,
    'connect_timeout' => 3,
    'verify_ssl' => false,
];
PHP;
    file_put_contents($file, $cfg);
    echo 'вкл';
} else {
    if (is_file($backup)) { copy($backup, $file); unlink($backup); }
    // Кеш сессии заглушки не должен пережить проверку
    foreach (glob(sys_get_temp_dir() . '/netinfra_zbx_*.json') as $f) @unlink($f);
    echo 'выкл';
}
