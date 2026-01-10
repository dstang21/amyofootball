<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Manage Draft Positions';

// Get seasons
$seasons_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC");
$seasons = $seasons_query->fetchAll();

// Get sources
$sources_query = $pdo->query("SELECT * FROM sources ORDER BY name");
$sources = $sources_query->fetchAll();

// Get players
$players_query = $pdo->query("SELECT * FROM players ORDER BY full_name");
$players = $players_query->fetchAll();

// Get selected season and source (carry over from form or URL)
$selected_season_id = $_POST['season_id'] ?? $_GET['season_id'] ?? '';
$selected_source_id = $_POST['source_id'] ?? $_GET['source_id'] ?? '';

// Handle form submission for adding/updating draft position
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_ranking') {
    $season_id = (int)$_POST['season_id'];
    $source_id = (int)$_POST['source_id'];
    $player_id = (int)$_POST['player_id'];
    $ranking = (float)$_POST['ranking'];
    
    if ($season_id && $source_id && $player_id && $ranking) {
        try {
            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both insert and update
            $stmt = $pdo->prepare("
                INSERT INTO draft_positions (player_id, season_id, source_id, ranking) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE ranking = VALUES(ranking), updated_at = CURRENT_TIMESTAMP
            ");
            
            if ($stmt->execute([$player_id, $season_id, $source_id, $ranking])) {
                $success = "Draft position updated successfully!";
                // Keep the same season and source selected
                $selected_season_id = $season_id;
                $selected_source_id = $source_id;
            } else {
                $error = "Error updating draft position.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "All fields are required.";
    }
}

// Get existing rankings for display (if season is selected)
$rankings = [];
$next_ranking = '';
$available_sources = [];

if ($selected_season_id) {
    // Get all sources that have rankings for this season
    $sources_query = $pdo->prepare("
        SELECT DISTINCT s.id, s.name 
        FROM sources s 
        JOIN draft_positions dp ON s.id = dp.source_id 
        WHERE dp.season_id = ? 
        ORDER BY s.id
    ");
    $sources_query->execute([$selected_season_id]);
    $available_sources = $sources_query->fetchAll();

    if (!empty($available_sources)) {
        // Build dynamic JOIN clauses for each source
        $source_joins = '';
        $source_selects = '';
        $source_params = [];
        
        foreach ($available_sources as $source) {
            $source_joins .= " LEFT JOIN draft_positions dp_s{$source['id']} ON p.id = dp_s{$source['id']}.player_id AND dp_s{$source['id']}.season_id = ? AND dp_s{$source['id']}.source_id = {$source['id']}";
            $source_selects .= ", dp_s{$source['id']}.ranking as source_{$source['id']}_rank";
            $source_params[] = $selected_season_id;
        }
        
        // Get all players with rankings in this season (spreadsheet format)
        $rankings_query = $pdo->prepare("
            SELECT 
                p.id as player_id,
                p.full_name, 
                p.first_name, 
                p.last_name, 
                pt.position, 
                t.abbreviation as team_abbr,
                ps_amyo.passing_yards, ps_amyo.passing_tds, ps_amyo.interceptions, ps_amyo.rushing_yards, ps_amyo.rushing_tds, 
                ps_amyo.fumbles, ps_amyo.receptions, ps_amyo.receiving_yards, ps_amyo.receiving_tds, ps_amyo.tackles,
                ps_amyo.defensive_interceptions, ps_amyo.forced_fumbles, ps_amyo.passes_defended, ps_amyo.fumble_recoveries, ps_amyo.sacks,
                pfp_amyo.calculated_points as amyo_points,
                pfp_sleeper.calculated_points as sleeper_points
                {$source_selects}
            FROM players p
            JOIN draft_positions dp ON p.id = dp.player_id AND dp.season_id = ?
            LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
            LEFT JOIN teams t ON pt.team_id = t.id
            LEFT JOIN projected_stats ps_amyo ON p.id = ps_amyo.player_id AND ps_amyo.season_id = ? AND ps_amyo.source_id = 1
            LEFT JOIN projected_fantasy_points pfp_amyo ON ps_amyo.id = pfp_amyo.projected_stats_id AND pfp_amyo.scoring_setting_id = 1
            LEFT JOIN projected_stats ps_sleeper ON p.id = ps_sleeper.player_id AND ps_sleeper.season_id = ? AND ps_sleeper.source_id = 2
            LEFT JOIN projected_fantasy_points pfp_sleeper ON ps_sleeper.id = pfp_sleeper.projected_stats_id AND pfp_sleeper.scoring_setting_id = 1
            {$source_joins}
            GROUP BY p.id, p.full_name, p.first_name, p.last_name, pt.position, t.abbreviation,
                     ps_amyo.passing_yards, ps_amyo.passing_tds, ps_amyo.interceptions, ps_amyo.rushing_yards, ps_amyo.rushing_tds, 
                     ps_amyo.fumbles, ps_amyo.receptions, ps_amyo.receiving_yards, ps_amyo.receiving_tds, ps_amyo.tackles,
                     ps_amyo.defensive_interceptions, ps_amyo.forced_fumbles, ps_amyo.passes_defended, ps_amyo.fumble_recoveries, ps_amyo.sacks,
                     pfp_amyo.calculated_points, pfp_sleeper.calculated_points
            HAVING " . implode(' IS NOT NULL OR ', array_map(function($source) {
                return "source_{$source['id']}_rank";
            }, $available_sources)) . " IS NOT NULL
            ORDER BY COALESCE(" . implode(', ', array_map(function($source) {
                return "dp_s{$source['id']}.ranking";
            }, $available_sources)) . ") ASC
        ");
        
        $params = array_merge(
            [$selected_season_id, $selected_season_id, $selected_season_id, $selected_season_id],
            $source_params
        );
        
        $rankings_query->execute($params);
        $rankings = $rankings_query->fetchAll();
    }
    
    // Get the next ranking number for the selected source (if any)
    if ($selected_source_id) {
        $max_ranking_query = $pdo->prepare("SELECT MAX(ranking) as max_rank FROM draft_positions WHERE season_id = ? AND source_id = ?");
        $max_ranking_query->execute([$selected_season_id, $selected_source_id]);
        $max_result = $max_ranking_query->fetch();
        $next_ranking = $max_result['max_rank'] ? ($max_result['max_rank'] + 1) : 1;
    }
}

