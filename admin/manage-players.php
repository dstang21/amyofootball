<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Manage Players';

// Get teams and seasons for dropdowns
$teams_query = $pdo->query("SELECT * FROM teams ORDER BY name");
$teams = $teams_query->fetchAll();

$seasons_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC");
$seasons = $seasons_query->fetchAll();

// Get current season (default selection)
$current_season = !empty($seasons) ? $seasons[0] : null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $first_name = sanitize($_POST['first_name']);
                $last_name = sanitize($_POST['last_name']);
                $full_name = "$first_name $last_name";
                $birth_date = $_POST['birth_date'] ?: null;
                $team_id = $_POST['team_id'] ?: null;
                $season_id = $_POST['season_id'] ?: null;
                $position = sanitize($_POST['position']);
                
                try {
                    $pdo->beginTransaction();
                    
                    // Insert player
                    $stmt = $pdo->prepare("INSERT INTO players (first_name, last_name, full_name, birth_date) VALUES (?, ?, ?, ?)");
                    if (!$stmt->execute([$first_name, $last_name, $full_name, $birth_date])) {
                        throw new Exception("Failed to add player");
                    }
                    
                    $player_id = $pdo->lastInsertId();
                    
                    // Add team/position assignment if provided
                    if ($season_id && $position) {
                        $team_stmt = $pdo->prepare("INSERT INTO player_teams (player_id, team_id, season_id, position, matches) VALUES (?, ?, ?, ?, ?)");
                        if (!$team_stmt->execute([$player_id, $team_id, $season_id, $position, 0])) {
                            throw new Exception("Failed to add team assignment");
                        }
                    }
                    
                    $pdo->commit();
                    $success = "Player added successfully!";
                } catch (Exception $e) {
                    $pdo->rollback();
                    $error = "Error adding player: " . $e->getMessage();
                }
                break;
                
            case 'edit':
                $id = $_POST['id'];
                $first_name = sanitize($_POST['first_name']);
                $last_name = sanitize($_POST['last_name']);
                $full_name = "$first_name $last_name";
                $birth_date = $_POST['birth_date'] ?: null;
                $team_id = $_POST['team_id'] ?: null;
                $season_id = $_POST['season_id'] ?: null;
                $position = sanitize($_POST['position']);
                
                try {
                    $pdo->beginTransaction();
                    
                    // Update player
                    $stmt = $pdo->prepare("UPDATE players SET first_name=?, last_name=?, full_name=?, birth_date=? WHERE id=?");
                    if (!$stmt->execute([$first_name, $last_name, $full_name, $birth_date, $id])) {
                        throw new Exception("Failed to update player");
                    }
                    
                    // Update team/position assignment if season and position provided
                    if ($season_id && $position) {
                        // Check if player-team assignment exists
                        $check_pt = $pdo->prepare("SELECT id FROM player_teams WHERE player_id = ? AND season_id = ?");
                        $check_pt->execute([$id, $season_id]);
                        $existing_pt = $check_pt->fetch();
                        
                        if ($existing_pt) {
                            // Update existing player-team
                            $update_pt = $pdo->prepare("UPDATE player_teams SET team_id = ?, position = ? WHERE id = ?");
                            $update_pt->execute([$team_id, $position, $existing_pt['id']]);
                        } else {
                            // Insert new player-team
                            $insert_pt = $pdo->prepare("INSERT INTO player_teams (player_id, team_id, season_id, position, matches) VALUES (?, ?, ?, ?, ?)");
                            $insert_pt->execute([$id, $team_id, $season_id, $position, 0]);
                        }
                    }
                    
                    $pdo->commit();
                    $success = "Player updated successfully!";
                } catch (Exception $e) {
                    $pdo->rollback();
                    $error = "Error updating player: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM players WHERE id=?");
                if ($stmt->execute([$id])) {
                    $success = "Player deleted successfully!";
                } else {
                    $error = "Error deleting player.";
                }
                break;
        }
    }
}

// Get all players with their current team/position info
$search = $_GET['search'] ?? '';
$where_clause = !empty($search) ? "WHERE p.full_name LIKE ?" : "";
$params = !empty($search) ? ["%$search%"] : [];
if ($current_season) {
    $params[] = $current_season['id'];
}

