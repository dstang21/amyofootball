<?php
require_once dirname(__DIR__) . '/config.php';

// Check players table structure
$stmt = $pdo->query("DESCRIBE players");
echo "Players Table:\n";
print_r($stmt->fetchAll());

// Get sample players with positions
$stmt = $pdo->query("
    SELECT DISTINCT p.id, p.full_name, pt.position 
    FROM players p 
    LEFT JOIN player_teams pt ON p.id = pt.player_id 
    LIMIT 20
");
echo "\n\nSample Players:\n";
print_r($stmt->fetchAll());
?>
