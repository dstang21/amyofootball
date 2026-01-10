<?php
$page_title = 'Fantasy Football Dashboard';
require_once 'fantasy-header.php';

// Include the SleeperController with proper path handling
if (file_exists('admin/SleeperController.php')) {
    require_once 'admin/SleeperController.php';
} else {
    require_once '../admin/SleeperController.php';
}

// Initialize controller
$controller = new SleeperController();

// Get dashboard data
$totalLeagues = count($controller->getLeagues());
$recentLeagues = array_slice($controller->getLeagues(), 0, 5);

// Get top ranked players from sleeper_player_stats
$topRankedPlayers = [];
try {
    $sql = "SELECT p.*, ps.stats 
            FROM sleeper_players p 
            JOIN sleeper_player_stats ps ON p.player_id = ps.player_id 
            WHERE ps.stats IS NOT NULL 
            AND JSON_EXTRACT(ps.stats, '$.rank_ppr') IS NOT NULL
            ORDER BY CAST(JSON_EXTRACT(ps.stats, '$.rank_ppr') AS UNSIGNED) ASC 
            LIMIT 10";
    $stmt = $controller->pdo->prepare($sql);
    $stmt->execute();
    $topRankedPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback to basic player list if stats query fails
    $topRankedPlayers = array_slice($controller->getPlayers('', '', '', 10), 0, 10);
}

// Get league statistics
$leagues = $controller->getLeagues();
$leagueStats = [];
$ownerStats = [];

foreach ($leagues as $league) {
    $users = $controller->getLeagueUsers($league['league_id']);
    $leagueStats[] = [
        'name' => $league['name'],
        'users' => count($users),
        'season' => $league['season']
    ];
    
    foreach ($users as $user) {
        if (isset($ownerStats[$user['display_name']])) {
            $ownerStats[$user['display_name']]++;
        } else {
            $ownerStats[$user['display_name']] = 1;
        }
    }
}

