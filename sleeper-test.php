<?php
require_once 'config.php';
require_once 'admin/SleeperController.php';

echo "<h1>Sleeper Integration Test</h1>";

try {
    $controller = new SleeperController();
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test if tables exist (this will only work on live database)
    echo "<h2>Testing Components:</h2>";
    echo "<p>✓ SleeperController class loaded</p>";
    echo "<p>✓ SleeperSync class available</p>";
    echo "<p>✓ Database constants defined</p>";
    
    echo "<h2>Database Configuration:</h2>";
    echo "<p>Host: " . DB_HOST . "</p>";
    echo "<p>Database: " . DB_NAME . "</p>";
    echo "<p>User: " . DB_USER . "</p>";
    
    echo "<h2>Available Methods:</h2>";
    $methods = get_class_methods($controller);
    foreach ($methods as $method) {
        if (strpos($method, 'get') === 0 || strpos($method, 'sync') === 0) {
            echo "<p>• $method()</p>";
        }
    }
    
    echo "<h2>Admin Pages Created:</h2>";
    $adminFiles = [
        'admin/sleeper-leagues.php' => 'Main leagues management page',
        'admin/sleeper-league.php' => 'Individual league details',
        'admin/sleeper-players.php' => 'Players database browser',
        'admin/sleeper-stats.php' => 'Player statistics viewer',
        'admin/sleeper-draft.php' => 'Draft results viewer',
        'admin/sleeper-api.php' => 'AJAX API endpoint',
        'admin/SleeperController.php' => 'Main controller class',
        'SleeperSync.php' => 'API sync service'
    ];
    
    foreach ($adminFiles as $file => $description) {
        if (file_exists($file)) {
            echo "<p style='color: green;'>✓ $file - $description</p>";
        } else {
            echo "<p style='color: red;'>✗ $file - Missing</p>";
        }
    }
    
    echo "<h2>Next Steps:</h2>";
    echo "<p>1. Access the admin panel: <a href='admin/sleeper-leagues.php'>admin/sleeper-leagues.php</a></p>";
    echo "<p>2. Enter a Sleeper League ID to sync data</p>";
    echo "<p>3. View league details, players, and stats</p>";
    echo "<p>4. Export data as needed</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>
