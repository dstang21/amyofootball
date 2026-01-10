<?php
require_once 'config.php';

// Restrict access to admin users only
if (!isAdmin()) {
    redirect('index.php');
}

$page_title = 'Teams';

// Get all teams with player counts
$teams_query = $pdo->query("
    SELECT t.*, COUNT(pt.player_id) as player_count
    FROM teams t
    LEFT JOIN player_teams pt ON t.id = pt.team_id
    GROUP BY t.id
    ORDER BY t.name
");
$teams = $teams_query->fetchAll();

include 'header.php';
?>

<div class="container">
    <h1>NFL Teams</h1>
    
    <div class="card">
        <div class="card-header">
            <h2>All Teams (<?php echo count($teams); ?> teams)</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($teams)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>City</th>
                                <th>Abbreviation</th>
                                <th>Players</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $team): ?>
                            <tr>
                                <td class="player-cell">
                                    <?php echo htmlspecialchars($team['name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($team['city'] ?: 'N/A'); ?>
                                </td>
                                <td>
                                    <strong style="color: var(--primary-color);">
                                        <?php echo htmlspecialchars($team['abbreviation']); ?>
                                    </strong>
                                </td>
                                <td class="stats-cell">
                                    <?php echo $team['player_count']; ?>
                                </td>
                                <td>
                                    <a href="team.php?id=<?php echo $team['id']; ?>" class="btn btn-primary btn-sm">View Players</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No teams found.</p>
                <?php if (isLoggedIn()): ?>
                    <a href="admin/manage-teams.php" class="btn btn-primary">Add Teams</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
