<?php
/**
 * modules/zabbix/ZabbixClient.php
 *
 * Клиент JSON-RPC API Zabbix. Написан под 5.0, но подстраивается под
 * версию сервера — она узнаётся методом apiinfo.version, который
 * единственный работает без авторизации.
 *
 * Различия версий, которые здесь учтены:
 *   до 5.4  — постоянных API-токенов нет, только user.login;
 *   до 6.0  — параметр логина называется user, начиная с 6.0 — username;
 *   до 6.4  — токен передаётся полем auth в теле запроса,
 *             с 6.4 — заголовком Authorization: Bearer.
 *
 * Сессия кешируется в файле: user.login на каждый запрос создаёт в
 * Zabbix новую сессию, и за день их накопятся тысячи.
 *
 * Зависимостей нет — только расширение curl, оно есть в поставке.
 */

class ZabbixClient
{
    /** @var array настройки из config/zabbix.php */
    private $cfg;

    /** @var string|null токен сессии */
    private $auth = null;

    /** @var string|null версия сервера, например «5.0.1» */
    private $version = null;

    /** @var string текст последней ошибки */
    private $error = '';

    /** @var string путь к файлу с кешем сессии */
    private $sessionFile;

    public function __construct(array $cfg = null)
    {
        $this->cfg = $cfg ?: self::loadConfig();
        // Кеш кладём рядом с проектом, имя привязано к адресу и логину:
        // при смене сервера старая сессия не подхватится
        $key = md5(($this->cfg['url'] ?? '') . '|' . ($this->cfg['user'] ?? ''));
        $this->sessionFile = sys_get_temp_dir() . '/netinfra_zbx_' . $key . '.json';
    }

    /** Читает config/zabbix.php; при отсутствии файла возвращает выключенную конфигурацию. */
    public static function loadConfig()
    {
        $file = dirname(__FILE__, 3) . '/config/zabbix.php';
        if (!is_file($file)) {
            return ['enabled' => false, 'url' => '', 'user' => '', 'password' => ''];
        }
        $cfg = require $file;
        return is_array($cfg) ? $cfg : ['enabled' => false];
    }

    public function isEnabled()
    {
        return !empty($this->cfg['enabled'])
            && !empty($this->cfg['url'])
            && !empty($this->cfg['user']);
    }

    public function getError()   { return $this->error; }
    public function getVersion() { return $this->version; }

    // ------------------------------------------------------------------
    // Транспорт
    // ------------------------------------------------------------------

    /**
     * Один вызов JSON-RPC.
     *
     * @param string $method    например host.get
     * @param array  $params
     * @param bool   $needAuth  apiinfo.version и user.login вызываются без токена
     * @return array|null       result или null при ошибке (см. getError)
     */
    public function call($method, array $params = [], $needAuth = true)
    {
        $this->error = '';

        if (empty($this->cfg['url'])) {
            $this->error = 'Не указан адрес API Zabbix';
            return null;
        }

        if ($needAuth && $this->auth === null && !$this->login()) {
            return null;
        }

        $payload = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params ?: new stdClass(),
            'id'      => 1,
        ];

        $headers = ['Content-Type: application/json-rpc'];

        // До 6.4 токен идёт в теле. Поле auth в 7.0 удалено, поэтому
        // для новых версий переключаемся на заголовок.
        if ($needAuth) {
            if ($this->atLeast('6.4')) {
                $headers[] = 'Authorization: Bearer ' . $this->auth;
            } else {
                $payload['auth'] = $this->auth;
            }
        }

