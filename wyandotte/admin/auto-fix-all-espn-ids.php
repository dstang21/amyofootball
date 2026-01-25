<?php
require_once dirname(__DIR__, 2) . '/config.php';
set_time_limit(300);

echo "<h2>Comprehensive ESPN ID Auto-Fix</h2>";
echo "<p>Scanning actual playoff game data to find correct ESPN IDs...</p>";

// Get all rostered players
$stmt = $pdo->query("
    SELECT DISTINCT
        p.id,
        p.full_name,
        p.sleeper_id,
        pt.position,
        t.abbreviation as team,
        sp.espn_id as current_espn_id
    FROM wyandotte_rosters wr
    JOIN players p ON wr.player_id = p.id
    JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams t ON pt.team_id = t.id
    LEFT JOIN sleeper_players sp ON p.sleeper_id = sp.player_id
    ORDER BY p.full_name
");
$allPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Scanning " . count($allPlayers) . " players...</p>";

// Scan all playoff games and collect ESPN IDs
$foundIds = [];
$dates = [];
for ($i = 10; $i <= 25; $i++) {
    $dates[] = '202601' . str_pad($i, 2, '0', STR_PAD_LEFT);
}

foreach ($dates as $date) {
    $scoreboardUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates={$date}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $scoreboardUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) continue;
    
    $scoreboard = json_decode($response, true);
    if (!isset($scoreboard['events'])) continue;
    
    foreach ($scoreboard['events'] as $event) {
        $gameId = $event['id'];
        
        $boxScoreUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/summary?event={$gameId}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $boxScoreUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $boxResponse = curl_exec($ch);
        curl_close($ch);
        
        if (!$boxResponse) continue;
        
        $boxScore = json_decode($boxResponse, true);
        if (!isset($boxScore['boxscore']['players'])) continue;
        
        foreach ($boxScore['boxscore']['players'] as $teamStats) {
            foreach ($teamStats['statistics'] as $statCategory) {
                if (!isset($statCategory['athletes'])) continue;
                
                foreach ($statCategory['athletes'] as $athlete) {
                    $espnId = $athlete['athlete']['id'] ?? null;
                    $name = $athlete['athlete']['displayName'] ?? '';
                    
                    if ($espnId && $name) {
                        $foundIds[$name] = $espnId;
                    }
                }
            }
        }
    }
    
    sleep(1);
}

echo "<p>Found " . count($foundIds) . " unique players in playoff games</p>";
echo "<hr>";

// Helper function to normalize names for matching
function normalizeName($name) {
    // Remove suffixes like Jr., Sr., II, III, IV, V
    $name = preg_replace('/\s+(Jr\.?|Sr\.?|II|III|IV|V)$/i', '', $name);
    // Remove periods from initials (R.J. -> RJ)
    $name = str_replace('.', '', $name);
    return trim($name);
}

// Create normalized lookup
$normalizedFoundIds = [];
foreach ($foundIds as $name => $id) {
    $normalized = normalizeName($name);
    $normalizedFoundIds[$normalized] = ['id' => $id, 'original' => $name];
}

// Match and update
$updated = 0;
$notFound = 0;
$alreadyCorrect = 0;

foreach ($allPlayers as $player) {
    // Try exact match first
    $correctId = $foundIds[$player['full_name']] ?? null;
    $matchedName = $player['full_name'];
    
    // If no exact match, try normalized name
    if (!$correctId) {
        $normalized = normalizeName($player['full_name']);
        if (isset($normalizedFoundIds[$normalized])) {
            $correctId = $normalizedFoundIds[$normalized]['id'];
            $matchedName = $normalizedFoundIds[$normalized]['original'];
        }
    }
    
    if ($correctId) {
        if ($player['current_espn_id'] == $correctId) {
            echo "<p style='color: green;'>✓ {$player['full_name']}: Already correct ({$correctId})</p>";
            $alreadyCorrect++;
        } else {
            $nameNote = ($matchedName !== $player['full_name']) ? " (matched as: {$matchedName})" : "";
            echo "<p style='color: blue;'>⟳ {$player['full_name']}{$nameNote}: Updating {$player['current_espn_id']} → {$correctId}</p>";
            
            if ($player['sleeper_id']) {
                $stmt = $pdo->prepare("
                    INSERT INTO sleeper_players (player_id, espn_id) 
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE espn_id = ?
                ");
                $stmt->execute([$player['sleeper_id'], $correctId, $correctId]);
                $updated++;
            }
        }
    } else {
        echo "<p style='color: red;'>✗ {$player['full_name']}: Not found in any playoff game (team eliminated or didn't play)</p>";
        $notFound++;
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>✓ Already correct: <strong style='color: green;'>{$alreadyCorrect}</strong></p>";
echo "<p>⟳ Updated: <strong style='color: blue;'>{$updated}</strong></p>";
echo "<p>✗ Not found: <strong style='color: red;'>{$notFound}</strong></p>";

if ($updated > 0) {
    echo "<hr>";
    echo "<h3>Next Step</h3>";
    echo "<p><a href='update-cumulative-stats.php' style='padding: 15px 30px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; font-size: 1.2rem;'>Update All Stats Now</a></p>";
}
?>
