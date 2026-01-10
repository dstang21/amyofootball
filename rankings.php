<?php
require_once 'config.php';

// Restrict access to admin users only
if (!isAdmin()) {
    redirect('index.php');
}

$page_title = 'Rankings';

// Get current season
$current_season_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC LIMIT 1");
$current_season = $current_season_query->fetch();

// Get available sources for the dropdown
$sources_query = $pdo->query("SELECT * FROM sources ORDER BY name");
$sources = $sources_query->fetchAll();

// Handle filters
$position_filter = $_GET['position'] ?? 'Offense'; // Default to all Offense
$team_filter = $_GET['team'] ?? '';
$source_filter = $_GET['source_id'] ?? '2'; // Default to source_id 2
$search = $_GET['search'] ?? '';
$show_all_players = isset($_GET['show_all']) ? 1 : 0; // Default to showing only undrafted players

// Define position groups
$offense_positions = ['QB', 'RB', 'WR', 'TE'];
$defense_positions = ['DL', 'LB', 'DB'];
$flex_positions = ['RB', 'WR', 'TE'];

// Get current position group based on filter
if ($position_filter === 'Defense') {
    $current_positions = $defense_positions;
} elseif ($position_filter === 'FLEX') {
    $current_positions = $flex_positions;
} elseif (in_array($position_filter, ['QB', 'RB', 'WR', 'TE'])) {
    $current_positions = [$position_filter]; // Single position
} elseif (in_array($position_filter, ['DL', 'LB', 'DB'])) {
    $current_positions = [$position_filter]; // Single defense position
} else {
    // Default to all offense positions
    $current_positions = $offense_positions;
}

// Get sources that actually have ranking data for the current season and position group
// Filter sources based on offense vs defense players
$is_defense_filter = in_array($position_filter, ['Defense', 'DL', 'LB', 'DB']);

