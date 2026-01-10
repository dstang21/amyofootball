<?php
require_once dirname(dirname(__DIR__)) . '/config.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

$page_title = 'Add New Player';

// Handle player creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_player') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $full_name = $first_name . ' ' . $last_name;
    $position = sanitize($_POST['position']);
    $team_id = (int)$_POST['team_id'];
    $season_id = (int)$_POST['season_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Insert player
        $stmt = $pdo->prepare("INSERT INTO players (first_name, last_name, full_name) VALUES (?, ?, ?)");
        $stmt->execute([$first_name, $last_name, $full_name]);
        $player_id = $pdo->lastInsertId();
        
        // Insert player_team relationship
        $stmt = $pdo->prepare("INSERT INTO player_teams (player_id, team_id, season_id, position) VALUES (?, ?, ?, ?)");
        $stmt->execute([$player_id, $team_id, $season_id, $position]);
        
        $pdo->commit();
        $success = "Player added successfully! You can now select them in rosters.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error adding player: " . $e->getMessage();
    }
}

// Get all NFL teams
$nfl_teams = $pdo->query("SELECT id, name, city FROM teams ORDER BY city, name")->fetchAll();

// Get current season
$current_season = $pdo->query("SELECT id, year FROM seasons ORDER BY year DESC LIMIT 1")->fetch();
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
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1e3c72; margin-bottom: 20px; }
        .nav { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #1e3c72; }
        .nav a { margin-right: 20px; color: #1e3c72; text-decoration: none; font-weight: bold; }
        .nav a:hover { text-decoration: underline; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input[type="text"], select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        button { padding: 12px 30px; background: #1e3c72; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #2a5298; }
        .form-container { background: #f8f9fa; padding: 25px; border-radius: 4px; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #0066cc; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="../index.php">← Back to League</a>
            <a href="teams.php">Manage Teams</a>
            <a href="rosters.php">Build Rosters</a>
            <a href="add-player.php">Add Player</a>
        </div>

        <h1>&#127944; Add New Player</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="info">
            <strong>Note:</strong> Only add players if they're not already in the system. This will add them to the main players database and make them available for all rosters.
        </div>

        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="action" value="add_player">
                <input type="hidden" name="season_id" value="<?php echo $current_season['id'] ?? 1; ?>">
                
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name:</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>

                <div class="form-group">
                    <label for="position">Position:</label>
                    <select name="position" id="position" required>
                        <option value="">-- Select Position --</option>
                        <option value="QB">QB - Quarterback</option>
                        <option value="RB">RB - Running Back</option>
                        <option value="WR">WR - Wide Receiver</option>
                        <option value="TE">TE - Tight End</option>
                        <option value="DB">DB - Defensive Back</option>
                        <option value="LB">LB - Linebacker</option>
                        <option value="DL">DL - Defensive Line</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="team_id">NFL Team:</label>
                    <select name="team_id" id="team_id" required>
                        <option value="">-- Select Team --</option>
                        <?php foreach ($nfl_teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>">
                                <?php echo htmlspecialchars($team['city'] . ' ' . $team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit">Add Player</button>
            </form>
        </div>
    </div>
</body>
</html>
