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

// Get existing play descriptions to avoid duplicates
$stmt = $pdo->query("SELECT description FROM wyandotte_plays");
$existingPlays = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

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
        
        // Get game info for plays
        $homeTeam = $competition['competitors'][0]['team']['abbreviation'] ?? '';
        $awayTeam = $competition['competitors'][1]['team']['abbreviation'] ?? '';
        $gameInfo = $awayTeam . ' @ ' . $homeTeam;
        
        // Process scoring plays while we have the data
        if (isset($boxScore['scoringPlays'])) {
            foreach ($boxScore['scoringPlays'] as $scoringPlay) {
                $playId = $scoringPlay['id'] ?? null;
                
                // Get play details from drives
                $playDetails = null;
                if (isset($boxScore['drives']['previous'])) {
                    foreach ($boxScore['drives']['previous'] as $drive) {
                        if (isset($drive['plays'])) {
                            foreach ($drive['plays'] as $play) {
                                if ($play['id'] == $playId) {
                                    $playDetails = $play;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                if (!$playDetails) continue;
                
                $description = $playDetails['text'] ?? '';
                
                // Skip if we've already recorded this play
                if (isset($existingPlays[$description])) continue;
                
                // Extract player name from play text
                $playerId = null;
                $playerName = '';
                foreach ($playerLookup as $pName => $pData) {
                    if (stripos($description, $pName) !== false) {
                        $playerId = $pData['id'];
                        $playerName = $pName;
                        break;
                    }
                }
                
                // Skip if player not on any roster
                if (!$playerId) continue;
                
                // Determine play type and points
                $playType = 'Play';
                $points = 0;
                
                if (stripos($description, 'touchdown pass') !== false || stripos($description, 'pass from') !== false) {
                    if (stripos($description, ' to ') !== false) {
                        // Check if our player is the passer or receiver
                        $beforeTo = stripos($description, ' to ');
                        $playerPos = stripos($description, $playerName);
                        if ($playerPos < $beforeTo) {
                            $playType = 'TD Pass';
                            $points = 4.0;
                        } else {
                            $playType = 'TD Reception';
                            $points = 6.0;
                        }
                    }
                } elseif ((stripos($description, 'rush') !== false || stripos($description, 'run for') !== false) && stripos($description, 'touchdown') !== false) {
                    $playType = 'TD Rush';
                    $points = 6.0;
                } elseif (stripos($description, 'intercepted') !== false || stripos($description, 'interception') !== false) {
                    $playType = 'Interception';
                    $points = 2.0;
                } elseif (stripos($description, 'sack') !== false) {
                    $playType = 'Sack';
                    $points = 1.0;
                } elseif (stripos($description, 'fumble') !== false && stripos($description, 'recovered') !== false) {
                    $playType = 'Fumble Recovery';
                    $points = 2.0;
                }
                
                // Get quarter and time
                $period = $playDetails['period']['number'] ?? 1;
                $clock = $playDetails['clock']['displayValue'] ?? '';
                $gameContext = $gameInfo . ' - Q' . $period . ' ' . $clock;
                
                // Insert play into database
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO wyandotte_plays (player_id, play_type, description, points, game_info, play_time)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$playerId, $playType, $description, $points, $gameContext]);
                    $existingPlays[$description] = true;
                } catch (PDOException $e) {
                    // Skip duplicate or error
                }
            }
        }
        
        // Parse player stats from box score
        if (isset($boxScore['boxscore']['players'])) {
            foreach ($boxScore['boxscore']['players'] as $teamStats) {
                $teamName = $teamStats['team']['displayName'] ?? '';
                
                foreach ($teamStats['statistics'] as $statCategory) {
                    $categoryName = $statCategory['name'] ?? '';
                    $labels = $statCategory['labels'] ?? [];
                    
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
                        
                        // Extract stats using labels as keys
                        $stats = $athlete['stats'] ?? [];
                        $parsedStats = [];
                        
                        for ($i = 0; $i < count($labels); $i++) {
                            $label = $labels[$i] ?? '';
                            $value = $stats[$i] ?? '0';
                            $parsedStats[$label] = $value;
                        }
                        
                        // Parse stats based on category with proper label matching
                        if ($categoryName === 'passing') {
                            // C/ATT format like "20/30"
                            $catt = explode('/', $parsedStats['C/ATT'] ?? '0/0');
                            $allPlayerStats[$playerId]['stats']['passing'] = [
                                'completions' => intval($catt[0] ?? 0),
                                'attempts' => intval($catt[1] ?? 0),
                                'yards' => intval($parsedStats['YDS'] ?? 0),
                                'tds' => intval($parsedStats['TD'] ?? 0),
                                'interceptions' => intval($parsedStats['INT'] ?? 0),
                            ];
                        } elseif ($categoryName === 'rushing') {
                            $allPlayerStats[$playerId]['stats']['rushing'] = [
                                'attempts' => intval($parsedStats['CAR'] ?? 0),
                                'yards' => intval($parsedStats['YDS'] ?? 0),
                                'tds' => intval($parsedStats['TD'] ?? 0),
                                'long' => intval($parsedStats['LONG'] ?? 0),
                            ];
                        } elseif ($categoryName === 'receiving') {
                            $allPlayerStats[$playerId]['stats']['receiving'] = [
                                'receptions' => intval($parsedStats['REC'] ?? 0),
                                'yards' => intval($parsedStats['YDS'] ?? 0),
                                'tds' => intval($parsedStats['TD'] ?? 0),
                                'long' => intval($parsedStats['LONG'] ?? 0),
                            ];
                        } elseif ($categoryName === 'defensive') {
                            $allPlayerStats[$playerId]['stats']['defensive'] = [
                                'tackles' => intval($parsedStats['TOT'] ?? 0),
                                'solo' => intval($parsedStats['SOLO'] ?? 0),
                                'sacks' => floatval($parsedStats['SACKS'] ?? 0),
                                'interceptions' => intval($parsedStats['INT'] ?? 0),
                            ];
                        }
                        
                        // Store raw data for debugging
                        $allPlayerStats[$playerId]['debug'] = [
                            'labels' => $labels,
                            'stats' => $stats,
                            'parsed' => $parsedStats
                        ];
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
