<?php
// Check what fantasy team/user tables exist on production
require_once dirname(__DIR__) . '/config.php';

echo "=== CHECKING FANTASY TABLES ===\n\n";

// Check for sleeper league users
try {
    $stmt = $pdo->query("DESCRIBE sleeper_league_users");
    echo "✓ sleeper_league_users table exists\n";
    echo "Columns:\n";
    while ($row = $stmt->fetch()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sleeper_league_users");
    $result = $stmt->fetch();
    echo "\nTotal users: {$result['count']}\n\n";
    
    if ($result['count'] > 0) {
        $stmt = $pdo->query("SELECT * FROM sleeper_league_users LIMIT 10");
        echo "Sample users:\n";
        while ($user = $stmt->fetch()) {
            echo "  - {$user['username']} (User ID: {$user['user_id']})\n";
        }
    }
} catch (PDOException $e) {
    echo "✗ sleeper_league_users table does NOT exist\n";
}

echo "\n";

// Check for sleeper rosters
try {
    $stmt = $pdo->query("DESCRIBE sleeper_rosters");
    echo "✓ sleeper_rosters table exists\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sleeper_rosters");
    $result = $stmt->fetch();
    echo "Total rosters: {$result['count']}\n";
} catch (PDOException $e) {
    echo "✗ sleeper_rosters table does NOT exist\n";
}

echo "\n";

// Check for fantasy_leagues
try {
    $stmt = $pdo->query("DESCRIBE fantasy_leagues");
    echo "✓ fantasy_leagues table exists\n";
    $stmt = $pdo->query("SELECT * FROM fantasy_leagues");
    echo "Leagues:\n";
    while ($league = $stmt->fetch()) {
        echo "  - {$league['display_name']} (ID: {$league['sleeper_league_id']})\n";
    }
} catch (PDOException $e) {
    echo "✗ fantasy_leagues table does NOT exist\n";
}
?>
