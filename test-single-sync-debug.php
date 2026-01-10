<?php
require_once 'config.php';
require_once 'SleeperSync.php';

echo "<h2>Test Single League Sync with Debug Output</h2>";

try {
    // Target the problematic league
    $leagueId = '1062950707059302400'; // 2024 A Few Wiseguys and Some Idiots
    
    echo "<h3>Testing League: $leagueId</h3>";
    
    // Clear existing rosters for this league to test fresh insert
    $stmt = $pdo->prepare("DELETE FROM sleeper_rosters WHERE league_id = ?");
    $stmt->execute([$leagueId]);
    echo "<p>Cleared existing rosters for league $leagueId</p>";
    
    // Create sync instance
    $sync = new SleeperSync($leagueId, $pdo);
    
    echo "<h3>Running syncBasics()...</h3>";
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; white-space: pre-wrap;'>";
    
    // Capture output
    ob_start();
    $sync->syncBasics();
    $output = ob_get_contents();
    ob_end_clean();
    
    echo htmlspecialchars($output);
    echo "</div>";
    
    // Check results
    echo "<h3>Results:</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sleeper_rosters WHERE league_id = ?");
    $stmt->execute([$leagueId]);
    $count = $stmt->fetch()['count'];
    
    echo "<p>Rosters synced: $count</p>";
    
    if ($count < 10) {
        echo "<h3>Detailed Roster Analysis:</h3>";
        $stmt = $pdo->prepare("SELECT roster_id, owner_id, wins, losses FROM sleeper_rosters WHERE league_id = ?");
        $stmt->execute([$leagueId]);
        $rosters = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Roster ID</th><th>Owner ID</th><th>Wins</th><th>Losses</th></tr>";
        foreach ($rosters as $roster) {
            echo "<tr><td>{$roster['roster_id']}</td><td>{$roster['owner_id']}</td><td>{$roster['wins']}</td><td>{$roster['losses']}</td></tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
