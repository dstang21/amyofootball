<?php
require_once dirname(__DIR__, 2) . '/config.php';

echo "<h2>Fixing Incorrect ESPN IDs</h2>";

$corrections = [
    'Drake Maye' => '4431452',
    'Kyren Williams' => '4430737',
    'Kenneth Walker III' => '4567048',
];

foreach ($corrections as $playerName => $correctEspnId) {
    echo "<p>Updating {$playerName} to ESPN ID {$correctEspnId}...</p>";
    
    $stmt = $pdo->prepare("
        UPDATE sleeper_players sp
        JOIN players p ON sp.player_id = p.sleeper_id
        SET sp.espn_id = ?
        WHERE p.full_name = ?
    ");
    $stmt->execute([$correctEspnId, $playerName]);
    
    echo "<p style='color: green;'>✓ Updated</p>";
}

echo "<hr>";
echo "<h3>✓ All ESPN IDs corrected!</h3>";
echo "<p><a href='update-cumulative-stats.php' style='padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px;'>Run Stats Update</a></p>";
?>
