<?php
// Increase execution time and memory limits
set_time_limit(60); // 1 minute
ini_set('memory_limit', '256M');

require_once 'config.php';
require_once 'SleeperSync.php';

echo "<h2>Manual Single League Sync Test</h2>";

// Test with one 2024 league first
$testLeagueId = '1062950707059302400'; // "A Few Wiseguys and Some Idiots" 2024

echo "<p>Testing sync for league ID: $testLeagueId</p>";

try {
    $sync = new SleeperSync($testLeagueId, $pdo);
    
    echo "<p>Syncing league info...</p>";
    $sync->syncLeague();
    echo "<p>✅ League info synced</p>";
    
    echo "<p>Syncing users...</p>";
    $sync->syncUsers();
    echo "<p>✅ Users synced</p>";
    
    echo "<p>Syncing rosters...</p>";
    $sync->syncRosters();
    echo "<p>✅ Rosters synced</p>";
    
    echo "<h3>✅ Test sync completed successfully!</h3>";
    echo "<p><a href='debug-sleeper-data.php'>Check Results</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Details: " . $e->getFile() . " line " . $e->getLine() . "</p>";
}
?>
