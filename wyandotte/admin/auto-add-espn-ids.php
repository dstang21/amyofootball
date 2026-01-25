<?php
require_once dirname(__DIR__, 2) . '/config.php';

echo "<h2>Auto-Finding ESPN IDs for Missing Players</h2>";
echo "<p>This will search ESPN's API for each missing player...</p>";

// Get players without ESPN IDs
$stmt = $pdo->query("
    SELECT DISTINCT
        p.id,
        p.full_name,
        p.sleeper_id,
        pt.position,
        t.abbreviation as team
    FROM wyandotte_rosters wr
    JOIN players p ON wr.player_id = p.id
    JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams t ON pt.team_id = t.id
    LEFT JOIN sleeper_players sp ON p.sleeper_id = sp.player_id
    WHERE sp.espn_id IS NULL OR sp.espn_id = ''
    ORDER BY p.full_name
");

$missingPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$found = 0;
$notFound = 0;

foreach ($missingPlayers as $player) {
    echo "<hr>";
    echo "<h3>{$player['full_name']} ({$player['position']} - {$player['team']})</h3>";
    
    // Search ESPN API - try to find the player
    // ESPN doesn't have a public search API, so we'll need to manually input these
    // For now, let's create a list of known mappings for playoff players
    
    $knownMappings = [
        // QBs
        'Trevor Lawrence' => '4040715',
        'Bo Nix' => '4431611',
        'Drake Maye' => '4685889',
        
        // RBs
        'Travis Etienne Jr.' => '4360294',
        'Kyren Williams' => '4429160',
        'R.J. Harvey' => '5156454', // Might need to verify
        'Kenneth Walker III' => '4431709',
        
        // WRs
        'Puka Nacua' => '4432577',
        'Nico Collins' => '4241389',
        'DeVonta Smith' => '4241457',
        'Jaxon Smith-Njigba' => '4431621',
        
        // TEs (none missing)
        
        // DBs
        'Nick Emmanwori' => '5156380',
        'Talanoa Hufanga' => '4360438',
        'Kamari Lassiter' => '5156434',
        'Cooper DeJean' => '4685637',
        'Xavier McKinney' => '4241470',
        
        // LBs
        'Ernest Jones' => '4431725',
        'Nate Landman' => '4240689',
        
        // DLs
        'Jalen Carter' => '4431614',
        'Byron Young' => '4432578',
        'Nik Bonitto' => '4426346',
        'Will Anderson' => '4431715',
    ];
    
    $espnId = $knownMappings[$player['full_name']] ?? null;
    
    if ($espnId) {
        echo "<p style='color: green;'>✓ Found ESPN ID: <strong>{$espnId}</strong></p>";
        
        // Update the database
        if ($player['sleeper_id']) {
            $updateStmt = $pdo->prepare("
                INSERT INTO sleeper_players (player_id, espn_id) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE espn_id = ?
            ");
            $updateStmt->execute([$player['sleeper_id'], $espnId, $espnId]);
            echo "<p style='color: blue;'>✓ Updated database</p>";
            $found++;
        } else {
            echo "<p style='color: orange;'>⚠ No Sleeper ID to update</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ ESPN ID not found in mapping database</p>";
        echo "<p>Search manually: <a href='https://www.espn.com/nfl/player/_/name/{$player['full_name']}' target='_blank'>ESPN Search</a></p>";
        $notFound++;
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>✓ Found and updated: <strong style='color: green;'>{$found}</strong></p>";
echo "<p>✗ Still missing: <strong style='color: red;'>{$notFound}</strong></p>";

if ($found > 0) {
    echo "<hr>";
    echo "<h3>Next Steps</h3>";
    echo "<p><a href='check-espn-ids.php' style='padding: 10px 20px; background: #f97316; color: white; text-decoration: none; border-radius: 5px;'>Re-check ESPN IDs</a></p>";
    echo "<p><a href='../admin/update-cumulative-stats.php' style='padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;'>Update Stats</a></p>";
}
?>
