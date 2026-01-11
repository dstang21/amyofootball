<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

// Get team_id parameter
$teamId = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

if (!$teamId) {
    echo json_encode(['error' => 'Team ID required']);
    exit;
}

// Get team info
$stmt = $pdo->prepare("SELECT * FROM wyandotte_teams WHERE id = ?");
$stmt->execute([$teamId]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    echo json_encode(['error' => 'Team not found']);
    exit;
}

// Get full roster with player details
$stmt = $pdo->prepare("
    SELECT r.*, p.full_name, p.id as player_id, pt.position, 
           nfl.abbreviation as nfl_team_abbr
    FROM wyandotte_rosters r
    JOIN players p ON r.player_id = p.id
    JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams nfl ON pt.team_id = nfl.id
    WHERE r.team_id = ?
    ORDER BY r.slot_number
");
$stmt->execute([$teamId]);
$roster = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'team' => $team,
    'roster' => $roster
]);
