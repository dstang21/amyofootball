<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Calculate Fantasy Points';

// Handle calculation request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['calculate'])) {
    $season_id = (int)$_POST['season_id'];
    $scoring_setting_ids = $_POST['scoring_settings'] ?? [];
    
    if (empty($season_id)) {
        $error = "Please select a season.";
    } elseif (empty($scoring_setting_ids)) {
        $error = "Please select at least one scoring setting.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $calculations_made = 0;
            $players_processed = 0;
            
            // Get all players with projected stats for the selected season
            // IMPORTANT: Only process AmyoFootball stats (source_id=1) for fantasy points calculation
            $players_query = $pdo->prepare("
                SELECT ps.id as projected_stats_id, ps.player_id, ps.season_id, ps.source_id, ps.passing_yards, ps.passing_tds, ps.interceptions,
                       ps.rushing_yards, ps.rushing_tds, ps.fumbles, ps.receptions,
                       ps.receiving_yards, ps.receiving_tds, p.full_name, s.name as source_name
                FROM projected_stats ps
                JOIN players p ON ps.player_id = p.id
                LEFT JOIN sources s ON ps.source_id = s.id
                WHERE ps.season_id = ? AND ps.source_id = 1
            ");
            $players_query->execute([$season_id]);
            $players_with_stats = $players_query->fetchAll();
            
            if (empty($players_with_stats)) {
                throw new Exception("No projected stats found for the selected season.");
            }
            
            // Get selected scoring settings
            $scoring_placeholders = str_repeat('?,', count($scoring_setting_ids));
            $scoring_placeholders = rtrim($scoring_placeholders, ',');
            
            $scoring_query = $pdo->prepare("SELECT * FROM scoring_settings WHERE id IN ($scoring_placeholders)");
            $scoring_query->execute($scoring_setting_ids);
            $scoring_settings = $scoring_query->fetchAll();
            
            if (empty($scoring_settings)) {
                throw new Exception("No valid scoring settings found for the selected IDs: " . implode(', ', $scoring_setting_ids));
            }
            
            // Validate that all requested scoring IDs exist
            $found_ids = array_column($scoring_settings, 'id');
            $missing_ids = array_diff($scoring_setting_ids, $found_ids);
            if (!empty($missing_ids)) {
                throw new Exception("The following scoring setting IDs do not exist in the database: " . implode(', ', $missing_ids) . ". Please check your scoring_settings table.");
            }
            
            // Calculate fantasy points for each player and each scoring setting
            foreach ($players_with_stats as $player) {
                $players_processed++;
                
                foreach ($scoring_settings as $scoring) {
                    // Calculate fantasy points using the scoring formula
                    $fantasy_points = 0;
                    
                    // DEBUG: Show what we're working with for first few players
                    $debug_this_player = $players_processed <= 3; // Debug first 3 players
                    
                    if ($debug_this_player) {
                        echo "<pre>DEBUG - Player: {$player['full_name']} (Source: {$player['source_name']})\n";
                        echo "Scoring System: {$scoring['name']}\n";
                        echo "Raw Stats:\n";
                        echo "  passing_yards: {$player['passing_yards']}\n";
                        echo "  passing_tds: {$player['passing_tds']}\n";
                        echo "  rushing_yards: {$player['rushing_yards']}\n";
                        echo "  rushing_tds: {$player['rushing_tds']}\n";
                        echo "  receiving_yards: {$player['receiving_yards']}\n";
                        echo "  receiving_tds: {$player['receiving_tds']}\n";
                        echo "Scoring Multipliers:\n";
                        echo "  passing_yards: {$scoring['passing_yards']}\n";
                        echo "  passing_td: {$scoring['passing_td']}\n";
                        echo "  rushing_yards: {$scoring['rushing_yards']}\n";
                        echo "  rushing_td: {$scoring['rushing_td']}\n";
                        echo "  receiving_yards: {$scoring['receiving_yards']}\n";
                        echo "  receiving_tds: {$scoring['receiving_tds']}\n";
                    }
                    
                    // Passing
                    $passing_yards_points = (float)($player['passing_yards'] ?? 0) * (float)$scoring['passing_yards'];
                    $passing_td_points = (float)($player['passing_tds'] ?? 0) * (float)$scoring['passing_td'];
                    $passing_int_points = (float)($player['interceptions'] ?? 0) * (float)$scoring['passing_int'];
                    $fantasy_points += $passing_yards_points + $passing_td_points + $passing_int_points;
                    
                    // Rushing (THIS IS CRITICAL FOR QBs!)
                    $rushing_yards_points = (float)($player['rushing_yards'] ?? 0) * (float)$scoring['rushing_yards'];
                    $rushing_td_points = (float)($player['rushing_tds'] ?? 0) * (float)$scoring['rushing_td'];
                    $fantasy_points += $rushing_yards_points + $rushing_td_points;
                    
                    // Receiving
                    $receptions_points = (float)($player['receptions'] ?? 0) * (float)$scoring['receptions'];
                    $receiving_yards_points = (float)($player['receiving_yards'] ?? 0) * (float)$scoring['receiving_yards'];
                    $receiving_td_points = (float)($player['receiving_tds'] ?? 0) * (float)$scoring['receiving_tds'];
                    $fantasy_points += $receptions_points + $receiving_yards_points + $receiving_td_points;
                    
                    // Penalties
                    $fumbles_points = (float)($player['fumbles'] ?? 0) * (float)$scoring['fumbles_lost'];
                    $fantasy_points += $fumbles_points;
                    
                    if ($debug_this_player) {
                        echo "Calculated Points Breakdown:\n";
                        echo "  Passing Yards: {$passing_yards_points}\n";
                        echo "  Passing TDs: {$passing_td_points}\n";
                        echo "  Interceptions: {$passing_int_points}\n";
                        echo "  Rushing Yards: {$rushing_yards_points}\n";
                        echo "  Rushing TDs: {$rushing_td_points}\n";
                        echo "  Receptions: {$receptions_points}\n";
                        echo "  Receiving Yards: {$receiving_yards_points}\n";
                        echo "  Receiving TDs: {$receiving_td_points}\n";
                        echo "  Fumbles: {$fumbles_points}\n";
                        echo "TOTAL FANTASY POINTS: {$fantasy_points}\n";
                        echo "ROUNDED: " . round($fantasy_points, 2) . "\n\n</pre>";
                    }
                    
                    // Insert or update the calculated points
                    $upsert_query = $pdo->prepare("
                        INSERT INTO projected_fantasy_points (player_id, season_id, projected_stats_id, scoring_setting_id, calculated_points)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE calculated_points = VALUES(calculated_points), updated_at = CURRENT_TIMESTAMP
                    ");
                    
                    $upsert_query->execute([
                        $player['player_id'],
                        $season_id,
                        $player['projected_stats_id'],
                        $scoring['id'],
                        round($fantasy_points, 2)
                    ]);
                    
                    $calculations_made++;
                }
            }
            
            $pdo->commit();
            $success = "Successfully calculated fantasy points! Processed {$players_processed} players with {$calculations_made} total calculations.";
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "Error calculating fantasy points: " . $e->getMessage();
        }
    }
}

