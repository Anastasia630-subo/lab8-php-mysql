<?php
// Настройки подключения вынесены из index.php и device_status.php (задание 1.2).
$config = require __DIR__ . '/config.php';
$db_host = $config['db_host'];
$db_user = $config['db_user'];
$db_password = $config['db_password'];
$database = $config['database'];
$db_port = $config['db_port'] ?? 3306;
$connection = new PDO(
    "mysql:host=$db_host;port=$db_port;dbname=$database;charset=utf8mb4",
    $db_user,
    $db_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false,
     PDO::MYSQL_ATTR_MULTI_STATEMENTS => false]
);
$connection->exec("SET time_zone = '+00:00'");
