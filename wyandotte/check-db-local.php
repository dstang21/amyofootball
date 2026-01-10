<?php
// Local config for wyandotte - adjust these for your local Laragon setup
$local_host = 'localhost';
$local_db = 'amyofootball';  // Using local database name
$local_user = 'root';  // Default Laragon user
$local_pass = '';      // Default Laragon password (empty)

try {
    $local_pdo = new PDO("mysql:host=$local_host;dbname=$local_db", $local_user, $local_pass);
    $local_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING EXISTING TABLES ===\n\n";
    
    // Check for fantasy leagues
    try {
        $stmt = $local_pdo->query("SELECT COUNT(*) as count FROM fantasy_leagues");
        $result = $stmt->fetch();
        echo "✓ fantasy_leagues table exists - {$result['count']} leagues found\n";
    } catch (PDOException $e) {
        echo "✗ fantasy_leagues table does NOT exist\n";
    }
    
    // Check for sleeper rosters
    try {
        $stmt = $local_pdo->query("SELECT COUNT(*) as count FROM sleeper_rosters");
        $result = $stmt->fetch();
        echo "✓ sleeper_rosters table exists - {$result['count']} rosters found\n";
    } catch (PDOException $e) {
        echo "✗ sleeper_rosters table does NOT exist\n";
    }
    
    // Check for sleeper league users
    try {
        $stmt = $local_pdo->query("SELECT COUNT(*) as count FROM sleeper_league_users");
        $result = $stmt->fetch();
        echo "✓ sleeper_league_users table exists - {$result['count']} users found\n";
    } catch (PDOException $e) {
        echo "✗ sleeper_league_users table does NOT exist\n";
    }
    
    echo "\n=== CHECKING WYANDOTTE TABLES ===\n\n";
    
    // Check if wyandotte tables exist
    try {
        $stmt = $local_pdo->query("SELECT COUNT(*) as count FROM wyandotte_teams");
        $result = $stmt->fetch();
        echo "✓ wyandotte_teams table EXISTS - {$result['count']} teams found\n";
    } catch (PDOException $e) {
        echo "✗ wyandotte_teams table NEEDS MIGRATION\n";
    }
    
    try {
        $stmt = $local_pdo->query("SELECT COUNT(*) as count FROM wyandotte_rosters");
        $result = $stmt->fetch();
        echo "✓ wyandotte_rosters table EXISTS - {$result['count']} rosters found\n";
    } catch (PDOException $e) {
        echo "✗ wyandotte_rosters table NEEDS MIGRATION\n";
    }
    
    try {
        $stmt = $local_pdo->query("SELECT COUNT(*) as count FROM wyandotte_scores");
        $result = $stmt->fetch();
        echo "✓ wyandotte_scores table EXISTS - {$result['count']} scores found\n";
    } catch (PDOException $e) {
        echo "✗ wyandotte_scores table NEEDS MIGRATION\n";
    }
    
    echo "\n=== MIGRATION NEEDED? ===\n";
    echo "Run wyandotte_tables.sql if any Wyandotte tables are missing.\n";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    echo "\nTry adjusting the credentials at the top of this file.\n";
}
?>
