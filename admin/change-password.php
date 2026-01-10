<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Change Password';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Get current user
        $user_id = $_SESSION[ADMIN_SESSION_NAME];
        $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect.";
        } else {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($update_stmt->execute([$new_password_hash, $user_id])) {
                $success = "Password changed successfully!";
            } else {
                $error = "Error updating password. Please try again.";
            }
        }
    }
}

// Get current user info
$user_id = $_SESSION[ADMIN_SESSION_NAME];
$user_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch();

include '../header.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>Change Password</h1>
        <p>Update your admin password for <?php echo htmlspecialchars($current_user['username']); ?></p>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div style="max-width: 500px; margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <h3>Update Your Password</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                        <small style="color: var(--text-secondary);">Enter your current password to confirm your identity</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                        <small style="color: var(--text-secondary);">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        <small style="color: var(--text-secondary);">Re-enter your new password</small>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">Change Password</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>

                <!-- Password Security Tips -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                    <h4>Password Security Tips</h4>
                    <ul style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 10px;">
                        <li>Use at least 8-12 characters</li>
                        <li>Include uppercase and lowercase letters</li>
                        <li>Include numbers and special characters</li>
                        <li>Avoid using personal information</li>
                        <li>Don't reuse passwords from other sites</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password strength indicator
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    // You could add a strength indicator here
    console.log('Password strength:', strength);
});

// Confirm password validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include '../footer.php'; ?>
