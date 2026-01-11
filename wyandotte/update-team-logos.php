<?php
require_once '../config.php';

// NFL team logos from ESPN
$nflLogos = [
    'ARI' => 'https://a.espncdn.com/i/teamlogos/nfl/500/ari.png',
    'ATL' => 'https://a.espncdn.com/i/teamlogos/nfl/500/atl.png',
    'BAL' => 'https://a.espncdn.com/i/teamlogos/nfl/500/bal.png',
    'BUF' => 'https://a.espncdn.com/i/teamlogos/nfl/500/buf.png',
    'CAR' => 'https://a.espncdn.com/i/teamlogos/nfl/500/car.png',
    'CHI' => 'https://a.espncdn.com/i/teamlogos/nfl/500/chi.png',
    'CIN' => 'https://a.espncdn.com/i/teamlogos/nfl/500/cin.png',
    'CLE' => 'https://a.espncdn.com/i/teamlogos/nfl/500/cle.png',
    'DAL' => 'https://a.espncdn.com/i/teamlogos/nfl/500/dal.png',
    'DEN' => 'https://a.espncdn.com/i/teamlogos/nfl/500/den.png',
    'DET' => 'https://a.espncdn.com/i/teamlogos/nfl/500/det.png',
    'GB' => 'https://a.espncdn.com/i/teamlogos/nfl/500/gb.png',
    'HOU' => 'https://a.espncdn.com/i/teamlogos/nfl/500/hou.png',
    'IND' => 'https://a.espncdn.com/i/teamlogos/nfl/500/ind.png',
    'JAX' => 'https://a.espncdn.com/i/teamlogos/nfl/500/jax.png',
    'KC' => 'https://a.espncdn.com/i/teamlogos/nfl/500/kc.png',
    'LAC' => 'https://a.espncdn.com/i/teamlogos/nfl/500/lac.png',
    'LAR' => 'https://a.espncdn.com/i/teamlogos/nfl/500/lar.png',
    'LV' => 'https://a.espncdn.com/i/teamlogos/nfl/500/lv.png',
    'MIA' => 'https://a.espncdn.com/i/teamlogos/nfl/500/mia.png',
    'MIN' => 'https://a.espncdn.com/i/teamlogos/nfl/500/min.png',
    'NE' => 'https://a.espncdn.com/i/teamlogos/nfl/500/ne.png',
    'NO' => 'https://a.espncdn.com/i/teamlogos/nfl/500/no.png',
    'NYG' => 'https://a.espncdn.com/i/teamlogos/nfl/500/nyg.png',
    'NYJ' => 'https://a.espncdn.com/i/teamlogos/nfl/500/nyj.png',
    'PHI' => 'https://a.espncdn.com/i/teamlogos/nfl/500/phi.png',
    'PIT' => 'https://a.espncdn.com/i/teamlogos/nfl/500/pit.png',
    'SEA' => 'https://a.espncdn.com/i/teamlogos/nfl/500/sea.png',
    'SF' => 'https://a.espncdn.com/i/teamlogos/nfl/500/sf.png',
    'TB' => 'https://a.espncdn.com/i/teamlogos/nfl/500/tb.png',
    'TEN' => 'https://a.espncdn.com/i/teamlogos/nfl/500/ten.png',
    'WSH' => 'https://a.espncdn.com/i/teamlogos/nfl/500/wsh.png'
];

// First, add logo column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE teams ADD COLUMN logo VARCHAR(500) DEFAULT NULL");
    echo "<p style='color: green;'>✓ Logo column added to teams table</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<p style='color: blue;'>Logo column already exists</p>";
    } else {
        echo "<p style='color: red;'>Error adding column: " . $e->getMessage() . "</p>";
    }
}

// Get all NFL teams
$stmt = $pdo->query("SELECT id, name, abbreviation, logo FROM teams");
$teams = $stmt->fetchAll();

echo "<h2>Updating NFL Team Logos</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Team Name</th><th>Abbreviation</th><th>Current Logo</th><th>New Logo</th><th>Status</th></tr>";

$updated = 0;
$skipped = 0;

foreach ($teams as $team) {
    $abbr = strtoupper($team['abbreviation']);
    $logoUrl = $nflLogos[$abbr] ?? null;
    
    echo "<tr>";
    echo "<td>{$team['name']}</td>";
    echo "<td>{$abbr}</td>";
    echo "<td>" . ($team['logo'] ? "Has logo" : "Empty") . "</td>";
    
    if ($logoUrl) {
        echo "<td><img src='{$logoUrl}' width='50'></td>";
        
        // Update the database
        $updateStmt = $pdo->prepare("UPDATE teams SET logo = ? WHERE id = ?");
        $updateStmt->execute([$logoUrl, $team['id']]);
        echo "<td style='color: green;'>✓ Updated</td>";
        $updated++;
    } else {
        echo "<td>No logo found</td>";
        echo "<td style='color: red;'>✗ Skipped (abbr: {$abbr})</td>";
        $skipped++;
    }
    
    echo "</tr>";
}

echo "</table>";
echo "<br><p><strong>Summary:</strong> Updated {$updated} teams, Skipped {$skipped} teams</p>";
echo "<p><a href='rosters.php'>← Back to Rosters</a></p>";
?>
