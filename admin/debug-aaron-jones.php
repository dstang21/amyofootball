<?php
require_once '../config.php';

$session_id = $_GET['session'] ?? null;
$source_id = $_GET['source'] ?? null;

if (!$session_id || !$source_id) {
    die("Missing session or source parameters");
}

// Get current season
$stmt = $pdo->prepare("SELECT id FROM seasons WHERE is_current = 1 LIMIT 1");
$stmt->execute();
$current_season = $stmt->fetch(PDO::FETCH_ASSOC);
$season_id = $current_season['id'];

echo "<h2>Debug: Aaron Jones (ID: 235) Data</h2>";
echo "<p>Session: $session_id, Source: $source_id, Season: $season_id</p>";

// Check basic player info
$stmt = $pdo->prepare("SELECT * FROM players WHERE id = 235");
$stmt->execute();
$player = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Player Info:</h3>";
echo "<pre>" . print_r($player, true) . "</pre>";

// Check team assignment
$stmt = $pdo->prepare("SELECT pt.*, t.name as team_name, t.abbreviation FROM player_teams pt LEFT JOIN teams t ON pt.team_id = t.id WHERE pt.player_id = 235 AND pt.season_id = ?");
$stmt->execute([$season_id]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Team Assignment:</h3>";
echo "<pre>" . print_r($team, true) . "</pre>";

// Check projected stats for current source
$stmt = $pdo->prepare("SELECT * FROM projected_stats WHERE player_id = 235 AND season_id = ? AND source_id = ?");
$stmt->execute([$season_id, $source_id]);
$current_stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Current Source Stats (ID: $source_id):</h3>";
echo "<pre>" . print_r($current_stats, true) . "</pre>";

// Check AmyoFootball stats (source_id = 1)
$stmt = $pdo->prepare("SELECT * FROM projected_stats WHERE player_id = 235 AND season_id = ? AND source_id = 1");
$stmt->execute([$season_id]);
$amyo_stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>AmyoFootball Stats (Source ID: 1):</h3>";
echo "<pre>" . print_r($amyo_stats, true) . "</pre>";

// Run the exact same query as the spreadsheet for this player
$sort_order = "p.full_name";
$scoring_setting_id = 5;

$stmt = $pdo->prepare("
    SELECT p.id as player_id,
           p.full_name,
           p.position,
           t.abbreviation as team_abbr,
           
           -- Current source stats
           ps_current.id as current_stats_id,
           ps_current.passing_yards as curr_pass_yds,
           ps_current.passing_tds as curr_pass_tds,
           ps_current.interceptions as curr_ints,
           ps_current.rushing_yards as curr_rush_yds,
           ps_current.rushing_tds as curr_rush_tds,
           ps_current.receptions as curr_rec,
           ps_current.receiving_yards as curr_rec_yds,
           ps_current.receiving_tds as curr_rec_tds,
           ps_current.fumbles as curr_fumbles,
           
           -- AmyoFootball stats (source_id = 1) as fallback
           ps_amyo.id as amyo_stats_id,
           ps_amyo.passing_yards as amyo_pass_yds,
           ps_amyo.passing_tds as amyo_pass_tds,
           ps_amyo.interceptions as amyo_ints,
           ps_amyo.rushing_yards as amyo_rush_yds,
           ps_amyo.rushing_tds as amyo_rush_tds,
           ps_amyo.receptions as amyo_rec,
           ps_amyo.receiving_yards as amyo_rec_yds,
           ps_amyo.receiving_tds as amyo_rec_tds,
           ps_amyo.fumbles as amyo_fumbles
           
    FROM players p
    LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
    LEFT JOIN teams t ON pt.team_id = t.id
    LEFT JOIN projected_stats ps_current ON p.id = ps_current.player_id AND ps_current.season_id = ? AND ps_current.source_id = ?
    LEFT JOIN projected_stats ps_amyo ON p.id = ps_amyo.player_id AND ps_amyo.season_id = ? AND ps_amyo.source_id = 1
    WHERE (ps_current.id IS NOT NULL OR ps_amyo.id IS NOT NULL)
      AND p.full_name IS NOT NULL 
      AND p.full_name != ''
      AND p.id = 235
    ORDER BY $sort_order
");

$stmt->execute([$season_id, $season_id, $source_id, $season_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Spreadsheet Query Result for Aaron Jones:</h3>";
echo "<pre>" . print_r($result, true) . "</pre>";

// Check first few players from the actual query
$stmt = $pdo->prepare("
    SELECT p.id as player_id,
           p.full_name,
           p.position,
           t.abbreviation as team_abbr
           
    FROM players p
    LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
    LEFT JOIN teams t ON pt.team_id = t.id
    LEFT JOIN projected_stats ps_current ON p.id = ps_current.player_id AND ps_current.season_id = ? AND ps_current.source_id = ?
    LEFT JOIN projected_stats ps_amyo ON p.id = ps_amyo.player_id AND ps_amyo.season_id = ? AND ps_amyo.source_id = 1
    WHERE (ps_current.id IS NOT NULL OR ps_amyo.id IS NOT NULL)
      AND p.full_name IS NOT NULL 
      AND p.full_name != ''
    ORDER BY $sort_order
    LIMIT 5
");

$stmt->execute([$season_id, $season_id, $source_id, $season_id]);
$first_players = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>First 5 Players from Spreadsheet Query:</h3>";
echo "<pre>" . print_r($first_players, true) . "</pre>";
?>
