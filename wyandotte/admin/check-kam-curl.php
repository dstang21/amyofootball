<?php
require_once dirname(__DIR__, 2) . '/config.php';

echo "<h2>Kam Curl Debug</h2>";

// Find Kam Curl in players
$stmt = $pdo->prepare("
    SELECT p.id, p.full_name, p.sleeper_id, sp.espn_id
    FROM players p
    LEFT JOIN sleeper_players sp ON p.sleeper_id = sp.player_id
    WHERE p.full_name LIKE '%Curl%'
");
$stmt->execute();
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    echo "<p style='color: red;'>Kam Curl not found in players table</p>";
    exit;
}

echo "<h3>Player Info</h3>";
echo "<pre>" . print_r($player, true) . "</pre>";

// Check playoff stats
$stmt = $pdo->prepare("SELECT * FROM wyandotte_player_playoff_stats WHERE player_id = ?");
$stmt->execute([$player['id']]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Playoff Stats from Database</h3>";
if ($stats) {
    echo "<pre>" . print_r($stats, true) . "</pre>";
} else {
    echo "<p style='color: red;'>No playoff stats found</p>";
}

// Check scoring settings for interceptions
echo "<h3>Interception Scoring Settings</h3>";
$stmt = $pdo->query("SELECT * FROM wyandotte_scoring_settings WHERE stat_key LIKE '%int%'");
$scoringSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>" . print_r($scoringSettings, true) . "</pre>";

// Manually fetch from ESPN if we have ESPN ID
if ($player['espn_id']) {
    echo "<h3>Fetching from ESPN API...</h3>";
    echo "<p>ESPN ID: {$player['espn_id']}</p>";
    
    // Check a recent playoff game - let's try Jan 18 (Divisional Round)
    $dates = ['20260118', '20260119', '20260111', '20260112', '20260113'];
    
    foreach ($dates as $date) {
        echo "<h4>Checking {$date}...</h4>";
        
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
            $gameInfo = $event['name'] ?? 'Unknown';
            
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
            
            // Look for Kam Curl
            foreach ($boxScore['boxscore']['players'] as $teamStats) {
                foreach ($teamStats['statistics'] as $statCategory) {
                    $categoryName = $statCategory['name'] ?? '';
                    
                    if (!isset($statCategory['athletes'])) continue;
                    
                    foreach ($statCategory['athletes'] as $athlete) {
                        $espnId = $athlete['athlete']['id'] ?? null;
                        $name = $athlete['athlete']['displayName'] ?? '';
                        
                        if ($espnId == $player['espn_id']) {
                            echo "<p style='color: green;'><strong>FOUND in {$gameInfo} ({$categoryName} stats)</strong></p>";
                            echo "<p>Name: {$name}</p>";
                            echo "<pre>" . print_r($athlete, true) . "</pre>";
                        }
                    }
                }
            }
        }
    }
}
?>
