-- Задания 1.6 и 1.7. Не импортировать на хостинге без права TRIGGER.
DELIMITER $$
CREATE TRIGGER block_user BEFORE INSERT ON USER_ACTION_TABLE
FOR EACH ROW
BEGIN
    DECLARE current_time_utc DATETIME;
    DECLARE window_start_value DATETIME;
    DECLARE blocked_until_value DATETIME;
    DECLARE device_block DATETIME;
    DECLARE request_count_value INT;
    DECLARE role_value VARCHAR(12);
    SET current_time_utc = UTC_TIMESTAMP();
    SET NEW.DATE_TIME = current_time_utc;
    SELECT BLOCKED_UNTIL INTO device_block FROM DEVICE_TABLE
      WHERE DEVICE_ID=NEW.DEVICE_ID FOR UPDATE;
    SELECT WINDOW_START, REQUEST_COUNT, BLOCKED_UNTIL, ROLE
      INTO window_start_value, request_count_value, blocked_until_value, role_value
      FROM USER_DEVICE_TABLE
      WHERE USER_ID=NEW.USER_ID AND DEVICE_ID=NEW.DEVICE_ID FOR UPDATE;
    IF NEW.ACTION IN ('control','simulate') AND role_value<>'operator' THEN
        SET NEW.RESULT = 'forbidden';
    ELSEIF blocked_until_value IS NOT NULL AND blocked_until_value>current_time_utc THEN
        SET NEW.RESULT = 'user_blocked';
    ELSE
        IF window_start_value IS NULL OR current_time_utc>=window_start_value+INTERVAL 60 SECOND THEN
            SET window_start_value = current_time_utc;
            SET request_count_value = 0;
        END IF;
        SET request_count_value = request_count_value+1;
        SET blocked_until_value = NULL;
        SET NEW.RESULT = 'allowed';
        IF request_count_value>10 THEN
            SET blocked_until_value = current_time_utc+INTERVAL 60 SECOND;
            SET NEW.RESULT = 'user_blocked';
        ELSEIF device_block IS NOT NULL AND device_block>current_time_utc THEN
            SET NEW.RESULT = 'device_blocked';
        END IF;
        UPDATE USER_DEVICE_TABLE SET WINDOW_START=window_start_value,
          REQUEST_COUNT=request_count_value, BLOCKED_UNTIL=blocked_until_value
          WHERE USER_ID=NEW.USER_ID AND DEVICE_ID=NEW.DEVICE_ID;
    END IF;
END$$

CREATE TRIGGER block_device BEFORE INSERT ON DEVICE_REQUEST_TABLE
FOR EACH ROW
BEGIN
    DECLARE current_time_utc DATETIME;
    DECLARE window_start_value DATETIME;
    DECLARE blocked_until_value DATETIME;
    DECLARE request_count_value INT;
    SET current_time_utc = UTC_TIMESTAMP();
    SET NEW.DATE_TIME = current_time_utc;
    SELECT WINDOW_START, REQUEST_COUNT, BLOCKED_UNTIL
      INTO window_start_value, request_count_value, blocked_until_value
      FROM DEVICE_TABLE WHERE DEVICE_ID=NEW.DEVICE_ID FOR UPDATE;
    IF blocked_until_value IS NOT NULL AND blocked_until_value>current_time_utc THEN
        SET NEW.RESULT = 'blocked';
    ELSE
        IF window_start_value IS NULL OR current_time_utc>=window_start_value+INTERVAL 60 SECOND THEN
            SET window_start_value = current_time_utc;
            SET request_count_value = 0;
        END IF;
        SET request_count_value = request_count_value+1;
        SET blocked_until_value = NULL;
        SET NEW.RESULT = 'allowed';
        IF request_count_value>30 THEN
            SET blocked_until_value = current_time_utc+INTERVAL 60 SECOND;
            SET NEW.RESULT = 'blocked';
        END IF;
        UPDATE DEVICE_TABLE SET WINDOW_START=window_start_value,
          REQUEST_COUNT=request_count_value, BLOCKED_UNTIL=blocked_until_value
          WHERE DEVICE_ID=NEW.DEVICE_ID;
    END IF;
END$$
DELIMITER ;
