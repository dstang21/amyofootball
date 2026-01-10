<?php
require_once 'config.php';

// Create admin user
$username = 'admin';
$password = 'admin123'; // Change this to your preferred password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        echo "Admin user already exists!\n";
    } else {
        // Insert admin user with ID 1
        $stmt = $pdo->prepare("INSERT INTO users (id, username, password) VALUES (1, ?, ?)");
        $stmt->execute([$username, $hashed_password]);
        echo "Admin user created successfully!\n";
        echo "Username: $username\n";
        echo "Password: $password\n";
        echo "Please change the password after first login.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
