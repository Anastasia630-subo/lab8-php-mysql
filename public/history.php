<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$u = currentUser();
$r = userOperation((int)$u['id'], uid($_GET['uid'] ?? null), 'history');
if (!$r) fail(404, 'Устройство не найдено');
if ($r['outcome'] !== 'allowed') {
    header('Retry-After: 60'); fail(429, 'История временно недоступна из-за блокировки');
}
$labels = ['view'=>'Просмотр','history'=>'История','control'=>'Управление'];
$outcomes = ['allowed'=>'Разрешено','user_blocked'=>'Блокировка пользователя',
    'device_blocked'=>'Блокировка устройства','forbidden'=>'Нет права'];
page('История управления', function () use ($r, $labels, $outcomes) { ?>
<p><a href="index.php">← Мои устройства</a></p><h2><?=h($r['state']['name'])?></h2>
<p>Последние 100 событий устройства. Время UTC.</p><div class="tablewrap"><table><thead><tr><th>Время UTC</th><th>Пользователь</th><th>Действие</th><th>Команда</th><th>Результат</th></tr></thead><tbody>
<?php foreach ($r['history'] as $e): ?><tr><td><?=h($e['happened_at'])?></td><td><?=h($e['username'])?></td><td><?=h($labels[$e['action']])?></td><td><?= $e['command'] === null ? '—' : ($e['command'] ? 'Включить' : 'Выключить') ?></td><td><?=h($outcomes[$e['outcome']])?></td></tr><?php endforeach ?>
</tbody></table></div><?php });
