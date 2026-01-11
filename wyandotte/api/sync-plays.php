<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

// This script fetches play-by-play data from ESPN and stores scoring plays in wyandotte_plays table

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
    echo json_encode(['success' => false, 'error' => 'Failed to fetch scoreboard']);
    exit;
}

$scoreboard = json_decode($scoreboardResponse, true);

// Get all rostered players from wyandotte league
$stmt = $pdo->query("
    SELECT DISTINCT p.id, p.full_name, p.first_name, p.last_name
    FROM wyandotte_rosters wr
    JOIN players p ON wr.player_id = p.id
");
$rosteredPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create lookup by player name
$playerLookup = [];
foreach ($rosteredPlayers as $player) {
    $key = strtolower($player['full_name']);
    $playerLookup[$key] = $player['id'];
    
    // Also add variations (first/last name only)
    $firstName = strtolower($player['first_name']);
    $lastName = strtolower($player['last_name']);
    $playerLookup[$firstName . ' ' . $lastName] = $player['id'];
}

$playsInserted = 0;
$playsSkipped = 0;

// Keep track of plays we've already seen to avoid duplicates
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
        
        // Only fetch plays for live or completed games
        if (!$isLive && !$isCompleted) continue;
        
        // Get game info
        $homeTeam = $competition['competitors'][0]['team']['abbreviation'] ?? '';
        $awayTeam = $competition['competitors'][1]['team']['abbreviation'] ?? '';
        $gameInfo = $awayTeam . ' @ ' . $homeTeam;
        
        // Get play-by-play data
        $playsUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/summary?event={$gameId}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $playsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $playsResponse = curl_exec($ch);
        curl_close($ch);
        
        if (!$playsResponse) continue;
        
        $gameData = json_decode($playsResponse, true);
        
        // Parse scoring plays
        if (isset($gameData['scoringPlays'])) {
            foreach ($gameData['scoringPlays'] as $scoringPlay) {
                $playId = $scoringPlay['id'] ?? null;
                
                // Get play details from drives
                $playDetails = null;
                if (isset($gameData['drives']['previous'])) {
                    foreach ($gameData['drives']['previous'] as $drive) {
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
                if (isset($existingPlays[$description])) {
                    $playsSkipped++;
                    continue;
                }
                
                // Extract player name from play text
                $playerId = null;
                foreach ($playerLookup as $playerName => $pId) {
                    if (stripos($description, $playerName) !== false) {
                        $playerId = $pId;
                        break;
                    }
                }
                
                // Skip if player not on any roster
                if (!$playerId) {
                    $playsSkipped++;
                    continue;
                }
                
                // Determine play type and points
                $playType = 'Play';
                $points = 0;
                
                if (stripos($description, 'touchdown pass') !== false || stripos($description, 'pass from') !== false) {
                    if (stripos($description, ' to ') !== false) {
                        // Check if our player is the passer
                        $parts = preg_split('/ (pass from|to) /i', $description);
                        if (count($parts) >= 2 && stripos($parts[0], $playerName) !== false) {
                            $playType = 'TD Pass';
                            $points = 4.0;
                        } elseif (count($parts) >= 2 && stripos($parts[1], $playerName) !== false) {
                            $playType = 'TD Reception';
                            $points = 6.0;
                        }
                    }
                } elseif (stripos($description, 'rush') !== false && stripos($description, 'touchdown') !== false) {
                    $playType = 'TD Rush';
                    $points = 6.0;
                } elseif (stripos($description, 'run for') !== false && stripos($description, 'touchdown') !== false) {
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
                    $playsInserted++;
                    
                    // Add to existing plays to avoid duplicates in same run
                    $existingPlays[$description] = true;
                } catch (PDOException $e) {
                    // Skip duplicate or error
                    $playsSkipped++;
                }
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'plays_inserted' => $playsInserted,
    'plays_skipped' => $playsSkipped,
    'timestamp' => date('Y-m-d H:i:s')
]);
