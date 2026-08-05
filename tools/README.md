# tools/ — служебные скрипты разработки

Частью приложения не являются. На рабочем сервере каталог можно удалить
целиком — интерфейс это не затронет.

| Файл | Назначение |
|---|---|
| `pagecheck.php` | Браузерная проверка страниц: открывает разделы в iframe, ловит ошибки JS и прогоняет сценарии |
| `moncheck.php` | Проверка страницы «Панель» и интеграции с Zabbix |
| `pcsave.php` | Приёмник результатов проверок, пишет `pcresult.txt` рядом |
| `zabbix_mock.php` | Заглушка API Zabbix 5.0: отвечает на `apiinfo.version`, `user.login`, `host.get`, `problem.get`, `trigger.get` |
| `zabbix_switch.php` | Временно переключает `config/zabbix.php` на заглушку (`?on=1`) и возвращает обратно (`?on=0`) |

## Как запускать

Проверки открываются в браузере и сохраняют отчёт в `tools/pcresult.txt`:

```bash
start http://netinfraphp/tools/moncheck.php
```

В безголовом режиме:

```bash
chrome --headless --disable-gpu --user-data-dir=%TEMP%\cpv http://netinfraphp/tools/moncheck.php
```

## Заглушка Zabbix

Нужна, когда рабочий сервер мониторинга недоступен с машины разработки.
`zabbix_switch.php?on=1` сохраняет текущий `config/zabbix.php` в
`config/zabbix.backup.php` и подставляет адрес заглушки; `?on=0`
восстанавливает исходный файл и чистит кеш сессии.

Заглушка намеренно повторяет особенности версии 5.0: параметр логина
называется `user` (в 6.0 переименован в `username`), токен передаётся
полем `auth` в теле запроса, а `problem.get` не отдаёт узлы — их
приходится добирать через `trigger.get`.