        $raw = $this->httpPost(json_encode($payload, JSON_UNESCAPED_UNICODE), $headers);
        if ($raw === null) return null;   // ошибка транспорта уже в $this->error

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->error = 'Сервер вернул не JSON: ' . mb_substr(trim($raw), 0, 200);
            return null;
        }

        if (isset($data['error'])) {
            $e = $data['error'];
            // data обычно содержит суть («Invalid params», «Not authorised»)
            $this->error = trim(($e['message'] ?? 'Ошибка API')
                . ' ' . ($e['data'] ?? ''));

            // Сессия протухла — один раз перелогиниваемся и повторяем
            if ($needAuth && $this->looksLikeAuthError($this->error)) {
                $this->forgetSession();
                if ($this->login()) {
                    return $this->call($method, $params, $needAuth);
                }
            }
            return null;
        }

        return array_key_exists('result', $data) ? $data['result'] : null;
    }

    /** Признаки протухшей сессии в тексте ошибки Zabbix. */
    private function looksLikeAuthError($msg)
    {
        $msg = mb_strtolower($msg);
        foreach (['not authorised', 'not authorized', 're-login', 'session terminated'] as $needle) {
            if (mb_strpos($msg, $needle) !== false) return true;
        }
        return false;
    }

    /** POST через curl. Возвращает тело ответа или null. */
    private function httpPost($body, array $headers)
    {
        $ch = curl_init($this->cfg['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int)($this->cfg['timeout'] ?? 10),
            CURLOPT_CONNECTTIMEOUT => (int)($this->cfg['connect_timeout'] ?? 4),
            CURLOPT_SSL_VERIFYPEER => !empty($this->cfg['verify_ssl']),
            CURLOPT_SSL_VERIFYHOST => !empty($this->cfg['verify_ssl']) ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        $raw  = curl_exec($ch);
        $errNo = curl_errno($ch);
        $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo) {
            // Сетевые ошибки объясняем по-человечески: в закрытой сети
            // это чаще всего межсетевой экран или опечатка в адресе
            $map = [
                CURLE_COULDNT_CONNECT   => 'Сервер не отвечает — проверьте адрес и правила межсетевого экрана',
                CURLE_COULDNT_RESOLVE_HOST => 'Не удалось разрешить имя хоста',
                CURLE_OPERATION_TIMEDOUT   => 'Превышено время ожидания ответа',
                CURLE_SSL_PEER_CERTIFICATE => 'Сертификат не прошёл проверку — при самоподписанном укажите verify_ssl = false',
            ];
            $this->error = ($map[$errNo] ?? 'Ошибка соединения') . ' (curl ' . $errNo . ')';
            return null;
        }

        if ($code === 404) {
            $this->error = 'HTTP 404: по этому адресу нет api_jsonrpc.php — возможно, Zabbix стоит в подкаталоге /zabbix/';
            return null;
        }
        if ($code >= 400) {
            $this->error = 'HTTP ' . $code . ' от сервера Zabbix';
            return null;
        }

        return $raw;
    }

    // ------------------------------------------------------------------
    // Авторизация
    // ------------------------------------------------------------------

    /** Версия сервера. Метод не требует авторизации — им же проверяем связь. */
    public function fetchVersion()
    {
        if ($this->version !== null) return $this->version;
        $v = $this->call('apiinfo.version', [], false);
        if (is_string($v)) $this->version = $v;
        return $this->version;
    }

    /** Сравнение версии сервера с заданной: atLeast('6.4'). */
    private function atLeast($target)
    {
        if ($this->version === null) return false;
        return version_compare($this->version, $target, '>=');
    }

    /**
     * Логин с кешированием сессии.
     * Токен из кеша проверяется дешёвым вызовом: если он мёртв,
     * Zabbix ответит «Not authorised», и мы залогинимся заново.
     */
    public function login()
    {
        if (empty($this->cfg['user'])) {
            $this->error = 'Не указана учётная запись Zabbix';
            return false;
        }

        $this->fetchVersion();
        if ($this->version === null) {
            // Ошибка уже записана в fetchVersion — связи нет
            return false;
        }

        if ($this->auth === null) {
            $this->auth = $this->readSession();
            if ($this->auth !== null) return true;
        }

        // До 6.0 параметр называется user, с 6.0 — username.
        // Это самая частая причина «Invalid params» на старых серверах.
        $loginField = $this->atLeast('6.0') ? 'username' : 'user';

        $result = $this->call('user.login', [
            $loginField => $this->cfg['user'],
            'password'  => $this->cfg['password'] ?? '',
        ], false);

        if (!is_string($result) || $result === '') {
            if ($this->error === '') $this->error = 'Zabbix не вернул токен сессии';
            return false;
        }

        $this->auth = $result;
        $this->writeSession($result);
        return true;
    }

    private function readSession()
    {
        if (!is_file($this->sessionFile)) return null;
        $data = json_decode((string)@file_get_contents($this->sessionFile), true);
        if (!is_array($data) || empty($data['auth'])) return null;
        // Сессии Zabbix живут долго, но держать вечный кеш незачем
        if (($data['created'] ?? 0) < time() - 3600) return null;
        return $data['auth'];
    }

    private function writeSession($auth)
    {
        @file_put_contents($this->sessionFile, json_encode([
            'auth'    => $auth,
            'created' => time(),
        ]));
        // Токен равносилен паролю на время жизни сессии
        @chmod($this->sessionFile, 0600);
    }

    private function forgetSession()
    {
        $this->auth = null;
        @unlink($this->sessionFile);
    }

    // ------------------------------------------------------------------
    // Прикладные методы
    // ------------------------------------------------------------------

    /**
     * Проверка связи: версия, доступность авторизации, счётчики.
     * Возвращает массив для страницы диагностики — не бросает исключений.
     */
    public function diagnose()
    {
        $out = [
            'url'       => $this->cfg['url'] ?? '',
            'enabled'   => $this->isEnabled(),
            'reachable' => false,
            'version'   => null,
            'logged_in' => false,
            'hosts'     => null,
            'problems'  => null,
            'error'     => '',
        ];

        if (empty($this->cfg['url'])) {
            $out['error'] = 'Не указан адрес API в config/zabbix.php';
            return $out;
        }

        $version = $this->fetchVersion();
        if ($version === null) {
            $out['error'] = $this->error;
            return $out;
        }
        $out['reachable'] = true;
        $out['version']   = $version;

        if (!$this->login()) {
            $out['error'] = $this->error;
            return $out;
        }
        $out['logged_in'] = true;

        // Считаем узлы: заодно убеждаемся, что у учётки есть права на чтение
        $hosts = $this->call('host.get', [
            'countOutput' => true,
            'filter'      => ['status' => 0],   // 0 — узел под мониторингом
        ]);
        if ($hosts === null) {
            $out['error'] = $this->error;
            return $out;
        }
        $out['hosts'] = (int)$hosts;

        $problems = $this->call('problem.get', ['countOutput' => true]);
        $out['problems'] = $problems === null ? null : (int)$problems;

        return $out;
    }

    /**
     * Узлы под мониторингом вместе с их сетевыми интерфейсами.
     * По интерфейсам потом сопоставляем оборудование с нашей базой.
     */
    public function getHosts()
    {
        return $this->call('host.get', [
            'output'           => ['hostid', 'host', 'name', 'status', 'available', 'error'],
            'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'useip', 'port', 'type'],
            'filter'           => ['status' => 0],
            'sortfield'        => 'name',
        ]);
    }

    /** Активные проблемы «как есть»: узел здесь ещё не известен. */
    public function getProblems()
    {
        return $this->call('problem.get', [
            'output'       => ['eventid', 'objectid', 'clock', 'name', 'severity', 'acknowledged'],
            'selectTags'   => 'extend',
            'recent'       => false,
            'sortfield'    => ['eventid'],
            'sortorder'    => 'DESC',
        ]);
    }

    /** Русские названия уровней важности — как в интерфейсе Zabbix. */
    public static function severityLabels()
    {
        return [
            0 => 'Не классифицировано',
            1 => 'Информация',
            2 => 'Предупреждение',
            3 => 'Средняя',
            4 => 'Высокая',
            5 => 'Чрезвычайная',
        ];
    }

    /**
     * Проблемы вместе с узлами, на которых они возникли.
     *
     * problem.get в 5.0 отдаёт только objectid триггера — узла в ответе
     * нет. Поэтому вторым запросом добираем триггеры с их узлами и
     * сшиваем. Делать это по одному триггеру на проблему нельзя:
     * при сотне аварий получилась бы сотня запросов.
     *
     * @param array $filter severities[], acknowledged (bool|null), search
     * @return array|null
     */
    public function getProblemsWithHosts(array $filter = [])
    {
        $params = [
            'output'     => ['eventid', 'objectid', 'clock', 'r_clock', 'name', 'severity', 'acknowledged'],
            'selectTags' => 'extend',
            'recent'     => false,
            'sortfield'  => ['eventid'],
            'sortorder'  => 'DESC',
            'limit'      => 500,
        ];
        if (!empty($filter['severities'])) {
            $params['severities'] = array_map('intval', $filter['severities']);
        }
        if (isset($filter['acknowledged']) && $filter['acknowledged'] !== null) {
            $params['acknowledged'] = (bool)$filter['acknowledged'];
        }

        $problems = $this->call('problem.get', $params);
        if (!is_array($problems)) return $problems;   // null — ошибка уже записана
        if (!$problems) return [];

        // Один запрос на все триггеры сразу
        $triggerIds = array_values(array_unique(array_column($problems, 'objectid')));
        $triggers = $this->call('trigger.get', [
            'triggerids'  => $triggerIds,
            'output'      => ['triggerid', 'description', 'priority'],
            'selectHosts' => ['hostid', 'host', 'name'],
        ]);

        $hostByTrigger = [];
        foreach ((array)$triggers as $t) {
            $h = $t['hosts'][0] ?? null;
            $hostByTrigger[$t['triggerid']] = [
                'hostid'    => $h['hostid'] ?? null,
                'host_name' => $h['name'] ?? ($h['host'] ?? null),
            ];
        }

        $labels = self::severityLabels();
        $now = time();
        foreach ($problems as &$p) {
            $link = $hostByTrigger[$p['objectid']] ?? ['hostid' => null, 'host_name' => null];
            $p['hostid']         = $link['hostid'];
            $p['host_name']      = $link['host_name'];
            $p['severity']       = (int)$p['severity'];
            $p['severity_label'] = $labels[$p['severity']] ?? '—';
            $p['clock']          = (int)$p['clock'];
            $p['acknowledged']   = (int)$p['acknowledged'];
            $p['duration_sec']   = max(0, $now - $p['clock']);
        }
        unset($p);

        // Поиск делаем на своей стороне: он идёт и по имени узла,
        // а его в фильтре problem.get нет
        if (!empty($filter['search'])) {
            $needle = mb_strtolower(trim($filter['search']));
            $problems = array_values(array_filter($problems, function ($p) use ($needle) {
                return mb_strpos(mb_strtolower($p['name'] . ' ' . (string)$p['host_name']), $needle) !== false;
            }));
        }

        return $problems;
    }
}