$players_query = $pdo->prepare("
    SELECT p.*, pt.position, pt.team_id, t.name as team_name, t.abbreviation as team_abbr
    FROM players p 
    LEFT JOIN player_teams pt ON p.id = pt.player_id " . ($current_season ? "AND pt.season_id = ?" : "") . "
    LEFT JOIN teams t ON pt.team_id = t.id
    $where_clause 
    ORDER BY p.full_name
");
$players_query->execute($params);
$players = $players_query->fetchAll();

// Get player being edited (with current team/position info)
$edit_player = null;
$edit_player_team = null;
if (isset($_GET['edit'])) {
    $edit_stmt = $pdo->prepare("SELECT * FROM players WHERE id = ?");
    $edit_stmt->execute([$_GET['edit']]);
    $edit_player = $edit_stmt->fetch();
    
    // Get current team/position for most recent season
    if ($edit_player && $current_season) {
        $team_stmt = $pdo->prepare("SELECT pt.*, t.name as team_name FROM player_teams pt LEFT JOIN teams t ON pt.team_id = t.id WHERE pt.player_id = ? AND pt.season_id = ?");
        $team_stmt->execute([$edit_player['id'], $current_season['id']]);
        $edit_player_team = $team_stmt->fetch();
    }
}

include 'admin-header.php';
include 'admin-nav.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Manage Players</h1>
        <div style="display: flex; gap: 10px;">
            <a href="assign-teams.php" class="btn btn-primary">Assign Teams</a>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Add/Edit Player Form -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo $edit_player ? 'Edit Player' : 'Add New Player'; ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $edit_player ? 'edit' : 'add'; ?>">
                    <?php if ($edit_player): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_player['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" 
                               value="<?php echo $edit_player ? htmlspecialchars($edit_player['first_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?php echo $edit_player ? htmlspecialchars($edit_player['last_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="birth_date">Birth Date (Optional)</label>
                        <input type="date" id="birth_date" name="birth_date" 
                               value="<?php echo $edit_player ? $edit_player['birth_date'] : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="season_id">Season</label>
                        <select id="season_id" name="season_id">
                            <option value="">Select Season (Optional)</option>
                            <?php foreach ($seasons as $season): ?>
                                <option value="<?php echo $season['id']; ?>" 
                                    <?php echo ($edit_player_team && $edit_player_team['season_id'] == $season['id']) || (!$edit_player && $current_season && $season['id'] == $current_season['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($season['year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="team_id">Team</label>
                        <select id="team_id" name="team_id">
                            <option value="">Free Agent / No Team</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" 
                                    <?php echo ($edit_player_team && $edit_player_team['team_id'] == $team['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($team['name'] . ' (' . $team['abbreviation'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="position">Position</label>
                        <select id="position" name="position">
                            <option value="">Select Position (Optional)</option>
                            <option value="QB" <?php echo ($edit_player_team && $edit_player_team['position'] == 'QB') ? 'selected' : ''; ?>>QB - Quarterback</option>
                            <option value="RB" <?php echo ($edit_player_team && $edit_player_team['position'] == 'RB') ? 'selected' : ''; ?>>RB - Running Back</option>
                            <option value="WR" <?php echo ($edit_player_team && $edit_player_team['position'] == 'WR') ? 'selected' : ''; ?>>WR - Wide Receiver</option>
                            <option value="TE" <?php echo ($edit_player_team && $edit_player_team['position'] == 'TE') ? 'selected' : ''; ?>>TE - Tight End</option>
                            <option value="K" <?php echo ($edit_player_team && $edit_player_team['position'] == 'K') ? 'selected' : ''; ?>>K - Kicker</option>
                            <option value="DST" <?php echo ($edit_player_team && $edit_player_team['position'] == 'DST') ? 'selected' : ''; ?>>DST - Defense/Special Teams</option>
                            <option value="DL" <?php echo ($edit_player_team && $edit_player_team['position'] == 'DL') ? 'selected' : ''; ?>>DL - Defensive Line</option>
                            <option value="LB" <?php echo ($edit_player_team && $edit_player_team['position'] == 'LB') ? 'selected' : ''; ?>>LB - Linebacker</option>
                            <option value="DB" <?php echo ($edit_player_team && $edit_player_team['position'] == 'DB') ? 'selected' : ''; ?>>DB - Defensive Back</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_player ? 'Update Player' : 'Add Player'; ?>
                    </button>
                    
                    <?php if ($edit_player): ?>
                        <a href="manage-players.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Players List -->
        <div class="card">
            <div class="card-header">
                <h3>All Players (<?php echo count($players); ?>)</h3>
                <form method="GET" action="" style="margin-top: 10px;">
                    <div style="display: flex; gap: 10px;">
                        <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search players..." style="flex: 1;">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                        <a href="manage-players.php" class="btn btn-secondary btn-sm">Clear</a>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <?php if (!empty($players)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Team</th>
                                    <th>Position</th>
                                    <th>Birth Date</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($players as $player): ?>
                                <tr>
                                    <td class="player-cell">
                                        <?php echo htmlspecialchars($player['full_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo $player['team_abbr'] ? htmlspecialchars($player['team_abbr']) : 'FA'; ?>
                                    </td>
                                    <td>
                                        <?php if ($player['position']): ?>
                                            <span class="position-badge position-<?php echo strtolower($player['position']); ?>">
                                                <?php echo htmlspecialchars($player['position']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="position-badge position-na">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $player['birth_date'] ? date('M j, Y', strtotime($player['birth_date'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($player['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?php echo $player['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <form method="POST" action="" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this player?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $player['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No players found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
include 'admin-nav-footer.php';
include 'admin-footer.php'; 
?>
