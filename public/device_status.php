<?php
require_once dirname(__DIR__) . '/includes/common.php';
$simulation = defined('LAB8_SIMULATOR');
if (!$simulation) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') stop_request(405, 'Требуется GET');
    // ID, Term и Rele сохранены из урока 4. ID теперь содержит уникальный LOGIN устройства.
    if (!is_string($_GET['ID'] ?? null) || !preg_match('/\A[0-9a-f]{32}\z/', $_GET['ID'])) stop_request(400, 'Неверный ID.');
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/\ABearer ([0-9a-f]{64})\z/', $auth, $token)) stop_request(401, 'Требуется ключ устройства.');
    $query = 'SELECT DEVICE_ID, PASSWORD FROM DEVICE_TABLE WHERE LOGIN=?';
    $result = $connection->prepare($query);
    $result->execute([$_GET['ID']]);
    $Arr = $result->fetch();
    if (!$Arr || !hash_equals($Arr['PASSWORD'], hash('sha256', $token[1]))) stop_request(401, 'Неверные реквизиты устройства.');
    $id = $Arr['DEVICE_ID'];
    $data = [];
    if (isset($_GET['Rele'])) {
        if (!is_string($_GET['Rele']) || !in_array($_GET['Rele'], ['0', '1'], true)) stop_request(400, 'Rele должен быть 0 или 1.');
        $data['Rele'] = (int)$_GET['Rele'];
    }
    if (isset($_GET['Term'])) {
        if (!is_string($_GET['Term'])) stop_request(400, 'Неверное значение Term.');
        if ($_GET['Term'] === 'null') $data['Term'] = null;
        else {
            $Term = filter_var($_GET['Term'], FILTER_VALIDATE_INT);
            if ($Term === false || $Term < -550 || $Term > 1250) stop_request(400, 'Term: целое от -550 до 1250.');
            $data['Term'] = $Term;
        }
    }
}

// Приём состояния и ответ устройству, как в уроке 4.
$connection->beginTransaction();
$result = $connection->prepare('SELECT * FROM DEVICE_TABLE WHERE DEVICE_ID=? FOR UPDATE');
$result->execute([$id]);
$Arr = $result->fetch();
if (!$Arr) stop_request(404, 'Устройство не найдено.');
$status = 'allowed';
if (!$config['use_triggers'] && count_request('DEVICE_TABLE', 'DEVICE_ID=?', [$id], $Arr, 30)) $status = 'blocked';
$result = $connection->prepare('INSERT INTO DEVICE_REQUEST_TABLE (DEVICE_ID, RESULT) VALUES (?,?)');
$result->execute([$id, $status]);
$result = $connection->prepare('SELECT RESULT FROM DEVICE_REQUEST_TABLE WHERE REQUEST_ID=?');
$result->execute([$connection->lastInsertId()]);
if ($result->fetchColumn() === 'blocked') {
    $connection->commit();
    header('Retry-After: 60');
    stop_request(429, 'Устройство временно заблокировано.');
}
$date_today = gmdate('Y-m-d H:i:s');
if (array_key_exists('Rele', $data)) {
    $query = 'INSERT INTO OUT_STATE_TABLE (DEVICE_ID, OUT_STATE, DATE_TIME) VALUES (?,?,?)
              ON DUPLICATE KEY UPDATE OUT_STATE=VALUES(OUT_STATE), DATE_TIME=VALUES(DATE_TIME)';
    $result = $connection->prepare($query);
    $result->execute([$id, $data['Rele'], $date_today]);
}
if (array_key_exists('Term', $data)) {
    $query = 'INSERT INTO TEMPERATURE_TABLE (DEVICE_ID, TEMPERATURE, DATE_TIME) VALUES (?,?,?)
              ON DUPLICATE KEY UPDATE TEMPERATURE=VALUES(TEMPERATURE), DATE_TIME=VALUES(DATE_TIME)';
    $result = $connection->prepare($query);
    $result->execute([$id, $data['Term'], $date_today]);
}
$result = $connection->prepare('UPDATE DEVICE_TABLE SET LAST_SEEN=? WHERE DEVICE_ID=?');
$result->execute([$date_today, $id]);
$query = 'SELECT COMMAND FROM COMMAND_TABLE WHERE DEVICE_ID=?';
$result = $connection->prepare($query);
$result->execute([$id]);
$Arr = $result->fetch();
$Command = $Arr ? $Arr['COMMAND'] : '?';
$connection->commit();
if ($simulation) { header('Location: index.php'); exit; }
header('Content-Type: text/plain; charset=utf-8');
echo "COMMAND $Command EOC";
