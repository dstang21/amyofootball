<?php
/**
 * Wyandotte League Migration Script
 * Run this on your production server to create the Wyandotte tables
 */

require_once dirname(__DIR__) . '/config.php';

echo "=== WYANDOTTE LEAGUE MIGRATION ===\n\n";

try {
    // Read the SQL file
    $sql_file = __DIR__ . '/wyandotte_tables.sql';
    $sql = file_get_contents($sql_file);
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "✓ Migration successful!\n\n";
    
    // Verify tables
    echo "Verifying tables...\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wyandotte_teams");
    $result = $stmt->fetch();
    echo "✓ wyandotte_teams table created - {$result['count']} teams\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wyandotte_rosters");
    $result = $stmt->fetch();
    echo "✓ wyandotte_rosters table created - {$result['count']} rosters\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wyandotte_scores");
    $result = $stmt->fetch();
    echo "✓ wyandotte_scores table created - {$result['count']} scores\n";
    
    echo "\n✓ MIGRATION COMPLETE!\n";
    echo "\nNext steps:\n";
    echo "1. Visit /wyandotte/admin/teams.php to create teams\n";
    echo "2. Visit /wyandotte/admin/rosters.php to build rosters\n";
    
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
