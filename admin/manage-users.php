<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Manage Users';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = sanitize($_POST['username']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($username) || empty($password) || empty($confirm_password)) {
                    $error = "Please fill in all fields.";
                } elseif ($password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } elseif (strlen($password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } else {
                    // Check if username already exists
                    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $check_stmt->execute([$username]);
                    
                    if ($check_stmt->fetch()) {
                        $error = "Username already exists.";
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                        
                        if ($stmt->execute([$username, $password_hash])) {
                            $success = "User added successfully!";
                        } else {
                            $error = "Error adding user.";
                        }
                    }
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                $current_user_id = $_SESSION[ADMIN_SESSION_NAME];
                
                if ($id == $current_user_id) {
                    $error = "You cannot delete your own account.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $success = "User deleted successfully!";
                    } else {
                        $error = "Error deleting user.";
                    }
                }
                break;
        }
    }
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY username")->fetchAll();
$current_user_id = $_SESSION[ADMIN_SESSION_NAME];

include '../header.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Manage Admin Users</h1>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Add User Form -->
        <div class="card">
            <div class="card-header">
                <h3>Add New Admin User</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required minlength="6">
                        <small style="color: var(--text-secondary);">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add User</button>
                </form>
            </div>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                <h3>All Admin Users (<?php echo count($users); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="player-cell">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                        <?php if ($user['id'] == $current_user_id): ?>
                                            <span class="position-badge position-qb" style="margin-left: 5px; font-size: 0.7rem;">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td>
                                        <span class="position-badge position-qb">Active</span>
                                    </td>
                                    <td>
                                        <?php if ($user['id'] == $current_user_id): ?>
                                            <a href="change-password.php" class="btn btn-warning btn-sm">Change Password</a>
                                        <?php else: ?>
                                            <form method="POST" action="" style="display: inline;" 
                                                  onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Security Notice -->
    <div class="card">
        <div class="card-header">
            <h3>Security Information</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Important:</strong> Only add trusted users as admins. Admin users can:
                <ul style="margin: 10px 0 0 20px;">
                    <li>Manage all players, teams, and stats</li>
                    <li>Update rankings and projections</li>
                    <li>Access all administrative functions</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include '../footer.php'; ?>
