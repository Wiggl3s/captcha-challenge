<?php
/**
 * Register Page
 *
 * $_POST form handling, session-based user storage,
 *               isset(), strlen(), password confirmation, redirect.
 */

if (!empty($_SESSION['user']) && !empty($_SESSION['captcha_passed'])) {
    header('Location: ?page=dashboard');
    exit;
}

if (!isset($_SESSION['registered_users'])) {
    $_SESSION['registered_users'] = ['admin' => 'password', 'demo' => 'demo'];
}

$error    = '';
$success  = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$username || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 12) {
        $error = 'Password must be at least 12 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (isset($_SESSION['registered_users'][$username])) {
        $error = 'Username already taken.';
    } else {
        $_SESSION['registered_users'][$username] = $password;
        $success = 'Account created! You can now log in.';
        $username = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Bad CAPTCHA</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-login">
    <div class="login-card">
        <div class="login-header">
            <div class="logo"></div>
            <h1>Create Account</h1>
            <p class="subtitle">Register a new account</p>
        </div>
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($username) ?>" placeholder="Choose a username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Choose a password" required>
            </div>
            <div class="form-group">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" placeholder="Confirm your password" required>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-block">Register</button>
            <p class="hint-text">
                Already have an account? <a href="?page=login"><strong>Log in</strong></a>
            </p>
        </form>
    </div>
</body>
</html>
