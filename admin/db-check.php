<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Database Check';

// Check database integrity
$checks = [];

// Check players table
try {
    $player_count = $pdo->query("SELECT COUNT(*) as count FROM players")->fetch()['count'];
    $checks['players'] = ['status' => 'OK', 'count' => $player_count, 'message' => "$player_count players found"];
} catch (Exception $e) {
    $checks['players'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// Check teams table
try {
    $team_count = $pdo->query("SELECT COUNT(*) as count FROM teams")->fetch()['count'];
    $checks['teams'] = ['status' => 'OK', 'count' => $team_count, 'message' => "$team_count teams found"];
} catch (Exception $e) {
    $checks['teams'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// Check seasons table
try {
    $season_count = $pdo->query("SELECT COUNT(*) as count FROM seasons")->fetch()['count'];
    $checks['seasons'] = ['status' => 'OK', 'count' => $season_count, 'message' => "$season_count seasons found"];
} catch (Exception $e) {
    $checks['seasons'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// Check player_teams table
try {
    $pt_count = $pdo->query("SELECT COUNT(*) as count FROM player_teams")->fetch()['count'];
    $checks['player_teams'] = ['status' => 'OK', 'count' => $pt_count, 'message' => "$pt_count player-team assignments"];
} catch (Exception $e) {
    $checks['player_teams'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// Check for orphaned records
try {
    $orphaned_pt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM player_teams pt 
        LEFT JOIN players p ON pt.player_id = p.id 
        WHERE p.id IS NULL
    ")->fetch()['count'];
    
    if ($orphaned_pt > 0) {
        $checks['orphaned_player_teams'] = ['status' => 'WARNING', 'message' => "$orphaned_pt orphaned player-team records"];
    } else {
        $checks['orphaned_player_teams'] = ['status' => 'OK', 'message' => "No orphaned player-team records"];
    }
} catch (Exception $e) {
    $checks['orphaned_player_teams'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// Get some sample data
$sample_players = $pdo->query("SELECT id, full_name FROM players ORDER BY id LIMIT 5")->fetchAll();
$sample_teams = $pdo->query("SELECT id, name, abbreviation FROM teams ORDER BY id LIMIT 5")->fetchAll();
$sample_seasons = $pdo->query("SELECT id, year FROM seasons ORDER BY year DESC LIMIT 3")->fetchAll();

include '../header.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Database Integrity Check</h1>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <!-- System Checks -->
    <div class="card">
        <div class="card-header">
            <h3>Database Table Status</h3>
        </div>
        <div class="card-body">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $table => $check): ?>
                    <tr>
                        <td><strong><?php echo ucfirst(str_replace('_', ' ', $table)); ?></strong></td>
                        <td>
                            <?php if ($check['status'] == 'OK'): ?>
                                <span class="position-badge position-qb">✓ OK</span>
                            <?php elseif ($check['status'] == 'WARNING'): ?>
                                <span class="position-badge position-wr">⚠ Warning</span>
                            <?php else: ?>
                                <span class="position-badge position-def">✗ Error</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($check['message']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
        <!-- Sample Players -->
        <div class="card">
            <div class="card-header">
                <h3>Sample Players</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($sample_players)): ?>
                    <?php foreach ($sample_players as $player): ?>
                        <div style="padding: 5px 0; border-bottom: 1px solid #eee;">
                            <strong>ID: <?php echo $player['id']; ?></strong><br>
                            <?php echo htmlspecialchars($player['full_name']); ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: red;">No players found!</p>
                    <a href="manage-players.php" class="btn btn-primary btn-sm">Add Players</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sample Teams -->
        <div class="card">
            <div class="card-header">
                <h3>Sample Teams</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($sample_teams)): ?>
                    <?php foreach ($sample_teams as $team): ?>
                        <div style="padding: 5px 0; border-bottom: 1px solid #eee;">
                            <strong>ID: <?php echo $team['id']; ?></strong><br>
                            <?php echo htmlspecialchars($team['abbreviation'] . ' - ' . $team['name']); ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: red;">No teams found!</p>
                    <a href="manage-teams.php" class="btn btn-primary btn-sm">Add Teams</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sample Seasons -->
        <div class="card">
            <div class="card-header">
                <h3>Sample Seasons</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($sample_seasons)): ?>
                    <?php foreach ($sample_seasons as $season): ?>
                        <div style="padding: 5px 0; border-bottom: 1px solid #eee;">
                            <strong>ID: <?php echo $season['id']; ?></strong><br>
                            <?php echo $season['year']; ?> Season
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: red;">No seasons found!</p>
                    <a href="manage-seasons.php" class="btn btn-primary btn-sm">Add Season</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Fixes -->
    <div class="card">
        <div class="card-header">
            <h3>Quick Setup</h3>
        </div>
        <div class="card-body">
            <p>If you're missing essential data, use these quick setup options:</p>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <?php if (empty($sample_seasons)): ?>
                    <a href="manage-seasons.php" class="btn btn-warning">Create 2025 Season</a>
                <?php endif; ?>
                
                <?php if (empty($sample_teams)): ?>
                    <a href="manage-teams.php" class="btn btn-warning">Add NFL Teams</a>
                <?php endif; ?>
                
                <?php if (empty($sample_players)): ?>
                    <a href="manage-players.php" class="btn btn-warning">Add Players</a>
                <?php endif; ?>
                
                <a href="manage-stats.php" class="btn btn-primary">Manage Stats</a>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>
