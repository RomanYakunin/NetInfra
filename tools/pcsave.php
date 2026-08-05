<?php
// Временный приёмник результатов браузерной проверки. Удалить после аудита.
file_put_contents(__DIR__ . '/pcresult.txt', file_get_contents('php://input'));
echo 'ok';
