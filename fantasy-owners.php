<?php
$page_title = 'Fantasy Owners';
require_once 'fantasy-header.php';

// Include the SleeperController with proper path handling
if (file_exists('admin/SleeperController.php')) {
    require_once 'admin/SleeperController.php';
} else {
    require_once '../admin/SleeperController.php';
}

// Initialize controller
$controller = new SleeperController();

// Get all leagues and compile owner statistics
$leagues = $controller->getLeagues();
$allOwners = [];
$ownerStats = [];

foreach ($leagues as $league) {
    $users = $controller->getLeagueUsers($league['league_id']);
    foreach ($users as $user) {
        $key = $user['user_id'];
        
        if (!isset($allOwners[$key])) {
            $allOwners[$key] = [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'avatar' => $user['avatar'],
                'leagues' => [],
                'total_leagues' => 0,
                'is_owner_count' => 0
            ];
        }
        
        $allOwners[$key]['leagues'][] = [
            'league_name' => $league['name'],
            'league_id' => $league['league_id'],
            'season' => $league['season'],
            'is_owner' => $user['is_owner'],
            'team_name' => $user['team_name']
        ];
        
        $allOwners[$key]['total_leagues']++;
        if ($user['is_owner']) {
            $allOwners[$key]['is_owner_count']++;
        }
    }
}

// Sort owners by total leagues (most active first)
usort($allOwners, function($a, $b) {
    return $b['total_leagues'] - $a['total_leagues'];
});
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Fantasy Owners</h1>
        <p>Meet all the fantasy football enthusiasts and their league participation.</p>
    </div>

    <?php if (empty($allOwners)): ?>
        <div class="recent-activity">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-users" style="font-size: 4em; color: #ddd; margin-bottom: 20px;"></i>
                <h3 style="color: #666;">No Owners Found</h3>
                <p style="color: #888;">There are no fantasy owners to display yet. Check back later!</p>
            </div>
        </div>
    <?php else: ?>
        <!-- Owner Statistics -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3><i class="fas fa-chart-bar"></i> Owner Statistics</h3>
                <div class="stats-grid">
                    <?php
                    $totalOwners = count($allOwners);
                    $leagueOwners = count(array_filter($allOwners, function($o) { return $o['is_owner_count'] > 0; }));
                    $multiLeagueOwners = count(array_filter($allOwners, function($o) { return $o['total_leagues'] > 1; }));
                    $maxLeagues = $totalOwners > 0 ? max(array_column($allOwners, 'total_leagues')) : 0;
                    ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $totalOwners; ?></div>
                        <div class="stat-label">Total Owners</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $leagueOwners; ?></div>
                        <div class="stat-label">League Owners</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $multiLeagueOwners; ?></div>
                        <div class="stat-label">Multi-League</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $maxLeagues; ?></div>
                        <div class="stat-label">Max Leagues</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <h3><i class="fas fa-crown"></i> Most Active Owners</h3>
                <?php 
                $topActiveOwners = array_slice($allOwners, 0, 5);
                foreach ($topActiveOwners as $index => $owner): 
                ?>
                    <div class="player-rank-item">
                        <div class="rank-number"><?php echo $index + 1; ?></div>
                        <div class="player-info">
                            <div class="player-name"><?php echo htmlspecialchars($owner['display_name'] ?: $owner['username']); ?></div>
                            <div class="player-details">
                                <?php echo $owner['total_leagues']; ?> league<?php echo $owner['total_leagues'] > 1 ? 's' : ''; ?>
                                <?php if ($owner['is_owner_count'] > 0): ?>
                                    • <?php echo $owner['is_owner_count']; ?> owned
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="player-score">
                            <?php echo $owner['total_leagues']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- All Owners Table -->
        <div class="fantasy-table">
            <table>
                <thead>
                    <tr>
                        <th>Owner</th>
                        <th>Username</th>
                        <th>Total Leagues</th>
                        <th>Leagues Owned</th>
                        <th>Recent Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allOwners as $owner): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <?php if ($owner['avatar']): ?>
                                        <img src="https://sleepercdn.com/avatars/thumbs/<?php echo htmlspecialchars($owner['avatar']); ?>" 
                                             alt="Avatar" style="width: 32px; height: 32px; border-radius: 50%; margin-right: 10px;">
                                    <?php else: ?>
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #ddd; margin-right: 10px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-user" style="color: #666;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($owner['display_name'] ?: $owner['username']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($owner['username']); ?></td>
                            <td>
                                <span class="stat-value" style="font-size: 1.2em; color: #2a5298;">
                                    <?php echo $owner['total_leagues']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($owner['is_owner_count'] > 0): ?>
                                    <span class="status-badge status-active">
                                        <?php echo $owner['is_owner_count']; ?> owned
                                    </span>
                                <?php else: ?>
                                    <span style="color: #666;">Member only</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $recentLeague = end($owner['leagues']);
                                if ($recentLeague):
                                ?>
                                    <div style="font-size: 0.9em;">
                                        <strong><?php echo htmlspecialchars($recentLeague['league_name']); ?></strong><br>
                                        <span style="color: #666;">Season <?php echo $recentLeague['season']; ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="fantasy-owner-detail.php?id=<?php echo urlencode($owner['user_id']); ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Profile
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
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
</style>

<?php require_once 'footer.php'; ?>
