<?php
require_once 'config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('index.php');
}

$page_title = 'Player Stats';

// Get current season
$current_season_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC LIMIT 1");
$current_season = $current_season_query->fetch();

// Handle filters
$position_filter = $_GET['position'] ?? '';
$team_filter = $_GET['team'] ?? '';
$search = $_GET['search'] ?? '';
$scoring_filter = $_GET['scoring'] ?? 5; // Default to Wyandotte (scoring_setting_id=5)

// Build query conditions
$where_conditions = ["ps.source_id = 1"]; // Only AmyoFootball stats
$params = [$current_season['id'] ?? 1];

if (!empty($position_filter)) {
    $where_conditions[] = "pt.position = ?";
    $params[] = $position_filter;
}

if (!empty($team_filter)) {
    $where_conditions[] = "t.id = ?";
    $params[] = $team_filter;
}

if (!empty($search)) {
    $where_conditions[] = "p.full_name LIKE ?";
    $params[] = "%$search%";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Add scoring filter parameter
$params[] = $scoring_filter;

// Get players with stats and fantasy points
$stats_query = $pdo->prepare("
    SELECT p.id, p.full_name, pt.position, t.abbreviation as team_abbr,
           ps.passing_yards, ps.passing_tds, ps.interceptions,
           ps.rushing_yards, ps.rushing_tds, ps.fumbles,
           ps.receptions, ps.receiving_yards, ps.receiving_tds,
           ps.tackles, ps.sacks, ps.defensive_interceptions,
           pfp.calculated_points,
           ss.name as scoring_name
    FROM projected_stats ps
    JOIN players p ON ps.player_id = p.id
    LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
    LEFT JOIN teams t ON pt.team_id = t.id
    LEFT JOIN projected_fantasy_points pfp ON ps.id = pfp.projected_stats_id AND pfp.scoring_setting_id = ?
    LEFT JOIN scoring_settings ss ON pfp.scoring_setting_id = ss.id
    $where_clause
    ORDER BY pfp.calculated_points DESC, p.full_name
");

$stats_query->execute($params);
$players = $stats_query->fetchAll();

// Get filter options
$positions = $pdo->query("
    SELECT DISTINCT pt.position 
    FROM player_teams pt 
    WHERE pt.position IS NOT NULL 
    ORDER BY pt.position
")->fetchAll();

$teams = $pdo->query("SELECT * FROM teams ORDER BY name")->fetchAll();

$scoring_settings = $pdo->query("SELECT * FROM scoring_settings ORDER BY name")->fetchAll();

include 'header.php';
?>

<div class="container">
    <h1>Player Stats & Fantasy Points</h1>
    <p class="subtitle">AmyoFootball projections (Source ID: 1) for <?php echo $current_season['year'] ?? 'Current'; ?> season</p>

    <!-- Filters -->
    <div class="search-filters" style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <form method="GET" action="">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                <div class="filter-group">
                    <label for="search">Search Players</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Player name...">
                </div>
                
                <div class="filter-group">
                    <label for="position">Position</label>
                    <select id="position" name="position">
                        <option value="">All Positions</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?php echo $pos['position']; ?>" <?php echo $position_filter == $pos['position'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pos['position']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="team">Team</label>
                    <select id="team" name="team">
                        <option value="">All Teams</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>" <?php echo $team_filter == $team['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="scoring">Scoring System</label>
                    <select id="scoring" name="scoring">
                        <?php foreach ($scoring_settings as $setting): ?>
                            <option value="<?php echo $setting['id']; ?>" <?php echo $scoring_filter == $setting['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($setting['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="stats.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Stats Table -->
    <?php if (!empty($players)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Player Statistics (<?php echo count($players); ?> players)</h3>
                <small>Showing AmyoFootball projections with <?php echo htmlspecialchars($players[0]['scoring_name'] ?? 'Fantasy'); ?> scoring</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Player</th>
                                <th>Team</th>
                                <th>Pos</th>
                                <th>Fantasy<br>Points</th>
                                <th>Pass<br>Yds</th>
                                <th>Pass<br>TDs</th>
                                <th>INTs</th>
                                <th>Rush<br>Yds</th>
                                <th>Rush<br>TDs</th>
                                <th>Rec</th>
                                <th>Rec<br>Yds</th>
                                <th>Rec<br>TDs</th>
                                <th>Fumbles</th>
                                <th>Tackles</th>
                                <th>Sacks</th>
                                <th>Def<br>INTs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player): ?>
                            <tr>
                                <td class="player-name">
                                    <a href="player.php?id=<?php echo $player['id']; ?>">
                                        <strong><?php echo htmlspecialchars($player['full_name']); ?></strong>
                                    </a>
                                </td>
                                <td class="team-cell">
                                    <?php echo htmlspecialchars($player['team_abbr'] ?? 'FA'); ?>
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
                                <td class="fantasy-points">
                                    <?php if ($player['calculated_points']): ?>
                                        <strong><?php echo number_format($player['calculated_points'], 1); ?></strong>
                                    <?php else: ?>
                                        <span class="no-data">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="stat-cell"><?php echo $player['passing_yards'] ? number_format($player['passing_yards']) : '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['passing_tds'] ?: '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['interceptions'] ?: '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['rushing_yards'] ? number_format($player['rushing_yards']) : '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['rushing_tds'] ?: '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['receptions'] ?: '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['receiving_yards'] ? number_format($player['receiving_yards']) : '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['receiving_tds'] ?: '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['fumbles'] ?: '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['tackles'] ?: '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['sacks'] ?: '-'; ?></td>
                                <td class="stat-cell"><?php echo $player['defensive_interceptions'] ?: '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <p>No player stats found for the selected filters. Try adjusting your search criteria.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.subtitle {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
}

.search-filters {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-weight: 500;
    margin-bottom: 5px;
    color: var(--dark-color);
}

.filter-group input,
.filter-group select {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.stats-table {
    width: 100%;
    font-size: 13px;
}

.stats-table th {
    background: var(--dark-color);
    color: white;
    padding: 8px 4px;
    text-align: center;
    font-size: 11px;
    line-height: 1.2;
}

.stats-table td {
    padding: 8px 4px;
    text-align: center;
    border-bottom: 1px solid #eee;
}

.player-name {
    text-align: left !important;
    min-width: 150px;
    font-weight: 500;
}

.player-name a {
    color: var(--primary-color);
    text-decoration: none;
}

.player-name a:hover {
    text-decoration: underline;
}

.team-cell {
    font-weight: 500;
    color: var(--dark-color);
}

.fantasy-points {
    background: #f8f9fa;
    font-weight: bold;
    color: #0277bd;
}

.stat-cell {
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.position-badge {
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: bold;
    color: white;
}

.position-qb { background: #dc2626; }
.position-rb { background: #059669; }
.position-wr { background: #2563eb; }
.position-te { background: #7c3aed; }
.position-k { background: #d97706; }
.position-dst { background: #374151; }
.position-dl { background: #374151; }
.position-lb { background: #374151; }
.position-db { background: #374151; }
.position-na { background: #6b7280; }

.no-data {
    color: #999;
    font-style: italic;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn:hover {
    opacity: 0.9;
}

@media (max-width: 768px) {
    .stats-table {
        font-size: 11px;
    }
    
    .stats-table th,
    .stats-table td {
        padding: 6px 2px;
    }
    
    .player-name {
        min-width: 120px;
    }
}
</style>

<?php include 'footer.php'; ?>
