-- MariaDB 10.4+ (XAMPP) / MySQL 8.0.16+. Импортировать в выбранную ПУСТУЮ БД.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uid CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    last_seen DATETIME(6) NULL,
    window_start DATETIME(6) NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until DATETIME(6) NULL
) ENGINE=InnoDB;

CREATE TABLE device_access (
    user_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(16) NOT NULL DEFAULT 'operator',
    window_start DATETIME(6) NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until DATETIME(6) NULL,
    PRIMARY KEY (user_id, device_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (device_id) REFERENCES devices(id),
    CONSTRAINT ck_access_role CHECK (role IN ('viewer', 'operator'))
) ENGINE=InnoDB;

CREATE TABLE device_state (
    device_id BIGINT UNSIGNED PRIMARY KEY,
    temperature DECIMAL(5,1) NULL,
    relay TINYINT NULL,
    command TINYINT NULL,
    temperature_at DATETIME(6) NULL,
    relay_at DATETIME(6) NULL,
    command_at DATETIME(6) NULL,
    FOREIGN KEY (device_id) REFERENCES devices(id),
    CHECK (temperature IS NULL OR temperature BETWEEN -55 AND 125),
    CHECK (relay IS NULL OR relay IN (0,1)),
    CHECK (command IS NULL OR command IN (0,1))
) ENGINE=InnoDB;

CREATE TABLE user_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(16) NOT NULL,
    command TINYINT NULL,
    happened_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    outcome VARCHAR(24) NOT NULL DEFAULT 'allowed',
    FOREIGN KEY (user_id, device_id) REFERENCES device_access(user_id, device_id),
    CHECK (action IN ('view','history','control')),
    CHECK (command IS NULL OR command IN (0,1)),
    CHECK ((action = 'control' AND command IS NOT NULL)
        OR (action <> 'control' AND command IS NULL)),
    CHECK (outcome IN ('allowed','user_blocked','device_blocked','forbidden')),
    INDEX ix_user_history (device_id, happened_at, id)
) ENGINE=InnoDB;

CREATE TABLE device_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id BIGINT UNSIGNED NOT NULL,
    happened_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    outcome VARCHAR(16) NOT NULL DEFAULT 'allowed',
    FOREIGN KEY (device_id) REFERENCES devices(id),
    CHECK (outcome IN ('allowed','blocked')),
    INDEX ix_device_history (device_id, happened_at, id)
) ENGINE=InnoDB;

-- Представление не содержит паролей и ключей устройств.
CREATE VIEW v_device_dashboard AS
SELECT a.user_id, a.role, a.blocked_until AS user_blocked_until,
       d.id, d.uid, d.name, d.last_seen,
       d.blocked_until AS device_blocked_until,
       s.temperature, s.relay, s.command,
       s.temperature_at, s.relay_at, s.command_at
FROM device_access a JOIN devices d ON d.id = a.device_id
JOIN device_state s ON s.device_id = d.id;

DELIMITER $$
CREATE TRIGGER bi_user_events BEFORE INSERT ON user_events
FOR EACH ROW
BEGIN
    DECLARE t DATETIME(6);
    DECLARE w DATETIME(6);
    DECLARE b DATETIME(6);
    DECLARE db DATETIME(6);
    DECLARE n INT UNSIGNED;
    DECLARE r VARCHAR(16);
    SET t = UTC_TIMESTAMP(6);
    SET NEW.happened_at = t;
    -- Единый порядок блокировок: сначала устройство, затем доступ.
    SELECT blocked_until INTO db FROM devices
      WHERE id = NEW.device_id FOR UPDATE;
    SELECT window_start, request_count, blocked_until, role INTO w,n,b,r
      FROM device_access
      WHERE user_id = NEW.user_id AND device_id = NEW.device_id FOR UPDATE;
    IF NEW.action = 'control' AND r <> 'operator' THEN
        SET NEW.outcome = 'forbidden';
    ELSEIF b IS NOT NULL AND b > t THEN
        SET NEW.outcome = 'user_blocked';
    ELSE
        IF w IS NULL OR t >= w + INTERVAL 60 SECOND THEN
            SET w = t;
            SET n = 0;
        END IF;
        SET n = n + 1;
        SET b = NULL;
        SET NEW.outcome = 'allowed';
        IF n > 10 THEN
            SET b = t + INTERVAL 60 SECOND;
            SET NEW.outcome = 'user_blocked';
        ELSEIF db IS NOT NULL AND db > t THEN
            SET NEW.outcome = 'device_blocked';
        END IF;
        UPDATE device_access SET window_start=w, request_count=n, blocked_until=b
          WHERE user_id=NEW.user_id AND device_id=NEW.device_id;
    END IF;
END$$

CREATE TRIGGER bi_device_events BEFORE INSERT ON device_events
FOR EACH ROW
BEGIN
    DECLARE t DATETIME(6);
    DECLARE w DATETIME(6);
    DECLARE b DATETIME(6);
    DECLARE n INT UNSIGNED;
    SET t = UTC_TIMESTAMP(6);
    SET NEW.happened_at = t;
    SELECT window_start, request_count, blocked_until INTO w,n,b
      FROM devices WHERE id=NEW.device_id FOR UPDATE;
    IF b IS NOT NULL AND b > t THEN
        SET NEW.outcome = 'blocked';
    ELSE
        IF w IS NULL OR t >= w + INTERVAL 60 SECOND THEN
            SET w = t;
            SET n = 0;
        END IF;
        SET n = n + 1;
        SET b = NULL;
        SET NEW.outcome = 'allowed';
        IF n > 30 THEN
            SET b = t + INTERVAL 60 SECOND;
            SET NEW.outcome = 'blocked';
        END IF;
        UPDATE devices SET window_start=w, request_count=n, blocked_until=b
          WHERE id=NEW.device_id;
    END IF;
END$$
DELIMITER ;
