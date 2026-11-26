<article class="card">
<h2><?=h($device_name)?></h2>
<?php if ($status === 'user_blocked'): ?>
<p class="alert">Ваш доступ к устройству заблокирован из-за частых обращений. Повторите попытку через 60 секунд.</p>
<?php elseif ($status === 'device_blocked'): ?>
<p class="alert">Подозрительное поведение устройства: слишком частые запросы. Данные и управление временно недоступны.</p>
<?php else: ?>
<p class="temperature"><?=h($temperature)?> °C</p>
<table><tr><th>Температура получена, МСК</th><td><?=h(moscow_time($temperature_dt))?></td></tr>
<tr><th>Реле</th><td><?=$out_state === '?' ? 'Нет данных' : ($out_state ? 'Включено' : 'Выключено')?></td></tr>
<tr><th>Состояние реле получено, МСК</th><td><?=h(moscow_time($out_state_dt))?></td></tr>
<tr><th>Сохранённая команда</th><td><?=$Command === '?' ? 'Не задана' : ($Command ? 'Включить' : 'Выключить')?></td></tr></table>
<?php if ($role === 'operator'): ?>
<form method="post" action="index.php" class="actions">
<input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
<input type="hidden" name="ID" value="<?=h($device['LOGIN'])?>">
<button name="button_on" value="1">Включить реле</button>
<button name="button_off" value="1" class="quiet">Выключить реле</button></form>
<form method="post" action="simulate.php"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
<input type="hidden" name="ID" value="<?=h($device['LOGIN'])?>"><button class="quiet">Обновить показания (симулятор)</button></form>
<?php else: ?><p>Режим просмотра</p><?php endif ?>
<p><a href="history.php?ID=<?=h($device['LOGIN'])?>">История управления →</a></p>
<?php endif ?></article>
