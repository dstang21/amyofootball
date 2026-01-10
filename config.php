<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u596677651_football');
define('DB_USER', 'u596677651_fball');
define('DB_PASS', 'MsJenny!81');

// Site configuration
define('SITE_NAME', 'AmyoFootball');
define('ADMIN_SESSION_NAME', 'amyo_admin');

// Start session
session_start();

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION[ADMIN_SESSION_NAME]);
}

function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION[ADMIN_SESSION_NAME] : null;
}

function isAdmin() {
    return getCurrentUserId() === 1 || getCurrentUserId() === '1';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>
