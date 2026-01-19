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

// Get cumulative playoff stats for all rostered players
$query = "
    SELECT 
        p.id as player_id,
        p.full_name as name,
        pt.position,
        nfl.abbreviation as team,
        nfl.playoff_status,
        ps.*
    FROM wyandotte_rosters wr
    JOIN players p ON wr.player_id = p.id
    LEFT JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams nfl ON pt.team_id = nfl.id
    LEFT JOIN wyandotte_player_playoff_stats ps ON p.id = ps.player_id
    " . ($teamId ? "WHERE wr.team_id = ?" : "") . "
    GROUP BY p.id
    ORDER BY p.full_name
";

$stmt = $teamId ? $pdo->prepare($query) : $pdo->prepare($query);
if ($teamId) {
    $stmt->execute([$teamId]);
} else {
    $stmt->execute();
}
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate fantasy points for each player
$playerScores = [];
foreach ($players as $player) {
    $playerId = $player['player_id'];
    $totalPoints = 0;
    $breakdown = [];
    
    // Calculate passing points
    $passYards = intval($player['pass_yards'] ?? 0);
    $passTd = intval($player['pass_tds'] ?? 0);
    $passInt = intval($player['pass_ints'] ?? 0);
    
    if ($passYards > 0 || $passTd > 0 || $passInt > 0) {
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
    $rushYards = intval($player['rush_yards'] ?? 0);
    $rushTd = intval($player['rush_tds'] ?? 0);
    
    if ($rushYards > 0 || $rushTd > 0) {
        $rushYardsPoints = $rushYards * ($scoringSettings['rush_yards'] ?? 0);
        $rushTdPoints = $rushTd * ($scoringSettings['rush_td'] ?? 0);
        
        $totalPoints += $rushYardsPoints + $rushTdPoints;
        $breakdown['rushing'] = [
            'yards' => ['value' => $rushYards, 'points' => round($rushYardsPoints, 2)],
            'tds' => ['value' => $rushTd, 'points' => round($rushTdPoints, 2)]
        ];
    }
    
    // Calculate receiving points
    $receptions = intval($player['receptions'] ?? 0);
    $recYards = intval($player['rec_yards'] ?? 0);
    $recTd = intval($player['rec_tds'] ?? 0);
    
    if ($receptions > 0 || $recYards > 0 || $recTd > 0) {
        $receptionPoints = $receptions * ($scoringSettings['reception'] ?? 0);
        $recYardsPoints = $recYards * ($scoringSettings['rec_yards'] ?? 0);
        $recTdPoints = $recTd * ($scoringSettings['rec_td'] ?? 0);
        
        $totalPoints += $receptionPoints + $recYardsPoints + $recTdPoints;
        $breakdown['receiving'] = [
            'receptions' => ['value' => $receptions, 'points' => round($receptionPoints, 2)],
            'yards' => ['value' => $recYards, 'points' => round($recYardsPoints, 2)],
            'tds' => ['value' => $recTd, 'points' => round($recTdPoints, 2)]
        ];
    }
    
    // Calculate defensive points
    $soloTackles = intval($player['tackles_solo'] ?? 0);
    $assistedTackles = intval($player['tackles_assisted'] ?? 0);
    $totalTackles = intval($player['tackles_total'] ?? 0);
    $sacks = floatval($player['sacks'] ?? 0);
    $ints = intval($player['interceptions'] ?? 0);
    
    if ($totalTackles > 0 || $sacks > 0 || $ints > 0) {
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
    
    $isEliminated = ($player['playoff_status'] ?? 'active') === 'eliminated';
    
    $playerScores[$playerId] = [
        'player_id' => $playerId,
        'name' => $player['name'],
        'position' => $player['position'],
        'team' => $player['team'],
        'total_points' => round($totalPoints, 2),
        'breakdown' => $breakdown,
        'is_live' => false, // Cumulative stats, not live
        'is_eliminated' => $isEliminated,
        'games_played' => intval($player['games_played'] ?? 0)
    ];
}

// Calculate team totals
$teamScores = [];
$teamRostersQuery = "
    SELECT wr.team_id, wt.team_name, wt.owner_name, wr.player_id
    FROM wyandotte_rosters wr
    JOIN wyandotte_teams wt ON wr.team_id = wt.id
    " . ($teamId ? "WHERE wr.team_id = ?" : "") . "
    ORDER BY wr.team_id, wr.slot_number
";
$stmt = $teamId ? $pdo->prepare($teamRostersQuery) : $pdo->prepare($teamRostersQuery);
if ($teamId) {
    $stmt->execute([$teamId]);
} else {
    $stmt->execute();
}
$teamRosters = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($teamRosters as $row) {
    $tid = $row['team_id'];
    if (!isset($teamScores[$tid])) {
        $teamScores[$tid] = [
            'team_id' => $tid,
            'team_name' => $row['team_name'],
            'owner_name' => $row['owner_name'],
            'total_points' => 0,
            'player_count' => 0,
            'players' => []
        ];
    }
    
    if (isset($playerScores[$row['player_id']])) {
        $teamScores[$tid]['total_points'] += $playerScores[$row['player_id']]['total_points'];
        $teamScores[$tid]['player_count']++;
        $teamScores[$tid]['players'][] = $playerScores[$row['player_id']];
    }
}

// Convert to arrays and sort by points
$teamScores = array_values($teamScores);
usort($teamScores, function($a, $b) {
    return $b['total_points'] <=> $a['total_points'];
});

echo json_encode([
    'success' => true,
    'team_scores' => $teamScores,
    'player_scores' => array_values($playerScores),
    'timestamp' => time(),
    'note' => 'Cumulative playoff stats - all games combined'
]);
?>
