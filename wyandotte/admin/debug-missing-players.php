<?php
require_once dirname(__DIR__, 2) . '/config.php';

echo "<h2>Debugging ESPN Data for Specific Players</h2>";

$testPlayerNames = [
    'Drake Maye',
    'Kyren Williams', 
    'Kenneth Walker III',
    'Jaxon Smith-Njigba',
    'Cooper DeJean',
    'Ernest Jones',
    'Nik Bonitto'
];

// Get their ESPN IDs
$placeholders = str_repeat('?,', count($testPlayerNames) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT p.id, p.full_name, sp.espn_id, pt.position, t.abbreviation as team
    FROM players p
    JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams t ON pt.team_id = t.id
    LEFT JOIN sleeper_players sp ON p.sleeper_id = sp.player_id
    WHERE p.full_name IN ($placeholders)
");
$stmt->execute($testPlayerNames);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Player</th><th>Position</th><th>Team</th><th>ESPN ID</th><th>Found in Games</th></tr>";

foreach ($players as $player) {
    echo "<tr>";
    echo "<td>{$player['full_name']}</td>";
    echo "<td>{$player['position']}</td>";
    echo "<td>{$player['team']}</td>";
    echo "<td>" . ($player['espn_id'] ?: '<span style="color:red">MISSING</span>') . "</td>";
    
    if ($player['espn_id']) {
        // Check if this ESPN ID appears in any playoff game
        $found = false;
        $gameDetails = [];
        
        // Check a few recent playoff games
        $testDates = ['20260118', '20260117', '20260112', '20260111', '20260110'];
        
        foreach ($testDates as $date) {
            $scoreboardUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates={$date}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $scoreboardUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
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
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $boxResponse = curl_exec($ch);
                curl_close($ch);
                
                if (!$boxResponse) continue;
                
                $boxScore = json_decode($boxResponse, true);
                if (!isset($boxScore['boxscore']['players'])) continue;
                
                foreach ($boxScore['boxscore']['players'] as $teamStats) {
                    foreach ($teamStats['statistics'] as $statCategory) {
                        if (!isset($statCategory['athletes'])) continue;
                        
                        foreach ($statCategory['athletes'] as $athlete) {
                            if (($athlete['athlete']['id'] ?? '') == $player['espn_id']) {
                                $found = true;
                                $gameName = $event['name'] ?? 'Unknown';
                                $gameDetails[] = "$gameName on $date";
                            }
                        }
                    }
                }
            }
        }
        
        if ($found) {
            echo "<td style='color:green'>✓ Found in: " . implode(', ', array_unique($gameDetails)) . "</td>";
        } else {
            echo "<td style='color:orange'>⚠ Not found in box scores (may not have played or team eliminated)</td>";
        }
    } else {
        echo "<td style='color:red'>N/A - No ESPN ID</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h3>Notes</h3>";
echo "<ul>";
echo "<li>Players on eliminated teams won't have stats in later rounds</li>";
echo "<li>Players who didn't play won't appear in box scores</li>";
echo "<li>Check if the team is still in the playoffs</li>";
echo "</ul>";
?>
