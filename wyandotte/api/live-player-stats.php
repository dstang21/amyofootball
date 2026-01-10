<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

// Get live player stats from ESPN
$cacheFile = __DIR__ . '/../cache/player_stats_live.json';
$cacheTime = 60; // Cache for 60 seconds

// Check cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    exit;
}

// Fetch current week's game IDs first
$scoreboardUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $scoreboardUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$scoreboardResponse = curl_exec($ch);
curl_close($ch);

if (!$scoreboardResponse) {
    echo json_encode(['error' => 'Failed to fetch scoreboard']);
    exit;
}

$scoreboard = json_decode($scoreboardResponse, true);
$allPlayerStats = [];

// Get all rostered players from wyandotte league
$stmt = $pdo->query("
    SELECT DISTINCT p.id, p.full_name, p.first_name, p.last_name, 
           pt.position, nfl.abbreviation as team_abbr
    FROM wyandotte_rosters wr
    JOIN players p ON wr.player_id = p.id
    JOIN player_teams pt ON p.id = pt.player_id
    JOIN teams nfl ON pt.team_id = nfl.id
");
$rosteredPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create lookup by player name
$playerLookup = [];
foreach ($rosteredPlayers as $player) {
    $key = strtolower($player['full_name']);
    $playerLookup[$key] = $player;
}

// Process each game
if (isset($scoreboard['events'])) {
    foreach ($scoreboard['events'] as $event) {
        $gameId = $event['id'];
        $competition = $event['competitions'][0] ?? null;
        
        if (!$competition) continue;
        
        $status = $competition['status']['type']['name'] ?? '';
        $isLive = in_array($status, ['STATUS_IN_PROGRESS', 'STATUS_HALFTIME']);
        $isCompleted = $status === 'STATUS_FINAL';
        
        // Only fetch stats for live or completed games
        if (!$isLive && !$isCompleted) continue;
        
        // Get box score for this game
        $boxScoreUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/summary?event={$gameId}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $boxScoreUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $boxScoreResponse = curl_exec($ch);
        curl_close($ch);
        
        if (!$boxScoreResponse) continue;
        
        $boxScore = json_decode($boxScoreResponse, true);
        
        // Parse player stats from box score
        if (isset($boxScore['boxscore']['players'])) {
            foreach ($boxScore['boxscore']['players'] as $teamStats) {
                $teamName = $teamStats['team']['displayName'] ?? '';
                
                foreach ($teamStats['statistics'] as $statCategory) {
                    $categoryName = $statCategory['name'] ?? ''; // passing, rushing, receiving, etc.
                    
                    if (!isset($statCategory['athletes'])) continue;
                    
                    foreach ($statCategory['athletes'] as $athlete) {
                        $playerName = $athlete['athlete']['displayName'] ?? '';
                        $playerKey = strtolower($playerName);
                        
                        // Check if this player is on any wyandotte roster
                        if (!isset($playerLookup[$playerKey])) continue;
                        
                        $rosteredPlayer = $playerLookup[$playerKey];
                        $playerId = $rosteredPlayer['id'];
                        
                        if (!isset($allPlayerStats[$playerId])) {
                            $allPlayerStats[$playerId] = [
                                'player_id' => $playerId,
                                'name' => $playerName,
                                'position' => $rosteredPlayer['position'],
                                'team' => $rosteredPlayer['team_abbr'],
                                'is_live' => $isLive,
                                'game_status' => $status,
                                'stats' => []
                            ];
                        }
                        
                        // Extract stats - this is already an indexed array
                        $stats = $athlete['stats'] ?? [];
                        
                        // Parse stats based on category
                        if ($categoryName === 'passing') {
                            $allPlayerStats[$playerId]['stats']['passing'] = [
                                'completions' => isset($stats[0]) ? intval($stats[0]) : 0,
                                'attempts' => isset($stats[1]) ? intval($stats[1]) : 0,
                                'yards' => isset($stats[2]) ? intval($stats[2]) : 0,
                                'avg' => isset($stats[3]) ? floatval($stats[3]) : 0,
                                'tds' => isset($stats[4]) ? intval($stats[4]) : 0,
                                'interceptions' => isset($stats[5]) ? intval($stats[5]) : 0,
                            ];
                        } elseif ($categoryName === 'rushing') {
                            $allPlayerStats[$playerId]['stats']['rushing'] = [
                                'attempts' => isset($stats[0]) ? intval($stats[0]) : 0,
                                'yards' => isset($stats[1]) ? intval($stats[1]) : 0,
                                'avg' => isset($stats[2]) ? floatval($stats[2]) : 0,
                                'long' => isset($stats[3]) ? intval($stats[3]) : 0,
                                'tds' => isset($stats[4]) ? intval($stats[4]) : 0,
                            ];
                        } elseif ($categoryName === 'receiving') {
                            $allPlayerStats[$playerId]['stats']['receiving'] = [
                                'receptions' => isset($stats[0]) ? intval($stats[0]) : 0,
                                'yards' => isset($stats[1]) ? intval($stats[1]) : 0,
                                'avg' => isset($stats[2]) ? floatval($stats[2]) : 0,
                                'long' => isset($stats[3]) ? intval($stats[3]) : 0,
                                'tds' => isset($stats[4]) ? intval($stats[4]) : 0,
                            ];
                        } elseif ($categoryName === 'defensive') {
                            $allPlayerStats[$playerId]['stats']['defensive'] = [
                                'tackles' => isset($stats[0]) ? intval($stats[0]) : 0,
                                'solo' => isset($stats[1]) ? intval($stats[1]) : 0,
                                'sacks' => isset($stats[2]) ? floatval($stats[2]) : 0,
                                'interceptions' => isset($stats[3]) ? intval($stats[3]) : 0,
                                'forced_fumbles' => isset($stats[4]) ? intval($stats[4]) : 0,
                            ];
                        }
                    }
                }
            }
        }
    }
}

$result = [
    'success' => true,
    'timestamp' => time(),
    'player_count' => count($allPlayerStats),
    'players' => array_values($allPlayerStats)
];

// Cache the result
if (!is_dir(__DIR__ . '/../cache')) {
    mkdir(__DIR__ . '/../cache', 0755, true);
}
file_put_contents($cacheFile, json_encode($result));

echo json_encode($result);
