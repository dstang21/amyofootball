<?php
require_once 'config.php';

echo "<h2>Fix Foreign Key Constraints and Indexes</h2>";

try {
    // First, let's see what foreign keys reference this table
    echo "<h3>Foreign Key Constraints Referencing sleeper_rosters:</h3>";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_NAME = 'sleeper_rosters'
        AND TABLE_SCHEMA = DATABASE()
    ");
    $foreignKeys = $stmt->fetchAll();
    
    if (empty($foreignKeys)) {
        echo "<p>No foreign key constraints found referencing sleeper_rosters.</p>";
        
        // Let's check what's preventing us from dropping the indexes
        echo "<h3>Checking what's preventing index drops...</h3>";
        
        // Try a different approach - let's see the actual table creation
        $stmt = $pdo->query("SHOW CREATE TABLE sleeper_rosters");
        $createTable = $stmt->fetch();
        echo "<pre>" . htmlspecialchars($createTable['Create Table']) . "</pre>";
        
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Constraint</th><th>Table</th><th>Column</th><th>References</th></tr>";
        foreach ($foreignKeys as $fk) {
            echo "<tr><td>{$fk['CONSTRAINT_NAME']}</td><td>{$fk['TABLE_NAME']}</td><td>{$fk['COLUMN_NAME']}</td><td>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</td></tr>";
        }
        echo "</table>";
        
        // Drop foreign key constraints first
        echo "<h3>Dropping Foreign Key Constraints:</h3>";
        foreach ($foreignKeys as $fk) {
            try {
                $sql = "ALTER TABLE `{$fk['TABLE_NAME']}` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`";
                echo "<p>Executing: $sql</p>";
                $pdo->exec($sql);
                echo "<p style='color: green;'>✅ Dropped foreign key: {$fk['CONSTRAINT_NAME']}</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ Error dropping {$fk['CONSTRAINT_NAME']}: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Now try to remove the problematic unique constraints
    echo "<h3>Removing Problematic Unique Constraints:</h3>";
    
    $indexesToDrop = ['unique_roster_id']; // Only drop the globally unique one
    
    foreach ($indexesToDrop as $indexName) {
        try {
            $sql = "ALTER TABLE sleeper_rosters DROP INDEX `$indexName`";
            echo "<p>Executing: $sql</p>";
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ Dropped index: $indexName</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Error dropping $indexName: " . $e->getMessage() . "</p>";
        }
    }
    
    // Show final indexes
    echo "<h3>Final Indexes on sleeper_rosters:</h3>";
    $stmt = $pdo->query("SHOW INDEX FROM sleeper_rosters");
    $indexes = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th></tr>";
    foreach ($indexes as $index) {
        echo "<tr><td>{$index['Key_name']}</td><td>{$index['Column_name']}</td><td>" . ($index['Non_unique'] == 0 ? 'YES' : 'NO') . "</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>✅ Ready to test sync!</h3>";
    echo "<p><a href='debug-roster-insert.php'>Test Roster Insert</a> | <a href='sync-historical-data.php'>Sync Historical Data</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
