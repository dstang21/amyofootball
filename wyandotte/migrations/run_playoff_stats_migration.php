<?php
require_once '../../config.php';

if (!isAdmin()) {
    die('Admin access required');
}

try {
    // Read and execute the migration
    $sql = file_get_contents(__DIR__ . '/create_playoff_cumulative_stats.sql');
    
    // Execute the statement
    $pdo->exec($sql);
    
    echo "✅ Migration completed successfully!<br>";
    echo "Created wyandotte_player_playoff_stats table for cumulative playoff statistics.<br>";
    echo "<br><a href='../../wyandotte/rosters.php'>Go to Rosters</a> | ";
    echo "<a href='../../wyandotte/admin/update-cumulative-stats.php'>Update Stats from Games</a>";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage();
}
?>
