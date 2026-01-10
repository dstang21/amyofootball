<?php
$page_title = 'Fantasy Rankings';
require_once 'fantasy-header.php';

// Include the SleeperController with proper path handling
if (file_exists('admin/SleeperController.php')) {
    require_once 'admin/SleeperController.php';
} else {
    require_once '../admin/SleeperController.php';
}

// Initialize controller
$controller = new SleeperController();

// Get filters from URL
$position = $_GET['position'] ?? '';
$team = $_GET['team'] ?? '';
$search = $_GET['search'] ?? '';

// Get players with filters
$players = $controller->getPlayers($search, $position, $team, 50, 0);
$teams = $controller->getTeams();
$positions = $controller->getPositions();
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-list-ol"></i> Fantasy Rankings</h1>
        <p>Comprehensive player rankings and statistics for fantasy football.</p>
    </div>

    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <div class="filter-grid">
                <div class="filter-item">
                    <label for="search">Search Players</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Player name...">
                </div>
                
                <div class="filter-item">
                    <label for="position">Position</label>
                    <select id="position" name="position">
                        <option value="">All Positions</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?php echo $pos; ?>" <?php echo $position === $pos ? 'selected' : ''; ?>>
                                <?php echo $pos; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label for="team">Team</label>
                    <select id="team" name="team">
                        <option value="">All Teams</option>
                        <?php foreach ($teams as $teamCode => $teamName): ?>
                            <option value="<?php echo $teamCode; ?>" <?php echo $team === $teamCode ? 'selected' : ''; ?>>
                                <?php echo $teamCode; ?> - <?php echo $teamName; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="fantasy-rankings.php" class="btn" style="margin-left: 10px;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($players)): ?>
        <div class="recent-activity">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-search" style="font-size: 4em; color: #ddd; margin-bottom: 20px;"></i>
                <h3 style="color: #666;">No Players Found</h3>
                <p style="color: #888;">
                    <?php if ($search || $position || $team): ?>
                        No players match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        No player data is available yet. Check back later!
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php else: ?>
        <!-- Results Summary -->
        <div class="results-summary">
            <div class="summary-card">
                <h3>Search Results</h3>
                <p>
                    Found <strong><?php echo count($players); ?></strong> players
                    <?php if ($search): ?>
                        matching "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    <?php endif; ?>
                    <?php if ($position): ?>
                        at <strong><?php echo $position; ?></strong> position
                    <?php endif; ?>
                    <?php if ($team): ?>
                        on <strong><?php echo $team; ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Player Rankings Table -->
        <div class="fantasy-table">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Player</th>
                        <th>Position</th>
                        <th>Team</th>
                        <th>Age</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $index => $player): ?>
                        <tr>
                            <td>
                                <div class="rank-badge">
                                    <?php echo $player['search_rank'] ?: ($index + 1); ?>
                                </div>
                            </td>
                            <td>
                                <div class="player-cell">
                                    <div class="player-name">
                                        <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>
                                    </div>
                                    <?php if ($player['college']): ?>
                                        <div class="player-college">
                                            <?php echo htmlspecialchars($player['college']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="position-badge position-<?php echo strtolower($player['position'] ?: 'unknown'); ?>">
                                    <?php echo $player['position'] ?: 'N/A'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($player['team']): ?>
                                    <strong><?php echo htmlspecialchars($player['team']); ?></strong>
                                    <?php if ($player['number']): ?>
                                        <br><span style="color: #666;">#<?php echo $player['number']; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #666;">Free Agent</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $player['age'] ?: 'N/A'; ?></td>
                            <td><?php echo $player['years_exp'] ?: 'Rookie'; ?></td>
                            <td>
                                <?php if ($player['status']): ?>
                                    <span class="status-badge status-<?php echo strtolower($player['status']); ?>">
                                        <?php echo ucfirst($player['status']); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($player['injury_status']): ?>
                                    <br><span class="injury-status">
                                        <i class="fas fa-medical"></i> <?php echo ucfirst($player['injury_status']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm" onclick="showPlayerDetails('<?php echo $player['player_id']; ?>')">
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Position Distribution -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-pie"></i> Position Distribution</h3>
                <?php
                $positionCounts = [];
                foreach ($players as $player) {
                    $pos = $player['position'] ?: 'Unknown';
                    $positionCounts[$pos] = ($positionCounts[$pos] ?? 0) + 1;
                }
                arsort($positionCounts);
                ?>
                <div class="position-stats">
                    <?php foreach ($positionCounts as $pos => $count): ?>
                        <div class="position-stat-item">
                            <span class="position-badge position-<?php echo strtolower($pos); ?>">
                                <?php echo $pos; ?>
                            </span>
                            <span class="position-count"><?php echo $count; ?> players</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dashboard-card">
                <h3><i class="fas fa-chart-bar"></i> Player Statistics</h3>
                <?php
                $avgAge = 0;
                $ageCount = 0;
                $rookieCount = 0;
                $veteranCount = 0;
                $activeCount = 0;

                foreach ($players as $player) {
                    if ($player['age']) {
                        $avgAge += $player['age'];
                        $ageCount++;
                    }
                    if (!$player['years_exp'] || $player['years_exp'] == 0) {
                        $rookieCount++;
                    } else {
                        $veteranCount++;
                    }
                    if ($player['status'] === 'Active') {
                        $activeCount++;
                    }
                }
                $avgAge = $ageCount > 0 ? round($avgAge / $ageCount, 1) : 0;
                ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $avgAge; ?></div>
                        <div class="stat-label">Avg Age</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $rookieCount; ?></div>
                        <div class="stat-label">Rookies</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $veteranCount; ?></div>
                        <div class="stat-label">Veterans</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $activeCount; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.filter-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
}

.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 20px;
    align-items: end;
}

