<?php
require_once dirname(dirname(__DIR__)) . '/config.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

$page_title = 'Wyandotte League - Select Participants';

// Get league_id from URL or default to first league
$league_id = isset($_GET['league_id']) ? $_GET['league_id'] : null;

// Get all leagues
$leagues = $pdo->query("SELECT * FROM sleeper_leagues ORDER BY season DESC")->fetchAll();

if (!$league_id && !empty($leagues)) {
    $league_id = $leagues[0]['league_id'];
}

// Handle adding participant
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_participant') {
        $user_id = sanitize($_POST['user_id']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO wyandotte_participants (user_id, league_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $league_id]);
            $success = "Participant added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding participant: " . $e->getMessage();
        }
    } elseif ($_POST['action'] == 'remove_participant') {
        $user_id = sanitize($_POST['user_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM wyandotte_participants WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $success = "Participant removed successfully!";
        } catch (PDOException $e) {
            $error = "Error removing participant: " . $e->getMessage();
        }
    }
}

// Get all users from selected league
$available_users = [];
if ($league_id) {
    $stmt = $pdo->prepare("
        SELECT slu.*, 
               CASE WHEN wp.user_id IS NOT NULL THEN 1 ELSE 0 END as is_participant
        FROM sleeper_league_users slu
        LEFT JOIN wyandotte_participants wp ON slu.user_id = wp.user_id
        WHERE slu.league_id = ?
        ORDER BY is_participant DESC, slu.display_name
    ");
    $stmt->execute([$league_id]);
    $available_users = $stmt->fetchAll();
}

// Get current participants with roster count
$participants = $pdo->query("
    SELECT wp.*, slu.username, slu.display_name, slu.team_name,
           COUNT(wr.id) as player_count 
    FROM wyandotte_participants wp
    JOIN sleeper_league_users slu ON wp.user_id = slu.user_id
    LEFT JOIN wyandotte_rosters wr ON wp.user_id = wr.user_id 
    GROUP BY wp.user_id 
    ORDER BY slu.display_name
")->fetchAll();

$participant_count = count($participants);
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
        select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        button { padding: 10px 20px; background: #1e3c72; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        button:hover { background: #2a5298; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #1e3c72; color: white; }
        tr:hover { background: #f5f5f5; }
        .add-form { background: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 30px; }
        .count { font-weight: bold; color: #1e3c72; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <a href="../index.php">← Back to League</a>
            <a href="teams.php">Select Participants</a>
            <a href="rosters.php">Build Rosters</a>
        </div>

        <h1>🏈 Wyandotte League - Select Participants</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="add-form">
            <h2>Add Participant (<span class="count"><?php echo $participant_count; ?>/10</span>)</h2>
            <?php if ($participant_count < 10): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add_participant">
                    <div class="form-group">
                        <label for="league_id">League:</label>
                        <select id="league_id" onchange="window.location.href='teams.php?league_id=' + this.value">
                            <?php foreach ($leagues as $league): ?>
                                <option value="<?php echo $league['league_id']; ?>" <?php echo ($league_id == $league['league_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($league['name']); ?> (<?php echo $league['season']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="user_id">Select User:</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">-- Choose a user --</option>
                            <?php foreach ($available_users as $user): ?>
                                <?php if (!$user['is_participant']): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['display_name']); ?> 
                                        (<?php echo htmlspecialchars($user['username']); ?>)
                                        <?php if ($user['team_name']): ?>
                                            - <?php echo htmlspecialchars($user['team_name']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-success">Add Participant</button>
                </form>
            <?php else: ?>
                <p style="color: #856404; font-weight: bold;">Maximum 10 participants reached!</p>
            <?php endif; ?>
        </div>

        <h2>Current Participants (<?php echo $participant_count; ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>Display Name</th>
                    <th>Username</th>
                    <th>Team Name</th>
                    <th>Players</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $participant): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($participant['display_name']); ?></td>
                        <td><?php echo htmlspecialchars($participant['username']); ?></td>
                        <td><?php echo htmlspecialchars($participant['team_name'] ?: 'N/A'); ?></td>
                        <td><?php echo $participant['player_count']; ?>/9</td>
                        <td>
                            <a href="rosters.php?user_id=<?php echo $participant['user_id']; ?>">Build Roster</a>
                            |
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this participant and their roster?');">
                                <input type="hidden" name="action" value="remove_participant">
                                <input type="hidden" name="user_id" value="<?php echo $participant['user_id']; ?>">
                                <button type="submit" class="btn-danger" style="padding: 5px 10px; font-size: 12px;">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($participants)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: #999;">No participants yet. Add users from the form above!</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