arsort($ownerStats);
$topOwners = array_slice($ownerStats, 0, 5, true);
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-football-ball"></i> Fantasy Football Dashboard</h1>
        <p>Your complete fantasy football hub with league insights, player rankings, and owner statistics.</p>
    </div>

    <!-- Dashboard Stats -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3><i class="fas fa-trophy"></i> Total Leagues</h3>
            <div class="stat-value"><?php echo $totalLeagues; ?></div>
            <div class="stat-label">Active Leagues</div>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-users"></i> Total Owners</h3>
            <div class="stat-value"><?php echo count($ownerStats); ?></div>
            <div class="stat-label">Unique Owners</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Recent Leagues -->
        <div class="recent-activity">
            <h3><i class="fas fa-clock"></i> Recent Leagues</h3>
            <?php if (empty($recentLeagues)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">No leagues available yet.</p>
            <?php else: ?>
                <?php foreach ($recentLeagues as $league): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($league['name']); ?></div>
                            <div class="activity-meta">
                                Season <?php echo $league['season']; ?> • 
                                <?php echo $league['total_rosters']; ?> teams • 
                                <span class="status-badge status-<?php echo strtolower($league['status']); ?>">
                                    <?php echo ucfirst($league['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if ($totalLeagues > 5): ?>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="fantasy-leagues.php" class="btn btn-primary">View All Leagues</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Players -->
        <div class="player-rankings">
            <h3><i class="fas fa-medal"></i> Top Ranked Players</h3>
            <?php if (empty($topPlayers)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">No player data available yet.</p>
            <?php else: ?>
                <?php foreach ($topPlayers as $index => $player): ?>
                    <div class="player-rank-item">
                        <div class="rank-number"><?php echo $index + 1; ?></div>
                        <div class="player-info">
                            <div class="player-name">
                                <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>
                            </div>
                            <div class="player-details">
                                <?php echo htmlspecialchars($player['position']); ?> • 
                                <?php echo htmlspecialchars($player['team'] ?: 'Free Agent'); ?>
                            </div>
                        </div>
                        <div class="player-score">
                            Rank <?php echo $player['search_rank'] ?: 'N/A'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="fantasy-rankings.php" class="btn btn-primary">View All Rankings</a>
            </div>
        </div>
    </div>

    <!-- Top Owners -->
    <?php if (!empty($topOwners)): ?>
    <div class="recent-activity">
        <h3><i class="fas fa-crown"></i> Most Active Owners</h3>
        <?php foreach ($topOwners as $ownerName => $leagueCount): ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title"><?php echo htmlspecialchars($ownerName); ?></div>
                    <div class="activity-meta">
                        Participating in <?php echo $leagueCount; ?> league<?php echo $leagueCount > 1 ? 's' : ''; ?>
                    </div>
                </div>
                <div class="player-score">
                    <?php echo $leagueCount; ?> team<?php echo $leagueCount > 1 ? 's' : ''; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="text-align: center; margin-top: 15px;">
            <a href="fantasy-owners.php" class="btn btn-primary">View All Owners</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- League Distribution Chart -->
    <?php if (!empty($leagueStats)): ?>
    <div class="chart-container">
        <h3><i class="fas fa-chart-pie"></i> League Distribution</h3>
        <canvas id="leagueChart"></canvas>
    </div>
    <?php endif; ?>

    <!-- Top Ranked Players Section -->
    <div class="recent-activity">
        <h3><i class="fas fa-ranking-star"></i> Top Ranked Players</h3>
        <?php
        // Get top ranked players from sleeper_player_stats table (offensive positions only)
        $stmt = $pdo->prepare("
            SELECT DISTINCT CONCAT(p.first_name, ' ', p.last_name) as player_name, p.position, p.team, ps.stats 
            FROM sleeper_player_stats ps
            JOIN sleeper_players p ON ps.player_id = p.player_id
            WHERE ps.stats IS NOT NULL AND ps.stats != ''
            AND ps.stats LIKE '%pos_rank_ppr%'
            AND p.position IN ('QB', 'RB', 'WR', 'TE', 'K')
            ORDER BY p.position, p.player_id
            LIMIT 50
        ");
        $stmt->execute();
        $rankedPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process and sort players by rank
        $playersWithRanks = [];
        foreach ($rankedPlayers as $player) {
            $stats = json_decode($player['stats'], true);
            if (isset($stats['pos_rank_ppr']) && is_numeric($stats['pos_rank_ppr'])) {
                $player['ppr_rank'] = (int)$stats['pos_rank_ppr'];
                $player['std_rank'] = isset($stats['rank_std']) ? (int)$stats['rank_std'] : null;
                // Only include players with valid ranks (between 1 and 500)
                if ($player['ppr_rank'] > 0 && $player['ppr_rank'] <= 500) {
                    $playersWithRanks[] = $player;
                }
            }
        }
        
        // Sort by PPR rank (ascending - lower number = better rank)
        usort($playersWithRanks, function($a, $b) {
            return $a['ppr_rank'] - $b['ppr_rank'];
        });
        
        // Display top 8 (best ranked players)
        $topRanked = array_slice($playersWithRanks, 0, 8);
        ?>
        
        <?php if (!empty($topRanked)): ?>
            <?php foreach ($topRanked as $player): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-<?php 
                            echo match($player['position']) {
                                'QB' => 'user-tie',
                                'RB' => 'running',
                                'WR' => 'hand-catch',
                                'TE' => 'football-ball',
                                'K' => 'bullseye',
                                'DEF' => 'shield-alt',
                                default => 'user'
                            }; 
                        ?>"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title"><?php echo htmlspecialchars($player['player_name']); ?></div>
                        <div class="activity-meta">
                            <?php echo htmlspecialchars($player['position']); ?> • 
                            <?php echo htmlspecialchars($player['team'] ?: 'Free Agent'); ?>
                        </div>
                    </div>
                    <div class="player-score">
                        PPR #<?php echo $player['ppr_rank']; ?>
                        <?php if ($player['std_rank']): ?>
                            <br><small>STD #<?php echo $player['std_rank']; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">No ranking data available</div>
                    <div class="activity-meta">Rankings will appear after data sync</div>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 15px;">
            <a href="fantasy-rankings.php" class="btn btn-primary">View All Rankings</a>
        </div>
    </div>
</div>

<!-- Add button styles -->
<style>
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
</style>

<?php if (!empty($leagueStats)): ?>
<script>
// League Distribution Chart
const ctx = document.getElementById('leagueChart').getContext('2d');
const leagueChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($leagueStats, 'name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($leagueStats, 'users')); ?>,
            backgroundColor: [
                '#2a5298',
                '#ff6b35',
                '#1e3c72',
                '#4CAF50',
                '#FF9800',
                '#9C27B0',
                '#F44336',
                '#795548'
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed + ' owners';
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