.filter-item label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.filter-item input,
.filter-item select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.results-summary {
    margin-bottom: 30px;
}

.summary-card {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 20px;
    border-radius: 8px;
}

.summary-card h3 {
    margin: 0 0 10px 0;
    color: #1976d2;
}

.summary-card p {
    margin: 0;
    color: #555;
}

.rank-badge {
    background: #2a5298;
    color: white;
    padding: 8px 12px;
    border-radius: 20px;
    font-weight: bold;
    text-align: center;
    min-width: 40px;
}

.player-cell {
    text-align: left;
}

.player-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 3px;
}

.player-college {
    font-size: 0.85em;
    color: #666;
}

.position-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
    text-transform: uppercase;
}

.position-qb { background: #e3f2fd; color: #1976d2; }
.position-rb { background: #e8f5e8; color: #388e3c; }
.position-wr { background: #fff3e0; color: #f57c00; }
.position-te { background: #fce4ec; color: #c2185b; }
.position-k { background: #f3e5f5; color: #7b1fa2; }
.position-def { background: #e0f2f1; color: #00695c; }
.position-unknown { background: #f5f5f5; color: #666; }

.injury-status {
    color: #f44336;
    font-size: 0.8em;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 0.8em;
}

.position-stats {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.position-stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.position-count {
    color: #666;
    font-size: 0.9em;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 1.8em;
    font-weight: bold;
    color: #2a5298;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.8em;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.page-header h1 {
    color: #1e3c72;
    margin: 0 0 10px 0;
    font-size: 2.5em;
}

.page-header p {
    color: #666;
    font-size: 1.1em;
    margin: 0;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.btn {
    display: inline-block;
    padding: 8px 16px;
    background: #2a5298;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 6px;
    font-size: 0.9em;
    font-weight: 500;
    transition: background 0.3s ease;
    cursor: pointer;
}

.btn:hover {
    background: #1e3c72;
}

.btn-primary {
    background: #ff6b35;
}

.btn-primary:hover {
    background: #e55a2d;
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
function showPlayerDetails(playerId) {
    // This could open a modal or redirect to a detailed player page
    alert('Player details for ID: ' + playerId + '\n\nThis feature could be enhanced to show detailed player statistics, injury history, and performance metrics.');
}
</script>

<?php require_once 'footer.php'; ?>
