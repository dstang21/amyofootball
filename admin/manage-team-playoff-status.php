<?php
require_once '../config.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Manage Playoff Status';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $teamId = $_POST['team_id'];
        $status = $_POST['playoff_status'];
        $eliminatedDate = $status === 'eliminated' ? ($_POST['eliminated_date'] ?: date('Y-m-d')) : null;
        $notes = $_POST['notes'] ?? '';
        
        $stmt = $pdo->prepare("
            UPDATE teams 
            SET playoff_status = ?, 
                eliminated_date = ?,
                notes = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $eliminatedDate, $notes, $teamId]);
        $success = "Team status updated successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all NFL teams with current status
$teams = $pdo->query("
    SELECT id, name, abbreviation, city, 
           playoff_status, eliminated_date, notes
    FROM teams 
    ORDER BY name
")->fetchAll();

// Count teams by status
$statusCounts = $pdo->query("
    SELECT playoff_status, COUNT(*) as count 
    FROM teams 
    GROUP BY playoff_status
")->fetchAll(PDO::FETCH_KEY_PAIR);

include 'admin-header.php';
include 'admin-nav.php';
?>

<div class="container">
    <div class="admin-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>🏈 NFL Playoff Status Tracker</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">
            Mark teams as eliminated to grey them out on rosters
        </p>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Status Summary -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; text-align: center;">
                <div style="padding: 15px; background: #10b981; color: white; border-radius: 8px;">
                    <div style="font-size: 2em; font-weight: bold;"><?php echo $statusCounts['active'] ?? 0; ?></div>
                    <div>Active Teams</div>
                </div>
                <div style="padding: 15px; background: #dc2626; color: white; border-radius: 8px;">
                    <div style="font-size: 2em; font-weight: bold;"><?php echo $statusCounts['eliminated'] ?? 0; ?></div>
                    <div>Eliminated</div>
                </div>
                <div style="padding: 15px; background: #3b82f6; color: white; border-radius: 8px;">
                    <div style="font-size: 2em; font-weight: bold;"><?php echo $statusCounts['playoff_bound'] ?? 0; ?></div>
                    <div>Playoff Bound</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Teams List -->
    <div class="card">
        <div class="card-header">
            <h3>All NFL Teams</h3>
        </div>
        <div class="card-body">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Status</th>
                        <th>Eliminated Date</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $team): ?>
                        <tr class="team-row-<?php echo $team['playoff_status']; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($team['name']); ?></strong><br>
                                <span style="font-size: 0.9em; color: #6b7280;"><?php echo $team['abbreviation']; ?></span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                    <input type="hidden" name="eliminated_date" value="<?php echo date('Y-m-d'); ?>">
                                    <input type="hidden" name="notes" value="<?php echo htmlspecialchars($team['notes'] ?? ''); ?>">
                                    
                                    <select name="playoff_status" 
                                            onchange="this.form.submit()" 
                                            style="padding: 5px 10px; border-radius: 5px; border: 2px solid #d1d5db;">
                                        <option value="active" <?php echo $team['playoff_status'] === 'active' ? 'selected' : ''; ?>>
                                            ✅ Active
                                        </option>
                                        <option value="eliminated" <?php echo $team['playoff_status'] === 'eliminated' ? 'selected' : ''; ?>>
                                            ❌ Eliminated
                                        </option>
                                        <option value="playoff_bound" <?php echo $team['playoff_status'] === 'playoff_bound' ? 'selected' : ''; ?>>
                                            🏆 Playoff Bound
                                        </option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td>
                                <?php echo $team['eliminated_date'] ? date('M j, Y', strtotime($team['eliminated_date'])) : '-'; ?>
                            </td>
                            <td style="font-size: 0.9em; color: #6b7280;">
                                <?php echo htmlspecialchars($team['notes'] ?: '-'); ?>
                            </td>
                            <td>
                                <button onclick="editNotes(<?php echo $team['id']; ?>, '<?php echo addslashes($team['notes'] ?? ''); ?>')" 
                                        class="btn btn-sm btn-secondary">
                                    📝 Edit Notes
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Notes Modal -->
<div id="notesModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%;">
        <h3 style="margin-top: 0;">Edit Notes</h3>
        <form method="POST">
            <input type="hidden" name="team_id" id="modal_team_id">
            <input type="hidden" name="playoff_status" id="modal_status">
            <input type="hidden" name="eliminated_date" value="<?php echo date('Y-m-d'); ?>">
            <input type="hidden" name="update_status" value="1">
            
            <textarea name="notes" id="modal_notes" rows="4" 
                      style="width: 100%; padding: 10px; border: 2px solid #d1d5db; border-radius: 5px; font-family: inherit;"
                      placeholder="Enter elimination notes or reason..."></textarea>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Save Notes</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.team-row-eliminated {
    background: #fee2e2;
}
.team-row-playoff_bound {
    background: #dbeafe;
}
.team-row-active {
    background: white;
}
.data-table {
    border-collapse: collapse;
}
.data-table th, .data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.data-table th {
    background: #f3f4f6;
    font-weight: bold;
}
.btn-sm {
    padding: 5px 15px;
    font-size: 0.9em;
}
</style>

<script>
function editNotes(teamId, currentNotes) {
    document.getElementById('modal_team_id').value = teamId;
    document.getElementById('modal_notes').value = currentNotes;
    
    // Get current status from the select dropdown
    const row = event.target.closest('tr');
    const select = row.querySelector('select[name="playoff_status"]');
    document.getElementById('modal_status').value = select.value;
    
    document.getElementById('notesModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('notesModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('notesModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php 
include 'admin-nav-footer.php';
include 'admin-footer.php'; 
?>
