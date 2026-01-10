<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

// Test basic database connectivity and data integrity
echo "<h2>AmyoFootball Site Test</h2>";

try {
    // Test database connection
    $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check for seasons
    $seasons = $pdo->query("SELECT COUNT(*) as count FROM seasons")->fetch();
    echo "<p>Seasons in database: " . $seasons['count'] . "</p>";
    
    // Check for players
    $players = $pdo->query("SELECT COUNT(*) as count FROM players")->fetch();
    echo "<p>Players in database: " . $players['count'] . "</p>";
    
    // Check for teams
    $teams = $pdo->query("SELECT COUNT(*) as count FROM teams")->fetch();
    echo "<p>Teams in database: " . $teams['count'] . "</p>";
    
    // Check for projected_stats
    $stats = $pdo->query("SELECT COUNT(*) as count FROM projected_stats")->fetch();
    echo "<p>Projected stats records: " . $stats['count'] . "</p>";
    
    // Check for player_teams
    $pt = $pdo->query("SELECT COUNT(*) as count FROM player_teams")->fetch();
    echo "<p>Player-team assignments: " . $pt['count'] . "</p>";
    
    echo "<h3>Sample Data</h3>";
    
    // Show sample players
    $sample_players = $pdo->query("SELECT full_name FROM players LIMIT 5")->fetchAll();
    if ($sample_players) {
        echo "<p><strong>Sample players:</strong></p><ul>";
        foreach ($sample_players as $player) {
            echo "<li>" . htmlspecialchars($player['full_name']) . "</li>";
        }
        echo "</ul>";
    }
    
    // Show sample teams
    $sample_teams = $pdo->query("SELECT name, abbreviation FROM teams LIMIT 5")->fetchAll();
    if ($sample_teams) {
        echo "<p><strong>Sample teams:</strong></p><ul>";
        foreach ($sample_teams as $team) {
            echo "<li>" . htmlspecialchars($team['abbreviation'] . ' - ' . $team['name']) . "</li>";
        }
        echo "</ul>";
    }
    
    echo "<p style='color: green;'><strong>✓ All basic tests passed! Your AmyoFootball site should be working correctly.</strong></p>";
    
    echo "<h3>Quick Links</h3>";
    echo "<ul>";
    echo "<li><a href='../index.php'>Go to Main Site</a></li>";
    echo "<li><a href='dashboard.php'>Go to Admin Dashboard</a></li>";
    echo "<li><a href='manage-stats.php'>Manage Projected Stats</a></li>";
    echo "<li><a href='db-check.php'>Database Check Tool</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 40px; }
h2 { color: #2c5aa0; }
h3 { color: #2c5aa0; margin-top: 30px; }
p { margin: 10px 0; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
a { color: #2c5aa0; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
