<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Manage Sources';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                
                if (empty($name)) {
                    $error = "Source name is required.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO sources (name) VALUES (?)");
                    if ($stmt->execute([$name])) {
                        $success = "Source added successfully!";
                    } else {
                        $error = "Error adding source.";
                    }
                }
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name']);
                
                if (empty($name)) {
                    $error = "Source name is required.";
                } else {
                    $stmt = $pdo->prepare("UPDATE sources SET name = ? WHERE id = ?");
                    if ($stmt->execute([$name, $id])) {
                        $success = "Source updated successfully!";
                    } else {
                        $error = "Error updating source.";
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Check if source is being used
                $check = $pdo->prepare("SELECT COUNT(*) as count FROM projected_stats WHERE source_id = ?");
                $check->execute([$id]);
                $usage = $check->fetch();
                
                if ($usage['count'] > 0) {
                    $error = "Cannot delete source - it has {$usage['count']} projected stats records.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM sources WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $success = "Source deleted successfully!";
                    } else {
                        $error = "Error deleting source.";
                    }
                }
                break;
        }
    }
}

// Get all sources with usage count
$sources = $pdo->query("
    SELECT s.*, COUNT(ps.id) as usage_count
    FROM sources s
    LEFT JOIN projected_stats ps ON s.id = ps.source_id
    GROUP BY s.id, s.name
    ORDER BY s.name
")->fetchAll();

include 'admin-header.php';
include 'admin-nav.php';
?>

<div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
    <h1>Manage Projection Sources</h1>
    <p>Create and manage sources for projected statistics</p>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Add New Source -->
<div class="card">
    <div class="card-header">
        <h3>Add New Source</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-col" style="flex: 1;">
                    <label for="name">Source Name</label>
                    <input type="text" id="name" name="name" required placeholder="e.g., FantasyPros, ESPN, CBS Sports">
                </div>
                <div class="form-col" style="align-self: end;">
                    <button type="submit" class="btn btn-primary">Add Source</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Existing Sources -->
<div class="card">
    <div class="card-header">
        <h3>Existing Sources</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($sources)): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Source Name</th>
                            <th>Projected Stats Records</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sources as $source): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($source['name']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($source['usage_count'] > 0): ?>
                                        <span class="position-badge position-qb"><?php echo $source['usage_count']; ?> records</span>
                                    <?php else: ?>
                                        <span class="position-badge position-na">No records</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="editSource(<?php echo $source['id']; ?>, '<?php echo htmlspecialchars($source['name'], ENT_QUOTES); ?>')" 
                                            class="btn btn-primary btn-sm">Edit</button>
                                    
                                    <?php if ($source['usage_count'] == 0): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this source?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $source['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-danger btn-sm" disabled title="Cannot delete - source has projected stats records">Delete</button>
                                    <?php endif; ?>
                                    
                                    <a href="manage-stats.php?source=<?php echo $source['id']; ?>" class="btn btn-success btn-sm">Manage Stats</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: var(--text-secondary);">
                No sources found. Add your first source above to get started.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Source Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px;">
        <h3 style="margin: 0 0 20px 0;">Edit Source</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label for="editName">Source Name</label>
                <input type="text" id="editName" name="name" required>
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Source</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSource(id, name) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<?php include 'admin-footer.php'; ?>
