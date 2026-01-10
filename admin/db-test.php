<?php
// Simple database connection test
require_once '../config.php';

echo "<h1>Database Connection Test</h1>";

echo "<h2>Constants Check:</h2>";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "<br>";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "<br>";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "<br>";
echo "DB_PASS: " . (defined('DB_PASS') ? '***DEFINED***' : 'NOT DEFINED') . "<br><br>";

echo "<h2>Global PDO Check:</h2>";
if (isset($pdo)) {
    echo "✅ Global \$pdo exists<br>";
    try {
        $stmt = $pdo->query("SELECT 1");
        echo "✅ Database connection works<br>";
    } catch (Exception $e) {
        echo "❌ Database query failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Global \$pdo does not exist<br>";
    echo "Trying to create new connection...<br>";
    
    try {
        $testPdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ New PDO connection successful<br>";
        
        $stmt = $testPdo->query("SELECT 1");
        echo "✅ Test query works<br>";
        
    } catch (Exception $e) {
        echo "❌ New PDO connection failed: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>SleeperController Test:</h2>";
try {
    require_once 'SleeperController.php';
    $controller = new SleeperController();
    echo "✅ SleeperController created successfully<br>";
    
    if ($controller->pdo) {
        echo "✅ SleeperController has PDO connection<br>";
        $stmt = $controller->pdo->query("SELECT 1");
        echo "✅ SleeperController PDO works<br>";
    } else {
        echo "❌ SleeperController PDO is null<br>";
    }
    
} catch (Exception $e) {
    echo "❌ SleeperController error: " . $e->getMessage() . "<br>";
}

echo "<h2>SleeperSync Test:</h2>";
try {
    require_once '../SleeperSync.php';
    
    // Test with the controller's PDO
    if (isset($controller) && $controller->pdo) {
        $sync = new SleeperSync('test', $controller->pdo);
        echo "✅ SleeperSync created successfully with controller PDO<br>";
    } else {
        // Test with new PDO
        $testPdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $sync = new SleeperSync('test', $testPdo);
        echo "✅ SleeperSync created successfully with new PDO<br>";
    }
    
} catch (Exception $e) {
    echo "❌ SleeperSync error: " . $e->getMessage() . "<br>";
}
?>
