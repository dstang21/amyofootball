<?php
require_once '../config.php';

try {
    // Check if source_id column already exists
    $check_column = $pdo->query("SHOW COLUMNS FROM projected_stats LIKE 'source_id'");
    
    if ($check_column->rowCount() == 0) {
        echo "Adding source_id column to projected_stats table...\n";
        
        // Add the source_id column
        $pdo->exec("ALTER TABLE projected_stats ADD COLUMN source_id bigint(20) UNSIGNED NOT NULL DEFAULT 1 AFTER season_id");
        
        // Add index for better performance
        $pdo->exec("ALTER TABLE projected_stats ADD INDEX idx_source_id (source_id)");
        
        // Add foreign key constraint if sources table exists
        try {
            $pdo->exec("ALTER TABLE projected_stats ADD CONSTRAINT fk_projected_stats_source FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE");
            echo "Added foreign key constraint.\n";
        } catch (Exception $e) {
            echo "Note: Could not add foreign key constraint (sources table may not exist): " . $e->getMessage() . "\n";
        }
        
        echo "Successfully added source_id column to projected_stats table.\n";
    } else {
        echo "source_id column already exists in projected_stats table.\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
