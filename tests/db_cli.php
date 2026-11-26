<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$path = getenv('LAB8_TEST_CONFIG');
if (!$path || !is_file($path)) exit("LAB8_TEST_CONFIG is required\n");
$c = require $path;
$p = new PDO($c['dsn'],$c['user'],$c['password'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$p->exec("SET time_zone = '+00:00'");
$input = json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$s = $p->prepare($input['sql']); $s->execute($input['params'] ?? []);
echo json_encode($s->columnCount() ? $s->fetchAll() : [],JSON_THROW_ON_ERROR);
