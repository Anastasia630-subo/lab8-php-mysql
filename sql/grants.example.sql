-- Выполняет администратор ПОСЛЕ schema.sql и bin/seed.php.
-- Перед выполнением замените пароль. После этого внесите реквизиты в config/local.php.
CREATE USER 'lab8_app'@'127.0.0.1' IDENTIFIED BY 'ChangeThisLocalPassword!';
GRANT SELECT ON lab8_iot.users TO 'lab8_app'@'127.0.0.1';
GRANT SELECT ON lab8_iot.devices TO 'lab8_app'@'127.0.0.1';
GRANT UPDATE (last_seen) ON lab8_iot.devices TO 'lab8_app'@'127.0.0.1';
GRANT SELECT ON lab8_iot.device_access TO 'lab8_app'@'127.0.0.1';
GRANT SELECT ON lab8_iot.device_state TO 'lab8_app'@'127.0.0.1';
GRANT UPDATE (temperature,relay,command,temperature_at,relay_at,command_at)
    ON lab8_iot.device_state TO 'lab8_app'@'127.0.0.1';
GRANT SELECT,INSERT ON lab8_iot.user_events TO 'lab8_app'@'127.0.0.1';
GRANT SELECT,INSERT ON lab8_iot.device_events TO 'lab8_app'@'127.0.0.1';
GRANT SELECT ON lab8_iot.v_device_dashboard TO 'lab8_app'@'127.0.0.1';
-- Триггеры и VIEW созданы установщиком: работают с его правами (DEFINER).
-- Приложение не получает DELETE, DDL, TRIGGER и право снимать блокировки.
