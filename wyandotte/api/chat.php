<?php
// Prevent any output before JSON
ob_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

// Generate or get user ID
function getUserId($providedId = null) {
    if ($providedId && preg_match('/^[a-z0-9]{6}$/', $providedId)) {
        return $providedId;
    }
    // Generate random 6 character ID
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6);
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get';

    if ($action === 'post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle FormData from JavaScript
        $username = trim($_POST['username'] ?? 'Anonymous');
        $message = trim($_POST['message'] ?? '');
        $avatar = $_POST['avatar'] ?? 'football';
        $userId = getUserId($_POST['user_id'] ?? null);
        
        if (empty($message)) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit;
        }
        
        if (strlen($message) > 500) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Message too long (max 500 characters)']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO wyandotte_chat (username, user_ip, avatar, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $userId, $avatar, $message]);
        
        ob_clean();
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'user_id' => $userId]);
        
    } elseif ($action === 'get') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        
        // Validate limit is a positive integer
        if ($limit < 1) $limit = 50;
        if ($limit > 500) $limit = 500;
        
        $stmt = $pdo->query("SELECT * FROM wyandotte_chat ORDER BY created_at DESC LIMIT " . $limit);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ob_clean();
        echo json_encode(['success' => true, 'messages' => $messages]);
        
    } elseif ($action === 'latest') {
        $stmt = $pdo->query("SELECT * FROM wyandotte_chat ORDER BY created_at DESC LIMIT 1");
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => $message]);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
