<?php
// Временно переключает config/zabbix.php на локальную заглушку для проверки.
// Скрипт лежит в tools/, поэтому корень проекта — уровнем выше.
$root   = dirname(__DIR__);
$file   = $root . '/config/zabbix.php';
$backup = $root . '/config/zabbix.backup.php';

if (($_GET['on'] ?? '') === '1') {
    if (!is_file($backup)) copy($file, $backup);
    $cfg = <<<'PHP'
<?php
return [
    'enabled'  => true,
    'url'      => 'http://netinfraphp/tools/zabbix_mock.php',
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
