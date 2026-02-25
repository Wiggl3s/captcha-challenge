<?php


if (!empty($_SESSION['user']) && !empty($_SESSION['captcha_passed'])) {
    header('Location: ?page=dashboard');
    exit;
}

$default_users = ['admin' => 'password', 'demo' => 'demo'];
if (!isset($_SESSION['registered_users'])) {
    $_SESSION['registered_users'] = $default_users;
}

$error    = '';
$username = '';
$show_register = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Please fill in all fields.';
    } elseif (($_SESSION['registered_users'][$username] ?? null) !== $password) {
        $error = 'Invalid username or password.';
        $show_register = true;
    } else {
        $_SESSION['user'] = $username;
        $_SESSION['captcha_passed'] = false;
        unset($_SESSION['assigned_captcha'], $_SESSION['skips_left']);
        unset($_SESSION['chess_game'], $_SESSION['ms'], $_SESSION['robot_dismissed']);
        header('Location: ?page=captcha-session');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bad CAPTCHA</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-login">
    <div class="login-card">
        <div class="login-header">
            <div class="logo"></div>
            <h1>Login</h1>
            <p class="subtitle">Prove your humanity to proceed</p>
        </div>
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($username) ?>" placeholder="Enter username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($show_register): ?>
                <a href="?page=register" class="btn btn-secondary btn-block">Create an Account</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
    </div>
</body>
</html>
