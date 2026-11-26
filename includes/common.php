<?php
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$var = dirname(__DIR__) . '/var';
if (!is_dir($var)) mkdir($var, 0700, true);
ini_set('error_log', $var . '/errors.log');
set_exception_handler(function (Throwable $error) {
    global $connection;
    if (isset($connection) && $connection->inTransaction()) $connection->rollBack();
    error_log(get_class($error) . ': ' . $error->getCode());
    http_response_code(500);
    exit('Не удалось выполнить запрос. Повторите попытку позже.');
});
require __DIR__ . '/connect.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function stop_request($code, $message) {
    global $connection;
    if ($connection->inTransaction()) $connection->rollBack();
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}
function start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $path = dirname(__DIR__) . '/var/sessions';
    if (!is_dir($path)) mkdir($path, 0700, true);
    session_save_path($path);
    session_name('lab8');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
}
function check_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') stop_request(405, 'Требуется POST');
    if (!is_string($_POST['csrf'] ?? null)
        || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        stop_request(403, 'Обновите страницу и повторите действие.');
    }
}
function require_user() {
    global $connection;
    start_session();
    if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
    $result = $connection->prepare('SELECT USER_ID, LOGIN FROM USER_TABLE WHERE USER_ID=?');
    $result->execute([$_SESSION['user_id']]);
    $Arr = $result->fetch();
    if (!$Arr) stop_request(401, 'Войдите в учётную запись.');
    return $Arr;
}
function device_id($user_id, $value) {
    global $connection;
    if (!is_string($value) || !preg_match('/\A[0-9a-f]{32}\z/', $value)) {
        stop_request(400, 'Неверный идентификатор устройства.');
    }
    $query = 'SELECT d.DEVICE_ID FROM DEVICE_TABLE d
              JOIN USER_DEVICE_TABLE a ON a.DEVICE_ID=d.DEVICE_ID
              WHERE d.LOGIN=? AND a.USER_ID=?';
    $result = $connection->prepare($query);
    $result->execute([$value, $user_id]);
    $id = $result->fetchColumn();
    if ($id === false) stop_request(404, 'Устройство не найдено.');
    return (int)$id;
}

// Счётчик для хостинга без триггеров. Вызывается внутри транзакции с блокировкой строки.
function count_request($table, $where, $params, $Arr, $limit) {
    global $connection;
    $now = time();
    if ($Arr['BLOCKED_UNTIL'] !== null && strtotime($Arr['BLOCKED_UNTIL']) > $now) return true;
    $window = $Arr['WINDOW_START'];
    $count = (int)$Arr['REQUEST_COUNT'];
    if ($window === null || strtotime($window) + 60 <= $now) {
        $window = gmdate('Y-m-d H:i:s');
        $count = 0;
    }
    $count++;
    $blocked = $count > $limit ? gmdate('Y-m-d H:i:s', $now + 60) : null;
    // Имена таблиц и условие задаются только в коде, значения передаются параметрами.
    $result = $connection->prepare("UPDATE $table SET WINDOW_START=?, REQUEST_COUNT=?, BLOCKED_UNTIL=? WHERE $where");
    $result->execute(array_merge([$window, $count, $blocked], $params));
    return $blocked !== null;
}
function user_request($user_id, $id, $action, $Command = null) {
    global $connection, $config;
    $result = $connection->prepare('SELECT BLOCKED_UNTIL FROM DEVICE_TABLE WHERE DEVICE_ID=? FOR UPDATE');
    $result->execute([$id]);
    $device_block = $result->fetchColumn();
    $result = $connection->prepare('SELECT * FROM USER_DEVICE_TABLE WHERE USER_ID=? AND DEVICE_ID=? FOR UPDATE');
    $result->execute([$user_id, $id]);
    $Arr = $result->fetch();
    if (!$Arr) stop_request(404, 'Устройство не найдено.');
    $status = 'allowed';
    if (!$config['use_triggers']) {
        if (in_array($action, ['control','simulate'], true) && $Arr['ROLE'] !== 'operator') $status = 'forbidden';
        elseif (count_request('USER_DEVICE_TABLE', 'USER_ID=? AND DEVICE_ID=?', [$user_id, $id], $Arr, 10)) $status = 'user_blocked';
        elseif ($device_block !== null && strtotime($device_block) > time()) $status = 'device_blocked';
    }
    $query = 'INSERT INTO USER_ACTION_TABLE (USER_ID, DEVICE_ID, ACTION, COMMAND, RESULT) VALUES (?,?,?,?,?)';
    $result = $connection->prepare($query);
    $result->execute([$user_id, $id, $action, $Command, $status]);
    $result = $connection->prepare('SELECT RESULT FROM USER_ACTION_TABLE WHERE ACTION_ID=?');
    $result->execute([$connection->lastInsertId()]);
    $Arr['RESULT'] = $result->fetchColumn();
    return $Arr;
}
