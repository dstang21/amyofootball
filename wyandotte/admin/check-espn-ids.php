<?php
require_once dirname(__DIR__, 2) . '/config.php';

echo "<h2>Checking ESPN ID Mapping for Wyandotte Players</h2>";

$stmt = $pdo->query("
    SELECT 
        p.id,
        p.full_name,
        pt.position,
        t.abbreviation as team,
        sp.espn_id,
        wt.team_name as wyandotte_team
    FROM wyandotte_rosters wr
    JOIN players p ON wr.player_id = p.id
    JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams t ON pt.team_id = t.id
    LEFT JOIN sleeper_players sp ON p.sleeper_id = sp.player_id
    JOIN wyandotte_teams wt ON wr.team_id = wt.id
    ORDER BY wt.team_name, wr.slot_number
");

$withIds = 0;
$withoutIds = 0;
$currentTeam = '';

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($currentTeam != $row['wyandotte_team']) {
        $currentTeam = $row['wyandotte_team'];
        echo "<h3>{$currentTeam}</h3>";
    }
    
    if ($row['espn_id']) {
        echo "<p style='color: green;'>✓ {$row['full_name']} ({$row['position']} - {$row['team']}) - ESPN ID: {$row['espn_id']}</p>";
        $withIds++;
    } else {
        echo "<p style='color: red;'>✗ {$row['full_name']} ({$row['position']} - {$row['team']}) - <strong>NO ESPN ID</strong> - Player ID: {$row['id']}</p>";
        $withoutIds++;
    }
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>✓ Players with ESPN IDs: <strong>{$withIds}</strong></p>";
echo "<p>✗ Players missing ESPN IDs: <strong style='color: red;'>{$withoutIds}</strong></p>";

if ($withoutIds > 0) {
    echo "<hr>";
    echo "<h3>Action Required</h3>";
    echo "<p>Players without ESPN IDs will not have their stats recorded. You need to:</p>";
    echo "<ol>";
    echo "<li>Find the ESPN ID for each missing player</li>";
    echo "<li>Update the sleeper_players table with the ESPN ID</li>";
    echo "<li>Re-run the update-cumulative-stats.php script</li>";
    echo "</ol>";
}
?>
