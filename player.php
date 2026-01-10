<?php
require_once 'config.php';

// Get player ID
$player_id = $_GET['id'] ?? null;

if (!$player_id) {
    redirect('players.php');
}

// Get player details
$player_query = $pdo->prepare("
    SELECT p.*, t.name as team_name, t.abbreviation as team_abbr, pt.position, pt.matches,
           ps.*, s.year as season_year
    FROM players p
    LEFT JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams t ON pt.team_id = t.id
    LEFT JOIN projected_stats ps ON p.id = ps.player_id AND ps.season_id = pt.season_id
    LEFT JOIN seasons s ON pt.season_id = s.id
    WHERE p.id = ?
    ORDER BY s.year DESC
    LIMIT 1
");
$player_query->execute([$player_id]);
$player = $player_query->fetch();

if (!$player) {
    redirect('players.php');
}

// Get player rankings
$rankings_query = $pdo->prepare("
    SELECT dp.*, s.year as season_year
    FROM draft_positions dp
    JOIN seasons s ON dp.season_id = s.id
    WHERE dp.player_id = ?
    ORDER BY s.year DESC
");
$rankings_query->execute([$player_id]);
$rankings = $rankings_query->fetchAll();

$page_title = $player['full_name'];
include 'header.php';
?>

<div class="container">
    <!-- Player Header -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <h1 style="margin: 0; color: white;"><?php echo htmlspecialchars($player['full_name']); ?></h1>
                    <p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.8);">
                        <?php echo htmlspecialchars($player['team_abbr'] ?? 'Free Agent'); ?>
                        <?php if ($player['position']): ?>
                            • <?php echo htmlspecialchars($player['position']); ?>
                        <?php endif; ?>
                        <?php if ($player['season_year']): ?>
                            • <?php echo $player['season_year']; ?> Season
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if ($player['position']): ?>
                        <span class="position-badge position-<?php echo strtolower($player['position']); ?>" style="font-size: 1rem; padding: 8px 12px;">
                            <?php echo htmlspecialchars($player['position']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <?php if ($player['birth_date']): ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo date('Y') - date('Y', strtotime($player['birth_date'])); ?></div>
                    <div class="stat-label">Age</div>
                </div>
                <?php endif; ?>
                
                <?php if ($player['matches']): ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $player['matches']; ?></div>
                    <div class="stat-label">Games Played</div>
                </div>
                <?php endif; ?>
                
                <?php 
                $total_tds = ($player['passing_tds'] ?? 0) + ($player['rushing_tds'] ?? 0) + ($player['receiving_tds'] ?? 0);
                if ($total_tds > 0):
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_tds; ?></div>
                    <div class="stat-label">Total TDs</div>
                </div>
                <?php endif; ?>
                
                <?php 
                $total_yards = ($player['passing_yards'] ?? 0) + ($player['rushing_yards'] ?? 0) + ($player['receiving_yards'] ?? 0);
                if ($total_yards > 0):
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($total_yards, 0); ?></div>
                    <div class="stat-label">Total Yards</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Projected Stats -->
        <div class="card">
            <div class="card-header">
                <h3>Projected Stats</h3>
            </div>
            <div class="card-body">
                <?php if ($player['passing_yards'] || $player['rushing_yards'] || $player['receiving_yards']): ?>
                    <!-- Offensive Stats -->
                    <?php if ($player['passing_yards'] || $player['passing_tds'] || $player['interceptions']): ?>
                        <h4 style="color: var(--primary-color); margin-bottom: 10px;">Passing</h4>
                        <table style="width: 100%; margin-bottom: 20px;">
                            <?php if ($player['passing_yards']): ?>
                            <tr><td>Yards:</td><td><strong><?php echo number_format($player['passing_yards'], 0); ?></strong></td></tr>
                            <?php endif; ?>
                            <?php if ($player['passing_tds']): ?>
                            <tr><td>TDs:</td><td><strong><?php echo $player['passing_tds']; ?></strong></td></tr>
                            <?php endif; ?>
                            <?php if ($player['interceptions']): ?>
                            <tr><td>INTs:</td><td><strong><?php echo $player['interceptions']; ?></strong></td></tr>
                            <?php endif; ?>
                        </table>
                    <?php endif; ?>

                    <?php if ($player['rushing_yards'] || $player['rushing_tds']): ?>
                        <h4 style="color: var(--primary-color); margin-bottom: 10px;">Rushing</h4>
                        <table style="width: 100%; margin-bottom: 20px;">
                            <?php if ($player['rushing_yards']): ?>
                            <tr><td>Yards:</td><td><strong><?php echo number_format($player['rushing_yards'], 0); ?></strong></td></tr>
                            <?php endif; ?>
                            <?php if ($player['rushing_tds']): ?>
                            <tr><td>TDs:</td><td><strong><?php echo $player['rushing_tds']; ?></strong></td></tr>
                            <?php endif; ?>
                        </table>
                    <?php endif; ?>

                    <?php if ($player['receptions'] || $player['receiving_yards'] || $player['receiving_tds']): ?>
                        <h4 style="color: var(--primary-color); margin-bottom: 10px;">Receiving</h4>
                        <table style="width: 100%; margin-bottom: 20px;">
                            <?php if ($player['receptions']): ?>
                            <tr><td>Receptions:</td><td><strong><?php echo $player['receptions']; ?></strong></td></tr>
                            <?php endif; ?>
                            <?php if ($player['receiving_yards']): ?>
                            <tr><td>Yards:</td><td><strong><?php echo number_format($player['receiving_yards'], 0); ?></strong></td></tr>
                            <?php endif; ?>
                            <?php if ($player['receiving_tds']): ?>
                            <tr><td>TDs:</td><td><strong><?php echo $player['receiving_tds']; ?></strong></td></tr>
                            <?php endif; ?>
                        </table>
                    <?php endif; ?>

                    <!-- Defensive Stats -->
                    <?php if ($player['tackles'] || $player['sacks'] || $player['defensive_interceptions']): ?>
                        <h4 style="color: var(--primary-color); margin-bottom: 10px;">Defense</h4>
                        <table style="width: 100%;">
                            <?php if ($player['tackles']): ?>
                            <tr><td>Tackles:</td><td><strong><?php echo $player['tackles']; ?></strong></td></tr>
                            <?php endif; ?>
                            <?php if ($player['sacks']): ?>
                            <tr><td>Sacks:</td><td><strong><?php echo $player['sacks']; ?></strong></td></tr>
                            <?php endif; ?>
                            <?php if ($player['defensive_interceptions']): ?>
                            <tr><td>INTs:</td><td><strong><?php echo $player['defensive_interceptions']; ?></strong></td></tr>
                            <?php endif; ?>
                        </table>
                    <?php endif; ?>

                <?php else: ?>
                    <p>No projected stats available for this player yet.</p>
                    <?php if (isLoggedIn()): ?>
                        <a href="admin/manage-stats.php?player=<?php echo $player['id']; ?>" class="btn btn-primary">Add Stats</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rankings -->
        <div class="card">
            <div class="card-header">
                <h3>Rankings</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($rankings)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Season</th>
                                    <th>Type</th>
                                    <th>Rank</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rankings as $ranking): ?>
                                <tr>
                                    <td><?php echo $ranking['season_year']; ?></td>
                                    <td><?php echo ucfirst($ranking['rank_side']); ?></td>
                                    <td class="rank-cell"><?php echo $ranking['ranking']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No rankings available for this player yet.</p>
                    <?php if (isLoggedIn()): ?>
                        <a href="admin/manage-rankings.php" class="btn btn-primary">Set Rankings</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="card">
        <div class="card-body" style="text-align: center;">
            <a href="players.php" class="btn btn-secondary">← Back to Players</a>
            <a href="rankings.php" class="btn btn-primary">View Rankings</a>
            <?php if (isLoggedIn()): ?>
                <a href="admin/manage-players.php?edit=<?php echo $player['id']; ?>" class="btn btn-warning">Edit Player</a>
                <a href="admin/manage-stats.php?player=<?php echo $player['id']; ?>" class="btn btn-success">Edit Stats</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
