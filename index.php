<?php
/**
 * Front Controller — single entry point for all pages.
 *
 * PHP Concepts: front-controller pattern, spl_autoload_register,
 *               in_array() whitelist, require, $_GET routing.
 *
 * Routes: ?page=login | register | captcha-session | dashboard | logout
 */

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