// Get seasons and scoring settings for the form
$seasons = $pdo->query("SELECT * FROM seasons ORDER BY year DESC")->fetchAll();
$scoring_settings = $pdo->query("SELECT * FROM scoring_settings ORDER BY name")->fetchAll();

// Get current calculation stats
$current_season = $pdo->query("SELECT * FROM seasons ORDER BY year DESC LIMIT 1")->fetch();
$calc_stats = [];
if ($current_season) {
    $stats_query = $pdo->prepare("
        SELECT ss.name, COUNT(DISTINCT pfp.id) as player_count, 
               AVG(pfp.calculated_points) as avg_points,
               MAX(pfp.calculated_points) as max_points,
               MIN(pfp.calculated_points) as min_points,
               COUNT(DISTINCT s.name) as source_count,
               GROUP_CONCAT(DISTINCT s.name) as sources
        FROM scoring_settings ss
        LEFT JOIN projected_fantasy_points pfp ON ss.id = pfp.scoring_setting_id AND pfp.season_id = ?
        LEFT JOIN projected_stats ps ON pfp.projected_stats_id = ps.id
        LEFT JOIN sources s ON ps.source_id = s.id
        GROUP BY ss.id, ss.name
        ORDER BY ss.name
    ");
    $stats_query->execute([$current_season['id']]);
    $calc_stats = $stats_query->fetchAll();
}

include '../header.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Calculate Fantasy Points</h1>
        <p>Calculate projected fantasy points based on player stats and scoring settings</p>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Run Fantasy Point Calculations</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-col">
                        <label for="season_id">Season</label>
                        <select id="season_id" name="season_id" required>
                            <option value="">Select Season</option>
                            <?php foreach ($seasons as $season): ?>
                                <option value="<?php echo $season['id']; ?>">
                                    <?php echo htmlspecialchars($season['year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Scoring Settings to Calculate</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; margin-top: 10px;">
                        <?php foreach ($scoring_settings as $setting): ?>
                            <label style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <input type="checkbox" name="scoring_settings[]" value="<?php echo $setting['id']; ?>" style="margin-right: 10px;">
                                <span><strong><?php echo htmlspecialchars($setting['name']); ?></strong></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small>Select which scoring systems to calculate points for. This will overwrite any existing calculations.</small>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" name="calculate" class="btn btn-primary">Calculate Fantasy Points</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Current Calculation Status -->
    <?php if (!empty($calc_stats)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Current Calculations (<?php echo $current_season['year']; ?> Season)</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Scoring Setting</th>
                                <th>Players Calculated</th>
                                <th>Sources Used</th>
                                <th>Average Points</th>
                                <th>Highest Points</th>
                                <th>Lowest Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calc_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['name']); ?></strong></td>
                                <td><?php echo $stat['player_count']; ?></td>
                                <td>
                                    <?php if ($stat['sources']): ?>
                                        <small><?php echo htmlspecialchars($stat['sources']); ?></small>
                                    <?php else: ?>
                                        <span class="position-badge position-na">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $stat['avg_points'] ? number_format($stat['avg_points'], 2) : '-'; ?></td>
                                <td><?php echo $stat['max_points'] ? number_format($stat['max_points'], 2) : '-'; ?></td>
                                <td><?php echo $stat['min_points'] ? number_format($stat['min_points'], 2) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Top Players Preview -->
    <?php if ($current_season): ?>
        <div class="card">
            <div class="card-header">
                <h3>Top Projected Players (Sample)</h3>
            </div>
            <div class="card-body">
                <?php
                $top_players_query = $pdo->prepare("
                    SELECT p.full_name, ss.name as scoring_name, pfp.calculated_points
                    FROM projected_fantasy_points pfp
                    JOIN players p ON pfp.player_id = p.id
                    JOIN scoring_settings ss ON pfp.scoring_setting_id = ss.id
                    WHERE pfp.season_id = ?
                    ORDER BY pfp.calculated_points DESC
                    LIMIT 20
                ");
                $top_players_query->execute([$current_season['id']]);
                $top_players = $top_players_query->fetchAll();
                ?>
                
                <?php if (!empty($top_players)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Player</th>
                                    <th>Scoring System</th>
                                    <th>Projected Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_players as $player): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($player['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($player['scoring_name']); ?></td>
                                    <td><strong><?php echo number_format($player['calculated_points'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No calculated fantasy points found. Run calculations above to see results.</p>
                <?php endif; ?>
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
</style>

<?php include '../footer.php'; ?>
