<?php
require dirname(__DIR__) . '/app/bootstrap.php';
postOnly();
$uid = uid($_SERVER['HTTP_X_DEVICE_UID'] ?? null);
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/\ABearer ([0-9a-f]{64})\z/D', $auth, $m)) fail(401, 'Требуется токен устройства');
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) fail(413, 'Слишком большой запрос');
$raw = file_get_contents('php://input', false, null, 0, 4097);
if ($raw === false || strlen($raw)>4096) fail(413, 'Слишком большой запрос');
try { $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
catch (JsonException) { fail(400, 'Некорректный JSON'); }
if (!is_array($data) || !str_starts_with(ltrim($raw), '{')
    || array_diff(array_keys($data), ['Term','Rele'])) fail(400, 'Неверные поля JSON');
if (array_key_exists('Term', $data) && $data['Term'] !== null
    && (!is_int($data['Term']) || $data['Term'] < -550 || $data['Term'] > 1250)) {
    fail(400, 'Term: целое от -550 до 1250 либо null');
}
if (array_key_exists('Rele', $data) && !in_array($data['Rele'], [0,1], true)) fail(400, 'Rele: 0 или 1');
$r = deviceOperation($uid, $m[1], $data);
if ($r['code'] === 429) header('Retry-After: 60');
http_response_code($r['code']);
header('Content-Type: text/plain; charset=utf-8');
echo $r['text'];
