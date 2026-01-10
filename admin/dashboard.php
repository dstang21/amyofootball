<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Admin Dashboard';

// Handle AmyoFootball rank calculation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'calculate_amyo_ranks') {
    $season_id = (int)$_POST['season_id'];
    $success_message = '';
    $error_message = '';
    
    if ($season_id) {
        try {
            $pdo->beginTransaction();
            
            // Get all players and their rankings from all sources except AmyoFootball (source_id = 1)
            $rankings_query = $pdo->prepare("
                SELECT dp.player_id, AVG(dp.ranking) as avg_ranking, COUNT(dp.ranking) as rank_count
                FROM draft_positions dp
                WHERE dp.season_id = ? AND dp.source_id != 1
                GROUP BY dp.player_id
                HAVING rank_count > 0
                ORDER BY avg_ranking ASC
            ");
            $rankings_query->execute([$season_id]);
            $player_averages = $rankings_query->fetchAll();
            
            if (empty($player_averages)) {
                throw new Exception("No rankings found for other sources in this season.");
            }
            
            // Delete existing AmyoFootball rankings for this season
            $delete_stmt = $pdo->prepare("DELETE FROM draft_positions WHERE season_id = ? AND source_id = 1");
            $delete_stmt->execute([$season_id]);
            
            // Insert new AmyoFootball rankings based on averages
            $insert_stmt = $pdo->prepare("
                INSERT INTO draft_positions (player_id, season_id, source_id, ranking, created_at, updated_at) 
                VALUES (?, ?, 1, ?, NOW(), NOW())
            ");
            
            $updated_count = 0;
            foreach ($player_averages as $player_avg) {
                $insert_stmt->execute([
                    $player_avg['player_id'], 
                    $season_id, 
                    round($player_avg['avg_ranking'], 2)
                ]);
                $updated_count++;
            }
            
            $pdo->commit();
            $success_message = "AmyoFootball rankings calculated successfully! Updated {$updated_count} players based on average rankings from other sources.";
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error_message = "Error calculating AmyoFootball rankings: " . $e->getMessage();
        }
    } else {
        $error_message = "Please select a season.";
    }
}

// Get seasons for the calculation form
$seasons_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC");
$seasons = $seasons_query->fetchAll();

// Get statistics
$total_players = $pdo->query("SELECT COUNT(*) as count FROM players")->fetch()['count'];
$total_teams = $pdo->query("SELECT COUNT(*) as count FROM teams")->fetch()['count'];
$total_seasons = $pdo->query("SELECT COUNT(*) as count FROM seasons")->fetch()['count'];
$total_rankings = $pdo->query("SELECT COUNT(*) as count FROM draft_positions")->fetch()['count'];
$total_scoring = $pdo->query("SELECT COUNT(*) as count FROM scoring_settings")->fetch()['count'];

// Recent activity
$recent_players = $pdo->query("SELECT * FROM players ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recent_rankings = $pdo->query("
    SELECT p.full_name, dp.ranking, s.name as source_name, dp.updated_at
    FROM draft_positions dp
    JOIN players p ON dp.player_id = p.id
    JOIN sources s ON dp.source_id = s.id
    ORDER BY dp.updated_at DESC
    LIMIT 5
")->fetchAll();

include 'admin-header.php';
include 'admin-nav.php';
?>

    <!-- Admin Header -->
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Admin Dashboard</h1>
        <p>Manage your AmyoFootball website</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_players; ?></div>
                    <div class="stat-label">Total Players</div>
                    <a href="manage-players.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">Manage</a>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_teams; ?></div>
                    <div class="stat-label">Teams</div>
                    <a href="manage-teams.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">Manage</a>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_seasons; ?></div>
                    <div class="stat-label">Seasons</div>
                    <a href="manage-seasons.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">Manage</a>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_rankings; ?></div>
                    <div class="stat-label">Rankings</div>
                    <a href="manage-rankings.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">Manage</a>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_scoring; ?></div>
                    <div class="stat-label">Scoring Settings</div>
                    <a href="manage-scoring.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">Manage</a>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Recent Players -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Players Added</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_players)): ?>
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach ($recent_players as $player): ?>
                                <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color);">
                                    <strong><?php echo htmlspecialchars($player['full_name']); ?></strong><br>
                                    <small style="color: var(--text-secondary);">
                                        Added: <?php echo date('M j, Y', strtotime($player['created_at'])); ?>
                                    </small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="manage-players.php" class="btn btn-primary btn-sm" style="margin-top: 15px;">View All</a>
                        <?php else: ?>
                            <p>No players added yet.</p>
                            <a href="manage-players.php" class="btn btn-primary">Add First Player</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Rankings Updates -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Rankings Updates</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_rankings)): ?>
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach ($recent_rankings as $ranking): ?>
                                <li style="padding: 8px 0; border-bottom: 1px solid var(--border-color);">
                                    <strong><?php echo htmlspecialchars($ranking['full_name']); ?></strong><br>
                                    <small style="color: var(--text-secondary);">
                                        #<?php echo $ranking['ranking']; ?> from <?php echo htmlspecialchars($ranking['source_name']); ?>
                                        - <?php echo date('M j, Y', strtotime($ranking['updated_at'])); ?>
                                    </small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="manage-rankings.php" class="btn btn-primary btn-sm" style="margin-top: 15px;">Manage Rankings</a>
                        <?php else: ?>
                            <p>No rankings set yet.</p>
                            <a href="manage-rankings.php" class="btn btn-primary">Set Rankings</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
                        <a href="manage-players.php?action=add" class="btn btn-success">Add New Player</a>
                        <a href="manage-teams.php?action=add" class="btn btn-success">Add New Team</a>
                        <a href="manage-seasons.php?action=add" class="btn btn-success">Add New Season</a>
                        <a href="manage-stats.php" class="btn btn-warning">Update Player Stats</a>
                        <a href="manage-rankings.php" class="btn btn-primary">Update Rankings</a>
                    </div>
                    
                    <!-- AmyoFootball Rank Calculation -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                        <h4>Calculate AmyoFootball Rankings</h4>
                        <p style="color: var(--text-secondary); margin-bottom: 15px;">
                            Calculate AmyoFootball rankings by averaging all other source rankings for each player.
                        </p>
                        <form method="POST" action="" style="display: flex; gap: 15px; align-items: end;">
                            <input type="hidden" name="action" value="calculate_amyo_ranks">
                            <div>
                                <label for="season_id"><strong>Season:</strong></label>
                                <select name="season_id" id="season_id" required style="padding: 8px; margin-top: 5px;">
                                    <option value="">Select Season</option>
                                    <?php foreach ($seasons as $season): ?>
                                        <option value="<?php echo $season['id']; ?>">
                                            <?php echo htmlspecialchars($season['year']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-warning" onclick="return confirm('This will replace all existing AmyoFootball rankings for the selected season. Continue?')">
                                Calculate AmyoFootball Rankings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

<?php 
include 'admin-nav-footer.php';
include 'admin-footer.php'; 
?>
