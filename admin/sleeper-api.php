<?php
// Suppress all output until we're ready to send JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config.php';

// Clean any output that might have been generated
ob_clean();
header('Content-Type: application/json');

// Check if user is admin
if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'SleeperController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$action = $_POST['action'] ?? '';
$leagueId = $_POST['league_id'] ?? '';

if ($action !== 'sync' || !$leagueId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action or missing league ID']);
    exit();
}

try {
    $controller = new SleeperController();
    
    if (!$controller->pdo) {
        throw new Exception("Database connection failed in SleeperController");
    }
    
    $result = $controller->syncLeague($leagueId);
    
    // Clean any remaining output before sending JSON
    ob_clean();
    echo json_encode($result);
} catch (Exception $e) {
    // Clean any output and send error
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Error $e) {
    // Catch PHP Fatal errors too
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'PHP Error: ' . $e->getMessage()]);
}
