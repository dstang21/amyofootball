<?php
require_once 'config.php';

echo "<h2>Debug: Sleeper Database Contents</h2>";

try {
    // Check sleeper_leagues table
    echo "<h3>Sleeper Leagues:</h3>";
    $stmt = $pdo->query("SELECT league_id, name, season, status, previous_league_id FROM sleeper_leagues ORDER BY season DESC");
    $leagues = $stmt->fetchAll();
    
    if (empty($leagues)) {
        echo "<p>❌ No leagues found in sleeper_leagues table</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>League ID</th><th>Name</th><th>Season</th><th>Status</th><th>Previous League ID</th></tr>";
        foreach ($leagues as $league) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($league['league_id']) . "</td>";
            echo "<td>" . htmlspecialchars($league['name']) . "</td>";
            echo "<td>" . htmlspecialchars($league['season']) . "</td>";
            echo "<td>" . htmlspecialchars($league['status']) . "</td>";
            echo "<td>" . htmlspecialchars($league['previous_league_id'] ?: 'None') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check sleeper_rosters table
    echo "<h3>Sleeper Rosters:</h3>";
    $stmt = $pdo->query("SELECT league_id, roster_id, wins, losses, fpts FROM sleeper_rosters ORDER BY wins DESC LIMIT 10");
    $rosters = $stmt->fetchAll();
    
    if (empty($rosters)) {
        echo "<p>❌ No rosters found in sleeper_rosters table</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>League ID</th><th>Roster ID</th><th>Wins</th><th>Losses</th><th>Points</th></tr>";
        foreach ($rosters as $roster) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($roster['league_id']) . "</td>";
            echo "<td>" . htmlspecialchars($roster['roster_id']) . "</td>";
            echo "<td>" . htmlspecialchars($roster['wins']) . "</td>";
            echo "<td>" . htmlspecialchars($roster['losses']) . "</td>";
            echo "<td>" . htmlspecialchars($roster['fpts']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check sleeper_league_users table
    echo "<h3>Sleeper Users:</h3>";
    $stmt = $pdo->query("SELECT league_id, display_name, team_name FROM sleeper_league_users LIMIT 10");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<p>❌ No users found in sleeper_league_users table</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>League ID</th><th>Display Name</th><th>Team Name</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['league_id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['display_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['team_name'] ?: 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
