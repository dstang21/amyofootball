<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config.php';

$action = $_GET['action'] ?? 'get';

// Get short IP (last segment only)
function getShortIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $parts = explode('.', $ip);
    return end($parts); // Return last segment only
}

if ($action === 'post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($data['username'] ?? 'Anonymous');
    $message = trim($data['message'] ?? '');
    $avatar = $data['avatar'] ?? 'football';
    
    if (empty($message)) {
        echo json_encode(['error' => 'Message cannot be empty']);
        exit;
    }
    
    if (strlen($message) > 500) {
        echo json_encode(['error' => 'Message too long (max 500 characters)']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO wyandotte_chat (username, user_ip, avatar, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, getShortIp(), $avatar, $message]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    
} elseif ($action === 'get') {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    $stmt = $pdo->prepare("SELECT * FROM wyandotte_chat ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    
} elseif ($action === 'latest') {
    $stmt = $pdo->query("SELECT * FROM wyandotte_chat ORDER BY created_at DESC LIMIT 1");
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'message' => $message]);
}
