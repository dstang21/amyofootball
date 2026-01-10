<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Manage Teams';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                $abbreviation = sanitize($_POST['abbreviation']);
                $city = sanitize($_POST['city']);
                
                $stmt = $pdo->prepare("INSERT INTO teams (name, abbreviation, city) VALUES (?, ?, ?)");
                if ($stmt->execute([$name, $abbreviation, $city])) {
                    $success = "Team added successfully!";
                } else {
                    $error = "Error adding team.";
                }
                break;
                
            case 'edit':
                $id = $_POST['id'];
                $name = sanitize($_POST['name']);
                $abbreviation = sanitize($_POST['abbreviation']);
                $city = sanitize($_POST['city']);
                
                $stmt = $pdo->prepare("UPDATE teams SET name=?, abbreviation=?, city=? WHERE id=?");
                if ($stmt->execute([$name, $abbreviation, $city, $id])) {
                    $success = "Team updated successfully!";
                } else {
                    $error = "Error updating team.";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM teams WHERE id=?");
                if ($stmt->execute([$id])) {
                    $success = "Team deleted successfully!";
                } else {
                    $error = "Error deleting team.";
                }
                break;
        }
    }
}

// Get all teams
$teams = $pdo->query("SELECT * FROM teams ORDER BY name")->fetchAll();

// Get team being edited
$edit_team = null;
if (isset($_GET['edit'])) {
    $edit_stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
    $edit_stmt->execute([$_GET['edit']]);
    $edit_team = $edit_stmt->fetch();
}

include '../header.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Manage Teams</h1>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
        <!-- Add/Edit Team Form -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo $edit_team ? 'Edit Team' : 'Add New Team'; ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $edit_team ? 'edit' : 'add'; ?>">
                    <?php if ($edit_team): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_team['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Team Name</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo $edit_team ? htmlspecialchars($edit_team['name']) : ''; ?>" 
                               placeholder="e.g. Patriots" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="abbreviation">Abbreviation</label>
                        <input type="text" id="abbreviation" name="abbreviation" 
                               value="<?php echo $edit_team ? htmlspecialchars($edit_team['abbreviation']) : ''; ?>" 
                               placeholder="e.g. NE" maxlength="10" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" 
                               value="<?php echo $edit_team ? htmlspecialchars($edit_team['city']) : ''; ?>" 
                               placeholder="e.g. New England">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_team ? 'Update Team' : 'Add Team'; ?>
                    </button>
                    
                    <?php if ($edit_team): ?>
                        <a href="manage-teams.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>

                <!-- Quick Add NFL Teams -->
                <?php if (!$edit_team): ?>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                    <h4>Quick Add NFL Teams</h4>
                    <p style="font-size: 0.9rem; color: var(--text-secondary);">Click to quickly add common NFL teams:</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 10px;">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="fillTeam('Chiefs', 'KC', 'Kansas City')">KC</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="fillTeam('Patriots', 'NE', 'New England')">NE</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="fillTeam('Cowboys', 'DAL', 'Dallas')">DAL</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="fillTeam('Packers', 'GB', 'Green Bay')">GB</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Teams List -->
        <div class="card">
            <div class="card-header">
                <h3>All Teams (<?php echo count($teams); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($teams)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Abbr</th>
                                    <th>Team Name</th>
                                    <th>City</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teams as $team): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--primary-color);">
                                            <?php echo htmlspecialchars($team['abbreviation']); ?>
                                        </strong>
                                    </td>
                                    <td class="player-cell">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($team['city'] ?: 'N/A'); ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($team['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?php echo $team['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <form method="POST" action="" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this team?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $team['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No teams found. Add your first team using the form on the left.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function fillTeam(name, abbr, city) {
    document.getElementById('name').value = name;
    document.getElementById('abbreviation').value = abbr;
    document.getElementById('city').value = city;
}
</script>

<?php include '../footer.php'; ?>
