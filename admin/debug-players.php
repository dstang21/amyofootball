<?php
require_once '../config.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$page_title = 'Debug Players';

// Get current season
$current_season_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC LIMIT 1");
$current_season = $current_season_query->fetch();

echo "<h1>Player Debug Tool</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f2f2f2; }
    .null-value { background: #ffcccb; color: red; font-style: italic; }
    .empty-value { background: #fff3cd; color: #856404; }
    .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
</style>";

// Check for problematic players
$problematic_players = $pdo->query("
    SELECT p.id, p.first_name, p.last_name, p.full_name, p.birth_date, p.created_at,
           COUNT(ps.id) as stats_count,
           COUNT(pt.id) as team_assignments,
           GROUP_CONCAT(DISTINCT s.name) as sources_used
    FROM players p
    LEFT JOIN projected_stats ps ON p.id = ps.player_id
    LEFT JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN sources s ON ps.source_id = s.id
    WHERE p.first_name IS NULL OR p.first_name = '' 
       OR p.last_name IS NULL OR p.last_name = ''
       OR p.full_name IS NULL OR p.full_name = ''
    GROUP BY p.id
    ORDER BY p.created_at DESC
")->fetchAll();

echo "<div class='card'>";
echo "<h2>Players with Missing Names</h2>";

if (!empty($problematic_players)) {
    echo "<p style='color: red;'>Found " . count($problematic_players) . " problematic player(s):</p>";
    
    echo "<table>";
    echo "<tr>";
    echo "<th>Player ID</th>";
    echo "<th>First Name</th>";
    echo "<th>Last Name</th>";
    echo "<th>Full Name</th>";
    echo "<th>Birth Date</th>";
    echo "<th>Created</th>";
    echo "<th>Stats Count</th>";
    echo "<th>Team Assignments</th>";
    echo "<th>Sources Used</th>";
    echo "<th>Actions</th>";
    echo "</tr>";
    
    foreach ($problematic_players as $player) {
        echo "<tr>";
        echo "<td>" . $player['id'] . "</td>";
        echo "<td class='" . (empty($player['first_name']) ? 'null-value' : '') . "'>" . 
             ($player['first_name'] ?: 'NULL/EMPTY') . "</td>";
        echo "<td class='" . (empty($player['last_name']) ? 'null-value' : '') . "'>" . 
             ($player['last_name'] ?: 'NULL/EMPTY') . "</td>";
        echo "<td class='" . (empty($player['full_name']) ? 'null-value' : '') . "'>" . 
             ($player['full_name'] ?: 'NULL/EMPTY') . "</td>";
        echo "<td>" . ($player['birth_date'] ?: 'Not Set') . "</td>";
        echo "<td>" . $player['created_at'] . "</td>";
        echo "<td>" . $player['stats_count'] . "</td>";
        echo "<td>" . $player['team_assignments'] . "</td>";
        echo "<td>" . ($player['sources_used'] ?: 'None') . "</td>";
        echo "<td>";
        echo "<a href='?delete_player=" . $player['id'] . "' onclick='return confirm(\"Delete this player and all related data?\")' style='color: red;'>Delete</a>";
        echo " | ";
        echo "<a href='edit-debug-player.php?id=" . $player['id'] . "'>Edit</a>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color: green;'>No players found with missing names!</p>";
}

echo "</div>";

// Check for players without team assignments in current season
if ($current_season) {
    echo "<div class='card'>";
    echo "<h2>Players Without Team/Position (Current Season)</h2>";
    
    $no_team_players = $pdo->prepare("
        SELECT p.id, p.full_name, COUNT(ps.id) as stats_count,
               GROUP_CONCAT(DISTINCT s.name) as sources_used
        FROM players p
        LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
        LEFT JOIN projected_stats ps ON p.id = ps.player_id AND ps.season_id = ?
        LEFT JOIN sources s ON ps.source_id = s.id
        WHERE pt.id IS NULL AND ps.id IS NOT NULL
        GROUP BY p.id
        ORDER BY p.full_name
    ");
    $no_team_players->execute([$current_season['id'], $current_season['id']]);
    $players_no_team = $no_team_players->fetchAll();
    
    if (!empty($players_no_team)) {
        echo "<p style='color: orange;'>Found " . count($players_no_team) . " player(s) with stats but no team/position:</p>";
        
        echo "<table>";
        echo "<tr><th>Player ID</th><th>Full Name</th><th>Stats Count</th><th>Sources</th></tr>";
        
        foreach ($players_no_team as $player) {
            echo "<tr>";
            echo "<td>" . $player['id'] . "</td>";
            echo "<td>" . htmlspecialchars($player['full_name']) . "</td>";
            echo "<td>" . $player['stats_count'] . "</td>";
            echo "<td>" . ($player['sources_used'] ?: 'None') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p style='color: green;'>All players with stats have team assignments!</p>";
    }
    
    echo "</div>";
}

// Handle deletion
if (isset($_GET['delete_player'])) {
    $player_id = (int)$_GET['delete_player'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete from all related tables
        $pdo->prepare("DELETE FROM projected_fantasy_points WHERE player_id = ?")->execute([$player_id]);
        $pdo->prepare("DELETE FROM projected_stats WHERE player_id = ?")->execute([$player_id]);
        $pdo->prepare("DELETE FROM player_teams WHERE player_id = ?")->execute([$player_id]);
        $pdo->prepare("DELETE FROM draft_positions WHERE player_id = ?")->execute([$player_id]);
        $pdo->prepare("DELETE FROM players WHERE id = ?")->execute([$player_id]);
        
        $pdo->commit();
        
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "Successfully deleted player ID $player_id and all related data!";
        echo "</div>";
        
        // Refresh page to show updated results
        echo "<script>setTimeout(() => window.location.href = 'debug-players.php', 2000);</script>";
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "Error deleting player: " . $e->getMessage();
        echo "</div>";
    }
}

// Show recent players for reference
echo "<div class='card'>";
echo "<h2>Recent Players (Last 20 Added)</h2>";

$recent_players = $pdo->query("
    SELECT p.id, p.full_name, p.first_name, p.last_name, p.created_at,
           COUNT(ps.id) as stats_count,
           COUNT(pt.id) as team_count
    FROM players p
    LEFT JOIN projected_stats ps ON p.id = ps.player_id
    LEFT JOIN player_teams pt ON p.id = pt.player_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 20
")->fetchAll();

echo "<table>";
echo "<tr><th>ID</th><th>Full Name</th><th>First</th><th>Last</th><th>Created</th><th>Stats</th><th>Teams</th></tr>";

foreach ($recent_players as $player) {
    $rowClass = '';
    if (empty($player['full_name']) || empty($player['first_name']) || empty($player['last_name'])) {
        $rowClass = 'style="background: #ffebee;"';
    }
    
    echo "<tr $rowClass>";
    echo "<td>" . $player['id'] . "</td>";
    echo "<td>" . htmlspecialchars($player['full_name'] ?: 'EMPTY') . "</td>";
    echo "<td>" . htmlspecialchars($player['first_name'] ?: 'EMPTY') . "</td>";
    echo "<td>" . htmlspecialchars($player['last_name'] ?: 'EMPTY') . "</td>";
    echo "<td>" . $player['created_at'] . "</td>";
    echo "<td>" . $player['stats_count'] . "</td>";
    echo "<td>" . $player['team_count'] . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

echo "<div class='card'>";
echo "<h2>Database Summary</h2>";
$total_players = $pdo->query("SELECT COUNT(*) as count FROM players")->fetch()['count'];
$players_with_stats = $pdo->query("SELECT COUNT(DISTINCT player_id) as count FROM projected_stats")->fetch()['count'];
$players_with_teams = $pdo->query("SELECT COUNT(DISTINCT player_id) as count FROM player_teams")->fetch()['count'];

echo "<p><strong>Total Players:</strong> $total_players</p>";
echo "<p><strong>Players with Stats:</strong> $players_with_stats</p>";
echo "<p><strong>Players with Team Assignments:</strong> $players_with_teams</p>";
echo "</div>";

echo "<p><a href='manage-players.php'>← Back to Manage Players</a></p>";
?>
