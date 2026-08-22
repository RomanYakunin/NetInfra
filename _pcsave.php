<?php
file_put_contents(__DIR__ . '/_pcresult.txt', file_get_contents('php://input'));
echo 'ok';
