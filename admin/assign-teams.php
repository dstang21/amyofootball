<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Assign Players to Teams';

// Get teams and seasons for dropdowns
$teams_query = $pdo->query("SELECT * FROM teams ORDER BY name");
$teams = $teams_query->fetchAll();

$seasons_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC");
$seasons = $seasons_query->fetchAll();

// Get current season (default selection)
$current_season = !empty($seasons) ? $seasons[0] : null;
$selected_season_id = $_GET['season'] ?? ($current_season ? $current_season['id'] : null);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'assign_team') {
        $player_id = $_POST['player_id'];
        $team_id = $_POST['team_id'];
        $position = sanitize($_POST['position']);
        $season_id = $_POST['season_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Check if player-team assignment already exists for this season
            $check_pt = $pdo->prepare("SELECT id FROM player_teams WHERE player_id = ? AND season_id = ?");
            $check_pt->execute([$player_id, $season_id]);
            $existing_pt = $check_pt->fetch();
            
            if ($existing_pt) {
                // Update existing assignment
                $update_pt = $pdo->prepare("UPDATE player_teams SET team_id = ?, position = ? WHERE id = ?");
                $update_pt->execute([$team_id, $position, $existing_pt['id']]);
            } else {
                // Insert new assignment
                $insert_pt = $pdo->prepare("INSERT INTO player_teams (player_id, team_id, season_id, position, matches) VALUES (?, ?, ?, ?, ?)");
                $insert_pt->execute([$player_id, $team_id, $season_id, $position, 0]);
            }
            
            $pdo->commit();
            $success = "Player successfully assigned to team!";
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "Error assigning player: " . $e->getMessage();
        }
    }
}

// Get players without team assignments or with free agent team (team_id = 33)
// Also include players with positions but no teams
if ($selected_season_id) {
    $unassigned_query = $pdo->prepare("
        SELECT p.id, p.full_name, p.first_name, p.last_name,
               pt.team_id, pt.position, t.name as team_name, t.abbreviation as team_abbr
        FROM players p 
        LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
        LEFT JOIN teams t ON pt.team_id = t.id
        WHERE pt.team_id IS NULL 
           OR pt.team_id = 33 
           OR pt.position IS NULL
           OR pt.position = ''
        ORDER BY p.full_name
    ");
    $unassigned_query->execute([$selected_season_id]);
    $unassigned_players = $unassigned_query->fetchAll();
} else {
    $unassigned_players = [];
}

include 'admin-header.php';
include 'admin-nav.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Assign Players to Teams</h1>
        <p>Assign players without teams or positions to teams and positions for the selected season</p>
        <a href="manage-players.php" class="btn btn-secondary">← Back to Manage Players</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Season Selection -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h3>Select Season</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="form-group" style="max-width: 300px;">
                    <label for="season">Season</label>
                    <select id="season" name="season" onchange="this.form.submit()">
                        <option value="">Select Season</option>
                        <?php foreach ($seasons as $season): ?>
                            <option value="<?php echo $season['id']; ?>" 
                                    <?php echo $selected_season_id == $season['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($season['year']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_season_id && !empty($unassigned_players)): ?>
        <!-- Unassigned Players -->
        <div class="card">
            <div class="card-header">
                <h3>Players Needing Team/Position Assignment (<?php echo count($unassigned_players); ?>)</h3>
                <small>Players without teams, free agents (FA), or missing positions</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Current Team</th>
                                <th>Current Position</th>
                                <th>Assign Team</th>
                                <th>Assign Position</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unassigned_players as $player): ?>
                            <tr>
                                <td class="player-cell">
                                    <strong><?php echo htmlspecialchars($player['full_name']); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    if (!$player['team_id'] || $player['team_id'] == 33) {
                                        echo '<span class="position-badge position-na">FA</span>';
                                    } else {
                                        echo htmlspecialchars($player['team_abbr']);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($player['position']): ?>
                                        <span class="position-badge position-<?php echo strtolower($player['position']); ?>">
                                            <?php echo htmlspecialchars($player['position']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="position-badge position-na">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="assign_team">
                                        <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                        <input type="hidden" name="season_id" value="<?php echo $selected_season_id; ?>">
                                        
                                        <select name="team_id" required style="width: 120px; font-size: 12px;">
                                            <option value="">Select Team</option>
                                            <?php foreach ($teams as $team): ?>
                                                <option value="<?php echo $team['id']; ?>" 
                                                        <?php echo $player['team_id'] == $team['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($team['abbreviation']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                </td>
                                <td>
                                        <select name="position" required style="width: 100px; font-size: 12px;">
                                            <option value="">Select Position</option>
                                            <option value="QB" <?php echo $player['position'] == 'QB' ? 'selected' : ''; ?>>QB</option>
                                            <option value="RB" <?php echo $player['position'] == 'RB' ? 'selected' : ''; ?>>RB</option>
                                            <option value="WR" <?php echo $player['position'] == 'WR' ? 'selected' : ''; ?>>WR</option>
                                            <option value="TE" <?php echo $player['position'] == 'TE' ? 'selected' : ''; ?>>TE</option>
                                            <option value="K" <?php echo $player['position'] == 'K' ? 'selected' : ''; ?>>K</option>
                                            <option value="DST" <?php echo $player['position'] == 'DST' ? 'selected' : ''; ?>>DST</option>
                                            <option value="DL" <?php echo $player['position'] == 'DL' ? 'selected' : ''; ?>>DL</option>
                                            <option value="LB" <?php echo $player['position'] == 'LB' ? 'selected' : ''; ?>>LB</option>
                                            <option value="DB" <?php echo $player['position'] == 'DB' ? 'selected' : ''; ?>>DB</option>
                                        </select>
                                </td>
                                <td>
                                        <button type="submit" class="btn btn-primary btn-sm">Assign</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($selected_season_id && empty($unassigned_players)): ?>
        <div class="card">
            <div class="card-header">
                <h3>All Players Assigned</h3>
            </div>
            <div class="card-body">
                <p>All players for the selected season have been assigned to teams and positions.</p>
            </div>
        </div>
    
    <?php elseif (!$selected_season_id): ?>
        <div class="card">
            <div class="card-header">
                <h3>Select a Season</h3>
            </div>
            <div class="card-body">
                <p>Please select a season above to view players needing team assignments.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
}

.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.player-cell {
    font-weight: 500;
}

.position-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    color: white;
}

.position-qb { background: #dc2626; }
.position-rb { background: #059669; }
.position-wr { background: #2563eb; }
.position-te { background: #7c3aed; }
.position-k { background: #d97706; }
.position-dst { background: #374151; }
.position-dl { background: #374151; }
.position-lb { background: #374151; }
.position-db { background: #374151; }
.position-na { background: #6b7280; }

table select {
    padding: 4px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}

table button {
    padding: 6px 12px;
    font-size: 12px;
}
</style>

<?php include '../footer.php'; ?>
