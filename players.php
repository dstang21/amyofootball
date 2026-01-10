<?php
require_once 'config.php';

// Restrict access to admin users only
if (!isAdmin()) {
    redirect('index.php');
}

$page_title = 'Players';

// Handle search and filters
$search = $_GET['search'] ?? '';
$position_filter = $_GET['position'] ?? '';
$team_filter = $_GET['team'] ?? '';
$stats_filter = $_GET['stats'] ?? ''; // New stats filter
$fantasy_scoring = $_GET['fantasy_scoring'] ?? ''; // Fantasy scoring filter
$sort = $_GET['sort'] ?? 'name';
$order = $_GET['order'] ?? 'asc';

// Get current season for stats
$current_season_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC LIMIT 1");
$current_season = $current_season_query->fetch();

// Get available fantasy scoring systems
$fantasy_scoring_systems = $pdo->query("SELECT * FROM scoring_settings ORDER BY name")->fetchAll();

// Debug output
if (isset($_GET['debug'])) {
    echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px; border: 1px solid #ccc;'>";
    echo "<h4>Debug Info:</h4>";
    echo "Current Season: " . ($current_season ? $current_season['year'] . " (ID: " . $current_season['id'] . ")" : "None") . "<br>";
    echo "Stats Filter: '" . $stats_filter . "'<br>";
    echo "Season ID: " . ($season_id ?? 'null') . "<br>";
    
    // Check if there are any projected stats
    if ($season_id) {
        $stats_count = $pdo->prepare("SELECT COUNT(*) as count FROM projected_stats WHERE season_id = ?");
        $stats_count->execute([$season_id]);
        $count = $stats_count->fetch();
        echo "Projected Stats for season: " . $count['count'] . " records<br>";
        
        // Show a sample
        $sample_stats = $pdo->prepare("SELECT p.full_name, ps.passing_yards, ps.rushing_yards FROM projected_stats ps JOIN players p ON ps.player_id = p.id WHERE ps.season_id = ? LIMIT 3");
        $sample_stats->execute([$season_id]);
        $samples = $sample_stats->fetchAll();
        if ($samples) {
            echo "Sample stats:<br>";
            foreach ($samples as $sample) {
                echo "- " . $sample['full_name'] . ": Pass=" . ($sample['passing_yards'] ?? 'null') . ", Rush=" . ($sample['rushing_yards'] ?? 'null') . "<br>";
            }
        }
    }
    echo "</div>";
}

// Build query
$where_conditions = [];
$params = [];

// Add season parameter for joins
$season_id = $current_season ? $current_season['id'] : null;

if (!empty($search)) {
    $where_conditions[] = "p.full_name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($position_filter)) {
    $where_conditions[] = "pt.position = ?";
    $params[] = $position_filter;
}

if (!empty($team_filter)) {
    $where_conditions[] = "pt.team_id = ?";
    $params[] = $team_filter;
}

// If stats filter is active, only show players with stats
if ($stats_filter === 'projected' && $current_season) {
    $where_conditions[] = "ps.player_id IS NOT NULL";
}

