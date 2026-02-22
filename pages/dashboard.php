<?php
/**
 * Dashboard — shown after successful login + captcha.
 *
 *  session gating, associative array lookup, htmlspecialchars().
 */

if (empty($_SESSION['user']) || empty($_SESSION['captcha_passed'])) {
    header('Location: ?page=login');
    exit;
}

$user   = htmlspecialchars($_SESSION['user']);
$type   = $_SESSION['captcha_type'] ?? 'unknown';
$labels = ['february' => 'February Days', 'chess' => 'Chess Puzzle', 'minesweeper' => 'Minesweeper'];
$label  = $labels[$type] ?? $type;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bad CAPTCHA</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-dashboard">
    <div class="dashboard-card">
        <h1>Welcome, <?= $user ?>!</h1>
        <p class="dashboard-congrats">Congrats, you just wasted your time.</p>
        <a href="?page=logout" class="btn btn-primary">Log Out</a>
    </div>
</body>
</html>
