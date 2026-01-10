<?php
require_once dirname(__DIR__) . '/config.php';

echo "Checking existing fantasy/team tables...\n\n";

// Check for fantasy leagues
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM fantasy_leagues");
    $result = $stmt->fetch();
    echo "fantasy_leagues table exists - {$result['count']} leagues found\n";
    
    if ($result['count'] > 0) {
        $stmt = $pdo->query("SELECT * FROM fantasy_leagues LIMIT 5");
        $leagues = $stmt->fetchAll();
        echo "Sample leagues:\n";
        foreach ($leagues as $league) {
            echo "  - {$league['display_name']} (ID: {$league['id']})\n";
        }
    }
} catch (PDOException $e) {
    echo "fantasy_leagues table does NOT exist\n";
}

echo "\n";

// Check for sleeper rosters
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sleeper_rosters");
    $result = $stmt->fetch();
    echo "sleeper_rosters table exists - {$result['count']} rosters found\n";
    
    if ($result['count'] > 0) {
        $stmt = $pdo->query("SELECT sr.*, sl.username FROM sleeper_rosters sr LEFT JOIN sleeper_league_users sl ON sr.owner_id = sl.user_id LIMIT 5");
        $rosters = $stmt->fetchAll();
        echo "Sample rosters:\n";
        foreach ($rosters as $roster) {
            echo "  - Owner: {$roster['username']} (Roster ID: {$roster['roster_id']})\n";
        }
    }
} catch (PDOException $e) {
    echo "sleeper_rosters table does NOT exist\n";
}

echo "\n";

// Check for teams table
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teams");
    $result = $stmt->fetch();
    echo "teams table exists - {$result['count']} teams found\n";
} catch (PDOException $e) {
    echo "teams table does NOT exist\n";
}

echo "\n";

// Check if wyandotte tables exist
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wyandotte_teams");
    $result = $stmt->fetch();
    echo "wyandotte_teams table EXISTS - {$result['count']} teams found\n";
} catch (PDOException $e) {
    echo "wyandotte_teams table does NOT exist - needs migration\n";
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wyandotte_rosters");
    $result = $stmt->fetch();
    echo "wyandotte_rosters table EXISTS - {$result['count']} rosters found\n";
} catch (PDOException $e) {
    echo "wyandotte_rosters table does NOT exist - needs migration\n";
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wyandotte_scores");
    $result = $stmt->fetch();
    echo "wyandotte_scores table EXISTS - {$result['count']} scores found\n";
} catch (PDOException $e) {
    echo "wyandotte_scores table does NOT exist - needs migration\n";
}
?>
