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
    'WAS' => 'https://a.espncdn.com/i/teamlogos/nfl/500/wsh.png'
];

// Team name to NFL abbreviation mapping (based on Wyandotte teams)
$teamMapping = [
    'Rams' => 'LAR',
    'Commanders' => 'WAS',
    'Steelers' => 'PIT',
    'Packers' => 'GB',
    'Lions' => 'DET',
    'Ravens' => 'BAL',
    'Bills' => 'BUF',
    'Texans' => 'HOU',
    'Vikings' => 'MIN',
    'Eagles' => 'PHI'
];

// Get all teams
$stmt = $pdo->query("SELECT id, team_name, logo FROM wyandotte_teams");
$teams = $stmt->fetchAll();

echo "<h2>Updating Team Logos</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Team Name</th><th>NFL Team</th><th>Current Logo</th><th>New Logo</th><th>Status</th></tr>";

foreach ($teams as $team) {
    $teamName = $team['team_name'];
    $nflAbbr = null;
    $logoUrl = null;
    
    // Find matching NFL team
    foreach ($teamMapping as $keyword => $abbr) {
        if (stripos($teamName, $keyword) !== false) {
            $nflAbbr = $abbr;
            $logoUrl = $nflLogos[$abbr] ?? null;
            break;
        }
    }
    
    echo "<tr>";
    echo "<td>{$teamName}</td>";
    echo "<td>" . ($nflAbbr ?? 'Not found') . "</td>";
    echo "<td>" . ($team['logo'] ? "✓" : "Empty") . "</td>";
    
    if ($logoUrl) {
        echo "<td><img src='{$logoUrl}' width='50'></td>";
        
        // Update the database
        $updateStmt = $pdo->prepare("UPDATE wyandotte_teams SET logo = ? WHERE id = ?");
        $updateStmt->execute([$logoUrl, $team['id']]);
        echo "<td style='color: green;'>✓ Updated</td>";
    } else {
        echo "<td>No logo</td>";
        echo "<td style='color: red;'>✗ Skipped</td>";
    }
    
    echo "</tr>";
}

echo "</table>";
echo "<br><p><a href='rosters.php'>← Back to Rosters</a></p>";
?>
