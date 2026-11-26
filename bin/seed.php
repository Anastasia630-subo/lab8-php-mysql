<?php
// Только CLI. Запускать с учётной записью установщика в config/local.php.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/db.php';
if ((int)query('SELECT COUNT(*) FROM users')->fetchColumn() !== 0) {
    fwrite(STDERR, "База не пуста. Повторное заполнение отменено.\n"); exit(1);
}
$pdo = db(); $pdo->beginTransaction();
try {
    $password = 'Demo_251361!';
    foreach (['nastya','viewer','other'] as $name) {
        query('INSERT INTO users (username,password_hash) VALUES (?,?)',
            [$name, password_hash($password, PASSWORD_DEFAULT)]);
    }
    $users = query('SELECT id,username FROM users ORDER BY id')->fetchAll();
    $devices = [];
    foreach (['Комната','Теплица','Чужое устройство'] as $name) {
        $uid = bin2hex(random_bytes(16)); $token = bin2hex(random_bytes(32));
        query('INSERT INTO devices (uid,name,token_hash) VALUES (?,?,?)',
            [$uid, $name, hash('sha256', $token)]);
        $id = (int)$pdo->lastInsertId();
        query('INSERT INTO device_state (device_id) VALUES (?)', [$id]);
        $devices[] = ['id'=>$id, 'uid'=>$uid, 'token'=>$token, 'name'=>$name];
    }
    foreach ([$devices[0],$devices[1]] as $d) {
        query('INSERT INTO device_access (user_id,device_id,role) VALUES (?,?,?)',
            [$users[0]['id'],$d['id'],'operator']);
    }
    query('INSERT INTO device_access (user_id,device_id,role) VALUES (?,?,?)',
        [$users[1]['id'],$devices[0]['id'],'viewer']);
    query('INSERT INTO device_access (user_id,device_id,role) VALUES (?,?,?)',
        [$users[2]['id'],$devices[2]['id'],'operator']);
    $pdo->commit();
    $path = dirname(__DIR__) . '/var/demo_credentials.json';
    file_put_contents($path, json_encode(['password'=>$password,'users'=>$users,'devices'=>$devices],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    chmod($path, 0600);
    echo "Созданы 3 пользователя и 3 устройства. Пароль: $password\n";
    echo "Ключи только для локальной демонстрации: var/demo_credentials.json\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
