<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $path = dirname(__DIR__) . '/config/local.php';
    if (!is_file($path)) throw new RuntimeException('Configure config/local.php');
    $c = require $path;
    $pdo = new PDO($c['dsn'], $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement {
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s;
}
