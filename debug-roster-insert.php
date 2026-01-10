<?php
require_once 'config.php';

echo "<h2>Debug Roster Insert Issue</h2>";

$testLeagueId = '1062950707059302400';

try {
    // First, check if the sleeper_rosters table exists
    echo "<h3>Step 1: Check Table Structure</h3>";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'sleeper_rosters'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "<p style='color: red;'>❌ sleeper_rosters table does not exist!</p>";
        echo "<p>You need to create the table first using the SQL I provided earlier.</p>";
        exit;
    } else {
        echo "<p style='color: green;'>✅ sleeper_rosters table exists</p>";
    }
    
    // Check table structure
    echo "<h4>Table Structure:</h4>";
    $stmt = $pdo->query("DESCRIBE sleeper_rosters");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>Step 2: Test Manual Roster Insert</h3>";
    
    // Get sample roster data from API
    $url = "https://api.sleeper.app/v1/league/$testLeagueId/rosters";
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'AmyoFootball/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    $rosters = json_decode($response, true);
    
    if (empty($rosters)) {
        echo "<p style='color: red;'>❌ Could not get roster data from API</p>";
        exit;
    }
    
    $roster = $rosters[0]; // Use first roster
    echo "<p>Testing with roster data:</p>";
    echo "<pre>" . htmlspecialchars(json_encode($roster, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Try the exact same INSERT that SleeperSync uses
    $sql = "INSERT INTO sleeper_rosters 
            (league_id, roster_id, owner_id, wins, losses, ties, fpts, fpts_decimal, fpts_against, fpts_against_decimal, total_moves, waiver_position, waiver_budget_used, players, starters, reserve, settings)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            owner_id = VALUES(owner_id),
            wins = VALUES(wins),
            losses = VALUES(losses),
            ties = VALUES(ties),
            fpts = VALUES(fpts),
            fpts_decimal = VALUES(fpts_decimal),
            fpts_against = VALUES(fpts_against),
            fpts_against_decimal = VALUES(fpts_against_decimal),
            total_moves = VALUES(total_moves),
            waiver_position = VALUES(waiver_position),
            waiver_budget_used = VALUES(waiver_budget_used),
            players = VALUES(players),
            starters = VALUES(starters),
            reserve = VALUES(reserve),
            settings = VALUES(settings)";

    echo "<h4>Attempting Insert...</h4>";
    
    // Enable error reporting for PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare($sql);
    
    $params = [
        $testLeagueId,
        $roster['roster_id'],
        $roster['owner_id'],
        $roster['settings']['wins'] ?? 0,
        $roster['settings']['losses'] ?? 0,
        $roster['settings']['ties'] ?? 0,
        $roster['settings']['fpts'] ?? 0.00,
        $roster['settings']['fpts_decimal'] ?? 0.00,
        $roster['settings']['fpts_against'] ?? 0.00,
        $roster['settings']['fpts_against_decimal'] ?? 0.00,
        $roster['settings']['total_moves'] ?? 0,
        $roster['settings']['waiver_position'] ?? 0,
        $roster['settings']['waiver_budget_used'] ?? 0,
        json_encode($roster['players'] ?? []),
        json_encode($roster['starters'] ?? []),
        json_encode($roster['reserve'] ?? []),
        json_encode($roster['settings'] ?? [])
    ];
    
    echo "<p>Parameters being inserted:</p>";
    echo "<ol>";
    foreach ($params as $i => $param) {
        $value = is_string($param) && strlen($param) > 100 ? substr($param, 0, 100) . '...' : $param;
        echo "<li>" . htmlspecialchars(var_export($value, true)) . "</li>";
    }
    echo "</ol>";
    
    $result = $stmt->execute($params);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Manual insert successful!</p>";
        echo "<p>Rows affected: " . $stmt->rowCount() . "</p>";
        
        // Check if it's actually in the database
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM sleeper_rosters WHERE league_id = ?");
        $checkStmt->execute([$testLeagueId]);
        $count = $checkStmt->fetch()['count'];
        echo "<p>Total rosters for this league: $count</p>";
        
        // Check what rosters exist with roster_id = 1
        echo "<h4>🔍 Checking existing roster_id = 1 records:</h4>";
        $conflictStmt = $pdo->prepare("SELECT league_id, roster_id, owner_id FROM sleeper_rosters WHERE roster_id = 1");
        $conflictStmt->execute();
        $conflicts = $conflictStmt->fetchAll();
        
        if (!empty($conflicts)) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>League ID</th><th>Roster ID</th><th>Owner ID</th></tr>";
            foreach ($conflicts as $conflict) {
                echo "<tr><td>{$conflict['league_id']}</td><td>{$conflict['roster_id']}</td><td>{$conflict['owner_id']}</td></tr>";
            }
            echo "</table>";
            echo "<p style='color: orange;'>⚠️ The roster_id constraint is preventing inserts because roster_id=1 already exists in another league!</p>";
            echo "<p><strong>Fix needed:</strong> Change the unique constraint to be on (league_id, roster_id) instead of just roster_id.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Manual insert failed</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
    echo "<p>Error Code: " . $e->getCode() . "</p>";
    
    if (strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "<h4>🔧 Fix: Create the missing table</h4>";
        echo "<p>Run the SQL table creation script I provided earlier to create the sleeper_rosters table.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
