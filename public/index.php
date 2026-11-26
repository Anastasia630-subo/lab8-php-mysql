<?php
require dirname(__DIR__) . '/includes/common.php';
$user = require_user();
$user_id = $user['USER_ID'];

// Обработка кнопок сохранена в index.php, как в уроке 3.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = device_id($user_id, $_POST['ID'] ?? null);
    if (isset($_POST['button_on']) === isset($_POST['button_off'])) stop_request(400, 'Выберите одну команду.');
    $Command = isset($_POST['button_on']) ? 1 : 0;
    $connection->beginTransaction();
    $Arr = user_request($user_id, $id, 'control', $Command);
    if ($Arr['RESULT'] === 'allowed') {
        $date_today = gmdate('Y-m-d H:i:s');
        $query = 'INSERT INTO COMMAND_TABLE (DEVICE_ID, COMMAND, DATE_TIME) VALUES (?,?,?)
                  ON DUPLICATE KEY UPDATE COMMAND=VALUES(COMMAND), DATE_TIME=VALUES(DATE_TIME)';
        $result = $connection->prepare($query);
        $result->execute([$id, $Command, $date_today]);
    }
    $connection->commit();
    if ($Arr['RESULT'] === 'forbidden') stop_request(403, 'Нет права управления.');
    if ($Arr['RESULT'] !== 'allowed') stop_request(429, 'Доступ временно заблокирован.');
    header('Location: index.php?saved=1'); exit;
}

$query = 'SELECT d.DEVICE_ID, d.LOGIN, d.NAME FROM DEVICE_TABLE d
          JOIN USER_DEVICE_TABLE a ON a.DEVICE_ID=d.DEVICE_ID
          WHERE a.USER_ID=? ORDER BY d.DEVICE_ID';
$result = $connection->prepare($query);
$result->execute([$user_id]);
$devices = $result->fetchAll();
?>
<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Мои устройства</title><link rel="stylesheet" href="style.css"></head><body>
<header><div><h1>Мои устройства</h1><p><?=h($user['LOGIN'])?></p></div>
<form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button class="quiet">Выйти</button></form></header>
<main><?php if (isset($_GET['saved'])): ?><p class="notice">Команда сохранена. Реле сообщит новое состояние при следующем обращении устройства.</p><?php endif ?>
<div class="devices">
<?php foreach ($devices as $device):
    $id = $device['DEVICE_ID'];
    $device_name = $device['NAME'];
    $connection->beginTransaction();
    $Arr = user_request($user_id, $id, 'view');
    $status = $Arr['RESULT'];
    $role = $Arr['ROLE'];
    $temperature = $temperature_dt = $out_state = $out_state_dt = $Command = '?';
    if ($status === 'allowed') {
        // Те же таблицы и переменные, что в исходном примере.
        $query = 'SELECT TEMPERATURE, DATE_TIME FROM TEMPERATURE_TABLE WHERE DEVICE_ID=?';
        $result = $connection->prepare($query);
        $result->execute([$id]);
        $Arr = $result->fetch();
        if ($Arr) {
            $temperature = $Arr['TEMPERATURE'] === null ? '?' : $Arr['TEMPERATURE'] / 10;
            $temperature_dt = $Arr['DATE_TIME'];
        }
        $query = 'SELECT OUT_STATE, DATE_TIME FROM OUT_STATE_TABLE WHERE DEVICE_ID=?';
        $result = $connection->prepare($query);
        $result->execute([$id]);
        $Arr = $result->fetch();
        if ($Arr) { $out_state = $Arr['OUT_STATE']; $out_state_dt = $Arr['DATE_TIME']; }
        $query = 'SELECT COMMAND FROM COMMAND_TABLE WHERE DEVICE_ID=?';
        $result = $connection->prepare($query);
        $result->execute([$id]);
        $Arr = $result->fetch();
        if ($Arr) $Command = $Arr['COMMAND'];
    }
    $connection->commit();
    require dirname(__DIR__) . '/includes/device_template.php';
endforeach; ?>
</div><?php if (!$devices): ?><p>Нет доступных устройств.</p><?php endif ?></main>
<footer>Лабораторная работа 8</footer></body></html>
