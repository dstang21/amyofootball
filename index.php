<?php
require_once 'config.php';
$page_title = 'Home';

// Get current season
$current_season_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC LIMIT 1");
$current_season = $current_season_query->fetch();

// Get top ranked players (from first available source)
$top_players_query = $pdo->prepare("
    SELECT p.*, dp.ranking, s.name as source_name, t.name as team_name, t.abbreviation as team_abbr, pt.position
    FROM players p
    JOIN draft_positions dp ON p.id = dp.player_id
    JOIN sources s ON dp.source_id = s.id
    LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = dp.season_id
    LEFT JOIN teams t ON pt.team_id = t.id
    WHERE dp.season_id = ? AND dp.source_id = (SELECT MIN(id) FROM sources)
    ORDER BY dp.ranking ASC
    LIMIT 10
");
$top_players_query->execute([$current_season['id'] ?? 1]);
$top_players = $top_players_query->fetchAll();

// Get stats for dashboard
$total_players = $pdo->query("SELECT COUNT(*) as count FROM players")->fetch()['count'];
$total_teams = $pdo->query("SELECT COUNT(*) as count FROM teams")->fetch()['count'];

include 'header.php';
?>

<div class="hero">
    <div class="container">
        <h1>Welcome to AmyoFootball</h1>
        <p>Your ultimate fantasy football rankings and player statistics resource</p>
        <a href="rankings.php" class="btn btn-primary btn-lg">View Full Rankings</a>
    </div>
</div>

<div class="container">
    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_players; ?></div>
            <div class="stat-label">Total Players</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_teams; ?></div>
            <div class="stat-label">NFL Teams</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $current_season['year'] ?? '2025'; ?></div>
            <div class="stat-label">Current Season</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($top_players); ?></div>
            <div class="stat-label">Top Ranked</div>
        </div>
    </div>

    <!-- Top Players Preview -->
    <div class="card">
        <div class="card-header">
            <h2>Top 10 Rankings<?php echo !empty($top_players) ? ' - ' . htmlspecialchars($top_players[0]['source_name']) : ''; ?></h2>
        </div>
        <div class="card-body">
            <?php if (!empty($top_players)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Player</th>
                                <th>Team</th>
                                <th>Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_players as $player): ?>
                            <tr>
                                <td class="rank-cell"><?php echo $player['ranking']; ?></td>
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="rankings.php" class="btn btn-primary">View All Rankings</a>
                </div>
            <?php else: ?>
                <p>No rankings available yet. <a href="admin/dashboard.php">Add some rankings</a> to get started!</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Updates -->
    <div class="card">
        <div class="card-header">
            <h2>What's New</h2>
        </div>
        <div class="card-body">
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li>Updated <?php echo $current_season['year'] ?? '2025'; ?> season rankings</li>
                <li>Latest player projections available</li>
                <li>Team rosters updated for the current season</li>
                <li>Enhanced search and filtering capabilities</li>
            </ul>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