if ($is_defense_filter) {
    // For defensive players, only show Yahoo or sources with "Defense" in the name
    $sources_with_data_query = $pdo->prepare("
        SELECT DISTINCT s.id, s.name 
        FROM sources s 
        JOIN draft_positions dp ON s.id = dp.source_id 
        JOIN players p ON dp.player_id = p.id
        LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = dp.season_id
        WHERE dp.season_id = ? AND pt.position IN (" . implode(',', array_fill(0, count($current_positions), '?')) . ")
        AND (s.name LIKE '%Yahoo%' OR s.name LIKE '%Defense%' OR s.name LIKE '%Def%')
        ORDER BY s.name
    ");
} else {
    // For offensive players, exclude sources with "Defense" in the name
    $sources_with_data_query = $pdo->prepare("
        SELECT DISTINCT s.id, s.name 
        FROM sources s 
        JOIN draft_positions dp ON s.id = dp.source_id 
        JOIN players p ON dp.player_id = p.id
        LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = dp.season_id
        WHERE dp.season_id = ? AND pt.position IN (" . implode(',', array_fill(0, count($current_positions), '?')) . ")
        AND s.name NOT LIKE '%Defense%' AND s.name NOT LIKE '%Def%'
        ORDER BY s.name
    ");
}

$sources_with_data_query->execute(array_merge([$current_season['id'] ?? 1], $current_positions));
$sources_with_data = $sources_with_data_query->fetchAll();

// Build query
$where_conditions = [];
$params = [$current_season['id'] ?? 1];

// Always filter by position group (offense or defense)
$where_conditions[] = "pt.position IN (" . implode(',', array_fill(0, count($current_positions), '?')) . ")";
$params = array_merge($params, $current_positions);

if (!empty($team_filter)) {
    $where_conditions[] = "t.id = ?";
    $params[] = $team_filter;
}

if (!empty($search)) {
    $where_conditions[] = "p.full_name LIKE ?";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "AND " . implode(" AND ", $where_conditions) : "";

// Get all players that have rankings, with their basic info and fantasy points
$players_query = $pdo->prepare("
    SELECT DISTINCT p.*, t.name as team_name, t.abbreviation as team_abbr, 
           pt.position, ps_amyo.passing_yards, ps_amyo.rushing_yards, ps_amyo.receiving_yards,
           ps_amyo.passing_tds, ps_amyo.rushing_tds, ps_amyo.receiving_tds,
           pfp_amyo.calculated_points as amyo_points,
           pfp_sleeper.calculated_points as sleeper_points
    FROM players p
    JOIN draft_positions dp ON p.id = dp.player_id
    LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = dp.season_id
    LEFT JOIN teams t ON pt.team_id = t.id
    LEFT JOIN projected_stats ps_amyo ON p.id = ps_amyo.player_id AND ps_amyo.season_id = dp.season_id AND ps_amyo.source_id = 1
    LEFT JOIN projected_fantasy_points pfp_amyo ON ps_amyo.id = pfp_amyo.projected_stats_id AND pfp_amyo.scoring_setting_id = 5
    LEFT JOIN projected_stats ps_sleeper ON p.id = ps_sleeper.player_id AND ps_sleeper.season_id = dp.season_id AND ps_sleeper.source_id = 2
    LEFT JOIN projected_fantasy_points pfp_sleeper ON ps_sleeper.id = pfp_sleeper.projected_stats_id AND pfp_sleeper.scoring_setting_id = 1
    WHERE dp.season_id = ?
");

// Apply filters to WHERE clause - only get players from the current position group
$where_conditions = [];
$params = [$current_season['id'] ?? 1];

// Always filter by position group
$where_conditions[] = "pt.position IN (" . implode(',', array_fill(0, count($current_positions), '?')) . ")";
$params = array_merge($params, $current_positions);

if (!empty($team_filter)) {
    $where_conditions[] = "t.id = ?";
    $params[] = $team_filter;
}

if (!empty($search)) {
    $where_conditions[] = "p.full_name LIKE ?";
    $params[] = "%$search%";
}

// Add drafted filter - by default only show undrafted players (drafted=0)
if ($show_all_players) {
    // Show all players (drafted and undrafted)
    // No additional filter needed
} else {
    // Only show undrafted players
    $where_conditions[] = "p.drafted = 0";
}

if (!empty($where_conditions)) {
    $players_query = $pdo->prepare(str_replace("WHERE dp.season_id = ?", "WHERE dp.season_id = ? AND " . implode(" AND ", $where_conditions), $players_query->queryString));
}

$players_query->execute($params);
$players = $players_query->fetchAll();

// Get all rankings for these players - only from sources that have data for this position group
$all_rankings = [];
if (!empty($players) && !empty($sources_with_data)) {
    $player_ids = array_column($players, 'id');
    $source_ids = array_column($sources_with_data, 'id');
    $placeholders_players = implode(',', array_fill(0, count($player_ids), '?'));
    $placeholders_sources = implode(',', array_fill(0, count($source_ids), '?'));
    
    $rankings_query = $pdo->prepare("
        SELECT dp.player_id, dp.ranking, s.name as source_name, s.id as source_id
        FROM draft_positions dp
        JOIN sources s ON dp.source_id = s.id
        WHERE dp.player_id IN ($placeholders_players) 
        AND dp.season_id = ? 
        AND s.id IN ($placeholders_sources)
        ORDER BY s.name
    ");
    
    $params_rankings = array_merge($player_ids, [$current_season['id'] ?? 1], $source_ids);
    $rankings_query->execute($params_rankings);
    $rankings_result = $rankings_query->fetchAll();
    
    // Group rankings by player
    foreach ($rankings_result as $rank) {
        $all_rankings[$rank['player_id']][$rank['source_id']] = [
            'ranking' => $rank['ranking'],
            'source_name' => $rank['source_name']
        ];
    }
}

// Sort players by the first available source ranking
$sort_source_id = !empty($sources_with_data) ? $sources_with_data[0]['id'] : 1;
// Override with selected source if it exists in sources_with_data
foreach ($sources_with_data as $source) {
    if ($source['id'] == $source_filter) {
        $sort_source_id = $source_filter;
        break;
    }
}

usort($players, function($a, $b) use ($all_rankings, $sort_source_id) {
    $rank_a = $all_rankings[$a['id']][$sort_source_id]['ranking'] ?? 999;
    $rank_b = $all_rankings[$b['id']][$sort_source_id]['ranking'] ?? 999;
    return $rank_a <=> $rank_b;
});

$rankings = $players; // For compatibility with existing code

// Get filter options
$teams = $pdo->query("SELECT * FROM teams ORDER BY name")->fetchAll();

// Define position filter options (restored individual positions, grouped by offense/defense)
$position_options = [
    ['value' => 'Offense', 'label' => 'All Offense (QB, RB, WR, TE)'],
    ['value' => 'QB', 'label' => 'QB'],
    ['value' => 'RB', 'label' => 'RB'],
    ['value' => 'WR', 'label' => 'WR'],
    ['value' => 'TE', 'label' => 'TE'],
    ['value' => 'FLEX', 'label' => 'FLEX (RB/WR/TE)'],
    ['value' => 'Defense', 'label' => 'All Defense (DL, LB, DB)'],
    ['value' => 'DL', 'label' => 'DL'],
    ['value' => 'LB', 'label' => 'LB'],
    ['value' => 'DB', 'label' => 'DB']
];

// Handle AJAX draft/undraft request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'draft_player') {
    header('Content-Type: application/json');
    
    $player_id = $_POST['player_id'] ?? 0;
    $draft_status = $_POST['draft_status'] ?? 1; // Default to draft (1)
    
    if (!$player_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid player ID']);
        exit;
    }
    
    // Validate draft_status (should be 0 or 1)
    $draft_status = (int)$draft_status;
    if ($draft_status !== 0 && $draft_status !== 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid draft status']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE players SET drafted = ? WHERE id = ?");
        $success = $stmt->execute([$draft_status, $player_id]);
        
        if ($success && $stmt->rowCount() > 0) {
            $action_message = $draft_status ? 'drafted' : 'undrafted';
            echo json_encode(['success' => true, 'message' => "Player {$action_message} successfully"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Player not found or status unchanged']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit;
}

include 'header.php';
?>

<div class="container-fluid px-2">
    <h1 class="mb-3">Player Rankings</h1>
    
    <!-- Search and Filters -->
    <div class="search-filters mb-3">
        <form method="GET" action="" id="filterForm" style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap; width: 100%;">
            <div class="search-box" style="min-width: 200px;">
                <label for="search" style="font-size: 0.8rem;">Search Players</label>
                <input type="search" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by player name..." style="padding: 4px 8px; font-size: 0.9rem;">
            </div>
            
            <div class="filter-group">
                <label style="font-size: 0.8rem; display: block;">Show Players</label>
                <label style="font-size: 0.9rem; font-weight: normal; cursor: pointer;">
                    <input type="checkbox" id="show_all" name="show_all" value="1" <?php echo $show_all_players ? 'checked' : ''; ?> style="margin-right: 5px;">
                    Show All (including drafted)
                </label>
            </div>
            
            <div class="filter-group">
                <label for="position" style="font-size: 0.8rem;">Position</label>
                <select id="position" name="position" style="padding: 4px 8px; font-size: 0.9rem;">
                    <?php foreach ($position_options as $pos): ?>
                        <option value="<?php echo $pos['value']; ?>" <?php echo $position_filter == $pos['value'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pos['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="team" style="font-size: 0.8rem;">Team</label>
                <select id="team" name="team" style="padding: 4px 8px; font-size: 0.9rem;">
                    <option value="">All Teams</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?php echo $team['id']; ?>" <?php echo $team_filter == $team['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($team['abbreviation'] . ' - ' . $team['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <a href="rankings.php" class="btn btn-secondary" style="padding: 4px 12px; font-size: 0.9rem;">Clear</a>
        </form>
    </div>

    <!-- Rankings Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0"><?php echo $position_filter; ?> Rankings Comparison (<?php echo count($rankings); ?> players)</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($rankings)): ?>
                <div class="table-responsive">
                    <table class="rankings-spreadsheet">
                        <thead>
                            <tr>
                                <th class="player-info action-col">Action</th>
                                <th class="player-info sortable" data-sort="rank">
                                    # <span class="sort-arrow"></span>
                                </th>
                                <th class="player-info sortable" data-sort="player">
                                    Player <span class="sort-arrow"></span>
                                </th>
                                <th class="player-info sortable" data-sort="team">
                                    Team <span class="sort-arrow"></span>
                                </th>
                                <th class="player-info sortable" data-sort="position">
                                    Pos <span class="sort-arrow"></span>
                                </th>
                                <th class="fantasy-points sortable" data-sort="amyo-fp">
                                    Amyo FP <span class="sort-arrow"></span>
                                </th>
                                <?php
                                // Get sources with data for column headers
                                foreach ($sources_with_data as $source): ?>
                                    <th class="ranking-col sortable" data-sort="source-<?php echo $source['id']; ?>">
                                        <?php echo htmlspecialchars($source['name']); ?> <span class="sort-arrow"></span>
                                    </th>
                                <?php endforeach; ?>
                                <th class="player-info stats-col">Key Stats</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rankings as $index => $player): ?>
                            <tr data-rank="<?php echo $index + 1; ?>"
                                data-player="<?php echo htmlspecialchars($player['full_name']); ?>"
                                data-team="<?php echo htmlspecialchars($player['team_abbr'] ?? 'FA'); ?>"
                                data-position="<?php echo htmlspecialchars($player['position'] ?? 'ZZ'); ?>"
                                data-amyo-fp="<?php echo $player['amyo_points'] ?? 0; ?>"
                                <?php foreach ($sources_with_data as $source): ?>
                                    data-source-<?php echo $source['id']; ?>="<?php echo isset($all_rankings[$player['id']][$source['id']]) ? $all_rankings[$player['id']][$source['id']]['ranking'] : 999; ?>"
                                <?php endforeach; ?>
                            >
                                <td class="action-col">
                                    <?php if ($player['drafted']): ?>
                                        <button class="btn btn-sm btn-warning undraft-btn" 
                                                data-player-id="<?php echo $player['id']; ?>"
                                                data-player-name="<?php echo htmlspecialchars($player['full_name']); ?>"
                                                style="font-size: 0.8rem; padding: 2px 8px;">
                                            Undraft
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-primary draft-btn" 
                                                data-player-id="<?php echo $player['id']; ?>"
                                                data-player-name="<?php echo htmlspecialchars($player['full_name']); ?>"
                                                style="font-size: 0.8rem; padding: 2px 8px;">
                                            Draft
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="rank-number">
                                    <?php echo $index + 1; ?>
                                </td>
                                <td class="player-name">
                                    <a href="player.php?id=<?php echo $player['id']; ?>">
                                        <?php echo htmlspecialchars($player['full_name']); ?>
                                    </a>
                                </td>
                                <td class="team-abbr">
                                    <?php echo htmlspecialchars($player['team_abbr'] ?? 'FA'); ?>
                                </td>
                                <td class="position">
                                    <span class="position-badge position-<?php echo strtolower($player['position'] ?? 'na'); ?>">
                                        <?php echo htmlspecialchars($player['position'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td class="fantasy-points">
                                    <?php
                                    // Display Amyo fantasy points
                                    if ($player['amyo_points']) {
                                        echo '<strong>' . number_format($player['amyo_points'], 1) . '</strong>';
                                    } else {
                                        // Try fallback query
                                        $amyo_points_query = $pdo->prepare("
                                            SELECT pfp.calculated_points
                                            FROM projected_stats ps
                                            JOIN projected_fantasy_points pfp ON ps.id = pfp.projected_stats_id
                                            WHERE ps.player_id = ? AND ps.season_id = ? AND ps.source_id = 1 AND pfp.scoring_setting_id = 5
                                            LIMIT 1
                                        ");
                                        $amyo_points_query->execute([$player['id'], $current_season['id'] ?? 1]);
                                        $amyo_result = $amyo_points_query->fetch();
                                        
                                        if ($amyo_result && $amyo_result['calculated_points']) {
                                            echo '<strong>' . number_format($amyo_result['calculated_points'], 1) . '</strong>';
                                        } else {
                                            echo '<span class="no-data">-</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <?php
                                // Display ranking for each source that has data in separate columns
                                foreach ($sources_with_data as $source): ?>
                                    <td class="ranking-col">
                                        <?php
                                        if (isset($all_rankings[$player['id']][$source['id']])) {
                                            $ranking = $all_rankings[$player['id']][$source['id']]['ranking'];
                                            // Highlight if this is the selected source filter
                                            if ($source_filter == $source['id']) {
                                                echo '<strong>' . number_format($ranking, 1) . '</strong>';
                                            } else {
                                                echo '<strong>' . number_format($ranking, 1) . '</strong>';
                                            }
                                        } else {
                                            echo '<span class="no-data">-</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="key-stats">
                                    <?php
                                    // Display key stats based on position in a compact format
                                    $stats = [];
                                    if ($player['position'] == 'QB') {
                                        if ($player['passing_yards']) $stats[] = number_format($player['passing_yards']) . ' pass';
                                        if ($player['rushing_yards']) $stats[] = number_format($player['rushing_yards']) . ' rush';
                                        if ($player['passing_tds']) $stats[] = $player['passing_tds'] . ' TD';
                                    } elseif ($player['position'] == 'RB') {
                                        if ($player['rushing_yards']) $stats[] = number_format($player['rushing_yards']) . ' rush';
                                        if ($player['receiving_yards']) $stats[] = number_format($player['receiving_yards']) . ' rec';
                                        $total_tds = ($player['rushing_tds'] ?? 0) + ($player['receiving_tds'] ?? 0);
                                        if ($total_tds > 0) $stats[] = $total_tds . ' TD';
                                    } elseif (in_array($player['position'], ['WR', 'TE'])) {
                                        if ($player['receiving_yards']) $stats[] = number_format($player['receiving_yards']) . ' rec';
                                        if ($player['receiving_tds']) $stats[] = $player['receiving_tds'] . ' TD';
                                    }
                                    
                                    echo !empty($stats) ? '<small>' . implode(', ', array_slice($stats, 0, 2)) . '</small>' : '<span class="no-data">No stats</span>';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif (empty($sources_with_data)): ?>
                <p>No ranking sources have data for <?php echo htmlspecialchars($position_filter); ?> positions.</p>
                <p>Try switching to <?php echo $position_filter === 'Offense' ? 'Defense' : 'Offense'; ?> to see available rankings.</p>
            <?php else: ?>
                <p>No rankings found matching your criteria for <?php echo htmlspecialchars($position_filter); ?>.</p>
                <a href="rankings.php?position=<?php echo htmlspecialchars($position_filter); ?>" class="btn btn-primary">Clear Filters</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    const searchInput = document.getElementById('search');
    const showAllCheckbox = document.getElementById('show_all');
    const positionSelect = document.getElementById('position');
    const teamSelect = document.getElementById('team');
    
    // Auto-submit when select options change
    showAllCheckbox.addEventListener('change', function() {
        form.submit();
    });
    
    positionSelect.addEventListener('change', function() {
        form.submit();
    });
    
    teamSelect.addEventListener('change', function() {
        form.submit();
    });
    
    // Auto-submit when search input loses focus or Enter is pressed
    searchInput.addEventListener('blur', function() {
        form.submit();
    });
    
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            form.submit();
        }
    });
    
    // Optional: Add a small delay for typing in search to avoid too many requests
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            // Only auto-submit if the field is empty (for clearing search)
            if (searchInput.value.trim() === '') {
                form.submit();
            }
        }, 500);
    });
    
    // Table sorting functionality
    const table = document.querySelector('.rankings-spreadsheet');
    const tbody = table.querySelector('tbody');
    const sortableHeaders = table.querySelectorAll('th.sortable');
    let currentSort = { column: null, direction: 'asc' };
    
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortType = this.getAttribute('data-sort');
            
            // Remove active class from all headers
            sortableHeaders.forEach(h => {
                h.classList.remove('sort-active', 'sort-asc', 'sort-desc');
            });
            
            // Determine sort direction
            if (currentSort.column === sortType) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.direction = 'asc';
            }
            currentSort.column = sortType;
            
            // Add active class and direction
            this.classList.add('sort-active', currentSort.direction === 'asc' ? 'sort-asc' : 'sort-desc');
            
            // Sort the table
            sortTable(sortType, currentSort.direction);
        });
    });
    
    function sortTable(sortType, direction) {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            let aVal = a.getAttribute('data-' + sortType);
            let bVal = b.getAttribute('data-' + sortType);
            
            // Handle different data types
            if (sortType.includes('fp') || sortType.includes('source-') || sortType === 'rank') {
                // Numeric sorting for fantasy points, rankings, and rank numbers
                aVal = parseFloat(aVal) || (sortType.includes('source-') ? 999 : 0);
                bVal = parseFloat(bVal) || (sortType.includes('source-') ? 999 : 0);
                
                if (direction === 'asc') {
                    return aVal - bVal;
                } else {
                    return bVal - aVal;
                }
            } else {
                // String sorting for player, team, position
                aVal = aVal ? aVal.toLowerCase() : '';
                bVal = bVal ? bVal.toLowerCase() : '';
                
                if (direction === 'asc') {
                    return aVal.localeCompare(bVal);
                } else {
                    return bVal.localeCompare(aVal);
                }
            }
        });
        
        // Remove all rows and re-add them in sorted order
        rows.forEach(row => tbody.removeChild(row));
        rows.forEach((row, index) => {
            // Update the rank number when sorting
            const rankCell = row.querySelector('.rank-number');
            if (rankCell) {
                rankCell.textContent = index + 1;
                row.setAttribute('data-rank', index + 1);
            }
            tbody.appendChild(row);
        });
    }
    
    // Set initial sort based on the first available source for this position group
    <?php if (!empty($sources_with_data)): ?>
    const initialSortHeader = document.querySelector('[data-sort="source-<?php echo $sources_with_data[0]['id']; ?>"]');
    if (initialSortHeader) {
        initialSortHeader.click();
    }
    <?php endif; ?>
    
    // Draft/Undraft button functionality
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('draft-btn')) {
            const playerId = e.target.getAttribute('data-player-id');
            const playerName = e.target.getAttribute('data-player-name');
            
            if (confirm(`Are you sure you want to draft ${playerName}?`)) {
                draftPlayer(playerId, e.target, 1); // 1 = draft
            }
        } else if (e.target && e.target.classList.contains('undraft-btn')) {
            const playerId = e.target.getAttribute('data-player-id');
            const playerName = e.target.getAttribute('data-player-name');
            
            if (confirm(`Are you sure you want to undraft ${playerName}?`)) {
                draftPlayer(playerId, e.target, 0); // 0 = undraft
            }
        }
    });
    
    function draftPlayer(playerId, buttonElement, draftStatus) {
        // Disable the button to prevent double-clicking
        buttonElement.disabled = true;
        const originalText = buttonElement.textContent;
        buttonElement.textContent = draftStatus ? 'Drafting...' : 'Undrafting...';
        
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=draft_player&player_id=${playerId}&draft_status=${draftStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Toggle the button type and class
                if (draftStatus) {
                    // Player was drafted - change to undraft button
                    buttonElement.className = 'btn btn-sm btn-warning undraft-btn';
                    buttonElement.textContent = 'Undraft';
                } else {
                    // Player was undrafted - change to draft button
                    buttonElement.className = 'btn btn-sm btn-primary draft-btn';
                    buttonElement.textContent = 'Draft';
                }
                buttonElement.disabled = false;
                
                // If we're not showing all players and player was drafted, remove this row after a brief delay
                if (draftStatus && !document.getElementById('show_all').checked) {
                    setTimeout(() => {
                        const row = buttonElement.closest('tr');
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0.5';
                            setTimeout(() => {
                                row.remove();
                                // Update rank numbers for remaining rows
                                const tbody = document.querySelector('.rankings-spreadsheet tbody');
                                const rows = tbody.querySelectorAll('tr');
                                rows.forEach((row, index) => {
                                    const rankCell = row.querySelector('.rank-number');
                                    if (rankCell) {
                                        rankCell.textContent = index + 1;
                                        row.setAttribute('data-rank', index + 1);
                                    }
                                });
                            }, 300);
                        }
                    }, 1000);
                }
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
                // Re-enable the button
                buttonElement.disabled = false;
                buttonElement.textContent = originalText;
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
            // Re-enable the button
            buttonElement.disabled = false;
            buttonElement.textContent = originalText;
        });
    }
});
</script>

<?php include 'footer.php'; ?>

<!-- Debug: Check if we have any projected fantasy points data -->
<?php
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
$debug_query->execute([$current_season['id'] ?? 1]);
$debug_result = $debug_query->fetch();

// Only show debug info if user is admin (you can remove this later)
if (isAdmin()) {
    echo "<!-- DEBUG: Stats={$debug_result['total_stats']}, Fantasy Points={$debug_result['total_fantasy_points']}, Amyo Stats={$debug_result['amyo_stats']}, Sleeper Stats={$debug_result['sleeper_stats']}, Amyo FP={$debug_result['amyo_fantasy_points']}, Sleeper FP={$debug_result['sleeper_fantasy_points']} -->\n";
}
