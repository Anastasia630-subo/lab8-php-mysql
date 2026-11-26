<?php
require dirname(__DIR__) . '/includes/common.php';
$user = require_user();
$id = device_id($user['USER_ID'], $_GET['ID'] ?? null);
$connection->beginTransaction();
$Arr = user_request($user['USER_ID'], $id, 'history');
if ($Arr['RESULT'] !== 'allowed') {
    $connection->commit(); stop_request(429, 'История временно недоступна из-за блокировки.');
}
$query = 'SELECT u.LOGIN, a.ACTION, a.COMMAND, a.DATE_TIME, a.RESULT
          FROM USER_ACTION_TABLE a JOIN USER_TABLE u ON u.USER_ID=a.USER_ID
          WHERE a.DEVICE_ID=? ORDER BY a.ACTION_ID DESC LIMIT 100';
$result = $connection->prepare($query);
$result->execute([$id]);
$history = $result->fetchAll();
$connection->commit();
$actions = ['view' => 'Просмотр', 'control' => 'Управление', 'history' => 'История', 'simulate' => 'Симулятор'];
$results = ['allowed' => 'Разрешено', 'forbidden' => 'Нет права', 'user_blocked' => 'Блокировка пользователя', 'device_blocked' => 'Блокировка устройства'];
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>История управления</title><link rel="stylesheet" href="style.css"></head>
<body><main><p><a href="index.php">← Мои устройства</a></p><h1>История управления</h1><p>Последние 100 событий.</p>
<div class="scroll"><table><thead><tr><th>Время, МСК</th><th>Пользователь</th><th>Действие</th><th>Команда</th><th>Результат</th></tr></thead><tbody>
<?php foreach ($history as $Arr): ?><tr><td><?=h(moscow_time($Arr['DATE_TIME']))?></td><td><?=h($Arr['LOGIN'])?></td><td><?=h($actions[$Arr['ACTION']])?></td><td><?=$Arr['COMMAND'] === null ? '—' : ($Arr['COMMAND'] ? 'Включить' : 'Выключить')?></td><td><?=h($results[$Arr['RESULT']])?></td></tr><?php endforeach ?>
</tbody></table></div></main></body></html>
