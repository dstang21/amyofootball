<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

// Get team_id parameter
$teamId = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

// Get scoring settings
$scoringSettings = [];
$stmt = $pdo->query("SELECT stat_key, points_value FROM wyandotte_scoring_settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $scoringSettings[$row['stat_key']] = floatval($row['points_value']);
}

// Get live player stats
$liveStatsFile = __DIR__ . '/../cache/player_stats_live.json';
if (!file_exists($liveStatsFile)) {
    echo json_encode(['error' => 'No live stats available', 'scores' => []]);
    exit;
}

$liveData = json_decode(file_get_contents($liveStatsFile), true);
if (!$liveData || !isset($liveData['players'])) {
    echo json_encode(['error' => 'Invalid stats data', 'scores' => []]);
    exit;
}

// Calculate fantasy points for each player
$playerScores = [];
foreach ($liveData['players'] as $player) {
    $playerId = $player['player_id'];
    $totalPoints = 0;
    $breakdown = [];
    
    // Calculate passing points
    if (isset($player['stats']['passing'])) {
        $p = $player['stats']['passing'];
        $passYards = intval($p['yards'] ?? 0);
        $passTd = intval($p['tds'] ?? 0);
        $passInt = intval($p['interceptions'] ?? 0);
        
        $passYardsPoints = $passYards * ($scoringSettings['pass_yards'] ?? 0);
        $passTdPoints = $passTd * ($scoringSettings['pass_td'] ?? 0);
        $passIntPoints = $passInt * ($scoringSettings['pass_int'] ?? 0);
        
        $totalPoints += $passYardsPoints + $passTdPoints + $passIntPoints;
        $breakdown['passing'] = [
            'yards' => ['value' => $passYards, 'points' => round($passYardsPoints, 2)],
            'tds' => ['value' => $passTd, 'points' => round($passTdPoints, 2)],
            'ints' => ['value' => $passInt, 'points' => round($passIntPoints, 2)]
        ];
    }
    
    // Calculate rushing points
    if (isset($player['stats']['rushing'])) {
        $r = $player['stats']['rushing'];
        $rushYards = intval($r['yards'] ?? 0);
        $rushTd = intval($r['tds'] ?? 0);
        
        $rushYardsPoints = $rushYards * ($scoringSettings['rush_yards'] ?? 0);
        $rushTdPoints = $rushTd * ($scoringSettings['rush_td'] ?? 0);
        
        $totalPoints += $rushYardsPoints + $rushTdPoints;
        $breakdown['rushing'] = [
            'yards' => ['value' => $rushYards, 'points' => round($rushYardsPoints, 2)],
            'tds' => ['value' => $rushTd, 'points' => round($rushTdPoints, 2)]
        ];
    }
    
    // Calculate receiving points
    if (isset($player['stats']['receiving'])) {
        $rec = $player['stats']['receiving'];
        $receptions = intval($rec['receptions'] ?? 0);
        $recYards = intval($rec['yards'] ?? 0);
        $recTd = intval($rec['tds'] ?? 0);
        
        $recPoints = $receptions * ($scoringSettings['receptions'] ?? 0);
        $recYardsPoints = $recYards * ($scoringSettings['rec_yards'] ?? 0);
        $recTdPoints = $recTd * ($scoringSettings['rec_td'] ?? 0);
        
        $totalPoints += $recPoints + $recYardsPoints + $recTdPoints;
        $breakdown['receiving'] = [
            'receptions' => ['value' => $receptions, 'points' => round($recPoints, 2)],
            'yards' => ['value' => $recYards, 'points' => round($recYardsPoints, 2)],
            'tds' => ['value' => $recTd, 'points' => round($recTdPoints, 2)]
        ];
    }
    
    // Calculate defensive points
    if (isset($player['stats']['defensive'])) {
        $d = $player['stats']['defensive'];
        $soloTackles = intval($d['solo'] ?? 0);
        $assistedTackles = intval($d['assisted'] ?? 0);
        $totalTackles = intval($d['tackles'] ?? 0);
        $sacks = floatval($d['sacks'] ?? 0);
        $ints = intval($d['interceptions'] ?? 0);
        
        $soloTacklePoints = $soloTackles * ($scoringSettings['tackle_solo'] ?? 0);
        $assistedTacklePoints = $assistedTackles * ($scoringSettings['tackle_assist'] ?? 0);
        $tacklePoints = $soloTacklePoints + $assistedTacklePoints;
        $sackPoints = $sacks * ($scoringSettings['sack'] ?? 0);
        $intPoints = $ints * ($scoringSettings['interception'] ?? 0);
        
        $totalPoints += $tacklePoints + $sackPoints + $intPoints;
        $breakdown['defensive'] = [
            'tackles' => ['value' => $totalTackles, 'points' => round($tacklePoints, 2), 'detail' => "$soloTackles solo ($soloTacklePoints pts), $assistedTackles ast (" . round($assistedTacklePoints, 1) . " pts)"],
            'sacks' => ['value' => $sacks, 'points' => round($sackPoints, 2)],
            'ints' => ['value' => $ints, 'points' => round($intPoints, 2)]
        ];
    }
    
    $playerScores[$playerId] = [
        'player_id' => $playerId,
        'name' => $player['name'],
        'position' => $player['position'],
        'team' => $player['team'],
        'total_points' => round($totalPoints, 2),
        'breakdown' => $breakdown,
        'is_live' => $player['is_live']
    ];
}

// Calculate team scores if team_id provided or all teams
$teamScores = [];

if ($teamId) {
    // Get specific team's roster and calculate score
    $stmt = $pdo->prepare("
        SELECT r.player_id, p.full_name, r.position
        FROM wyandotte_rosters r
        JOIN players p ON r.player_id = p.id
        WHERE r.team_id = ?
    ");
    $stmt->execute([$teamId]);
    $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $teamTotal = 0;
    $teamPlayers = [];
    
    foreach ($roster as $player) {
        $playerId = $player['player_id'];
        if (isset($playerScores[$playerId])) {
            $score = $playerScores[$playerId];
            $teamTotal += $score['total_points'];
            $teamPlayers[] = $score;
        }
    }
    
    $teamScores = [[
        'team_id' => $teamId,
        'total_points' => round($teamTotal, 2),
        'players' => $teamPlayers
    ]];
} else {
    // Calculate all team scores
    $teams = $pdo->query("SELECT id, team_name, owner_name, logo FROM wyandotte_teams")->fetchAll();
    
    foreach ($teams as $team) {
        $stmt = $pdo->prepare("
            SELECT r.player_id, p.full_name, r.position
            FROM wyandotte_rosters r
            JOIN players p ON r.player_id = p.id
            WHERE r.team_id = ?
        ");
        $stmt->execute([$team['id']]);
        $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $teamTotal = 0;
        $teamPlayers = [];
        
        foreach ($roster as $player) {
            $playerId = $player['player_id'];
            if (isset($playerScores[$playerId])) {
                $score = $playerScores[$playerId];
                $teamTotal += $score['total_points'];
                $teamPlayers[] = $score;
            }
        }
        
        $teamScores[] = [
            'team_id' => $team['id'],
            'team_name' => $team['team_name'],
            'owner_name' => $team['owner_name'],
            'logo' => $team['logo'],
            'total_points' => round($teamTotal, 2),
            'player_count' => count($teamPlayers),
            'players' => $teamPlayers
        ];
    }
    
    // Sort by total points descending
    usort($teamScores, function($a, $b) {
        return $b['total_points'] <=> $a['total_points'];
    });
}

echo json_encode([
    'success' => true,
    'timestamp' => time(),
    'team_scores' => $teamScores
]);
