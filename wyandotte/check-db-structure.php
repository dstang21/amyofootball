<?php
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: text/plain');

echo "=== Checking Wyandotte Tables ===\n\n";

try {
    // Check wyandotte_chat
    $stmt = $pdo->query("SHOW TABLES LIKE 'wyandotte_chat'");
    $exists = $stmt->fetch();
    echo "wyandotte_chat: " . ($exists ? "EXISTS" : "MISSING") . "\n";
    
    // Check wyandotte_plays
    $stmt = $pdo->query("SHOW TABLES LIKE 'wyandotte_plays'");
    $exists = $stmt->fetch();
    echo "wyandotte_plays: " . ($exists ? "EXISTS" : "MISSING") . "\n";
    
    // Check wyandotte_teams
    $stmt = $pdo->query("SHOW TABLES LIKE 'wyandotte_teams'");
    $exists = $stmt->fetch();
    echo "wyandotte_teams: " . ($exists ? "EXISTS" : "MISSING") . "\n";
    
    // Check wyandotte_rosters
    $stmt = $pdo->query("SHOW TABLES LIKE 'wyandotte_rosters'");
    $exists = $stmt->fetch();
    echo "wyandotte_rosters: " . ($exists ? "EXISTS" : "MISSING") . "\n";
    
    echo "\n=== Chat Messages Count ===\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM wyandotte_chat");
        $result = $stmt->fetch();
        echo "Total messages: " . $result['count'] . "\n";
        
        $stmt = $pdo->query("SELECT * FROM wyandotte_chat ORDER BY created_at DESC LIMIT 3");
        $messages = $stmt->fetchAll();
        echo "\nLatest messages:\n";
        foreach ($messages as $msg) {
            echo "  - [{$msg['created_at']}] {$msg['username']}: " . substr($msg['message'], 0, 50) . "...\n";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
