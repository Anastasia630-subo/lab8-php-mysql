<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h($title)?> · Температура</title><link rel="stylesheet" href="style.css"></head>
<body><header><div><span class="brand">ТЕМПЕРАТУРА</span><span class="subtitle">Управление устройствами</span></div>
<?php if (isset($_SESSION['user_id'])): ?><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><button class="quiet">Выйти</button></form><?php endif ?></header>
<main><h1><?=h($title)?></h1><?php $body(); ?></main>
<footer>Лабораторная работа 8 · Время в журнале: UTC</footer></body></html>
