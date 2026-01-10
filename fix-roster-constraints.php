<?php
require_once 'config.php';

echo "<h2>Fix Sleeper Rosters Table Constraints</h2>";

try {
    // Show current indexes
    echo "<h3>Current Indexes on sleeper_rosters:</h3>";
    $stmt = $pdo->query("SHOW INDEX FROM sleeper_rosters");
    $indexes = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th></tr>";
    foreach ($indexes as $index) {
        echo "<tr><td>{$index['Key_name']}</td><td>{$index['Column_name']}</td><td>" . ($index['Non_unique'] == 0 ? 'YES' : 'NO') . "</td></tr>";
    }
    echo "</table>";
    
    // Find the unique constraint on roster_id
    $uniqueIndexes = [];
    foreach ($indexes as $index) {
        if ($index['Non_unique'] == 0 && $index['Column_name'] == 'roster_id') {
            $uniqueIndexes[] = $index['Key_name'];
        }
    }
    
    if (!empty($uniqueIndexes)) {
        echo "<h3>Fixing Unique Constraint Issue:</h3>";
        
        foreach ($uniqueIndexes as $indexName) {
            echo "<p>Dropping unique index: $indexName</p>";
            try {
                $pdo->exec("ALTER TABLE sleeper_rosters DROP INDEX `$indexName`");
                echo "<p style='color: green;'>✅ Dropped index: $indexName</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ Error dropping $indexName: " . $e->getMessage() . "</p>";
            }
        }
        
        // Add the proper composite unique constraint
        echo "<p>Adding composite unique constraint (league_id, roster_id)...</p>";
        try {
            $pdo->exec("ALTER TABLE sleeper_rosters ADD UNIQUE KEY unique_league_roster (league_id, roster_id)");
            echo "<p style='color: green;'>✅ Added composite unique constraint</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error adding constraint: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p>No unique constraint found on roster_id column.</p>";
        
        // Check if the composite constraint already exists
        $hasComposite = false;
        foreach ($indexes as $index) {
            if ($index['Key_name'] == 'unique_league_roster') {
                $hasComposite = true;
                break;
            }
        }
        
        if (!$hasComposite) {
            echo "<p>Adding composite unique constraint (league_id, roster_id)...</p>";
            try {
                $pdo->exec("ALTER TABLE sleeper_rosters ADD UNIQUE KEY unique_league_roster (league_id, roster_id)");
                echo "<p style='color: green;'>✅ Added composite unique constraint</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ Error adding constraint: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Composite unique constraint already exists</p>";
        }
    }
    
    echo "<h3>Updated Indexes:</h3>";
    $stmt = $pdo->query("SHOW INDEX FROM sleeper_rosters");
    $newIndexes = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th></tr>";
    foreach ($newIndexes as $index) {
        echo "<tr><td>{$index['Key_name']}</td><td>{$index['Column_name']}</td><td>" . ($index['Non_unique'] == 0 ? 'YES' : 'NO') . "</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>✅ Table constraints fixed!</h3>";
    echo "<p>Now try running the sync again: <a href='sync-historical-data.php'>Sync Historical Data</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
