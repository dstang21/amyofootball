<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // Catch any stray output
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

// Get live player stats from ESPN
$cacheFile = __DIR__ . '/../cache/player_stats_live.json';
$cacheTime = 60; // Cache for 60 seconds

// Check cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    ob_end_clean(); // Clear buffer before cached response
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
    
    // Add first initial + last name format (e.g., "j.love" for "Jordan Love")
    $nameParts = explode(' ', $player['full_name']);
    if (count($nameParts) >= 2) {
        $firstInitial = strtolower(substr($nameParts[0], 0, 1));
        $lastName = strtolower($nameParts[count($nameParts) - 1]);
        $abbreviated = $firstInitial . '.' . $lastName;
        $playerLookup[$abbreviated] = $player;
    }
    
    // Add last name only
    if (count($nameParts) >= 2) {
        $lastName = strtolower($nameParts[count($nameParts) - 1]);
        $playerLookup[$lastName] = $player;
    }
}

// Get existing play descriptions from the last 4 hours to avoid duplicates
$stmt = $pdo->query("SELECT description FROM wyandotte_plays WHERE play_time >= NOW() - INTERVAL 4 HOUR");
$existingPlays = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

$playsProcessed = 0;
$playsInserted = 0;
$playsSkipped = 0;
$playErrors = [];

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
        
        // Process ALL plays from drives (not just scoring plays)
        if (isset($boxScore['drives']['previous'])) {
            foreach ($boxScore['drives']['previous'] as $drive) {
                // Get the team that has possession in this drive
                // Try multiple possible locations for team data
                $driveTeam = $drive['team']['abbreviation'] ?? 
                             $drive['team']['abbrev'] ?? 
                             $drive['displayName'] ?? 
                             null;
                
                // If still no team, try to extract from description or skip
                if (!$driveTeam && isset($drive['description'])) {
                    if (preg_match('/([A-Z]{2,3})\s/', $drive['description'], $match)) {
                        $driveTeam = $match[1];
                    }
                }
                
                if (isset($drive['plays'])) {
                    foreach ($drive['plays'] as $play) {
                        $playsProcessed++;
                        
                        $description = $play['text'] ?? '';
                        
                        // Skip if we've already recorded this play
                        if (isset($existingPlays[$description])) {
                            $playsSkipped++;
                            continue;
                        }
                        
                        // Extract player name from play text
                        $playerId = null;
                        $playerName = '';
                
                // Extract player name from the BEGINNING of the description (before the action)
                // Format is usually: "Player Name action..." or "Initial.LastName action..."
                preg_match('/^([A-Z]\.[A-Za-z]+)/', $description, $matches);
                $playStartName = $matches[1] ?? null;
                
                // Try to match player names - prioritize the name at start of play
                if ($playStartName) {
                    foreach ($playerLookup as $pName => $pData) {
                        if (stripos($playStartName, $pName) !== false || stripos($pName, $playStartName) !== false) {
                            $playerId = $pData['id'];
                            $playerName = $pData['full_name'];
                            break;
                        }
                    }
                }
                
                // If still no match, try broader search in full description
                if (!$playerId) {
                    foreach ($playerLookup as $pName => $pData) {
                        if (stripos($description, $pName) !== false) {
                            $playerId = $pData['id'];
                            $playerName = $pData['full_name'];
                            break;
                        }
                    }
                }
                
                // If no roster match, auto-create player in database
                if (!$playerId) {
                    // Use the name from start of play
                    $foundName = $playStartName ?? 'Unknown Player';
                    $playerName = $foundName;
                    
                    // Parse name (e.g., "J.Love" -> first: "J", last: "Love")
                    $nameParts = explode('.', $foundName);
                    $firstName = $nameParts[0] ?? 'Unknown';
                    $lastName = $nameParts[1] ?? 'Player';
                    $fullName = $firstName . '. ' . $lastName;
                    
                    // Insert new player into database
                    try {
                        $insertStmt = $pdo->prepare("
                            INSERT INTO players (first_name, last_name, full_name)
                            VALUES (?, ?, ?)
                        ");
                        $insertStmt->execute([$firstName, $lastName, $fullName]);
                        $playerId = $pdo->lastInsertId();
                        $playerName = $fullName;
                        $missingPlayers[$foundName] = 'AUTO-ADDED';
                    } catch (PDOException $e) {
                        // If duplicate or error, track it
                        $missingPlayers[$foundName] = 'ERROR: ' . $e->getMessage();
                    }
                }
                
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
                $period = $play['period']['number'] ?? 1;
                $clock = $play['clock']['displayValue'] ?? '';
                // Include drive team in game context for proper team logo lookup
                $gameContext = ($driveTeam ? $driveTeam . ' | ' : '') . $gameInfo . ' - Q' . $period . ' ' . $clock;
                
                // Insert play into database
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO wyandotte_plays (player_id, play_type, description, points, game_info, play_time)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$playerId, $playType, $description, $points, $gameContext]);
                    $existingPlays[$description] = true;
                    $playsInserted++;
                } catch (PDOException $e) {
                    // Skip duplicate or error
                    $playsSkipped++;
                    $playErrors[] = "DB Error: " . $e->getMessage();
                }
                    }
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
    'players' => array_values($allPlayerStats),
    'plays_sync' => [
        'processed' => $playsProcessed,
        'inserted' => $playsInserted,
        'skipped' => $playsSkipped,
        'errors' => $playErrors,
        'missing_players' => $missingPlayers
    ]
];

// Cache the result
if (!is_dir(__DIR__ . '/../cache')) {
    mkdir(__DIR__ . '/../cache', 0755, true);
}
file_put_contents($cacheFile, json_encode($result));

ob_end_clean(); // Clear any captured output
echo json_encode($result);
