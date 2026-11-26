<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
// php bin/simulator.php http://127.0.0.1:8080 1 216 0
// Реле имитируется вручную: очередной Rele должен отражать полученную команду.
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
if (!preg_match('~\Ahttps?://(?:127\.0\.0\.1|localhost)(?::\d+)?(?:/[^?#]*)?\z~', $base)) {
    fwrite(STDERR, "Симулятор предназначен для localhost.\n"); exit(1);
}
$number = filter_var($argv[2] ?? '1', FILTER_VALIDATE_INT);
$credentials = json_decode(file_get_contents(dirname(__DIR__) . '/var/demo_credentials.json'), true, 512, JSON_THROW_ON_ERROR);
if (!$number || !isset($credentials['devices'][$number-1])) exit("Укажите номер устройства 1–3\n");
$d = $credentials['devices'][$number-1];
$data = new stdClass();
if (isset($argv[3])) {
    $term = filter_var($argv[3], FILTER_VALIDATE_INT);
    if ($term === false) exit("Term должен быть целым числом\n");
    $data->Term = $term;
}
if (isset($argv[4])) {
    if (!in_array($argv[4], ['0','1'], true)) exit("Rele должен быть 0 или 1\n");
    $data->Rele = (int)$argv[4];
}
$ctx = stream_context_create(['http'=>[
    'method'=>'POST', 'ignore_errors'=>true, 'timeout'=>10,
    'header'=>"Content-Type: application/json\r\nX-Device-UID: {$d['uid']}\r\nAuthorization: Bearer {$d['token']}\r\n",
    'content'=>json_encode($data, JSON_THROW_ON_ERROR),
]]);
$result = file_get_contents($base . '/device_status.php', false, $ctx);
echo ($http_response_header[0] ?? 'Нет ответа') . "\n" . $result . "\n";
