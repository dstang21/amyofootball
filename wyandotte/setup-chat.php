<?php
require_once dirname(__DIR__) . '/config.php';

// Create chat table
$sql = file_get_contents(__DIR__ . '/chat_table.sql');

try {
    $pdo->exec($sql);
    echo "✓ Chat table created successfully!<br>";
    echo "<a href='rosters.php'>Go to Rosters</a>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
