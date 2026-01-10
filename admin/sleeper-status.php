<?php
require_once '../config.php';
require_once 'admin-header.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$status = [
    'sleeper_sync_exists' => file_exists('../SleeperSync.php'),
    'sleeper_sync_readable' => is_readable('../SleeperSync.php'),
    'sleeper_controller_exists' => file_exists('SleeperController.php'),
    'syntax_error' => false,
    'error_message' => ''
];

// Test if SleeperSync.php has syntax errors
if ($status['sleeper_sync_exists']) {
    $output = [];
    $return_var = 0;
    exec('php -l ../SleeperSync.php 2>&1', $output, $return_var);
    
    if ($return_var !== 0) {
        $status['syntax_error'] = true;
        $status['error_message'] = implode("\n", $output);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sleeper Integration Status - AmyoFootball Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <?php include 'admin-nav.php'; ?>
        
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Sleeper Integration Status</h1>
                
                <div class="card">
                    <div class="card-header">
                        <h5>File Status Check</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <td>SleeperSync.php exists</td>
                                <td>
                                    <?php if ($status['sleeper_sync_exists']): ?>
                                        <span class="badge bg-success">✓ Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>SleeperSync.php readable</td>
                                <td>
                                    <?php if ($status['sleeper_sync_readable']): ?>
                                        <span class="badge bg-success">✓ Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>SleeperController.php exists</td>
                                <td>
                                    <?php if ($status['sleeper_controller_exists']): ?>
                                        <span class="badge bg-success">✓ Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Syntax Check</td>
                                <td>
                                    <?php if (!$status['syntax_error']): ?>
                                        <span class="badge bg-success">✓ No Errors</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ Syntax Error</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if ($status['syntax_error']): ?>
                        <div class="alert alert-danger mt-3">
                            <h6>Syntax Error Details:</h6>
                            <pre><code><?= htmlspecialchars($status['error_message']) ?></code></pre>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6>Resolution Steps:</h6>
                            <ol>
                                <li>Upload the corrected SleeperSync.php file to your server</li>
                                <li>The corrected file should be available in your local development environment</li>
                                <li>Use FTP, cPanel File Manager, or your hosting provider's upload method</li>
                                <li>Refresh this page to verify the fix</li>
                            </ol>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!$status['syntax_error'] && $status['sleeper_sync_exists']): ?>
                        <div class="alert alert-success">
                            <h6>✓ All Clear!</h6>
                            <p>SleeperSync.php is working correctly. You can now use the Sleeper integration features.</p>
                            <a href="sleeper-leagues.php" class="btn btn-primary">Go to Sleeper Leagues</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php require_once 'admin-footer.php'; ?>
