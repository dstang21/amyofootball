<?php
// This file should be included after the main header.php in admin pages
// It provides the admin-specific navigation and layout
?>

<div class="admin-layout">
    <!-- Admin Sidebar Navigation -->
    <div class="admin-sidebar">
        <!-- User Info Section -->
        <div class="admin-user-info" style="background: rgba(44, 90, 160, 0.1); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0; color: var(--primary-color);">Admin Panel</h4>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #666; font-size: 0.9em;">Logged in as Admin</span>
                <div class="dropdown" style="position: relative;">
                    <button class="btn btn-sm btn-secondary" onclick="toggleUserMenu()" style="padding: 5px 10px; font-size: 0.8em;">⚙️</button>
                    <div id="userMenu" class="dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; background: white; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); min-width: 150px; z-index: 1000;">
                        <a href="change-password.php" style="display: block; padding: 10px 15px; text-decoration: none; color: #333; border-bottom: 1px solid #eee;">Change Password</a>
                        <a href="../logout.php" style="display: block; padding: 10px 15px; text-decoration: none; color: #d32f2f;">Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Navigation -->
        <h3 style="color: var(--primary-color); margin-bottom: 15px;">Management</h3>
        <ul style="list-style: none; padding: 0;">
            <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">📊 Dashboard</a></li>
            <li><a href="manage-players.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage-players.php' ? 'active' : ''; ?>">👥 Players</a></li>
            <li><a href="manage-teams.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage-teams.php' ? 'active' : ''; ?>">🏈 Teams</a></li>
            <li><a href="manage-seasons.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage-seasons.php' ? 'active' : ''; ?>">📅 Seasons</a></li>
            <li><a href="manage-stats.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage-stats.php' ? 'active' : ''; ?>">📈 Projected Stats</a></li>
            <li><a href="manage-sources.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage-sources.php' ? 'active' : ''; ?>">📡 Data Sources</a></li>
            <li><a href="manage-rankings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage-rankings.php' ? 'active' : ''; ?>">🏆 Rankings</a></li>
            <li><a href="manage-scoring.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage-scoring.php' ? 'active' : ''; ?>">⚖️ Scoring Settings</a></li>
        </ul>

        <!-- Sleeper Integration Section -->
        <h3 style="color: var(--primary-color); margin: 30px 0 15px 0;">Sleeper Integration</h3>
        <ul style="list-style: none; padding: 0;">
            <li><a href="sleeper-leagues.php" class="<?php echo strpos(basename($_SERVER['PHP_SELF']), 'sleeper-') === 0 ? 'active' : ''; ?>">🏆 Sleeper Leagues</a></li>
            <li><a href="sleeper-players.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'sleeper-players.php' ? 'active' : ''; ?>">👤 Sleeper Players</a></li>
            <li><a href="sleeper-stats.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'sleeper-stats.php' ? 'active' : ''; ?>">📊 Player Stats</a></li>
            <li><a href="league-history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'league-history.php' ? 'active' : ''; ?>">📜 League History</a></li>
        </ul>

        <!-- Tools Section -->
        <h3 style="color: var(--primary-color); margin: 30px 0 15px 0;">Tools & Utilities</h3>
        <ul style="list-style: none; padding: 0;">
            <li><a href="calculate-fantasy-points.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'calculate-fantasy-points.php' ? 'active' : ''; ?>">🧮 Calculate Fantasy Points</a></li>
            <li><a href="manage-users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'active' : ''; ?>">🔐 Admin Users</a></li>
            <li><a href="db-check.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'db-check.php' ? 'active' : ''; ?>">🔍 Database Check</a></li>
            <li><a href="site-test.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'site-test.php' ? 'active' : ''; ?>">🧪 Site Test</a></li>
        </ul>

        <!-- Back to Site -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <a href="../index.php" class="btn btn-outline" style="width: 100%; text-align: center;">← Back to Site</a>
        </div>
    </div>

    <!-- Main Admin Content Area -->
    <div class="admin-content" style="flex: 1; min-height: calc(100vh - 200px);">

<script>
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.dropdown');
    const menu = document.getElementById('userMenu');
    if (dropdown && !dropdown.contains(event.target)) {
        menu.style.display = 'none';
    }
});
</script>

<style>
.admin-sidebar ul li a {
    display: block;
    padding: 12px 15px;
    text-decoration: none;
    color: #333;
    border-radius: 6px;
    margin-bottom: 5px;
    transition: all 0.3s ease;
}

.admin-sidebar ul li a:hover {
    background-color: rgba(44, 90, 160, 0.1);
    color: var(--primary-color);
    transform: translateX(5px);
}

.admin-sidebar ul li a.active {
    background-color: var(--primary-color);
    color: white;
}

.dropdown-menu a:hover {
    background-color: #f5f5f5;
}
</style>
