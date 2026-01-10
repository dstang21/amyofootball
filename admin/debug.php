<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

// Simple debug page to test everything is working
$page_title = 'System Debug';

// Test database connection
$db_status = "Connected";
try {
    $test_query = $pdo->query("SELECT COUNT(*) as count FROM players");
    $player_count = $test_query->fetch()['count'];
} catch (Exception $e) {
    $db_status = "Error: " . $e->getMessage();
    $player_count = 0;
}

include '../header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header" style="background: var(--success-color); color: white;">
            <h2>AmyoFootball System Status</h2>
        </div>
        <div class="card-body">
            <h3>System Information</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>PHP Version:</strong></td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>Database Status:</strong></td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><?php echo $db_status; ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>Total Players:</strong></td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><?php echo $player_count; ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>Session Status:</strong></td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><?php echo session_status() == PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>Login Status:</strong></td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><?php echo isLoggedIn() ? 'Logged In' : 'Not Logged In'; ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>Current Path:</strong></td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;"><?php echo $_SERVER['PHP_SELF']; ?></td>
                </tr>
            </table>

            <h3 style="margin-top: 30px;">CSS Test</h3>
            <div style="display: flex; gap: 10px; margin: 15px 0;">
                <button class="btn btn-primary">Primary Button</button>
                <button class="btn btn-success">Success Button</button>
                <button class="btn btn-warning">Warning Button</button>
                <button class="btn btn-danger">Danger Button</button>
            </div>

            <h3>Position Badges Test</h3>
            <div style="display: flex; gap: 10px; margin: 15px 0;">
                <span class="position-badge position-qb">QB</span>
                <span class="position-badge position-rb">RB</span>
                <span class="position-badge position-wr">WR</span>
                <span class="position-badge position-te">TE</span>
                <span class="position-badge position-def">DEF</span>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <a href="../index.php" class="btn btn-secondary">Go to Homepage</a>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>
