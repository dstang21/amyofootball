<?php
require_once '../config.php';

if (!isLoggedIn()) {
    redirect('../login.php');
}

$page_title = 'Database Migration - Add projected_stats_id';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['run_migration'])) {
    try {
        $pdo->beginTransaction();
        
        // Check if column already exists
        $check_column = $pdo->query("SHOW COLUMNS FROM projected_fantasy_points LIKE 'projected_stats_id'");
        if ($check_column->rowCount() == 0) {
            
            // Step 1: Add the new column
            $pdo->exec("ALTER TABLE projected_fantasy_points ADD COLUMN projected_stats_id BIGINT UNSIGNED NULL AFTER season_id");
            
            // Step 2: Update existing records to link them to projected_stats records
            $update_query = $pdo->prepare("
                UPDATE projected_fantasy_points pfp 
                SET projected_stats_id = (
                    SELECT ps.id 
                    FROM projected_stats ps 
                    WHERE ps.player_id = pfp.player_id 
                    AND ps.season_id = pfp.season_id 
                    LIMIT 1
                )
                WHERE projected_stats_id IS NULL
            ");
            $update_query->execute();
            $updated_rows = $update_query->rowCount();
            
            // Step 3: Delete orphaned records that couldn't be linked
            $delete_query = $pdo->exec("DELETE FROM projected_fantasy_points WHERE projected_stats_id IS NULL");
            
            // Step 4: Add foreign key constraint
            $pdo->exec("ALTER TABLE projected_fantasy_points ADD CONSTRAINT fk_projected_fantasy_points_stats FOREIGN KEY (projected_stats_id) REFERENCES projected_stats(id) ON DELETE CASCADE");
            
            // Step 5: Add index for better performance
            $pdo->exec("CREATE INDEX idx_projected_fantasy_points_stats ON projected_fantasy_points(projected_stats_id)");
            
            $pdo->commit();
            $success = "Migration completed successfully! Updated $updated_rows existing records and deleted $delete_query orphaned records.";
            
        } else {
            $error = "Column 'projected_stats_id' already exists. Migration has already been run.";
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Migration failed: " . $e->getMessage();
    }
}

// Check current state
$column_exists = false;
$record_count = 0;
try {
    $check_column = $pdo->query("SHOW COLUMNS FROM projected_fantasy_points LIKE 'projected_stats_id'");
    $column_exists = $check_column->rowCount() > 0;
    
    $count_query = $pdo->query("SELECT COUNT(*) as count FROM projected_fantasy_points");
    $record_count = $count_query->fetch()['count'];
} catch (Exception $e) {
    $error = "Error checking database state: " . $e->getMessage();
}

include 'admin-header.php';
include 'admin-nav.php';
?>

<div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
    <h1>Database Migration</h1>
    <p>Add projected_stats_id column to projected_fantasy_points table</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Migration Status</h3>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-col">
                <strong>Column 'projected_stats_id' exists:</strong> 
                <?php if ($column_exists): ?>
                    <span class="position-badge position-qb">✓ Yes</span>
                <?php else: ?>
                    <span class="position-badge position-def">✗ No</span>
                <?php endif; ?>
            </div>
            <div class="form-col">
                <strong>Projected Fantasy Points Records:</strong> 
                <span class="position-badge position-na"><?php echo $record_count; ?></span>
            </div>
        </div>
        
        <?php if (!$column_exists): ?>
            <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
                <h4 style="color: #856404; margin: 0 0 15px 0;">Migration Required</h4>
                <p style="color: #856404; margin: 0 0 20px 0;">
                    The database needs to be updated to support source-specific fantasy points calculations. 
                    This migration will:
                </p>
                <ul style="color: #856404; margin-left: 20px;">
                    <li>Add 'projected_stats_id' column to link fantasy points to specific projection sources</li>
                    <li>Update existing records to link to available projected stats</li>
                    <li>Remove any orphaned records that can't be linked</li>
                    <li>Add foreign key constraint and index for data integrity</li>
                </ul>
                
                <form method="POST" style="margin-top: 20px;">
                    <button type="submit" name="run_migration" class="btn btn-warning" 
                            onclick="return confirm('Are you sure you want to run this migration? This will modify your database structure.')">
                        Run Migration
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div style="margin-top: 30px; padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;">
                <h4 style="color: #155724; margin: 0 0 15px 0;">Migration Complete</h4>
                <p style="color: #155724; margin: 0;">
                    The database has been successfully updated. Fantasy points calculations now support multiple projection sources.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>What This Migration Does</h3>
    </div>
    <div class="card-body">
        <h4>Before Migration:</h4>
        <p>Fantasy points were linked to: player + season + scoring system</p>
        <p style="font-family: monospace; background: #f8f9fa; padding: 10px; border-radius: 5px;">
            projected_fantasy_points: [player_id, season_id, scoring_setting_id] → calculated_points
        </p>
        
        <h4 style="margin-top: 20px;">After Migration:</h4>
        <p>Fantasy points are now linked to: specific projected stats + scoring system</p>
        <p style="font-family: monospace; background: #f8f9fa; padding: 10px; border-radius: 5px;">
            projected_fantasy_points: [projected_stats_id, scoring_setting_id] → calculated_points<br>
            projected_stats: [player_id, season_id, source_id] → stats
        </p>
        
        <h4 style="margin-top: 20px;">Benefits:</h4>
        <ul>
            <li><strong>Multiple Sources:</strong> Players can have fantasy points calculated from different projection sources</li>
            <li><strong>Source Tracking:</strong> Know which projections generated which fantasy points</li>
            <li><strong>Data Integrity:</strong> Fantasy points are automatically deleted when projections are removed</li>
            <li><strong>Better Performance:</strong> Indexed relationships for faster queries</li>
        </ul>
    </div>
</div>

<?php include 'admin-footer.php'; ?>
