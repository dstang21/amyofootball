<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Check credentials
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION[ADMIN_SESSION_NAME] = $user['id'];
            redirect('admin/dashboard.php');
        } else {
            $error = "Invalid username or password.";
        }
    }
}

$page_title = 'Admin Login';
include 'header.php';
?>

<div class="container" style="max-width: 500px; margin-top: 50px;">
    <div class="card">
        <div class="card-header">
            <h2>Admin Login</h2>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
            
            <p style="margin-top: 20px; text-align: center; color: var(--text-secondary);">
                Access the admin dashboard to manage players, rankings, and statistics.
            </p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