// Debug: Check if we have any projected fantasy points data
if ($selected_season_id) {
    $debug_query = $pdo->prepare("
        SELECT 
            COUNT(*) as total_stats,
            COUNT(pfp.id) as total_fantasy_points,
            COUNT(CASE WHEN ps.source_id = 1 THEN 1 END) as amyo_stats,
            COUNT(CASE WHEN ps.source_id = 2 THEN 1 END) as sleeper_stats,
            COUNT(CASE WHEN ps.source_id = 1 AND pfp.id IS NOT NULL THEN 1 END) as amyo_fantasy_points,
            COUNT(CASE WHEN ps.source_id = 2 AND pfp.id IS NOT NULL THEN 1 END) as sleeper_fantasy_points
        FROM projected_stats ps
        LEFT JOIN projected_fantasy_points pfp ON ps.id = pfp.projected_stats_id AND pfp.scoring_setting_id = 1
        WHERE ps.season_id = ?
    ");
    $debug_query->execute([$selected_season_id]);
    $debug_result = $debug_query->fetch();
    
    // Only show debug info if user is admin (you can remove this later)
    if (isAdmin()) {
        echo "<!-- DEBUG: Stats={$debug_result['total_stats']}, Fantasy Points={$debug_result['total_fantasy_points']}, Amyo Stats={$debug_result['amyo_stats']}, Sleeper Stats={$debug_result['sleeper_stats']}, Amyo FP={$debug_result['amyo_fantasy_points']}, Sleeper FP={$debug_result['sleeper_fantasy_points']} -->\n";
    }
}

include '../header.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Manage Draft Positions</h1>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Add/Update Draft Position Form -->
    <div class="card">
        <div class="card-header">
            <h3>Add/Update Draft Position</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_ranking">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    
                    <div>
                        <label for="season_id"><strong>Season:</strong></label>
                        <select name="season_id" id="season_id" required style="width: 100%; padding: 8px; margin-top: 5px;">
                            <option value="">Select Season</option>
                            <?php foreach ($seasons as $season): ?>
                                <option value="<?php echo $season['id']; ?>" <?php echo $selected_season_id == $season['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($season['year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="source_id"><strong>Source:</strong></label>
                        <select name="source_id" id="source_id" required style="width: 100%; padding: 8px; margin-top: 5px;">
                            <option value="">Select Source</option>
                            <?php foreach ($sources as $source): ?>
                                <option value="<?php echo $source['id']; ?>" <?php echo $selected_source_id == $source['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($source['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="player_id"><strong>Player:</strong></label>
                        <select name="player_id" id="player_id" required style="width: 100%; padding: 8px; margin-top: 5px;">
                            <option value="">Select Player</option>
                            <?php foreach ($players as $player): ?>
                                <option value="<?php echo $player['id']; ?>">
                                    <?php echo htmlspecialchars($player['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="ranking"><strong>Ranking:</strong></label>
                        <input type="number" name="ranking" id="ranking" required 
                               step="0.01" min="0.01" max="999.99"
                               style="width: 100%; padding: 8px; margin-top: 5px;"
                               value="<?php echo $next_ranking; ?>"
                               placeholder="e.g., 1.5, 10.25">
                    </div>
                    
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-primary">Add/Update Ranking</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Display Current Rankings -->
    <?php if ($selected_season_id && !empty($rankings) && !empty($available_sources)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Current Rankings - Spreadsheet View</h3>
                <p>Season: <?php 
                    $season_name = '';
                    foreach ($seasons as $s) {
                        if ($s['id'] == $selected_season_id) {
                            $season_name = $s['year'];
                            break;
                        }
                    }
                    echo $season_name; 
                ?></p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="rankings-spreadsheet">
                        <thead>
                            <tr>
                                <th class="player-info">Player</th>
                                <th class="player-info">Team</th>
                                <th class="player-info">Position</th>
                                <th class="player-info">Key Stats</th>
                                <th class="fantasy-points">Amyo FP</th>
                                <th class="fantasy-points">Sleeper FP</th>
                                <?php foreach ($available_sources as $source): ?>
                                    <th class="ranking-col"><?php echo htmlspecialchars($source['name']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rankings as $player): ?>
                            <tr>
                                <td class="player-name"><?php echo htmlspecialchars($player['full_name']); ?></td>
                                <td class="team-abbr"><?php echo htmlspecialchars($player['team_abbr'] ?? 'FA'); ?></td>
                                <td class="position">
                                    <?php if ($player['position']): ?>
                                        <span class="position-badge position-<?php echo strtolower($player['position']); ?>">
                                            <?php echo htmlspecialchars($player['position']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="position-badge position-na">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="key-stats">
                                    <?php
                                    // Display key stats based on position
                                    $stats = [];
                                    if ($player['position'] == 'QB') {
                                        if ($player['passing_yards']) $stats[] = number_format($player['passing_yards']) . ' pass yds';
                                        if ($player['passing_tds']) $stats[] = $player['passing_tds'] . ' pass TDs';
                                        if ($player['rushing_yards']) $stats[] = number_format($player['rushing_yards']) . ' rush yds';
                                    } elseif ($player['position'] == 'RB') {
                                        if ($player['rushing_yards']) $stats[] = number_format($player['rushing_yards']) . ' rush yds';
                                        if ($player['rushing_tds']) $stats[] = $player['rushing_tds'] . ' rush TDs';
                                        if ($player['receptions']) $stats[] = $player['receptions'] . ' rec';
                                        if ($player['receiving_yards']) $stats[] = number_format($player['receiving_yards']) . ' rec yds';
                                    } elseif (in_array($player['position'], ['WR', 'TE'])) {
                                        if ($player['receptions']) $stats[] = $player['receptions'] . ' rec';
                                        if ($player['receiving_yards']) $stats[] = number_format($player['receiving_yards']) . ' rec yds';
                                        if ($player['receiving_tds']) $stats[] = $player['receiving_tds'] . ' rec TDs';
                                    } elseif (in_array($player['position'], ['DL', 'LB', 'DB'])) {
                                        if ($player['tackles']) $stats[] = $player['tackles'] . ' tackles';
                                        if ($player['sacks']) $stats[] = $player['sacks'] . ' sacks';
                                        if ($player['defensive_interceptions']) $stats[] = $player['defensive_interceptions'] . ' INTs';
                                    }
                                    
                                    echo !empty($stats) ? '<small>' . implode(', ', $stats) . '</small>' : '<span class="no-data">No stats</span>';
                                    ?>
                                </td>
                                <td class="fantasy-points">
                                    <?php echo $player['amyo_points'] ? '<strong>' . number_format($player['amyo_points'], 1) . '</strong>' : '<span class="no-data">-</span>'; ?>
                                </td>
                                <td class="fantasy-points">
                                    <?php echo $player['sleeper_points'] ? '<strong>' . number_format($player['sleeper_points'], 1) . '</strong>' : '<span class="no-data">-</span>'; ?>
                                </td>
                                <?php foreach ($available_sources as $source): ?>
                                    <td class="ranking-col">
                                        <?php 
                                        $rank_key = "source_{$source['id']}_rank";
                                        echo $player[$rank_key] ? '<strong>' . number_format($player[$rank_key], 1) . '</strong>' : '<span class="no-data">-</span>';
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($selected_season_id && empty($rankings)): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 40px;">
                <h3>No Rankings Found</h3>
                <p>No draft positions have been set for the selected season.</p>
                <p>Use the form above to add rankings.</p>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
// Focus on player select when page loads
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('player_id').focus();
});

// Auto-submit form to refresh rankings when season changes (source not required for display anymore)
document.getElementById('season_id').addEventListener('change', function() {
    updateRankingsDisplay();
});

document.getElementById('source_id').addEventListener('change', function() {
    updateRankingsDisplay();
});

function updateRankingsDisplay() {
    const seasonId = document.getElementById('season_id').value;
    
    if (seasonId) {
        // Redirect to same page with selected season to show rankings
        window.location.href = `manage-rankings.php?season_id=${seasonId}`;
    }
}
</script>

<?php include '../footer.php'; ?>
