<?php
require_once '../config.php';

$total = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
$matched = $pdo->query("SELECT COUNT(*) FROM players WHERE sleeper_id IS NOT NULL AND sleeper_id != ''")->fetchColumn();
$unmatched = $pdo->query("SELECT COUNT(*) FROM players WHERE sleeper_id IS NULL OR sleeper_id = ''")->fetchColumn();

echo "<h2>Player Match Statistics</h2>";
echo "<p><strong>Total players:</strong> $total</p>";
echo "<p><strong>Already matched:</strong> $matched</p>";
echo "<p><strong>Unmatched:</strong> $unmatched</p>";
echo "<hr>";

// Sample some unmatched players to see what we're dealing with
$samples = $pdo->query("SELECT full_name FROM players WHERE sleeper_id IS NULL OR sleeper_id = '' LIMIT 20")->fetchAll();
echo "<h3>Sample Unmatched Players:</h3><ul>";
foreach ($samples as $s) {
    echo "<li>" . htmlspecialchars($s['full_name']) . "</li>";
}
echo "</ul>";
