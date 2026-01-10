<?php
require_once 'config.php';
require_once 'SleeperSync.php';

echo "<h2>Debug Detailed Sync Process</h2>";

$testLeagueId = '1062950707059302400'; // A Few Wiseguys and Some Idiots 2024

echo "<h3>Testing sync for League ID: $testLeagueId</h3>";

try {
    $sync = new SleeperSync($testLeagueId, $pdo);
    
    echo "<h4>Step 1: Sync League Info</h4>";
    echo "<p>Calling API...</p>";
    
    // Manually test the API call method
    $reflection = new ReflectionClass($sync);
    $apiCallMethod = $reflection->getMethod('apiCall');
    $apiCallMethod->setAccessible(true);
    
    $leagueData = $apiCallMethod->invoke($sync, "/league/$testLeagueId");
    echo "<p>✅ API returned league data: " . $leagueData['name'] . " (Season: " . $leagueData['season'] . ")</p>";
    
    // Check if league already exists
    $stmt = $pdo->prepare("SELECT league_id FROM sleeper_leagues WHERE league_id = ?");
    $stmt->execute([$testLeagueId]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "<p>⚠️ League already exists in database</p>";
    } else {
        echo "<p>📝 League not in database, will insert</p>";
    }
    
    echo "<h4>Step 2: Sync Users</h4>";
    $usersData = $apiCallMethod->invoke($sync, "/league/$testLeagueId/users");
    echo "<p>✅ API returned " . count($usersData) . " users</p>";
    
    // Check if users already exist
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sleeper_league_users WHERE league_id = ?");
    $stmt->execute([$testLeagueId]);
    $userCount = $stmt->fetch()['count'];
    echo "<p>📊 Database currently has $userCount users for this league</p>";
    
    echo "<h4>Step 3: Sync Rosters</h4>";
    $rostersData = $apiCallMethod->invoke($sync, "/league/$testLeagueId/rosters");
    echo "<p>✅ API returned " . count($rostersData) . " rosters</p>";
    
    // Show sample roster data
    if (!empty($rostersData)) {
        $firstRoster = $rostersData[0];
        echo "<p>Sample roster data:</p>";
        echo "<ul>";
        echo "<li>Roster ID: " . ($firstRoster['roster_id'] ?? 'N/A') . "</li>";
        echo "<li>Owner ID: " . ($firstRoster['owner_id'] ?? 'N/A') . "</li>";
        echo "<li>Wins: " . ($firstRoster['settings']['wins'] ?? 'N/A') . "</li>";
        echo "<li>Losses: " . ($firstRoster['settings']['losses'] ?? 'N/A') . "</li>";
        echo "<li>Points: " . ($firstRoster['settings']['fpts'] ?? 'N/A') . "</li>";
        echo "</ul>";
    }
    
    // Check if rosters already exist
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sleeper_rosters WHERE league_id = ?");
    $stmt->execute([$testLeagueId]);
    $rosterCount = $stmt->fetch()['count'];
    echo "<p>📊 Database currently has $rosterCount rosters for this league</p>";
    
    echo "<h4>Step 4: Run Actual Sync</h4>";
    $result = $sync->syncBasics();
    
    if ($result['success']) {
        echo "<p style='color: green;'>✅ Sync completed: " . $result['message'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Sync failed: " . $result['message'] . "</p>";
    }
    
    echo "<h4>Step 5: Check Final Results</h4>";
    
    // Check final counts
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sleeper_league_users WHERE league_id = ?");
    $stmt->execute([$testLeagueId]);
    $finalUserCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sleeper_rosters WHERE league_id = ?");
    $stmt->execute([$testLeagueId]);
    $finalRosterCount = $stmt->fetch()['count'];
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Data Type</th><th>API Returned</th><th>Before Sync</th><th>After Sync</th><th>Change</th></tr>";
    echo "<tr><td>Users</td><td>" . count($usersData) . "</td><td>$userCount</td><td>$finalUserCount</td><td>" . ($finalUserCount - $userCount) . "</td></tr>";
    echo "<tr><td>Rosters</td><td>" . count($rostersData) . "</td><td>$rosterCount</td><td>$finalRosterCount</td><td>" . ($finalRosterCount - $rosterCount) . "</td></tr>";
    echo "</table>";
    
    if ($finalRosterCount == 0 && count($rostersData) > 0) {
        echo "<h4>🔍 Issue Found: API has data but database insert failed</h4>";
        echo "<p>This suggests a database constraint or SQL error during insert.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
}
?>
