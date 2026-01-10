<?php
$page_title = 'Fantasy Leagues';
require_once 'fantasy-header.php';

// Include the SleeperController with proper path handling
if (file_exists('admin/SleeperController.php')) {
    require_once 'admin/SleeperController.php';
} else {
    require_once '../admin/SleeperController.php';
}

// Initialize controller
$controller = new SleeperController();

// Get all leagues
$leagues = $controller->getLeagues();
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-trophy"></i> Fantasy Leagues</h1>
        <p>Browse all active fantasy football leagues and their details.</p>
    </div>

    <?php if (empty($leagues)): ?>
        <div class="recent-activity">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-trophy" style="font-size: 4em; color: #ddd; margin-bottom: 20px;"></i>
                <h3 style="color: #666;">No Leagues Available</h3>
                <p style="color: #888;">There are no fantasy leagues set up yet. Check back later!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="fantasy-table">
            <table>
                <thead>
                    <tr>
                        <th>League Name</th>
                        <th>Season</th>
                        <th>Teams</th>
                        <th>Status</th>
                        <th>Sport</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leagues as $league): ?>
                        <tr>
                            <td>
                                <a href="fantasy-league-detail.php?league_id=<?php echo urlencode($league['league_id']); ?>" 
                                   class="league-name-link">
                                    <strong><?php echo htmlspecialchars($league['name']); ?></strong>
                                </a>
                            </td>
                            <td><?php echo $league['season']; ?></td>
                            <td>
                                <span class="stat-value" style="font-size: 1.2em; color: #2a5298;">
                                    <?php echo $league['total_rosters']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($league['status']); ?>">
                                    <?php echo ucfirst($league['status']); ?>
                                </span>
                            </td>
                            <td><?php echo strtoupper($league['sport'] ?: 'NFL'); ?></td>
                            <td>
                                <a href="fantasy-league-detail.php?league_id=<?php echo urlencode($league['league_id']); ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- League Statistics -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-bar"></i> League Statistics</h3>
                <div class="stats-grid">
                    <?php
                    $totalTeams = array_sum(array_column($leagues, 'total_rosters'));
                    $avgTeamsPerLeague = $totalTeams > 0 ? round($totalTeams / count($leagues), 1) : 0;
                    $currentSeasonLeagues = count(array_filter($leagues, function($l) { return $l['season'] == date('Y'); }));
                    ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $totalTeams; ?></div>
                        <div class="stat-label">Total Teams</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $avgTeamsPerLeague; ?></div>
                        <div class="stat-label">Avg Teams/League</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $currentSeasonLeagues; ?></div>
                        <div class="stat-label"><?php echo date('Y'); ?> Season</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <h3><i class="fas fa-calendar"></i> Season Breakdown</h3>
                <?php
                $seasonCounts = [];
                foreach ($leagues as $league) {
                    $season = $league['season'];
                    $seasonCounts[$season] = ($seasonCounts[$season] ?? 0) + 1;
                }
                krsort($seasonCounts);
                ?>
                <?php foreach ($seasonCounts as $season => $count): ?>
                    <div class="season-item">
                        <span class="season-year"><?php echo $season; ?> Season</span>
                        <span class="season-count"><?php echo $count; ?> league<?php echo $count > 1 ? 's' : ''; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 15px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 2em;
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

.season-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.season-item:last-child {
    border-bottom: none;
}

.season-year {
    font-weight: 600;
    color: #333;
}

.season-count {
    color: #2a5298;
    font-weight: 500;
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
    border-radius: 6px;
    font-size: 0.9em;
    font-weight: 500;
    transition: background 0.3s ease;
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

.league-name-link {
    color: #2a5298;
    text-decoration: none;
    transition: color 0.3s ease;
}

.league-name-link:hover {
    color: #ff6b35;
    text-decoration: underline;
}

.league-name-link strong {
    font-weight: 600;
}
</style>

<?php require_once 'footer.php'; ?>
