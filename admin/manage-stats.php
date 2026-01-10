<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Manage Projected Stats';

// Check if source is selected
$selected_source = $_GET['source'] ?? $_SESSION['selected_source'] ?? '';

// If no source selected, show source selection page
if (!$selected_source) {
    $sources = $pdo->query("SELECT * FROM sources ORDER BY name")->fetchAll();
    include 'admin-header.php';
    include 'admin-nav.php';
    ?>
    
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Select Projection Source</h1>
        <p>Choose a source for projected statistics</p>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Available Sources</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($sources)): ?>
                <div class="sources-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
                    <?php foreach ($sources as $source): ?>
                        <div class="source-card" style="border: 2px solid var(--border-color); border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease;" 
                             onclick="selectSource(<?php echo $source['id']; ?>, '<?php echo htmlspecialchars($source['name']); ?>')">
                            <h4 style="color: var(--primary-color); margin: 0 0 10px 0;"><?php echo htmlspecialchars($source['name']); ?></h4>
                            <p style="color: var(--text-secondary); margin: 0;">Click to manage projections from this source</p>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="manage-sources.php" class="btn btn-secondary">Manage Sources</a>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px;">
                    <h3>No Sources Found</h3>
                    <p>You need to create at least one projection source before managing stats.</p>
                    <a href="manage-sources.php" class="btn btn-primary">Create First Source</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function selectSource(sourceId, sourceName) {
        window.location.href = 'manage-stats.php?source=' + sourceId;
    }
    
    // Style source cards on hover
    document.addEventListener('DOMContentLoaded', function() {
        const sourceCards = document.querySelectorAll('.source-card');
        sourceCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.borderColor = 'var(--primary-color)';
                this.style.boxShadow = 'var(--shadow)';
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.borderColor = 'var(--border-color)';
                this.style.boxShadow = 'none';
                this.style.transform = 'translateY(0)';
            });
        });
    });
    </script>
    
    <?php
    include 'admin-footer.php';
    exit;
}

// Store selected source in session for persistence
$_SESSION['selected_source'] = $selected_source;

// Get source name for display
$source_query = $pdo->prepare("SELECT name FROM sources WHERE id = ?");
$source_query->execute([$selected_source]);
$source_data = $source_query->fetch();
$source_name = $source_data['name'] ?? 'Unknown Source';

// Get current season
$current_season_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC LIMIT 1");
$current_season = $current_season_query->fetch();

