<?php
// Increase execution time and memory limits
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

require_once 'config.php';
require_once 'admin/SleeperController.php';

echo "<h2>Sync Historical League Data</h2>";

try {
    $controller = new SleeperController();
    
    // Get all complete leagues that don't have roster data
    $sql = "SELECT sl.league_id, sl.name, sl.season,
                   COUNT(sr.league_id) as roster_count
            FROM sleeper_leagues sl
            LEFT JOIN sleeper_rosters sr ON sl.league_id = sr.league_id
            WHERE sl.status = 'complete' 
            GROUP BY sl.league_id, sl.name, sl.season
            HAVING roster_count = 0
            ORDER BY sl.season DESC
            LIMIT 3"; // Limit to 3 at a time to prevent timeouts
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $leagues = $stmt->fetchAll();
    
    if (empty($leagues)) {
        echo "<p>✅ No more historical leagues to sync!</p>";
        
        // Show current status
        echo "<h3>Current League Roster Status:</h3>";
        $statusSql = "SELECT sl.league_id, sl.name, sl.season, sl.status,
                             COUNT(sr.league_id) as roster_count
                      FROM sleeper_leagues sl
                      LEFT JOIN sleeper_rosters sr ON sl.league_id = sr.league_id
                      WHERE sl.status = 'complete'
                      GROUP BY sl.league_id, sl.name, sl.season
                      ORDER BY sl.season DESC";
        $statusStmt = $pdo->prepare($statusSql);
        $statusStmt->execute();
        $statusLeagues = $statusStmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Season</th><th>League Name</th><th>Roster Count</th><th>Status</th></tr>";
        foreach ($statusLeagues as $league) {
            $status = $league['roster_count'] > 0 ? '✅ Synced' : '❌ Needs Sync';
            echo "<tr>";
            echo "<td>{$league['season']}</td>";
            echo "<td>{$league['name']}</td>";
            echo "<td>{$league['roster_count']}</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p><a href='debug-sleeper-data.php'>Check Current Data</a> | ";
        echo "<a href='fantasy-league-detail.php?league_id=1180273607582396416'>View League Details</a></p>";
    } else {
        echo "<p>Syncing " . count($leagues) . " leagues (processing in small batches to prevent timeouts):</p>";
        
        foreach ($leagues as $league) {
            echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd;'>";
            echo "<h4>Syncing: {$league['name']} ({$league['season']}) - ID: {$league['league_id']}</h4>";
            echo "<p>Current roster count: {$league['roster_count']}</p>";
            
            // Flush output so user sees progress
            ob_flush();
            flush();
            
            try {
                // Use a simpler sync method that's less likely to timeout
                $result = $controller->syncLeagueBasics($league['league_id']);
                
                if ($result['success']) {
                    echo "<p style='color: green;'>✅ {$result['message']}</p>";
                    
                    // Check how many rosters were actually added
                    $checkSql = "SELECT COUNT(*) as new_count FROM sleeper_rosters WHERE league_id = ?";
                    $checkStmt = $pdo->prepare($checkSql);
                    $checkStmt->execute([$league['league_id']]);
                    $newCount = $checkStmt->fetch()['new_count'];
                    echo "<p>📊 Added {$newCount} rosters for this league</p>";
                } else {
                    echo "<p style='color: red;'>❌ {$result['message']}</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
            }
            
            echo "</div>";
            
            // Flush output again
            ob_flush();
            flush();
            
            // Add delay to be nice to the API
            sleep(2);
        }
        
        echo "<p><strong>✅ Batch complete!</strong></p>";
        echo "<p><a href='sync-historical-data.php'>Sync Next Batch</a> | ";
        echo "<a href='debug-sleeper-data.php'>Check Current Data</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Try refreshing the page or <a href='debug-sleeper-data.php'>check current data</a></p>";
}
?>
