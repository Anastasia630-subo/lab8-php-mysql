<?php
require dirname(__DIR__) . '/includes/common.php';
start_session();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!is_string($login) || !is_string($password) || strlen($login) > 64 || strlen($password) > 200) stop_request(400, 'Проверьте данные для входа.');
    $query = 'SELECT USER_ID, PASSWORD FROM USER_TABLE WHERE LOGIN=?';
    $result = $connection->prepare($query);
    $result->execute([$login]);
    $Arr = $result->fetch();
    if ($Arr && password_verify($password, $Arr['PASSWORD'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $Arr['USER_ID'];
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        header('Location: index.php'); exit;
    }
    http_response_code(401);
    $error = 'Неверный логин или пароль.';
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Вход</title><link rel="stylesheet" href="style.css"></head>
<body><main class="login"><h1>Управление устройствами</h1><form method="post">
<input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
<label>Логин<input name="login" required maxlength="64" autocomplete="username"></label>
<label>Пароль<input type="password" name="password" required maxlength="200" autocomplete="current-password"></label>
<?php if ($error): ?><p class="alert"><?=h($error)?></p><?php endif ?><button>Войти</button></form></main></body></html>
