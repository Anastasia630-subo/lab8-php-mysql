# Лабораторная работа 8

PHP и MySQL. Основа: [уроки 1–4 LAZY SMART](https://lazysmart.ru/uroki-po-sozdaniyu-veb-prilozheniya-dlya-iot/).
Сохранены четыре исходные таблицы, основные переменные, кнопки и параметры устройства.
Добавлены пользователи, несколько устройств, история и ограничения обращений.

## Запуск

1. Импортировать `sql/schema.sql` и `sql/triggers.sql` в MySQL.
2. Скопировать `includes/config.example.php` в `includes/config.php` и указать подключение к БД.
3. Выполнить `php seed.php`, затем `php -S 127.0.0.1:8080 -t public`.
4. Открыть `http://127.0.0.1:8080/login.php`. Учебный вход: `nastya` / `Demo_251361!`.

Подключение к БД находится в `includes/connect.php`, карточка устройства в `includes/device_template.php`.
На странице доступны команды реле, симулятор показаний и история.

Устройство обращается к `device_status.php?ID=<LOGIN>&Term=216&Rele=0` с заголовком
`Authorization: Bearer <ключ>`. Параметры `Term` и `Rele` можно опустить.
Учебные ключи создаются в `var/devices.json` и в Git не публикуются.

На InfinityFree триггеры недоступны: импортируется только `schema.sql`, в закрытом конфиге
устанавливается `use_triggers=false`. Те же ограничения выполняет PHP. Веб-корень: `public`.
