<?php
require_once dirname(dirname(__DIR__)) . '/config.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

$page_title = 'Wyandotte League - Build Rosters';

// Get team if specified
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;
$current_team = null;

if ($team_id) {
    $stmt = $pdo->prepare("SELECT * FROM wyandotte_teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $current_team = $stmt->fetch();
}

// Handle roster updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_roster') {
    $team_id = (int)$_POST['team_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete existing roster
        $stmt = $pdo->prepare("DELETE FROM wyandotte_rosters WHERE team_id = ?");
        $stmt->execute([$team_id]);
        
        // Insert new roster
        $stmt = $pdo->prepare("INSERT INTO wyandotte_rosters (team_id, player_id, position, slot_number) VALUES (?, ?, ?, ?)");
        
        $positions = ['QB' => 1, 'RB1' => 2, 'RB2' => 3, 'WR1' => 4, 'WR2' => 5, 'TE' => 6, 'DB' => 7, 'LB' => 8, 'DL' => 9];
        
        foreach ($positions as $pos_key => $slot_num) {
            if (!empty($_POST[$pos_key]) && $_POST[$pos_key] != '') {
                $player_id = (int)$_POST[$pos_key];
                $position = preg_replace('/[0-9]/', '', $pos_key); // Remove numbers from position
                $stmt->execute([$team_id, $player_id, $position, $slot_num]);
            }
        }
        
        $pdo->commit();
        $success = "Roster saved successfully!";
        
        // Reload current team
        $stmt = $pdo->prepare("SELECT * FROM wyandotte_teams WHERE id = ?");
        $stmt->execute([$team_id]);
        $current_team = $stmt->fetch();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error saving roster: " . $e->getMessage();
    }
}

// Get all teams for dropdown
$teams = $pdo->query("SELECT * FROM wyandotte_teams ORDER BY team_name")->fetchAll();

// Get current roster if team selected
$current_roster = [];
if ($team_id) {
    $stmt = $pdo->prepare("
        SELECT r.*, p.full_name, r.position, r.slot_number
        FROM wyandotte_rosters r
        JOIN players p ON r.player_id = p.id
        WHERE r.team_id = ?
        ORDER BY r.slot_number
    ");
    $stmt->execute([$team_id]);
    $roster_data = $stmt->fetchAll();
    
    foreach ($roster_data as $row) {
        $current_roster[$row['slot_number']] = $row;
    }
}

// Get players by position from player_teams
$players_by_position = [];
$positions = ['QB', 'RB', 'WR', 'TE', 'DB', 'LB', 'DL'];

foreach ($positions as $pos) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.full_name, pt.position
        FROM players p
        JOIN player_teams pt ON p.id = pt.player_id
        WHERE pt.position = ?
        ORDER BY p.full_name
    ");
    $stmt->execute([$pos]);
    $players_by_position[$pos] = $stmt->fetchAll();
}