// If fantasy scoring filter is active, only show players with fantasy points
if (!empty($fantasy_scoring) && $current_season) {
    $where_conditions[] = "pfp.calculated_points IS NOT NULL";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Determine sort column
$valid_sorts = ['name', 'position', 'team', 'passing_yards', 'rushing_yards', 'receiving_yards', 'fantasy_points'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'name';
}

$sort_column = match($sort) {
    'name' => 'p.full_name',
    'position' => 'pt.position',
    'team' => 't.name',
    'passing_yards' => 'ps.passing_yards',
    'rushing_yards' => 'ps.rushing_yards', 
    'receiving_yards' => 'ps.receiving_yards',
    'fantasy_points' => 'pfp.calculated_points',
    default => 'p.full_name'
};

$sort_order = $order === 'desc' ? 'DESC' : 'ASC';

// Build the main query
$base_query = "
    FROM players p
    LEFT JOIN player_teams pt ON p.id = pt.player_id AND (pt.season_id = ? OR pt.season_id IS NULL)
    LEFT JOIN teams t ON pt.team_id = t.id
";

// Add joins based on filters
if ($stats_filter === 'projected' || $sort === 'passing_yards' || $sort === 'rushing_yards' || $sort === 'receiving_yards') {
    $base_query .= " LEFT JOIN projected_stats ps ON p.id = ps.player_id AND ps.season_id = ?";
    array_unshift($params, $current_season ? $current_season['id'] : 0, $current_season ? $current_season['id'] : 0);
} else {
    array_unshift($params, $current_season ? $current_season['id'] : 0);
}

// Add fantasy points join if needed
if (!empty($fantasy_scoring) || $sort === 'fantasy_points') {
    $base_query .= " LEFT JOIN projected_fantasy_points pfp ON ps.id = pfp.projected_stats_id AND pfp.scoring_setting_id = ?";
    if (!empty($fantasy_scoring)) {
        $params[] = $fantasy_scoring;
    } else {
        $params[] = 0; // Default to first scoring system or 0 if none selected
    }
}

// Build SELECT clause
$select_clause = "
    SELECT p.id, p.full_name, p.first_name, p.last_name, p.birth_date,
           t.name as team_name, t.abbreviation as team_abbr, 
           pt.position
";

// Add stats columns if needed
if ($stats_filter === 'projected') {
    $select_clause .= ",
           ps.passing_yards, ps.passing_tds, ps.interceptions,
           ps.rushing_yards, ps.rushing_tds, ps.fumbles,
           ps.receptions, ps.receiving_yards, ps.receiving_tds,
           ps.tackles, ps.sacks, ps.defensive_interceptions
    ";
} else {
    $select_clause .= ",
           NULL as passing_yards, NULL as passing_tds, NULL as interceptions,
           NULL as rushing_yards, NULL as rushing_tds, NULL as fumbles,
           NULL as receptions, NULL as receiving_yards, NULL as receiving_tds,
           NULL as tackles, NULL as sacks, NULL as defensive_interceptions
    ";
}

// Add fantasy points if needed
if (!empty($fantasy_scoring) || $sort === 'fantasy_points') {
    $select_clause .= ", pfp.calculated_points as fantasy_points";
    // Get the scoring system name for display
    if (!empty($fantasy_scoring)) {
        $scoring_system = array_filter($fantasy_scoring_systems, fn($s) => $s['id'] == $fantasy_scoring);
        $scoring_system_name = !empty($scoring_system) ? reset($scoring_system)['name'] : '';
    }
} else {
    $select_clause .= ", NULL as fantasy_points";
}

// Execute the query
$players_query = $pdo->prepare($select_clause . $base_query . " $where_clause ORDER BY $sort_column $sort_order");
$players_query->execute($params);
$players = $players_query->fetchAll();

// Get filter options
$positions = $pdo->query("SELECT DISTINCT position FROM player_teams WHERE position IS NOT NULL ORDER BY position")->fetchAll();
$teams = $pdo->query("SELECT * FROM teams ORDER BY name")->fetchAll();

include 'header.php';
?>

<div class="container">
    <h1>All Players</h1>
    
    <!-- Search and Filters -->
    <div class="search-filters">
        <form method="GET" action="" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap; width: 100%;">
            <div class="search-box">
                <label for="search">Search Players</label>
                <input type="search" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by player name...">
            </div>
            
            <div class="filter-group">
                <label for="stats">Stats Display</label>
                <select id="stats" name="stats">
                    <option value="">No Stats</option>
                    <option value="projected" <?php echo $stats_filter === 'projected' ? 'selected' : ''; ?>>
                        Amyo Projections<?php echo !$current_season ? ' (No Season)' : ''; ?>
                    </option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="fantasy_scoring">Fantasy Points</label>
                <select id="fantasy_scoring" name="fantasy_scoring">
                    <option value="">No Fantasy Points</option>
                    <?php foreach ($fantasy_scoring_systems as $system): ?>
                        <option value="<?php echo $system['id']; ?>" <?php echo $fantasy_scoring == $system['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($system['name']); ?><?php echo !$current_season ? ' (No Season)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                            <?php echo htmlspecialchars($team['abbreviation'] . ' - ' . $team['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="fantasy_scoring">Fantasy Scoring</label>
                <select id="fantasy_scoring" name="fantasy_scoring">
                    <option value="">All Scoring</option>
                    <?php foreach ($fantasy_scoring_systems as $system): ?>
                        <option value="<?php echo $system['id']; ?>" <?php echo $fantasy_scoring == $system['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($system['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Hidden inputs to preserve sort -->
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
            
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="players.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <!-- Players Table -->
    <div class="card">
        <div class="card-header">
            <h2>
                Player Directory (<?php echo count($players); ?> players)
                <?php if ($stats_filter === 'projected'): ?>
                    <span style="color: var(--accent-color); font-size: 0.8em; font-weight: normal;">
                        - Showing Amyo Projections <?php echo $current_season ? 'for ' . $current_season['year'] : ''; ?>
                    </span>
                <?php endif; ?>
            </h2>
        </div>
        <div class="card-body">
            <?php if (!empty($players)): ?>
                <div class="table-responsive">
                    <table class="sortable-table">
                        <thead>
                            <tr>
                                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name', 'order' => ($sort === 'name' && $order === 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                    Player <?php if ($sort === 'name'): ?><?php echo $order === 'asc' ? '↑' : '↓'; ?><?php endif; ?>
                                </a></th>
                                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'team', 'order' => ($sort === 'team' && $order === 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                    Team <?php if ($sort === 'team'): ?><?php echo $order === 'asc' ? '↑' : '↓'; ?><?php endif; ?>
                                </a></th>
                                <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'position', 'order' => ($sort === 'position' && $order === 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                    Position <?php if ($sort === 'position'): ?><?php echo $order === 'asc' ? '↑' : '↓'; ?><?php endif; ?>
                                </a></th>
                                
                                <?php if ($stats_filter === 'projected'): ?>
                                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'passing_yards', 'order' => ($sort === 'passing_yards' && $order === 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                        Pass Yds <?php if ($sort === 'passing_yards'): ?><?php echo $order === 'asc' ? '↑' : '↓'; ?><?php endif; ?>
                                    </a></th>
                                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'rushing_yards', 'order' => ($sort === 'rushing_yards' && $order === 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                        Rush Yds <?php if ($sort === 'rushing_yards'): ?><?php echo $order === 'asc' ? '↑' : '↓'; ?><?php endif; ?>
                                    </a></th>
                                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'receiving_yards', 'order' => ($sort === 'receiving_yards' && $order === 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                        Rec Yds <?php if ($sort === 'receiving_yards'): ?><?php echo $order === 'asc' ? '↑' : '↓'; ?><?php endif; ?>
                                    </a></th>
                                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'total_tds', 'order' => ($sort === 'total_tds' && $order === 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                        Total TDs <?php if ($sort === 'total_tds'): ?><?php echo $order === 'asc' ? '↑' : '↓'; ?><?php endif; ?>
                                    </a></th>
                                    <th>Tackles</th>
                                    <th>Sacks</th>
                                    <th>Def INTs</th>
                                <?php endif; ?>
                                
                                <?php if (!empty($fantasy_scoring) || $sort === 'fantasy_points'): ?>
                                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'fantasy_points', 'order' => ($sort === 'fantasy_points' && $order === 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                        Fantasy Points <?php if ($sort === 'fantasy_points'): ?><?php echo $order === 'asc' ? '↑' : '↓'; ?><?php endif; ?>
                                    </a></th>
                                <?php endif; ?>
                                
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player): ?>
                            <tr>
                                <td class="player-cell">
                                    <a href="player.php?id=<?php echo $player['id']; ?>">
                                        <?php echo htmlspecialchars($player['full_name']); ?>
                                    </a>
                                </td>
                                <td class="team-cell">
                                    <?php echo htmlspecialchars($player['team_abbr'] ?? 'FA'); ?>
                                </td>
                                <td>
                                    <span class="position-badge position-<?php echo strtolower($player['position'] ?? 'na'); ?>">
                                        <?php echo htmlspecialchars($player['position'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                
                                <?php if ($stats_filter === 'projected'): ?>
                                    <td class="stats-cell">
                                        <?php echo $player['passing_yards'] ? number_format($player['passing_yards'], 1) : '-'; ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php echo $player['rushing_yards'] ? number_format($player['rushing_yards'], 1) : '-'; ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php echo $player['receiving_yards'] ? number_format($player['receiving_yards'], 1) : '-'; ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php 
                                        $total_tds = ($player['passing_tds'] ?? 0) + ($player['rushing_tds'] ?? 0) + ($player['receiving_tds'] ?? 0);
                                        echo $total_tds > 0 ? $total_tds : '-';
                                        ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php echo $player['tackles'] ? number_format($player['tackles']) : '-'; ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php echo $player['sacks'] ? number_format($player['sacks'], 1) : '-'; ?>
                                    </td>
                                    <td class="stats-cell">
                                        <?php echo $player['defensive_interceptions'] ? number_format($player['defensive_interceptions']) : '-'; ?>
                                    </td>
                                <?php endif; ?>
                                
                                <?php if (!empty($fantasy_scoring) || $sort === 'fantasy_points'): ?>
                                    <td class="stats-cell">
                                        <?php echo $player['fantasy_points'] !== null ? number_format($player['fantasy_points'], 2) : '-'; ?>
                                    </td>
                                <?php endif; ?>
                                
                                <td>
                                    <a href="player.php?id=<?php echo $player['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No players found matching your criteria.</p>
                <a href="players.php" class="btn btn-primary">View All Players</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.sort-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.sort-link:hover {
    color: var(--accent-color);
    text-decoration: underline;
}

.sortable-table th {
    background-color: #f8f9fa;
    position: relative;
}

.sortable-table th a {
    display: block;
    padding: 12px 8px;
    width: 100%;
    height: 100%;
}

.stats-cell {
    text-align: right;
    font-weight: 500;
}

.search-filters {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.search-filters label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.search-filters input,
.search-filters select {
    width: 100%;
    min-width: 150px;
}

@media (max-width: 768px) {
    .search-filters form {
        flex-direction: column;
        align-items: stretch !important;
    }
    
    .search-filters > form > div {
        margin-bottom: 15px;
    }
    
    .table-responsive {
        font-size: 0.9em;
    }
}
</style>

<script>
// Auto-submit form when stats filter changes
document.getElementById('stats').addEventListener('change', function() {
    this.form.submit();
});

// Add loading state to table when sorting
document.addEventListener('DOMContentLoaded', function() {
    const sortLinks = document.querySelectorAll('.sort-link');
    sortLinks.forEach(link => {
        link.addEventListener('click', function() {
            const table = document.querySelector('.sortable-table');
            table.style.opacity = '0.6';
            const loadingText = document.createElement('div');
            loadingText.textContent = 'Sorting...';
            loadingText.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 10px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);';
            table.parentElement.style.position = 'relative';
            table.parentElement.appendChild(loadingText);
        });
    });
});
</script>

<?php include 'footer.php'; ?>
