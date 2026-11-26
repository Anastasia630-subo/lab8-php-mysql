<?php
require dirname(__DIR__) . '/includes/common.php';
$user = require_user();
check_csrf();
$id = device_id($user['USER_ID'], $_POST['ID'] ?? null);
$connection->beginTransaction();
$Arr = user_request($user['USER_ID'], $id, 'simulate');
$allowed = $Arr['RESULT'] === 'allowed' && $Arr['ROLE'] === 'operator';
if ($allowed) {
    $result = $connection->prepare('SELECT COMMAND FROM COMMAND_TABLE WHERE DEVICE_ID=?');
    $result->execute([$id]);
    $Command = $result->fetchColumn();
}
$connection->commit();
if (!$allowed) stop_request($Arr['ROLE'] === 'operator' ? 429 : 403, 'Нет доступа к симулятору.');
// Программное устройство сообщает показания и исполнение сохранённой команды.
$data = ['Term' => $id % 2 ? 216 : 184, 'Rele' => $Command === false ? 0 : (int)$Command];
define('LAB8_SIMULATOR', true);
require __DIR__ . '/device_status.php';
