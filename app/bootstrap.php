<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/service.php';
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/var/errors.log');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Referrer-Policy: no-referrer');

set_exception_handler(function (Throwable $e): void {
    if (dbInTransaction()) db()->rollBack();
    $ref = bin2hex(random_bytes(6));
    // В журнал не попадают SQL, параметры, пароль и токен.
    error_log('incident=' . $ref . ' type=' . get_class($e) . ' code=' . $e->getCode());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Внутренняя ошибка. Код обращения: ' . $ref;
});

function dbInTransaction(): bool {
    try { return db()->inTransaction(); } catch (Throwable) { return false; }
}
function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function fail(int $code, string $message): never {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}
function postOnly(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST'); fail(405, 'Требуется POST');
    }
}
function startSession(): void {
    $sessionPath = dirname(__DIR__) . '/var/sessions';
    if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
        throw new RuntimeException('Session directory is not writable');
    }
    session_save_path($sessionPath);
    session_name('lab8');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true, 'samesite' => 'Strict',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    if (!session_start()) throw new RuntimeException('Session could not be started');
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf(): void {
    if (!is_string($_POST['csrf'] ?? null)
        || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) fail(403, 'Неверный CSRF-токен');
}
function currentUser(): array {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php'); exit;
    }
    $u = query('SELECT id,username FROM users WHERE id=?', [$_SESSION['user_id']])->fetch();
    if (!$u) fail(401, 'Требуется вход');
    return $u;
}
function uid(mixed $value): string {
    if (!is_string($value) || !preg_match('/\A[0-9a-f]{32}\z/D', $value)) {
        fail(400, 'Неверный идентификатор устройства');
    }
    return $value;
}
function page(string $title, callable $body): void {
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__ . '/templates/layout.php';
}
