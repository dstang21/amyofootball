<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Manage Scoring Settings';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
            $name = sanitize($_POST['name']);
            $passing_yards = (float)$_POST['passing_yards'];
            $passing_td = (float)$_POST['passing_td'];
            $passing_int = (float)$_POST['passing_int'];
            $rushing_yards = (float)$_POST['rushing_yards'];
            $rushing_td = (float)$_POST['rushing_td'];
            $receptions = (float)$_POST['receptions'];
            $receiving_yards = (float)$_POST['receiving_yards'];
            $receiving_tds = (float)$_POST['receiving_tds'];
            $fumbles_lost = (float)$_POST['fumbles_lost'];
            
            if (empty($name)) {
                $error = "Scoring setting name is required.";
            } else {
                if ($_POST['action'] == 'add') {
                    $stmt = $pdo->prepare("INSERT INTO scoring_settings (name, passing_yards, passing_td, passing_int, rushing_yards, rushing_td, receptions, receiving_yards, receiving_tds, fumbles_lost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$name, $passing_yards, $passing_td, $passing_int, $rushing_yards, $rushing_td, $receptions, $receiving_yards, $receiving_tds, $fumbles_lost])) {
                        $success = "Scoring setting added successfully!";
                    } else {
                        $error = "Error adding scoring setting.";
                    }
                } else {
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("UPDATE scoring_settings SET name = ?, passing_yards = ?, passing_td = ?, passing_int = ?, rushing_yards = ?, rushing_td = ?, receptions = ?, receiving_yards = ?, receiving_tds = ?, fumbles_lost = ? WHERE id = ?");
                    if ($stmt->execute([$name, $passing_yards, $passing_td, $passing_int, $rushing_yards, $rushing_td, $receptions, $receiving_yards, $receiving_tds, $fumbles_lost, $id])) {
                        $success = "Scoring setting updated successfully!";
                    } else {
                        $error = "Error updating scoring setting.";
                    }
                }
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM scoring_settings WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = "Scoring setting deleted successfully!";
            } else {
                $error = "Error deleting scoring setting.";
            }
        }
    }
}

// Get scoring settings
$scoring_settings = $pdo->query("SELECT * FROM scoring_settings ORDER BY name")->fetchAll();

// Get editing data if requested
$editing = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM scoring_settings WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editing = $stmt->fetch();
}

