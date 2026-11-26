<?php
require dirname(__DIR__) . '/app/bootstrap.php';
startSession();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf();
    $name = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if (!is_string($name) || !is_string($pass) || strlen($name)>64 || strlen($pass)>1024) {
        fail(400, 'Некорректные данные');
    }
    $u = query('SELECT id,password_hash FROM users WHERE username=?', [$name])->fetch();
    // Фиктивный хеш делает проверку несуществующего имени сопоставимой по времени.
    $hash = $u['password_hash'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    if (password_verify($pass, $hash) && $u) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: index.php', true, 303); exit;
    }
    http_response_code(401); $error = 'Неверный логин или пароль';
}
page('Вход', function () use ($error) { ?>
<form method="post" class="login"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
<label>Логин<input name="username" required maxlength="64" autocomplete="username"></label>
<label>Пароль<input type="password" name="password" required autocomplete="current-password"></label>
<?php if ($error): ?><p class="alert"><?=h($error)?></p><?php endif ?><button>Войти</button></form>
<?php });
