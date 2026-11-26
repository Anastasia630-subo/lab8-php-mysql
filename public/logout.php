<?php
require dirname(__DIR__) . '/includes/common.php';
start_session();
check_csrf();
$_SESSION = [];
session_destroy();
header('Location: login.php');
