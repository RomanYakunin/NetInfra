<?php
// Заглушка API Zabbix 5.0 для проверки клиента без доступа к предприятию.
// Временный файл, удаляется после проверки.
header('Content-Type: application/json');
$req = json_decode(file_get_contents('php://input'), true);
$m   = $req['method'] ?? '';
$p   = $req['params'] ?? [];
$id  = $req['id'] ?? 1;

function ok($result, $id) { echo json_encode(['jsonrpc'=>'2.0','result'=>$result,'id'=>$id]); exit; }
function err($msg, $data, $id) {
    echo json_encode(['jsonrpc'=>'2.0','error'=>['code'=>-32602,'message'=>$msg,'data'=>$data],'id'=>$id]);
    exit;
}

if ($m === 'apiinfo.version') ok('5.0.1', $id);

if ($m === 'user.login') {
    // 5.0 ждёт именно "user"; на "username" настоящий сервер ответит Invalid params
    if (!isset($p['user'])) err('Invalid params.', 'Invalid parameter "/": unexpected parameter "username".', $id);
    if ($p['user'] !== 'netinfra' || ($p['password'] ?? '') !== 'secret') {
        err('Login name or password is incorrect.', '', $id);
    }
    ok('c1b2a3d4e5f60718293a4b5c6d7e8f90', $id);
}

// Дальше всё требует токен в поле auth (до 6.4 заголовка Bearer нет)
if (($req['auth'] ?? '') !== 'c1b2a3d4e5f60718293a4b5c6d7e8f90') {
    err('Not authorised.', 'Session terminated, re-login, please.', $id);
}

if ($m === 'host.get') {
    if (!empty($p['countOutput'])) ok('2', $id);
    ok([
        ['hostid'=>'10084','host'=>'sw-acc-130-01','name'=>'SW-ACC-130-01','status'=>'0',
         'available'=>'1','error'=>'',
         'interfaces'=>[['interfaceid'=>'1','ip'=>'10.2.34.130','dns'=>'','useip'=>'1','port'=>'161','type'=>'2']]],
        ['hostid'=>'10085','host'=>'sw-acc-131-01','name'=>'SW-ACC-131-01','status'=>'0',
         'available'=>'2','error'=>'Timeout while connecting',
         'interfaces'=>[['interfaceid'=>'2','ip'=>'10.2.34.131','dns'=>'','useip'=>'1','port'=>'161','type'=>'2']]],
    ], $id);
}

if ($m === 'problem.get') {
    if (!empty($p['countOutput'])) ok('3', $id);
    $all = [
        ['eventid'=>'901','objectid'=>'2001','clock'=>(string)(time()-600),'r_clock'=>'0',
         'name'=>'Интерфейс Gi0/2: линк пропал','severity'=>'3','acknowledged'=>'0',
         'tags'=>[['tag'=>'scope','value'=>'availability']]],
        ['eventid'=>'902','objectid'=>'2002','clock'=>(string)(time()-8400),'r_clock'=>'0',
         'name'=>'Недоступен по ICMP','severity'=>'5','acknowledged'=>'1',
         'tags'=>[['tag'=>'scope','value'=>'availability']]],
        ['eventid'=>'903','objectid'=>'2003','clock'=>(string)(time()-95),'r_clock'=>'0',
         'name'=>'Высокая загрузка CPU (>90%)','severity'=>'2','acknowledged'=>'0','tags'=>[]],
    ];
    if (!empty($p['severities'])) {
        $want = array_map('intval', $p['severities']);
        $all = array_values(array_filter($all, function ($x) use ($want) {
            return in_array((int)$x['severity'], $want, true);
        }));
    }
    if (isset($p['acknowledged'])) {
        $want = $p['acknowledged'] ? '1' : '0';
        $all = array_values(array_filter($all, function ($x) use ($want) {
            return $x['acknowledged'] === $want;
        }));
    }
    ok($all, $id);
}

// problem.get отдаёт только objectid триггера — узел добирается отсюда
if ($m === 'trigger.get') {
    $map = [
        '2001' => ['10084','sw-acc-130-01','SW-ACC-130-01'],
        '2002' => ['10085','sw-acc-131-01','SW-ACC-131-01'],
        '2003' => ['10084','sw-acc-130-01','SW-ACC-130-01'],
    ];
    $out = [];
    foreach ((array)($p['triggerids'] ?? []) as $tid) {
        if (!isset($map[$tid])) continue;
        [$hostid, $host, $name] = $map[$tid];
        $out[] = ['triggerid'=>$tid,'description'=>'—','priority'=>'3',
                  'hosts'=>[['hostid'=>$hostid,'host'=>$host,'name'=>$name]]];
    }
    ok($out, $id);
}

err('Method not found.', 'Incorrect API "' . $m . '".', $id);
