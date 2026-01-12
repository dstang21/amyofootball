<?php
require_once '../config.php';

if (!isAdmin()) {
    die('Admin access required');
}

try {
    // Read and execute the migration
    $sql = file_get_contents(__DIR__ . '/add_playoff_status_to_teams.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && substr($statement, 0, 2) !== '--') {
            $pdo->exec($statement);
        }
    }
    
    echo "✅ Migration completed successfully!<br>";
    echo "Added playoff_status, eliminated_date, and notes columns to teams table.<br>";
    echo "<br><a href='../admin/manage-team-playoff-status.php'>Go to Playoff Status Manager</a>";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage();
}
?>
