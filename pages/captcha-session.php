<?php
/**
 * CAPTCHA Controller — thin routing layer.
 *
 *  factory pattern, session gating, do/while loop, POST handling.
 */

if (empty($_SESSION['user']))            { header('Location: ?page=login');     exit; }
if (!empty($_SESSION['captcha_passed'])) { header('Location: ?page=dashboard'); exit; }



$show_popup = empty($_SESSION['robot_dismissed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dismiss_robot') {
    $_SESSION['robot_dismissed'] = true;
    header('Location: ?page=captcha-session');
    exit;
}

if ($show_popup): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAPTCHA Challenge</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-login">
<div class="login-card">
    <div class="login-header">
        <div class="popup-icon">&#129302;</div>
        <h2>Robot ka?</h2>
        <p class="subtitle">Pag-prove nga dili ka robot!</p>
    </div>
    <div class="login-form" style="text-align:center;">
        <form method="POST">
            <input type="hidden" name="action" value="dismiss_robot">
            <button type="submit" class="btn btn-primary btn-block">Dili ko robot!</button>
        </form>
    </div>
</div>
</body>
</html>
<?php exit; endif;

// ── Skip & assignment logic ──────────────────────────────────

$types = ['february', 'chess', 'minesweeper'];

if (!isset($_SESSION['skips_left']))       $_SESSION['skips_left'] = 2;
if (!isset($_SESSION['assigned_captcha'])) $_SESSION['assigned_captcha'] = $types[array_rand($types)];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'skip' && $_SESSION['skips_left'] > 0) {
    $_SESSION['skips_left']--;
    $old = $_SESSION['assigned_captcha'];
    do { $new = $types[array_rand($types)]; } while ($new === $old);
    $_SESSION['assigned_captcha'] = $new;
    unset($_SESSION['ms'], $_SESSION['chess_game']);
    header('Location: ?page=captcha-session');
    exit;
}

// ── Create captcha & handle POST ─────────────────────────────

$captcha = Captcha::create($_SESSION['assigned_captcha']);
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['captcha_type'])) {
    $error = $captcha->handlePost() ?? '';
}

$skips = $_SESSION['skips_left'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAPTCHA Challenge</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-login">
<div class="login-card">
    <div class="login-header">
        <h1>CAPTCHA Challenge</h1>
        <p class="subtitle">Logged in as <strong><?= htmlspecialchars($_SESSION['user']) ?></strong></p>
    </div>

    <div class="captcha-assign-bar">
        <div class="assign-info">
            <span class="assign-label">Your challenge:</span>
            <span class="assign-type"><?= $captcha->getLabel() ?></span>
        </div>
        <div class="assign-actions">
            <span class="skip-count"><?= $skips ?> skip(s) left</span>
            <?php if ($skips > 0): ?>
                <form method="POST" class="skip-form">
                    <input type="hidden" name="action" value="skip">
                    <button type="submit" class="btn btn-sm btn-skip">Skip &raquo;</button>
                </form>
            <?php else: ?>
                <span class="skip-locked">No skips left</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="captcha-body">
        <?php $captcha->render($error); ?>
    </div>
</div>
</body>
</html>
