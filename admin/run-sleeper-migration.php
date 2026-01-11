<?php
require_once '../config.php';

// Check if user is admin
if (!isAdmin()) {
    die('Access denied');
}

echo "<h2>Adding sleeper_id column to players table...</h2>";

try {
    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM players LIKE 'sleeper_id'");
    if ($check->rowCount() > 0) {
        echo "<p style='color: orange;'>✓ sleeper_id column already exists!</p>";
    } else {
        // Add the column
        $pdo->exec("ALTER TABLE players ADD COLUMN sleeper_id VARCHAR(10) NULL AFTER birth_date");
        echo "<p style='color: green;'>✓ Added sleeper_id column</p>";
        
        // Add index
        $pdo->exec("ALTER TABLE players ADD INDEX idx_sleeper_id (sleeper_id)");
        echo "<p style='color: green;'>✓ Added index on sleeper_id</p>";
    }
    
    echo "<hr>";
    echo "<h3>Migration Complete!</h3>";
    echo "<p>You can now proceed to: <a href='match-sleeper-players.php'>Match Sleeper Players</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