// Combine LB and DL for the DL slot
$players_by_position['DL_SLOT'] = array_merge(
    $players_by_position['DL'] ?? [],
    $players_by_position['LB'] ?? []
);
// Sort combined list by name
usort($players_by_position['DL_SLOT'], function($a, $b) {
    return strcmp($a['full_name'], $b['full_name']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1e3c72; margin-bottom: 20px; }
        .nav { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #1e3c72; }
        .nav a { margin-right: 20px; color: #1e3c72; text-decoration: none; font-weight: bold; }
        .nav a:hover { text-decoration: underline; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 25px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; font-size: 16px; }
        select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background: white; }
        button { padding: 12px 30px; background: #1e3c72; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #2a5298; }
        .team-selector { background: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 30px; }
        .team-selector select { max-width: 400px; }
        .roster-form { background: #fff; }
        .position-group { background: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid #1e3c72; }
        .position-label { color: #1e3c72; font-size: 18px; text-transform: uppercase; }
        .no-team { text-align: center; padding: 60px 20px; color: #999; }
        .current-player { color: #28a745; font-size: 12px; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="../index.php">ΓåÉ Back to League</a>
            <a href="teams.php">Manage Teams</a>
            <a href="rosters.php">Build Rosters</a>            <a href="add-player.php">Add Player</a>        </div>

        <h1>&#127944; Build Team Roster</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="team-selector">
            <label for="team-select">Select Team:</label>
            <select id="team-select" onchange="window.location.href='rosters.php?team_id=' + this.value">
                <option value="">-- Choose a team --</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?php echo $team['id']; ?>" <?php echo ($team_id == $team['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($team['team_name']); ?> (<?php echo htmlspecialchars($team['owner_name']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($current_team): ?>
            <h2>Roster for: <?php echo htmlspecialchars($current_team['team_name']); ?></h2>
            <p style="margin-bottom: 30px; color: #666;">Owner: <?php echo htmlspecialchars($current_team['owner_name']); ?></p>

            <form method="POST" class="roster-form">
                <input type="hidden" name="action" value="save_roster">
                <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">

                <div class="position-group">
                    <label class="position-label">Quarterback (QB)</label>
                    <select name="QB" required>
                        <option value="">-- Select QB --</option>
                        <?php foreach ($players_by_position['QB'] as $player): ?>
                            <option value="<?php echo $player['id']; ?>" 
                                <?php echo (isset($current_roster[1]) && $current_roster[1]['player_id'] == $player['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="position-group">
                    <label class="position-label">Running Back 1 (RB)</label>
                    <select name="RB1" required>
                        <option value="">-- Select RB --</option>
                        <?php foreach ($players_by_position['RB'] as $player): ?>
                            <option value="<?php echo $player['id']; ?>"
                                <?php echo (isset($current_roster[2]) && $current_roster[2]['player_id'] == $player['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="position-group">
                    <label class="position-label">Running Back 2 (RB)</label>
                    <select name="RB2" required>
                        <option value="">-- Select RB --</option>
                        <?php foreach ($players_by_position['RB'] as $player): ?>
                            <option value="<?php echo $player['id']; ?>"
                                <?php echo (isset($current_roster[3]) && $current_roster[3]['player_id'] == $player['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="position-group">
                    <label class="position-label">Wide Receiver 1 (WR)</label>
                    <select name="WR1" required>
                        <option value="">-- Select WR --</option>
                        <?php foreach ($players_by_position['WR'] as $player): ?>
                            <option value="<?php echo $player['id']; ?>"
                                <?php echo (isset($current_roster[4]) && $current_roster[4]['player_id'] == $player['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="position-group">
                    <label class="position-label">Wide Receiver 2 (WR)</label>
                    <select name="WR2" required>
                        <option value="">-- Select WR --</option>
                        <?php foreach ($players_by_position['WR'] as $player): ?>
                            <option value="<?php echo $player['id']; ?>"
                                <?php echo (isset($current_roster[5]) && $current_roster[5]['player_id'] == $player['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="position-group">
                    <label class="position-label">Tight End (TE)</label>
                    <select name="TE" required>
                        <option value="">-- Select TE --</option>
                        <?php foreach ($players_by_position['TE'] as $player): ?>
                            <option value="<?php echo $player['id']; ?>"
                                <?php echo (isset($current_roster[6]) && $current_roster[6]['player_id'] == $player['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="position-group">
                    <label class="position-label">Defensive Back (DB)</label>
                    <select name="DB" required>
                        <option value="">-- Select DB --</option>
                        <?php foreach ($players_by_position['DB'] as $player): ?>
                            <option value="<?php echo $player['id']; ?>"
                                <?php echo (isset($current_roster[7]) && $current_roster[7]['player_id'] == $player['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="position-group">
                    <label class="position-label">Linebacker (LB)</label>
                    <select name="LB" required>
                        <option value="">-- Select LB --</option>
                        <?php foreach ($players_by_position['LB'] as $player): ?>
                            <option value="<?php echo $player['id']; ?>"
                                <?php echo (isset($current_roster[8]) && $current_roster[8]['player_id'] == $player['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="position-group">
                    <label class="position-label">Defensive Line (DL or LB)</label>
                    <select name="DL" required>
                        <option value="">-- Select DL/LB --</option>
                        <?php foreach ($players_by_position['DL_SLOT'] as $player): ?>
                            <option value="<?php echo $player['id']; ?>"
                                <?php echo (isset($current_roster[9]) && $current_roster[9]['player_id'] == $player['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($player['full_name']); ?> (<?php echo $player['position']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit">≡ƒÆ╛ Save Roster</button>
            </form>
        <?php else: ?>
            <div class="no-team">
                <h2>Please select a team from the dropdown above</h2>
                <p style="margin-top: 10px;">Or <a href="teams.php">create a new team</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
