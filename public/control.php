<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$u = currentUser(); postOnly(); csrf();
$uid = uid($_POST['uid'] ?? null);
$value = $_POST['command'] ?? null;
if (!is_string($value) || !in_array($value, ['0','1'], true)) fail(400, 'Команда должна быть 0 или 1');
$r = userOperation((int)$u['id'], $uid, 'control', (int)$value);
if (!$r) fail(404, 'Устройство не найдено');
if ($r['outcome'] === 'forbidden') fail(403, 'Нет права управления');
if ($r['outcome'] !== 'allowed') {
    header('Retry-After: 60'); fail(429, 'Доступ временно заблокирован');
}
header('Location: index.php?saved=1', true, 303);