if (!$current_season) {
    $error = "No seasons found. Please create a season first.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['player_id']) && $current_season) {
    $player_id = (int)$_POST['player_id'];
    $season_id = $current_season['id'];
    $team_id = $_POST['team_id'] ? (int)$_POST['team_id'] : null;
    $position = sanitize($_POST['position']) ?: null;
    $matches = (int)$_POST['matches'] ?: 0;
    
    // Debug info collection
    $debug_info = [
        'player_id' => $player_id,
        'season_id' => $season_id,
        'team_id' => $team_id,
        'position' => $position,
        'matches' => $matches,
        'post_data' => $_POST
    ];
    
    if (empty($player_id) || $player_id <= 0) {
        $error = "Invalid player ID provided: '$player_id'";
        if (isset($_GET['debug']) || isset($_POST['debug'])) {
            $error .= "<br><br><strong>Debug Info:</strong><br>";
            $error .= "Raw POST player_id: '" . ($_POST['player_id'] ?? 'NOT SET') . "'<br>";
            $error .= "Converted player_id: '$player_id'<br>";
            $error .= "Selected player from GET: '" . ($_GET['player'] ?? 'NOT SET') . "'<br>";
            $error .= "Raw POST data: <pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
        }
    } else {
        // Collect all stat fields
        $stats = [
            'passing_yards' => $_POST['passing_yards'] ?: null,
            'passing_tds' => $_POST['passing_tds'] ?: null,
            'interceptions' => $_POST['interceptions'] ?: null,
            'rushing_yards' => $_POST['rushing_yards'] ?: null,
            'rushing_tds' => $_POST['rushing_tds'] ?: null,
            'fumbles' => $_POST['fumbles'] ?: null,
            'receptions' => $_POST['receptions'] ?: null,
            'receiving_yards' => $_POST['receiving_yards'] ?: null,
            'receiving_tds' => $_POST['receiving_tds'] ?: null,
            'tackles' => $_POST['tackles'] ?: null,
            'defensive_interceptions' => $_POST['defensive_interceptions'] ?: null,
            'forced_fumbles' => $_POST['forced_fumbles'] ?: null,
            'passes_defended' => $_POST['passes_defended'] ?: null,
            'fumble_recoveries' => $_POST['fumble_recoveries'] ?: null,
            'sacks' => $_POST['sacks'] ?: null
        ];
        
        try {
            $pdo->beginTransaction();
            
            // Verify player exists
            $verify_player = $pdo->prepare("SELECT id, full_name FROM players WHERE id = ?");
            $verify_player->execute([$player_id]);
            $player_record = $verify_player->fetch();
            if (!$player_record) {
                throw new Exception("Player not found with ID: '$player_id'");
            }
            
            // Verify team exists if provided
            if ($team_id) {
                $verify_team = $pdo->prepare("SELECT id, name FROM teams WHERE id = ?");
                $verify_team->execute([$team_id]);
                $team_record = $verify_team->fetch();
                if (!$team_record) {
                    throw new Exception("Team not found with ID: '$team_id'");
                }
            }
            
            // Verify season exists
            $verify_season = $pdo->prepare("SELECT id, year FROM seasons WHERE id = ?");
            $verify_season->execute([$season_id]);
            $season_record = $verify_season->fetch();
            if (!$season_record) {
                throw new Exception("Season not found with ID: '$season_id'");
            }
        
        // Update or insert player-team assignment
        if ($team_id && $position) {
            // Check if player-team assignment exists
            $check_pt = $pdo->prepare("SELECT id FROM player_teams WHERE player_id = ? AND season_id = ?");
            $check_pt->execute([$player_id, $season_id]);
            $existing_pt = $check_pt->fetch();
            
            if ($existing_pt) {
                // Update existing player-team
                $update_pt = $pdo->prepare("UPDATE player_teams SET team_id = ?, position = ?, matches = ? WHERE id = ?");
                $update_pt->execute([$team_id, $position, $matches, $existing_pt['id']]);
            } else {
                // Insert new player-team
                $insert_pt = $pdo->prepare("INSERT INTO player_teams (player_id, team_id, season_id, position, matches) VALUES (?, ?, ?, ?, ?)");
                $insert_pt->execute([$player_id, $team_id, $season_id, $position, $matches]);
            }
        } elseif ($position && !$team_id) {
            // Handle case where position is set but no team (free agent)
            $check_pt = $pdo->prepare("SELECT id FROM player_teams WHERE player_id = ? AND season_id = ?");
            $check_pt->execute([$player_id, $season_id]);
            $existing_pt = $check_pt->fetch();
            
            if ($existing_pt) {
                // Update existing player-team (set team to null for free agent)
                $update_pt = $pdo->prepare("UPDATE player_teams SET team_id = NULL, position = ?, matches = ? WHERE id = ?");
                $update_pt->execute([$position, $matches, $existing_pt['id']]);
            } else {
                // Insert new player-team (free agent)
                $insert_pt = $pdo->prepare("INSERT INTO player_teams (player_id, team_id, season_id, position, matches) VALUES (?, NULL, ?, ?, ?)");
                $insert_pt->execute([$player_id, $season_id, $position, $matches]);
            }
        }
        
        // Update or insert projected stats
        $check_stmt = $pdo->prepare("SELECT id FROM projected_stats WHERE player_id = ? AND season_id = ? AND source_id = ?");
        $check_stmt->execute([$player_id, $season_id, $selected_source]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Update existing stats
            $update_fields = [];
            $update_values = [];
            
            foreach ($stats as $field => $value) {
                $update_fields[] = "$field = ?";
                $update_values[] = $value;
            }
            
            $update_values[] = $existing['id'];
            $update_stmt = $pdo->prepare("UPDATE projected_stats SET " . implode(', ', $update_fields) . " WHERE id = ?");
            $update_stmt->execute($update_values);
        } else {
            // Insert new stats
            $fields = array_keys($stats);
            $placeholders = str_repeat('?,', count($fields));
            $placeholders = rtrim($placeholders, ',');
            
            $insert_values = [$player_id, $season_id, $selected_source];
            foreach ($stats as $value) {
                $insert_values[] = $value;
            }
            
            $insert_stmt = $pdo->prepare("INSERT INTO projected_stats (player_id, season_id, source_id, " . implode(', ', $fields) . ") VALUES (?, ?, ?, $placeholders)");
            $insert_stmt->execute($insert_values);
        }
        
        $pdo->commit();
        $success = "Player stats, team, and position updated successfully!";
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Error updating player data: " . $e->getMessage();
        
        // Add debugging info
        if (isset($_GET['debug']) || isset($_POST['debug'])) {
            $error .= "<br><br><strong>Debug Info:</strong><br>";
            $error .= "Player ID: " . $player_id . "<br>";
            $error .= "Season ID: " . $season_id . "<br>";
            $error .= "Team ID: " . ($team_id ?: 'NULL') . "<br>";
            $error .= "Position: " . ($position ?: 'NULL') . "<br>";
            $error .= "Player record: " . (isset($player_record) ? json_encode($player_record) : 'Not checked') . "<br>";
            $error .= "Team record: " . (isset($team_record) ? json_encode($team_record) : 'Not checked') . "<br>";
            $error .= "Season record: " . (isset($season_record) ? json_encode($season_record) : 'Not checked') . "<br>";
        }
    }
    } // Close the else block
} // Close the main POST if block

