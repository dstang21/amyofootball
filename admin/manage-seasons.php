<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Manage Seasons';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $year = (int)$_POST['year'];
                
                $stmt = $pdo->prepare("INSERT INTO seasons (year) VALUES (?)");
                if ($stmt->execute([$year])) {
                    $success = "Season added successfully!";
                } else {
                    $error = "Error adding season. Year may already exist.";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM seasons WHERE id=?");
                if ($stmt->execute([$id])) {
                    $success = "Season deleted successfully!";
                } else {
                    $error = "Error deleting season.";
                }
                break;
        }
    }
}

// Get all seasons
$seasons = $pdo->query("SELECT * FROM seasons ORDER BY year DESC")->fetchAll();

include '../header.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Manage Seasons</h1>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Add Season Form -->
        <div class="card">
            <div class="card-header">
                <h3>Add New Season</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label for="year">Season Year</label>
                        <input type="number" id="year" name="year" 
                               value="<?php echo date('Y'); ?>" 
                               min="2000" max="2030" required>
                        <small style="color: var(--text-secondary);">Enter the year for this football season</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Season</button>
                </form>

                <!-- Quick Add Buttons -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                    <h4>Quick Add</h4>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                        <?php 
                        $current_year = date('Y');
                        for ($i = 0; $i <= 2; $i++): 
                            $year = $current_year + $i;
                        ?>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('year').value = <?php echo $year; ?>">
                            <?php echo $year; ?>
                        </button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seasons List -->
        <div class="card">
            <div class="card-header">
                <h3>All Seasons (<?php echo count($seasons); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($seasons)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($seasons as $season): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--primary-color); font-size: 1.1rem;">
                                            <?php echo $season['year']; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if ($season['year'] == date('Y')): ?>
                                            <span class="position-badge position-qb">Current</span>
                                        <?php elseif ($season['year'] > date('Y')): ?>
                                            <span class="position-badge position-wr">Future</span>
                                        <?php else: ?>
                                            <span class="position-badge position-def">Past</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($season['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="manage-rankings.php?season=<?php echo $season['id']; ?>" class="btn btn-primary btn-sm">Rankings</a>
                                        <form method="POST" action="" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure? This will delete all rankings and stats for this season!');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $season['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No seasons found. Add your first season using the form on the left.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Season Statistics -->
    <?php if (!empty($seasons)): ?>
    <div class="card">
        <div class="card-header">
            <h3>Season Overview</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <?php foreach ($seasons as $season): 
                    // Get stats for this season
                    $season_stats = $pdo->prepare("
                        SELECT 
                            (SELECT COUNT(*) FROM draft_positions WHERE season_id = ?) as rankings_count,
                            (SELECT COUNT(*) FROM projected_stats WHERE season_id = ?) as stats_count,
                            (SELECT COUNT(*) FROM player_teams WHERE season_id = ?) as player_teams_count
                    ");
                    $season_stats->execute([$season['id'], $season['id'], $season['id']]);
                    $stats = $season_stats->fetch();
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $season['year']; ?></div>
                    <div class="stat-label">
                        <?php echo $stats['rankings_count']; ?> Rankings<br>
                        <?php echo $stats['stats_count']; ?> Projections<br>
                        <?php echo $stats['player_teams_count']; ?> Player-Teams
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../footer.php'; ?>
