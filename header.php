<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <nav>
                <ul class="nav-links">
                    <?php $base_path = strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '../' : ''; ?>
                    <li><a href="<?php echo $base_path; ?>index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>Home</a></li>
                    
                    <!-- Fantasy Football Section for all users -->
                    <li><a href="<?php echo $base_path; ?>fantasy.php" <?php echo strpos($_SERVER['PHP_SELF'], 'fantasy') !== false ? 'class="active"' : ''; ?>>Fantasy Football</a></li>
                    
                    <?php if (isAdmin()): ?>
                        <li><a href="<?php echo $base_path; ?>rankings.php" <?php echo basename($_SERVER['PHP_SELF']) == 'rankings.php' ? 'class="active"' : ''; ?>>Rankings</a></li>
                        <li><a href="<?php echo $base_path; ?>stats.php" <?php echo basename($_SERVER['PHP_SELF']) == 'stats.php' ? 'class="active"' : ''; ?>>Stats</a></li>
                        <li><a href="<?php echo $base_path; ?>players.php" <?php echo basename($_SERVER['PHP_SELF']) == 'players.php' ? 'class="active"' : ''; ?>>Players</a></li>
                        <li><a href="<?php echo $base_path; ?>teams.php" <?php echo basename($_SERVER['PHP_SELF']) == 'teams.php' ? 'class="active"' : ''; ?>>Teams</a></li>
                    <?php endif; ?>
                    
                    <!-- Future public menu items can be added here -->
                    
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li><a href="<?php echo $base_path; ?>admin/dashboard.php" <?php echo strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'class="active"' : ''; ?>>Admin</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo $base_path; ?>logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo $base_path; ?>login.php" <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'class="active"' : ''; ?>>Admin Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
