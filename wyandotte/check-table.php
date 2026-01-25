<?php
require_once dirname(__DIR__) . '/config.php';

echo "Checking wyandotte_player_playoff_stats table...\n\n";

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wyandotte_player_playoff_stats'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table EXISTS\n\n";
        
        // Check row count
        $count = $pdo->query("SELECT COUNT(*) FROM wyandotte_player_playoff_stats")->fetchColumn();
        echo "Total records: $count\n\n";
        
        if ($count > 0) {
            echo "Sample data:\n";
            $stmt = $pdo->query("
                SELECT ps.*, p.full_name 
                FROM wyandotte_player_playoff_stats ps
                JOIN players p ON ps.player_id = p.id
                LIMIT 5
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "- {$row['full_name']}: ";
                echo "Pass Yds: {$row['pass_yards']}, Rush Yds: {$row['rush_yards']}, ";
                echo "Rec: {$row['receptions']}, Tackles: {$row['tackles_total']}\n";
            }
        } else {
            echo "⚠ Table is EMPTY - no stats recorded yet\n";
        }
    } else {
        echo "✗ Table MISSING\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
