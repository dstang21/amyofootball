<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once dirname(__DIR__, 2) . '/config.php';

echo "<h2>Updating Cumulative Playoff Stats</h2>";
echo "<p>This will fetch ALL playoff games and accumulate stats...</p>";

// Get all rostered players with ESPN IDs
$stmt = $pdo->query("
    SELECT DISTINCT p.id, p.full_name, sp.espn_id
    FROM wyandotte_rosters wr
    JOIN players p ON wr.player_id = p.id
    LEFT JOIN sleeper_players sp ON p.sleeper_id = sp.player_id
    WHERE sp.espn_id IS NOT NULL AND sp.espn_id != ''
");
$rosteredPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create lookup by ESPN ID
$playerLookup = [];
foreach ($rosteredPlayers as $player) {
    $playerLookup[$player['espn_id']] = $player;
}

echo "<p>Found " . count($rosteredPlayers) . " rostered players with ESPN IDs</p>";

// NFL Playoffs 2026 started January 10, 2026 (Wild Card Weekend)
// Fetch all games since then
$startDate = '20260110'; // Format: YYYYMMDD
$endDate = date('Ymd'); // Today

echo "<p>Fetching games from {$startDate} to {$endDate}...</p>";

$allPlayerStats = [];

// Fetch games for each day in the playoff period
$currentDate = new DateTime($startDate);
$endDateTime = new DateTime($endDate);

while ($currentDate <= $endDateTime) {
    $dateStr = $currentDate->format('Ymd');
    echo "<p>Checking games on " . $currentDate->format('Y-m-d') . "...</p>";
    
    $scoreboardUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates={$dateStr}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $scoreboardUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $scoreboardResponse = curl_exec($ch);
    curl_close($ch);
    
    if (!$scoreboardResponse) {
        echo "<p style='color: orange;'>No data for this date</p>";
        $currentDate->modify('+1 day');
        continue;
    }
    
    $scoreboard = json_decode($scoreboardResponse, true);
    
    if (!isset($scoreboard['events']) || empty($scoreboard['events'])) {
        echo "<p style='color: gray;'>No games on this date</p>";
        $currentDate->modify('+1 day');
        continue;
    }
    
    echo "<p style='color: green;'>Found " . count($scoreboard['events']) . " games</p>";
    
    // Process each game
    foreach ($scoreboard['events'] as $event) {
        $gameId = $event['id'];
        $gameName = $event['name'] ?? 'Unknown Game';
        
        echo "<p>&nbsp;&nbsp;Processing: {$gameName}...</p>";
        
        // Get box score for this game
        $boxScoreUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/summary?event={$gameId}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $boxScoreUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $boxScoreResponse = curl_exec($ch);
        curl_close($ch);
        
        if (!$boxScoreResponse) continue;
        
        $boxScore = json_decode($boxScoreResponse, true);
        
        if (!isset($boxScore['boxscore']['players'])) continue;
        
        // Parse player stats from box score
        foreach ($boxScore['boxscore']['players'] as $teamStats) {
            foreach ($teamStats['statistics'] as $statCategory) {
                $categoryName = strtolower($statCategory['name'] ?? '');
                $labels = $statCategory['labels'] ?? [];
                
                if (!isset($statCategory['athletes'])) continue;
                
                foreach ($statCategory['athletes'] as $athlete) {
                    $espnId = $athlete['athlete']['id'] ?? null;
                    $athleteName = $athlete['athlete']['displayName'] ?? 'Unknown';
                    
                    // Debug: Check if this is one of our problem players
                    $problemPlayers = ['Drake Maye', 'Kyren Williams', 'Kenneth Walker III'];
                    if (in_array($athleteName, $problemPlayers)) {
                        echo "<p style='color: blue;'>DEBUG: Found {$athleteName} with ESPN ID {$espnId} in {$categoryName}</p>";
                        if (!isset($playerLookup[$espnId])) {
                            echo "<p style='color: red;'>ERROR: ESPN ID {$espnId} not in lookup!</p>";
                        }
                    }
                    
                    if (!$espnId || !isset($playerLookup[$espnId])) continue;
                    
                    $playerId = $playerLookup[$espnId]['id'];
                    
                    // Initialize player stats if not exists
                    if (!isset($allPlayerStats[$playerId])) {
                        $allPlayerStats[$playerId] = [
                            'player_id' => $playerId,
                            'pass_yards' => 0,
                            'pass_tds' => 0,
                            'pass_ints' => 0,
                            'pass_completions' => 0,
                            'pass_attempts' => 0,
                            'rush_yards' => 0,
                            'rush_tds' => 0,
                            'rush_attempts' => 0,
                            'receptions' => 0,
                            'rec_yards' => 0,
                            'rec_tds' => 0,
                            'tackles_solo' => 0,
                            'tackles_assisted' => 0,
                            'tackles_total' => 0,
                            'sacks' => 0,
                            'interceptions' => 0,
                            'games_played' => 0
                        ];
                    }
                    
                    // Extract stats
                    $stats = $athlete['stats'] ?? [];
                    $parsedStats = [];
                    
                    for ($i = 0; $i < count($labels); $i++) {
                        $label = $labels[$i] ?? '';
                        $value = $stats[$i] ?? '0';
                        $parsedStats[$label] = $value;
                    }
                    
                    // Accumulate stats by category
                    if ($categoryName === 'passing') {
                        $catt = explode('/', $parsedStats['C/ATT'] ?? '0/0');
                        $allPlayerStats[$playerId]['pass_completions'] += intval($catt[0] ?? 0);
                        $allPlayerStats[$playerId]['pass_attempts'] += intval($catt[1] ?? 0);
                        $allPlayerStats[$playerId]['pass_yards'] += intval($parsedStats['YDS'] ?? 0);
                        $allPlayerStats[$playerId]['pass_tds'] += intval($parsedStats['TD'] ?? 0);
                        $allPlayerStats[$playerId]['pass_ints'] += intval($parsedStats['INT'] ?? 0);
                    } elseif ($categoryName === 'rushing') {
                        $allPlayerStats[$playerId]['rush_attempts'] += intval($parsedStats['CAR'] ?? 0);
                        $allPlayerStats[$playerId]['rush_yards'] += intval($parsedStats['YDS'] ?? 0);
                        $allPlayerStats[$playerId]['rush_tds'] += intval($parsedStats['TD'] ?? 0);
                    } elseif ($categoryName === 'receiving') {
                        $allPlayerStats[$playerId]['receptions'] += intval($parsedStats['REC'] ?? 0);
                        $allPlayerStats[$playerId]['rec_yards'] += intval($parsedStats['YDS'] ?? 0);
                        $allPlayerStats[$playerId]['rec_tds'] += intval($parsedStats['TD'] ?? 0);
                    } elseif ($categoryName === 'defensive') {
                        // Check if tackles are in "SOLO/AST" format
                        if (isset($parsedStats['SOLO']) && strpos($parsedStats['SOLO'], '/') !== false) {
                            $tackleParts = explode('/', $parsedStats['SOLO']);
                            $solo = intval($tackleParts[0] ?? 0);
                            $assisted = intval($tackleParts[1] ?? 0);
                        } else {
                            $total = intval($parsedStats['TOT'] ?? 0);
                            $solo = intval($parsedStats['SOLO'] ?? 0);
                            $assisted = max(0, $total - $solo);
                        }
                        
                        $allPlayerStats[$playerId]['tackles_solo'] += $solo;
                        $allPlayerStats[$playerId]['tackles_assisted'] += $assisted;
                        $allPlayerStats[$playerId]['tackles_total'] += ($solo + $assisted);
                        $allPlayerStats[$playerId]['sacks'] += floatval($parsedStats['SACKS'] ?? 0);
                        $allPlayerStats[$playerId]['interceptions'] += intval($parsedStats['INT'] ?? 0);
                    }
                }
            }
        }
    }
    
    $currentDate->modify('+1 day');
    sleep(1); // Be nice to ESPN's API
}

echo "<h3>Saving to Database...</h3>";

// Clear existing stats first to ensure clean cumulative calculation
echo "<p>Clearing old stats...</p>";
$pdo->exec("DELETE FROM wyandotte_player_playoff_stats");
echo "<p>✓ Cleared</p>";

// Save accumulated stats to database
$saved = 0;
$updated = 0;

foreach ($allPlayerStats as $playerId => $stats) {
    try {
        // Use INSERT ... ON DUPLICATE KEY UPDATE
        $sql = "
            INSERT INTO wyandotte_player_playoff_stats (
                player_id, pass_yards, pass_tds, pass_ints, pass_completions, pass_attempts,
                rush_yards, rush_tds, rush_attempts,
                receptions, rec_yards, rec_tds,
                tackles_solo, tackles_assisted, tackles_total, sacks, interceptions,
                games_played
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                1
            ) ON DUPLICATE KEY UPDATE
                pass_yards = VALUES(pass_yards),
                pass_tds = VALUES(pass_tds),
                pass_ints = VALUES(pass_ints),
                pass_completions = VALUES(pass_completions),
                pass_attempts = VALUES(pass_attempts),
                rush_yards = VALUES(rush_yards),
                rush_tds = VALUES(rush_tds),
                rush_attempts = VALUES(rush_attempts),
                receptions = VALUES(receptions),
                rec_yards = VALUES(rec_yards),
                rec_tds = VALUES(rec_tds),
                tackles_solo = VALUES(tackles_solo),
                tackles_assisted = VALUES(tackles_assisted),
                tackles_total = VALUES(tackles_total),
                sacks = VALUES(sacks),
                interceptions = VALUES(interceptions),
                games_played = VALUES(games_played)
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $playerId,
            $stats['pass_yards'], $stats['pass_tds'], $stats['pass_ints'], 
            $stats['pass_completions'], $stats['pass_attempts'],
            $stats['rush_yards'], $stats['rush_tds'], $stats['rush_attempts'],
            $stats['receptions'], $stats['rec_yards'], $stats['rec_tds'],
            $stats['tackles_solo'], $stats['tackles_assisted'], $stats['tackles_total'],
            $stats['sacks'], $stats['interceptions']
        ]);
        
        $saved++;
        
        $playerName = '';
        foreach ($rosteredPlayers as $p) {
            if ($p['id'] == $playerId) {
                $playerName = $p['full_name'];
                break;
            }
        }
        
        echo "<p style='color: green;'>✓ Saved stats for {$playerName}</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error saving player {$playerId}: " . $e->getMessage() . "</p>";
    }
}

echo "<h3 style='color: green;'>✓ Complete!</h3>";
echo "<p>Saved stats for {$saved} players</p>";
echo "<p><a href='../rosters.php'>View Rosters</a></p>";
?>