include '../header.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Manage Scoring Settings</h1>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="card">
        <div class="card-header">
            <h3><?php echo $editing ? 'Edit Scoring Setting' : 'Add New Scoring Setting'; ?></h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editing ? 'edit' : 'add'; ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?php echo $editing['id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-col" style="grid-column: 1 / -1;">
                        <label for="name">Scoring Setting Name</label>
                        <input type="text" id="name" name="name" value="<?php echo $editing ? htmlspecialchars($editing['name']) : ''; ?>" required>
                    </div>
                </div>

                <h4 style="color: var(--primary-color); margin: 30px 0 15px 0;">Passing</h4>
                <div class="form-row">
                    <div class="form-col">
                        <label for="passing_yards">Passing Yards</label>
                        <input type="number" step="0.01" id="passing_yards" name="passing_yards" value="<?php echo $editing ? $editing['passing_yards'] : '0.04'; ?>">
                        <small>Points per yard (e.g., 0.04 = 1 point per 25 yards)</small>
                    </div>
                    <div class="form-col">
                        <label for="passing_td">Passing TD</label>
                        <input type="number" step="0.01" id="passing_td" name="passing_td" value="<?php echo $editing ? $editing['passing_td'] : '4.00'; ?>">
                        <small>Points per touchdown</small>
                    </div>
                    <div class="form-col">
                        <label for="passing_int">Passing Interception</label>
                        <input type="number" step="0.01" id="passing_int" name="passing_int" value="<?php echo $editing ? $editing['passing_int'] : '-2.00'; ?>">
                        <small>Points per interception (usually negative)</small>
                    </div>
                </div>

                <h4 style="color: var(--primary-color); margin: 30px 0 15px 0;">Rushing</h4>
                <div class="form-row">
                    <div class="form-col">
                        <label for="rushing_yards">Rushing Yards</label>
                        <input type="number" step="0.01" id="rushing_yards" name="rushing_yards" value="<?php echo $editing ? $editing['rushing_yards'] : '0.10'; ?>">
                        <small>Points per yard (e.g., 0.10 = 1 point per 10 yards)</small>
                    </div>
                    <div class="form-col">
                        <label for="rushing_td">Rushing TD</label>
                        <input type="number" step="0.01" id="rushing_td" name="rushing_td" value="<?php echo $editing ? $editing['rushing_td'] : '6.00'; ?>">
                        <small>Points per touchdown</small>
                    </div>
                </div>

                <h4 style="color: var(--primary-color); margin: 30px 0 15px 0;">Receiving</h4>
                <div class="form-row">
                    <div class="form-col">
                        <label for="receptions">Receptions</label>
                        <input type="number" step="0.01" id="receptions" name="receptions" value="<?php echo $editing ? $editing['receptions'] : '1.00'; ?>">
                        <small>Points per reception (PPR = 1.00, Half PPR = 0.50)</small>
                    </div>
                    <div class="form-col">
                        <label for="receiving_yards">Receiving Yards</label>
                        <input type="number" step="0.01" id="receiving_yards" name="receiving_yards" value="<?php echo $editing ? $editing['receiving_yards'] : '0.10'; ?>">
                        <small>Points per yard</small>
                    </div>
                    <div class="form-col">
                        <label for="receiving_tds">Receiving TD</label>
                        <input type="number" step="0.01" id="receiving_tds" name="receiving_tds" value="<?php echo $editing ? $editing['receiving_tds'] : '6.00'; ?>">
                        <small>Points per touchdown</small>
                    </div>
                </div>

                <h4 style="color: var(--primary-color); margin: 30px 0 15px 0;">Penalties</h4>
                <div class="form-row">
                    <div class="form-col">
                        <label for="fumbles_lost">Fumbles Lost</label>
                        <input type="number" step="0.01" id="fumbles_lost" name="fumbles_lost" value="<?php echo $editing ? $editing['fumbles_lost'] : '-2.00'; ?>">
                        <small>Points per fumble lost (usually negative)</small>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Update Scoring Setting' : 'Add Scoring Setting'; ?></button>
                    <?php if ($editing): ?>
                        <a href="manage-scoring.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Scoring Settings -->
    <div class="card">
        <div class="card-header">
            <h3>Current Scoring Settings</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($scoring_settings)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Pass Yds</th>
                                <th>Pass TD</th>
                                <th>Pass INT</th>
                                <th>Rush Yds</th>
                                <th>Rush TD</th>
                                <th>Receptions</th>
                                <th>Rec Yds</th>
                                <th>Rec TD</th>
                                <th>Fumbles</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scoring_settings as $setting): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($setting['name']); ?></strong></td>
                                <td><?php echo number_format($setting['passing_yards'], 2); ?></td>
                                <td><?php echo number_format($setting['passing_td'], 2); ?></td>
                                <td><?php echo number_format($setting['passing_int'], 2); ?></td>
                                <td><?php echo number_format($setting['rushing_yards'], 2); ?></td>
                                <td><?php echo number_format($setting['rushing_td'], 2); ?></td>
                                <td><?php echo number_format($setting['receptions'], 2); ?></td>
                                <td><?php echo number_format($setting['receiving_yards'], 2); ?></td>
                                <td><?php echo number_format($setting['receiving_tds'], 2); ?></td>
                                <td><?php echo number_format($setting['fumbles_lost'], 2); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $setting['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this scoring setting?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $setting['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No scoring settings found. Add one above to get started!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>