// Get player filter
$selected_player = $_GET['player'] ?? '';

// Get players with current stats
if ($current_season) {
    if ($selected_player) {
        $players_query = $pdo->prepare("
            SELECT p.id as player_id, p.full_name, p.first_name, p.last_name, p.birth_date, 
                   ps.id as stats_id, ps.passing_yards, ps.passing_tds, ps.interceptions,
                   ps.rushing_yards, ps.rushing_tds, ps.fumbles, ps.receptions,
                   ps.receiving_yards, ps.receiving_tds, ps.tackles, ps.defensive_interceptions,
                   ps.forced_fumbles, ps.passes_defended, ps.fumble_recoveries, ps.sacks,
                   pt.position, pt.matches, pt.team_id, t.abbreviation as team_abbr, t.name as team_name
            FROM players p
            LEFT JOIN projected_stats ps ON p.id = ps.player_id AND ps.season_id = ? AND ps.source_id = ?
            LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
            LEFT JOIN teams t ON pt.team_id = t.id
            WHERE p.id = ?
            ORDER BY p.full_name
        ");
        $players_query->execute([$current_season['id'], $selected_source, $current_season['id'], $selected_player]);
    } else {
        $players_query = $pdo->prepare("
            SELECT p.id as player_id, p.full_name, p.first_name, p.last_name, p.birth_date, 
                   ps.id as stats_id, ps.passing_yards, ps.passing_tds, ps.interceptions,
                   ps.rushing_yards, ps.rushing_tds, ps.fumbles, ps.receptions,
                   ps.receiving_yards, ps.receiving_tds, ps.tackles, ps.defensive_interceptions,
                   ps.forced_fumbles, ps.passes_defended, ps.fumble_recoveries, ps.sacks,
                   pt.position, pt.matches, pt.team_id, t.abbreviation as team_abbr, t.name as team_name
            FROM players p
            LEFT JOIN projected_stats ps ON p.id = ps.player_id AND ps.season_id = ? AND ps.source_id = ?
            LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
            LEFT JOIN teams t ON pt.team_id = t.id
            ORDER BY p.full_name
        ");
        $players_query->execute([$current_season['id'], $selected_source, $current_season['id']]);
    }
    $players = $players_query->fetchAll();
    
    // Get all players for dropdown
    $all_players = $pdo->query("SELECT * FROM players ORDER BY full_name")->fetchAll();
    
    // Get all teams for dropdown
    $all_teams = $pdo->query("SELECT * FROM teams ORDER BY name")->fetchAll();
} else {
    $players = [];
    $all_players = [];
    $all_teams = [];
}

include '../header.php';

// Debug output if requested
if (isset($_GET['debug']) && isset($_GET['player'])) {
    echo "<div style='background: #f0f0f0; padding: 20px; margin: 20px 0; border: 1px solid #ccc;'>";
    echo "<h3>Debug Info:</h3>";
    echo "<strong>Selected Player ID:</strong> " . ($_GET['player'] ?? 'none') . "<br>";
    echo "<strong>Players found:</strong> " . count($players) . "<br>";
    if (!empty($players)) {
        echo "<strong>First player data:</strong><br>";
        echo "<pre>" . htmlspecialchars(print_r($players[0], true)) . "</pre>";
    }
    echo "</div>";
}
?>

<?php 
include 'admin-header.php';
include 'admin-nav.php';
?>

    <!-- Admin Header -->
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Manage Projected Stats</h1>
                <p>Update player statistics and team assignments - Source: <strong><?php echo htmlspecialchars($source_name); ?></strong></p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="stats-spreadsheet.php?source=<?php echo $selected_source; ?>" class="btn btn-success">📊 Edit Spreadsheet</a>
                <a href="manage-stats.php" class="btn btn-secondary">Change Source</a>
            </div>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo $error; ?>
            <div style="margin-top: 10px;">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['debug' => '1'])); ?>" class="btn btn-sm btn-secondary">Show Debug Info</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($current_season): ?>
        <!-- Player Selection -->
        <div class="card">
            <div class="card-header">
                <h3>Update Projected Stats - <?php echo $current_season['year']; ?> Season</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <input type="hidden" name="source" value="<?php echo htmlspecialchars($selected_source); ?>">
                    <div style="display: flex; gap: 15px; align-items: end;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label for="player">Select Player to Update</label>
                            <select id="player" name="player" onchange="this.form.submit()">
                                <option value="">Choose a player...</option>
                                <?php foreach ($all_players as $player): ?>
                                    <option value="<?php echo $player['id']; ?>" <?php echo $selected_player == $player['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($player['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <a href="manage-stats.php?source=<?php echo htmlspecialchars($selected_source); ?>" class="btn btn-secondary">Show All</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stats Forms -->
        <?php if ($selected_player && !empty($players)): ?>
            <?php $player = $players[0]; ?>
            <div class="card">
                <div class="card-header">
                    <h3>Update Stats for <?php echo htmlspecialchars($player['full_name']); ?></h3>
                    <?php if ($player['team_name'] || $player['position']): ?>
                        <p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.8);">
                            Current: <?php echo $player['team_name'] ?? 'No Team'; ?> - <?php echo $player['position'] ?? 'No Position'; ?>
                            <?php if ($player['matches']): ?>
                                • <?php echo $player['matches']; ?> games
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                    <input type="hidden" name="player_id" value="<?php echo $player['player_id']; ?>">
                    
                    <!-- Player Assignment Section -->
                    <h4 style="color: var(--primary-color); margin-bottom: 15px;">Player Assignment</h4>
                    <div class="form-row">
                        <div class="form-col">
                            <label for="team_id">Team</label>
                            <select id="team_id" name="team_id">
                                <option value="">Free Agent</option>
                                <?php foreach ($all_teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>" <?php echo $player['team_id'] == $team['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['abbreviation'] . ' - ' . $team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-col">
                            <label for="position">Position</label>
                            <select id="position" name="position">
                                <option value="">Select Position</option>
                                <option value="QB" <?php echo $player['position'] == 'QB' ? 'selected' : ''; ?>>Quarterback (QB)</option>
                                <option value="RB" <?php echo $player['position'] == 'RB' ? 'selected' : ''; ?>>Running Back (RB)</option>
                                <option value="FB" <?php echo $player['position'] == 'FB' ? 'selected' : ''; ?>>Fullback (FB)</option>
                                <option value="WR" <?php echo $player['position'] == 'WR' ? 'selected' : ''; ?>>Wide Receiver (WR)</option>
                                <option value="TE" <?php echo $player['position'] == 'TE' ? 'selected' : ''; ?>>Tight End (TE)</option>
                                <option value="C" <?php echo $player['position'] == 'C' ? 'selected' : ''; ?>>Center (C)</option>
                                <option value="G" <?php echo $player['position'] == 'G' ? 'selected' : ''; ?>>Guard (G)</option>
                                <option value="T" <?php echo $player['position'] == 'T' ? 'selected' : ''; ?>>Tackle (T)</option>
                                <option value="DE" <?php echo $player['position'] == 'DE' ? 'selected' : ''; ?>>Defensive End (DE)</option>
                                <option value="DT" <?php echo $player['position'] == 'DT' ? 'selected' : ''; ?>>Defensive Tackle (DT)</option>
                                <option value="LB" <?php echo $player['position'] == 'LB' ? 'selected' : ''; ?>>Linebacker (LB)</option>
                                <option value="CB" <?php echo $player['position'] == 'CB' ? 'selected' : ''; ?>>Cornerback (CB)</option>
                                <option value="S" <?php echo $player['position'] == 'S' ? 'selected' : ''; ?>>Safety (S)</option>
                                <option value="K" <?php echo $player['position'] == 'K' ? 'selected' : ''; ?>>Kicker (K)</option>
                                <option value="P" <?php echo $player['position'] == 'P' ? 'selected' : ''; ?>>Punter (P)</option>
                            </select>
                        </div>
                        <div class="form-col">
                            <label for="matches">Games Played</label>
                            <input type="number" id="matches" name="matches" 
                                   value="<?php echo $player['matches'] ?? ''; ?>" 
                                   placeholder="0" min="0" max="17">
                        </div>
                    </div>
                        
                        <!-- Offensive Stats -->
                        <h4 style="color: var(--primary-color); margin-bottom: 15px;">Offensive Stats</h4>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="passing_yards">Passing Yards</label>
                                <input type="number" id="passing_yards" name="passing_yards" step="0.1" 
                                       value="<?php echo $player['passing_yards'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="passing_tds">Passing TDs</label>
                                <input type="number" id="passing_tds" name="passing_tds" 
                                       value="<?php echo $player['passing_tds'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="interceptions">Interceptions</label>
                                <input type="number" id="interceptions" name="interceptions" 
                                       value="<?php echo $player['interceptions'] ?? ''; ?>" placeholder="0">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <label for="rushing_yards">Rushing Yards</label>
                                <input type="number" id="rushing_yards" name="rushing_yards" step="0.1" 
                                       value="<?php echo $player['rushing_yards'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="rushing_tds">Rushing TDs</label>
                                <input type="number" id="rushing_tds" name="rushing_tds" 
                                       value="<?php echo $player['rushing_tds'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="fumbles">Fumbles</label>
                                <input type="number" id="fumbles" name="fumbles" 
                                       value="<?php echo $player['fumbles'] ?? ''; ?>" placeholder="0">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <label for="receptions">Receptions</label>
                                <input type="number" id="receptions" name="receptions" 
                                       value="<?php echo $player['receptions'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="receiving_yards">Receiving Yards</label>
                                <input type="number" id="receiving_yards" name="receiving_yards" step="0.1" 
                                       value="<?php echo $player['receiving_yards'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="receiving_tds">Receiving TDs</label>
                                <input type="number" id="receiving_tds" name="receiving_tds" 
                                       value="<?php echo $player['receiving_tds'] ?? ''; ?>" placeholder="0">
                            </div>
                        </div>

                        <!-- Defensive Stats -->
                        <h4 style="color: var(--primary-color); margin: 30px 0 15px 0;">Defensive Stats</h4>
                        <div class="form-row">
                            <div class="form-col">
                                <label for="tackles">Tackles</label>
                                <input type="number" id="tackles" name="tackles" 
                                       value="<?php echo $player['tackles'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="sacks">Sacks</label>
                                <input type="number" id="sacks" name="sacks" step="0.1" 
                                       value="<?php echo $player['sacks'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="defensive_interceptions">Def Interceptions</label>
                                <input type="number" id="defensive_interceptions" name="defensive_interceptions" 
                                       value="<?php echo $player['defensive_interceptions'] ?? ''; ?>" placeholder="0">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <label for="forced_fumbles">Forced Fumbles</label>
                                <input type="number" id="forced_fumbles" name="forced_fumbles" 
                                       value="<?php echo $player['forced_fumbles'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="fumble_recoveries">Fumble Recoveries</label>
                                <input type="number" id="fumble_recoveries" name="fumble_recoveries" 
                                       value="<?php echo $player['fumble_recoveries'] ?? ''; ?>" placeholder="0">
                            </div>
                            <div class="form-col">
                                <label for="passes_defended">Passes Defended</label>
                                <input type="number" id="passes_defended" name="passes_defended" 
                                       value="<?php echo $player['passes_defended'] ?? ''; ?>" placeholder="0">
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 30px;">
                            <button type="submit" class="btn btn-primary">Update Player Stats & Assignment</button>
                            <a href="manage-stats.php?source=<?php echo htmlspecialchars($selected_source); ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif (!$selected_player): ?>
            <!-- Stats Overview Table -->
            <div class="card">
                <div class="card-header">
                    <h3>All Player Stats Overview</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($players)): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Player</th>
                                        <th>Team</th>
                                        <th>Position</th>
                                        <th>Games</th>
                                        <th>Pass Yds</th>
                                        <th>Rush Yds</th>
                                        <th>Rec Yds</th>
                                        <th>Total TDs</th>
                                        <th>Has Stats</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($players as $player): ?>
                                        <tr>
                                    <td class="player-cell">
                                        <?php echo htmlspecialchars($player['full_name']); ?>
                                    </td>
                                    <td class="team-cell">
                                        <?php echo htmlspecialchars($player['team_abbr'] ?? 'FA'); ?>
                                    </td>
                                    <td>
                                        <span class="position-badge position-<?php echo strtolower($player['position'] ?? 'na'); ?>">
                                            <?php echo htmlspecialchars($player['position'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="stats-cell">
                                        <?php echo $player['matches'] ?? '0'; ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php echo $player['passing_yards'] ? number_format($player['passing_yards'], 0) : '-'; ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php echo $player['rushing_yards'] ? number_format($player['rushing_yards'], 0) : '-'; ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php echo $player['receiving_yards'] ? number_format($player['receiving_yards'], 0) : '-'; ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php 
                                        $total_tds = ($player['passing_tds'] ?? 0) + ($player['rushing_tds'] ?? 0) + ($player['receiving_tds'] ?? 0);
                                        echo $total_tds > 0 ? $total_tds : '-';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($player['passing_yards'] || $player['rushing_yards'] || $player['receiving_yards'] || $player['team_id']): ?>
                                            <span class="position-badge position-qb">✓ Complete</span>
                                        <?php else: ?>
                                            <span class="position-badge position-def">Needs Setup</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?player=<?php echo $player['player_id']; ?>&source=<?php echo htmlspecialchars($selected_source); ?>" class="btn btn-primary btn-sm">Edit All</a>
                                    </td>
                                </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No players found. <a href="manage-players.php">Add some players</a> first!</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align: center;">
                <h3>No Season Found</h3>
                <p>You need to create a season before you can manage projected stats.</p>
                <a href="manage-seasons.php" class="btn btn-primary">Create Season</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-calculate total touchdowns
document.addEventListener('input', function(e) {
    if (e.target.name && (e.target.name.includes('_tds') || e.target.name.includes('tds'))) {
        const passingTds = parseInt(document.querySelector('[name="passing_tds"]').value) || 0;
        const rushingTds = parseInt(document.querySelector('[name="rushing_tds"]').value) || 0;
        const receivingTds = parseInt(document.querySelector('[name="receiving_tds"]').value) || 0;
        const totalTds = passingTds + rushingTds + receivingTds;
        
        // You could display this total somewhere if needed
        console.log('Total TDs: ' + totalTds);
    }
});
</script>

<?php 
include 'admin-nav-footer.php';
include 'admin-footer.php'; 
?>
