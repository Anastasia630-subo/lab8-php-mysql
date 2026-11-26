<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$user = currentUser();
$devices = query('SELECT d.id,d.uid,d.name FROM devices d JOIN device_access a
    ON a.device_id=d.id WHERE a.user_id=? ORDER BY d.id', [$user['id']])->fetchAll();
$results = [];
foreach ($devices as $d) $results[$d['uid']] = userOperation((int)$user['id'], $d['uid'], 'view');
page('Мои устройства', function () use ($user, $devices, $results) { ?>
<p class="intro"><?=h($user['username'])?> · Показания датчиков и команды реле</p>
<?php if (isset($_GET['saved'])): ?><p class="notice">Команда сохранена. Фактическое реле изменится после следующего запроса устройства.</p><?php endif ?>
<div class="grid"><?php foreach ($devices as $d) {
    $result = $results[$d['uid']];
    if ($result) require dirname(__DIR__) . '/app/templates/device.php';
} ?></div><?php if (!$devices): ?><p>Нет доступных устройств.</p><?php endif ?>
<?php });
