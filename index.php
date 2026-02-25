<?php


session_start();

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) require_once $file;
});

$page    = $_GET['page'] ?? 'login';
$allowed = ['login', 'register', 'captcha-session', 'dashboard', 'logout'];

if (!in_array($page, $allowed)) {
    $page = 'login';
}

require __DIR__ . "/pages/$page.php";
