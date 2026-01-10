<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>AmyoFootball - CSS Test</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <h1>CSS Test Page - Admin Directory</h1>
        </div>
    </header>
    
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Testing CSS from Admin Directory</h2>
            </div>
            <div class="card-body">
                <p>If you can see this styled correctly, the CSS is loading from the admin directory.</p>
                <button class="btn btn-primary">Test Button</button>
            </div>
        </div>
    </div>
</body>
</html>
