<?php
require_once dirname(dirname(__DIR__)) . '/config.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

$page_title = 'Wyandotte League - Manage Teams';

// Handle team creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create_team') {
        $team_name = sanitize($_POST['team_name']);
        $owner_name = sanitize($_POST['owner_name']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO wyandotte_teams (team_name, owner_name) VALUES (?, ?)");
            $stmt->execute([$team_name, $owner_name]);
            $success = "Team created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating team: " . $e->getMessage();
        }
    } elseif ($_POST['action'] == 'delete_team') {
        $team_id = (int)$_POST['team_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM wyandotte_teams WHERE id = ?");
            $stmt->execute([$team_id]);
            $success = "Team deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting team: " . $e->getMessage();
        }
    }
}

// Get all teams
$teams = $pdo->query("
    SELECT t.*, 
           COUNT(r.id) as player_count 
    FROM wyandotte_teams t 
    LEFT JOIN wyandotte_rosters r ON t.id = r.team_id 
    GROUP BY t.id 
    ORDER BY t.team_name
")->fetchAll();
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
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        button { padding: 10px 20px; background: #1e3c72; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        button:hover { background: #2a5298; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #1e3c72; color: white; }
        tr:hover { background: #f5f5f5; }
        .create-form { background: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="index.php">ΓåÉ Back to League</a>
            <a href="teams.php">Manage Teams</a>
            <a href="rosters.php">Build Rosters</a>
            <a href="../../admin/dashboard.php">Main Admin</a>
        </div>

        <h1>&#127944; Wyandotte League - Manage Teams</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="create-form">
            <h2>Create New Team</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_team">
                <div class="form-group">
                    <label for="team_name">Team Name:</label>
                    <input type="text" id="team_name" name="team_name" required>
                </div>
                <div class="form-group">
                    <label for="owner_name">Owner Name:</label>
                    <input type="text" id="owner_name" name="owner_name" required>
                </div>
                <button type="submit">Create Team</button>
            </form>
        </div>

        <h2>Teams (<?php echo count($teams); ?>/10)</h2>
        <table>
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Owner</th>
                    <th>Players</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($team['team_name']); ?></td>
                        <td><?php echo htmlspecialchars($team['owner_name']); ?></td>
                        <td><?php echo $team['player_count']; ?>/9</td>
                        <td>
                            <a href="rosters.php?team_id=<?php echo $team['id']; ?>">Build Roster</a>
                            |
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this team?');">
                                <input type="hidden" name="action" value="delete_team">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                <button type="submit" class="btn-danger" style="padding: 5px 10px; font-size: 12px;">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($teams)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: #999;">No teams yet. Create your first team above!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
