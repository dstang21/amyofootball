<?php
require_once 'config.php';
require_once 'admin/SleeperController.php';

echo "<h2>Debug 2024 A Few Wiseguys League Roster Issue</h2>";

try {
    $controller = new SleeperController($pdo);
    
    // Find the 2024 league
    $stmt = $pdo->prepare("SELECT league_id FROM sleeper_leagues WHERE name = ? AND season = ?");
    $stmt->execute(['A Few Wiseguys and Some Idiots', '2024']);
    $league = $stmt->fetch();
    
    if (!$league) {
        echo "<p style='color: red;'>❌ Could not find 2024 'A Few Wiseguys and Some Idiots' league</p>";
        exit;
    }
    
    $leagueId = $league['league_id'];
    echo "<h3>League ID: $leagueId</h3>";
    
    // Get API data for this league
    echo "<h3>API Roster Data:</h3>";
    $url = "https://api.sleeper.app/v1/league/{$leagueId}/rosters";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "<p style='color: red;'>❌ API Error: HTTP $httpCode</p>";
        exit;
    }
    
    $rosters = json_decode($response, true);
    echo "<p>API returned " . count($rosters) . " rosters</p>";
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Roster ID</th><th>Owner ID</th><th>Wins</th><th>Losses</th><th>Players Count</th></tr>";
    
    $nullOwners = 0;
    foreach ($rosters as $roster) {
        $ownerId = $roster['owner_id'] ?? 'NULL';
        if ($ownerId === 'NULL' || $ownerId === null) {
            $nullOwners++;
        }
        
        echo "<tr>";
        echo "<td>{$roster['roster_id']}</td>";
        echo "<td>" . ($ownerId === 'NULL' ? '<span style="color: red;">NULL</span>' : $ownerId) . "</td>";
        echo "<td>" . ($roster['settings']['wins'] ?? 0) . "</td>";
        echo "<td>" . ($roster['settings']['losses'] ?? 0) . "</td>";
        echo "<td>" . count($roster['players'] ?? []) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p>Rosters with NULL owner_id: $nullOwners</p>";
    
    // Check what's in our database
    echo "<h3>Database Roster Data:</h3>";
    $stmt = $pdo->prepare("SELECT roster_id, owner_id, wins, losses FROM sleeper_rosters WHERE league_id = ?");
    $stmt->execute([$leagueId]);
    $dbRosters = $stmt->fetchAll();
    
    echo "<p>Database has " . count($dbRosters) . " rosters</p>";
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Roster ID</th><th>Owner ID</th><th>Wins</th><th>Losses</th></tr>";
    foreach ($dbRosters as $roster) {
        echo "<tr>";
        echo "<td>{$roster['roster_id']}</td>";
        echo "<td>{$roster['owner_id']}</td>";
        echo "<td>{$roster['wins']}</td>";
        echo "<td>{$roster['losses']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Try a manual sync of one problematic roster
    if ($nullOwners > 0) {
        echo "<h3>Testing Manual Sync with NULL owner_id:</h3>";
        
        // Find first roster with null owner_id
        $testRoster = null;
        foreach ($rosters as $roster) {
            if (($roster['owner_id'] ?? null) === null) {
                $testRoster = $roster;
                break;
            }
        }
        
        if ($testRoster) {
            echo "<p>Testing roster ID: {$testRoster['roster_id']} (owner_id is null)</p>";
            
            try {
                $sql = "INSERT INTO sleeper_rosters 
                        (league_id, roster_id, owner_id, wins, losses, ties, fpts, fpts_decimal, fpts_against, fpts_against_decimal, total_moves, waiver_position, waiver_budget_used, players, starters, reserve, settings)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        owner_id = VALUES(owner_id),
                        wins = VALUES(wins),
                        losses = VALUES(losses)";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $leagueId,
                    $testRoster['roster_id'],
                    $testRoster['owner_id'] ?? 'PLACEHOLDER_USER',
                    $testRoster['settings']['wins'] ?? 0,
                    $testRoster['settings']['losses'] ?? 0,
                    $testRoster['settings']['ties'] ?? 0,
                    $testRoster['settings']['fpts'] ?? 0.00,
                    $testRoster['settings']['fpts_decimal'] ?? 0.00,
                    $testRoster['settings']['fpts_against'] ?? 0.00,
                    $testRoster['settings']['fpts_against_decimal'] ?? 0.00,
                    $testRoster['settings']['total_moves'] ?? 0,
                    $testRoster['settings']['waiver_position'] ?? 0,
                    $testRoster['settings']['waiver_budget_used'] ?? 0,
                    json_encode($testRoster['players'] ?? []),
                    json_encode($testRoster['starters'] ?? []),
                    json_encode($testRoster['reserve'] ?? []),
                    json_encode($testRoster['settings'] ?? [])
                ]);
                
                $rowsAffected = $stmt->rowCount();
                echo "<p style='color: green;'>✅ Manual insert successful. Rows affected: $rowsAffected</p>";
                
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ Manual insert failed: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<h3>Solution:</h3>";
    echo "<p><a href='sync-historical-data.php'>Re-run Full Sync</a> to fix the missing rosters</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
