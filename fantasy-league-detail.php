<?php
$page_title = 'League Details';
require_once 'fantasy-header.php';

// Include the SleeperController with proper path handling
if (file_exists('admin/SleeperController.php')) {
    require_once 'admin/SleeperController.php';
} else {
    require_once '../admin/SleeperController.php';
}

// Get league ID from URL
$leagueId = $_GET['league_id'] ?? '';
if (!$leagueId) {
    header('Location: fantasy-leagues.php');
    exit;
}

// Initialize controller
$controller = new SleeperController();

// Get league details
try {
    $league = $controller->getLeague($leagueId);
    if (!$league) {
        throw new Exception("League not found");
    }
    
    // Get additional league data
    $selectedSeason = $_GET['season'] ?? null;
    $standings = $controller->getLeagueStandings($leagueId, $selectedSeason);
    $users = $controller->getLeagueUsers($leagueId);
    $rosters = $controller->getLeagueRosters($leagueId);
    $matchups = $controller->getRecentMatchups($leagueId, 5);
    
    // Get current page/tab
    $currentTab = $_GET['tab'] ?? 'overview';
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="container">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            Error: <?php echo htmlspecialchars($error); ?>
        </div>
        <a href="fantasy-leagues.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Leagues
        </a>
    <?php else: ?>
        
        <!-- League Header -->
        <div class="league-header">
            <div class="league-info">
                <div class="league-avatar">
                    <?php if ($league['avatar']): ?>
                        <img src="https://sleepercdn.com/avatars/<?php echo $league['avatar']; ?>" 
                             alt="League Avatar" class="avatar-img">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <i class="fas fa-trophy"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="league-details">
                    <h1><?php echo htmlspecialchars($league['name']); ?></h1>
                    <div class="league-meta">
                        <span class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <?php echo $league['season']; ?> Season
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-users"></i>
                            <?php echo $league['total_rosters']; ?> Teams
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-circle status-<?php echo strtolower($league['status']); ?>"></i>
                            <?php echo ucfirst($league['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="league-actions">
                <a href="fantasy-leagues.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Leagues
                </a>
            </div>
        </div>

        <!-- League Navigation Tabs -->
        <div class="league-nav">
            <nav class="nav-tabs">
                <a href="?league_id=<?php echo $leagueId; ?>&tab=overview" 
                   class="nav-tab <?php echo $currentTab === 'overview' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Overview
                </a>
                <a href="?league_id=<?php echo $leagueId; ?>&tab=standings" 
                   class="nav-tab <?php echo $currentTab === 'standings' ? 'active' : ''; ?>">
                    <i class="fas fa-list-ol"></i> Standings
                </a>
                <a href="?league_id=<?php echo $leagueId; ?>&tab=champions" 
                   class="nav-tab <?php echo $currentTab === 'champions' ? 'active' : ''; ?>">
                    <i class="fas fa-crown"></i> Champions
                </a>
                <a href="?league_id=<?php echo $leagueId; ?>&tab=rankings" 
                   class="nav-tab <?php echo $currentTab === 'rankings' ? 'active' : ''; ?>">
                    <i class="fas fa-ranking-star"></i> League Rankings
                </a>
                <a href="?league_id=<?php echo $leagueId; ?>&tab=history" 
                   class="nav-tab <?php echo $currentTab === 'history' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> League History
                </a>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            
            <?php if ($currentTab === 'overview'): ?>
                <!-- Overview Tab -->
                <div class="tab-pane active">
                    <div class="row">
                        <!-- League Stats -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-chart-bar"></i> League Stats</h4>
                                </div>
                                <div class="card-body">
                                    <div class="stat-grid">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo count($users); ?></div>
                                            <div class="stat-label">Total Owners</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $league['total_rosters']; ?></div>
                                            <div class="stat-label">Teams</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $league['season']; ?></div>
                                            <div class="stat-label">Season</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Performers -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-star"></i> Top Performers This Season</h4>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($standings)): ?>
                                        <div class="performers-list">
                                            <?php 
                                            $topPerformers = array_slice($standings, 0, 5);
                                            foreach ($topPerformers as $index => $team): 
                                            ?>
                                                <div class="performer-item">
                                                    <div class="rank-badge rank-<?php echo $index + 1; ?>">
                                                        #<?php echo $index + 1; ?>
                                                    </div>
                                                    <div class="performer-info">
                                                        <div class="team-name">
                                                            <?php echo htmlspecialchars($team['team_name'] ?: $team['display_name']); ?>
                                                        </div>
                                                        <div class="team-record">
                                                            <?php echo $team['wins']; ?>-<?php echo $team['losses']; ?>
                                                            <?php if ($team['ties'] > 0): ?>-<?php echo $team['ties']; ?><?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="performer-stats">
                                                        <div class="points"><?php echo number_format($team['fpts'], 1); ?> pts</div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No standings data available yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <?php if (!empty($matchups)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h4><i class="fas fa-clock"></i> Recent Matchups</h4>
                        </div>
                        <div class="card-body">
                            <div class="matchups-grid">
                                <?php foreach (array_slice($matchups, 0, 6) as $matchup): ?>
                                    <div class="matchup-card">
                                        <div class="week-label">Week <?php echo $matchup['week']; ?></div>
                                        <div class="matchup-teams">
                                            <div class="team">
                                                <span class="team-name"><?php echo htmlspecialchars($matchup['team_name']); ?></span>
                                                <span class="points"><?php echo number_format($matchup['points'], 1); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($currentTab === 'standings'): ?>
                <!-- Current Standings Tab -->
                <div class="tab-pane active">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-list-ol"></i> League Standings</h4>
                            <div class="season-selector">
                                <?php
                                // Get available seasons for this league
                                $availableSeasons = $controller->getLeagueSeasons($leagueId);
                                $selectedSeason = $_GET['season'] ?? ($availableSeasons[0]['season'] ?? date('Y'));
                                ?>
                                <?php if (!empty($availableSeasons)): ?>
                                    <label for="seasonSelect">Season:</label>
                                    <select id="seasonSelect" class="form-select d-inline-block w-auto" onchange="changeSeason(this.value)">
                                        <?php foreach ($availableSeasons as $season): ?>
                                            <option value="<?php echo $season['season']; ?>" <?php echo $season['season'] == $selectedSeason ? 'selected' : ''; ?>>
                                                <?php echo $season['season']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($standings)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Team</th>
                                                <th>Owner</th>
                                                <th>Record</th>
                                                <th>Points For</th>
                                                <th>Points Against</th>
                                                <th>Moves</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($standings as $index => $team): ?>
                                                <tr>
                                                    <td>
                                                        <span class="rank-badge rank-<?php echo $index + 1; ?>">
                                                            <?php echo $index + 1; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($team['team_name'] ?: 'Team ' . $team['roster_id']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($team['display_name']); ?></td>
                                                    <td>
                                                        <span class="record">
                                                            <?php echo $team['wins']; ?>-<?php echo $team['losses']; ?>
                                                            <?php if ($team['ties'] > 0): ?>-<?php echo $team['ties']; ?><?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo number_format($team['fpts'], 1); ?></td>
                                                    <td><?php echo number_format($team['fpts_against'], 1); ?></td>
                                                    <td><?php echo $team['total_moves']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    No standings data available for this league yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($currentTab === 'champions'): ?>
                <!-- Champions Tab -->
                <div class="tab-pane active">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-crown"></i> League Champions</h4>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get previous league champions
                            $champions = $controller->getLeagueChampions($leagueId);
                            ?>
                            <?php if (!empty($champions)): ?>
                                <div class="champions-list">
                                    <?php foreach ($champions as $champion): ?>
                                        <div class="champion-card">
                                            <div class="champion-year"><?php echo $champion['season']; ?></div>
                                            <div class="champion-info">
                                                <div class="champion-name">
                                                    <?php echo htmlspecialchars($champion['champion_name']); ?>
                                                </div>
                                                <div class="champion-record">
                                                    Record: <?php echo $champion['wins']; ?>-<?php echo $champion['losses']; ?>
                                                    <?php if ($champion['ties'] > 0): ?>-<?php echo $champion['ties']; ?><?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="champion-trophy">
                                                <i class="fas fa-trophy"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle"></i>
                                    <p>No championship history available yet.</p>
                                    <small class="text-muted">Champions will appear as seasons complete.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($currentTab === 'rankings'): ?>
                <!-- League Rankings Tab -->
                <div class="tab-pane active">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-ranking-star"></i> League Player Rankings</h4>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get league-specific player rankings
                            $leagueRankings = $controller->getLeaguePlayerRankings($leagueId);
                            ?>
                            <?php if (!empty($leagueRankings)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Player</th>
                                                <th>Position</th>
                                                <th>Team</th>
                                                <th>Owner</th>
                                                <th>Fantasy Points</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($leagueRankings as $index => $player): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><strong><?php echo htmlspecialchars($player['player_name']); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo match($player['position']) {
                                                                'QB' => 'primary',
                                                                'RB' => 'success',
                                                                'WR' => 'warning',
                                                                'TE' => 'info',
                                                                'K' => 'secondary',
                                                                'DEF' => 'dark',
                                                                default => 'light'
                                                            }; 
                                                        ?>"><?php echo $player['position']; ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($player['nfl_team']); ?></td>
                                                    <td><?php echo htmlspecialchars($player['owner_name']); ?></td>
                                                    <td><?php echo number_format($player['fantasy_points'], 1); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    No player ranking data available for this league yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($currentTab === 'history'): ?>
                <!-- League History Tab -->
                <div class="tab-pane active">
                    <div class="row">
                        <!-- Season History -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-history"></i> Season History</h4>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $seasonHistory = $controller->getLeagueHistory($leagueId);
                                    ?>
                                    <?php if (!empty($seasonHistory)): ?>
                                        <div class="history-timeline">
                                            <?php foreach ($seasonHistory as $season): ?>
                                                <div class="timeline-item">
                                                    <div class="timeline-marker"></div>
                                                    <div class="timeline-content">
                                                        <h5><?php echo $season['season']; ?> Season</h5>
                                                        <p class="text-muted">
                                                            <?php echo $season['total_teams']; ?> teams participated
                                                        </p>
                                                        <?php if (isset($season['champion'])): ?>
                                                            <div class="champion-info">
                                                                <i class="fas fa-crown text-warning"></i>
                                                                Champion: <?php echo htmlspecialchars($season['champion']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No historical data available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- League Stats Over Time -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4><i class="fas fa-chart-line"></i> League Statistics</h4>
                                </div>
                                <div class="card-body">
                                    <div class="stat-summary">
                                        <div class="stat-item">
                                            <h5>Total Seasons</h5>
                                            <p class="stat-value"><?php echo count($seasonHistory); ?></p>
                                        </div>
                                        <div class="stat-item">
                                            <h5>Most Active Owner</h5>
                                            <p class="stat-value">
                                                <?php 
                                                $mostActive = $controller->getMostActiveOwner($leagueId);
                                                echo htmlspecialchars($mostActive['name'] ?? 'N/A');
                                                ?>
                                            </p>
                                        </div>
                                        <div class="stat-item">
                                            <h5>Highest Scoring Season</h5>
                                            <p class="stat-value">
                                                <?php 
                                                $highestSeason = $controller->getHighestScoringSeasonTeam($leagueId);
                                                echo $highestSeason ? number_format($highestSeason['points'], 1) . ' pts' : 'N/A';
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    <?php endif; ?>
</div>

<!-- Custom CSS for League Detail Page -->
<style>
.league-header {
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    padding: 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.league-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.league-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid rgba(255,255,255,0.3);
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2em;
}

.league-details h1 {
    margin: 0 0 10px 0;
    font-size: 2.5em;
    font-weight: bold;
}

.league-meta {
    display: flex;
    gap: 20px;
    font-size: 1.1em;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-active { color: #28a745; }
.status-complete { color: #6c757d; }
.status-drafting { color: #ffc107; }

.league-nav {
    margin-bottom: 30px;
}

.nav-tabs {
    display: flex;
    border-bottom: 2px solid #e9ecef;
    gap: 0;
}

.nav-tab {
    padding: 15px 25px;
    text-decoration: none;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-tab:hover {
    color: #2a5298;
    background: rgba(42, 82, 152, 0.05);
}

.nav-tab.active {
    color: #2a5298;
    border-bottom-color: #2a5298;
    background: rgba(42, 82, 152, 0.05);
}

.performers-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.performer-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.rank-badge {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
}

.rank-1 { background: #ffd700; color: #333; }
.rank-2 { background: #c0c0c0; color: #333; }
.rank-3 { background: #cd7f32; }
.rank-4, .rank-5 { background: #6c757d; }

.performer-info {
    flex: 1;
}

.team-name {
    font-weight: bold;
    font-size: 1.1em;
}

.team-record {
    color: #666;
    font-size: 0.9em;
}

.performer-stats {
    text-align: right;
}

.points {
    font-weight: bold;
    color: #2a5298;
}

.matchups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.matchup-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}

.week-label {
    font-size: 0.9em;
    color: #666;
    margin-bottom: 10px;
}

.champions-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.champion-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    border-radius: 10px;
    color: #333;
}

.champion-year {
    font-size: 1.5em;
    font-weight: bold;
    min-width: 80px;
}

.champion-info {
    flex: 1;
}

.champion-name {
    font-size: 1.2em;
    font-weight: bold;
}

.champion-trophy {
    font-size: 2em;
    color: #b8860b;
}

.history-timeline {
    position: relative;
    padding-left: 30px;
}

.history-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -38px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #2a5298;
    border: 3px solid white;
    box-shadow: 0 0 0 3px #e9ecef;
}

.timeline-content h5 {
    margin: 0 0 5px 0;
    color: #2a5298;
}

.champion-info {
    margin-top: 10px;
    padding: 10px;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 5px;
}

.stat-summary {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.stat-summary .stat-item {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-summary h5 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.stat-summary .stat-value {
    font-size: 1.8em;
    font-weight: bold;
    color: #2a5298;
    margin: 0;
}

.season-selector {
    float: right;
    margin-top: -5px;
}

.season-selector label {
    margin-right: 10px;
    font-weight: normal;
}
</style>

<script>
function changeSeason(season) {
    const url = new URL(window.location.href);
    url.searchParams.set('season', season);
    window.location.href = url.toString();
}
</script>

<?php require_once 'footer.php'; ?>
