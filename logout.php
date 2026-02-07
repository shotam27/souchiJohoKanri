<?php
require_once __DIR__ . '/config.php';

$database = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, DB_TYPE, DB_PORT);
$user = new User($database);

// ログアウト処理
$user->logout();

// ログインページにリダイレクト
header('Location: login.php');
exit;
