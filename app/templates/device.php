<article class="card" data-device="<?=h($d['uid'])?>"><div class="cardtop"><h2><?=h($d['name'])?></h2><span class="tag">Устройство <?=h($d['id'])?></span></div>
<?php if ($result['outcome'] === 'user_blocked'): ?>
<p class="alert">Ваш доступ к этому устройству временно заблокирован из-за частых обращений. Повторите попытку через 60 секунд.</p>
<?php elseif ($result['outcome'] === 'device_blocked'): ?>
<p class="alert">Подозрительное поведение устройства: слишком частые запросы к серверу. Устройство временно заблокировано. Данные и управление недоступны.</p>
<?php else: $s = $result['state']; ?>
<p class="uid">UID <?=h($d['uid'])?></p>
<p class="temperature"><?= $s['temperature'] === null ? 'Нет данных' : h($s['temperature']) . ' °C' ?></p>
<dl><dt>Фактическое реле</dt><dd><?= $s['relay'] === null ? 'Нет данных' : ($s['relay'] ? 'Включено' : 'Выключено') ?></dd>
<dt>Сохранённая команда</dt><dd><?= $s['command'] === null ? 'Не задана' : ($s['command'] ? 'Включить' : 'Выключить') ?></dd>
<dt>Последняя связь UTC</dt><dd><?=h($s['last_seen'] ?? 'Ещё не было')?></dd></dl>
<?php if ($s['role'] === 'operator'): ?><form method="post" action="control.php" class="controls">
<input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="uid" value="<?=h($d['uid'])?>">
<button name="command" value="1">Включить реле</button><button class="quiet" name="command" value="0">Выключить</button></form>
<?php else: ?><p>Режим просмотра</p><?php endif ?>
<a href="history.php?uid=<?=h($d['uid'])?>">История управления →</a>
<?php endif ?></article>
