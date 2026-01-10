<?php
require_once 'config.php';

// Set default page title if not set
if (!isset($page_title)) {
    $page_title = 'Fantasy Football Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="fantasy-style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <nav>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="fantasy.php" class="active">Fantasy Football</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="rankings.php">Rankings</a></li>
                        <li><a href="stats.php">Stats</a></li>
                        <li><a href="players.php">Players</a></li>
                        <li><a href="teams.php">Teams</a></li>
                        <li><a href="admin/dashboard.php">Admin</a></li>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn()): ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Admin Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Fantasy Football Sub-Navigation -->
    <nav class="fantasy-nav">
        <div class="container">
            <ul class="fantasy-nav-links">
                <li><a href="fantasy.php" <?php echo basename($_SERVER['PHP_SELF']) == 'fantasy.php' ? 'class="active"' : ''; ?>><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="fantasy-leagues.php" <?php echo basename($_SERVER['PHP_SELF']) == 'fantasy-leagues.php' ? 'class="active"' : ''; ?>><i class="fas fa-trophy"></i> Leagues</a></li>
                <li><a href="fantasy-owners.php" <?php echo basename($_SERVER['PHP_SELF']) == 'fantasy-owners.php' ? 'class="active"' : ''; ?>><i class="fas fa-users"></i> Owners</a></li>
                <li><a href="fantasy-rankings.php" <?php echo basename($_SERVER['PHP_SELF']) == 'fantasy-rankings.php' ? 'class="active"' : ''; ?>><i class="fas fa-list-ol"></i> Rankings</a></li>
            </ul>
        </div>
    </nav>

    <main class="fantasy-main">
