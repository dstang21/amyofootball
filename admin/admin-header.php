<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?> - Admin</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <header style="background: var(--dark-color); color: white; padding: 15px 0; border-bottom: 3px solid var(--primary-color);">
        <div class="header-content" style="display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; padding: 0 20px;">
            <a href="../index.php" class="logo" style="color: white; text-decoration: none; font-size: 1.8rem; font-weight: bold;">
                <?php echo SITE_NAME; ?> <span style="font-size: 0.8em; opacity: 0.8;">Admin</span>
            </a>
            <div style="display: flex; align-items: center; gap: 20px;">
                <a href="../index.php" class="btn btn-outline" style="color: white; border-color: white;">View Site</a>
                <a href="../logout.php" class="btn btn-danger" style="background: #d32f2f;">Logout</a>
            </div>
        </div>
    </header>

    <main style="background: #f8f9fa; min-height: calc(100vh - 80px);">
        <div class="container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
