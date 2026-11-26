<?php
// Однократное заполнение пустой учебной базы из командной строки.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/includes/connect.php';
if ($connection->query('SELECT COUNT(*) FROM USER_TABLE')->fetchColumn()
    || $connection->query('SELECT COUNT(*) FROM DEVICE_TABLE')->fetchColumn()) {
    exit("База уже заполнена.\n");
}
$path = __DIR__ . '/var';
if (!is_dir($path)) mkdir($path, 0700, true);
$connection->beginTransaction();
try {
    $password = 'Demo_251361!';
    $users = [];
    foreach (['nastya', 'viewer', 'other'] as $login) {
        $query = 'INSERT INTO USER_TABLE (LOGIN, PASSWORD) VALUES (?,?)';
        $result = $connection->prepare($query);
        $result->execute([$login, password_hash($password, PASSWORD_DEFAULT)]);
        $users[$login] = (int)$connection->lastInsertId();
    }
    $devices = [];
    foreach (['Комната', 'Теплица', 'Чужое устройство'] as $number => $device_name) {
        $login = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $query = 'INSERT INTO DEVICE_TABLE (LOGIN, PASSWORD, NAME) VALUES (?,?,?)';
        $result = $connection->prepare($query);
        $result->execute([$login, hash('sha256', $token), $device_name]);
        $id = (int)$connection->lastInsertId();
        $devices[] = ['DEVICE_ID' => $id, 'LOGIN' => $login, 'token' => $token];
        $result = $connection->prepare('INSERT INTO USER_DEVICE_TABLE (USER_ID, DEVICE_ID, ROLE) VALUES (?,?,?)');
        $result->execute([$number < 2 ? $users['nastya'] : $users['other'], $id, 'operator']);
        if ($number === 0) $result->execute([$users['viewer'], $id, 'viewer']);
        $date_today = gmdate('Y-m-d H:i:s');
        $result = $connection->prepare('INSERT INTO TEMPERATURE_TABLE (DEVICE_ID, TEMPERATURE, DATE_TIME) VALUES (?,?,?)');
        $result->execute([$id, $number === 1 ? 184 : 216, $date_today]);
        $result = $connection->prepare('INSERT INTO OUT_STATE_TABLE (DEVICE_ID, OUT_STATE, DATE_TIME) VALUES (?,?,?)');
        $result->execute([$id, $number === 1 ? 1 : 0, $date_today]);
    }
    if (file_put_contents($path . '/devices.json', json_encode($devices, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        throw new RuntimeException('Не удалось сохранить учебные ключи.');
    }
    chmod($path . '/devices.json', 0600);
    $connection->commit();
    echo "Созданы три пользователя и три устройства. Учебный пароль: $password\n";
} catch (Throwable $error) {
    $connection->rollBack();
    throw $error;
}
