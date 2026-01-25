<?php
/**
 * Wyandotte Playoff Scoring Diagnostic Script (Windows/PHP Version)
 * Run this to identify what's wrong with the scoring system
 */

echo "=========================================\n";
echo "Wyandotte Playoff Scoring Diagnostics\n";
echo "=========================================\n\n";

require_once dirname(__DIR__) . '/config.php';

$issues = [];
$warnings = [];
$successes = [];

// Check 1: Table Existence
echo "1. Checking if cumulative stats table exists...\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wyandotte_player_playoff_stats'");
    if ($stmt->rowCount() > 0) {
        $successes[] = "✓ Table EXISTS";
        echo "   ✓ Table EXISTS\n\n";
        
        // Check record count
        echo "2. Checking record count...\n";
        $count = $pdo->query("SELECT COUNT(*) FROM wyandotte_player_playoff_stats")->fetchColumn();
        
        if ($count > 0) {
            $successes[] = "✓ Table has $count records";
            echo "   ✓ Table has $count records\n\n";
            
            echo "3. Sample data from table:\n";
            $stmt = $pdo->query("
                SELECT p.full_name, ps.pass_yards, ps.rush_yards, ps.receptions, ps.tackles_total, ps.games_played, ps.last_updated
                FROM wyandotte_player_playoff_stats ps
                JOIN players p ON ps.player_id = p.id
                ORDER BY (ps.pass_yards + ps.rush_yards + ps.receptions) DESC
                LIMIT 10
            ");
            
            printf("   %-25s | %8s | %8s | %4s | %7s | %6s | %s\n", 
                "Player", "PassYds", "RushYds", "Rec", "Tackles", "Games", "Updated");
            echo "   " . str_repeat("─", 110) . "\n";
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                printf("   %-25s | %8d | %8d | %4d | %7d | %6d | %s\n",
                    substr($row['full_name'], 0, 25),
                    $row['pass_yards'],
                    $row['rush_yards'],
                    $row['receptions'],
                    $row['tackles_total'],
                    $row['games_played'],
                    $row['last_updated']
                );
            }
            echo "\n";
        } else {
            $issues[] = "✗ Table is EMPTY - No stats recorded yet";
            echo "   ✗ Table is EMPTY - No stats recorded yet\n";
            echo "   ACTION REQUIRED: Run 'php wyandotte/admin/update-cumulative-stats.php'\n\n";
        }
    } else {
        $issues[] = "✗ Table MISSING";
        echo "   ✗ Table MISSING\n";
        echo "   ACTION REQUIRED: Run 'php wyandotte/migrations/run_playoff_stats_migration.php'\n\n";
    }
} catch (Exception $e) {
    $issues[] = "✗ Database error: " . $e->getMessage();
    echo "   ✗ Database error: " . $e->getMessage() . "\n\n";
}

// Check 4: API Endpoint
echo "4. Checking API endpoint...\n";
try {
    $apiUrl = __DIR__ . '/api/calculate-cumulative-scores.php';
    ob_start();
    include $apiUrl;
    $apiResponse = ob_get_clean();
    
    $data = json_decode($apiResponse, true);
    if (isset($data['success']) && $data['success']) {
        $successes[] = "✓ API is working";
        echo "   ✓ API is working\n";
        
        if (isset($data['team_scores']) && count($data['team_scores']) > 0) {
            echo "   Teams with scores: " . count($data['team_scores']) . "\n";
            echo "   Top 3 teams:\n";
            for ($i = 0; $i < min(3, count($data['team_scores'])); $i++) {
                $team = $data['team_scores'][$i];
                printf("     %d. %-20s - %6.1f pts (%d players)\n", 
                    $i + 1,
                    substr($team['team_name'], 0, 20),
                    $team['total_points'],
                    $team['player_count']
                );
            }
        } else {
            $warnings[] = "⚠ API working but no team scores returned";
            echo "   ⚠ API working but no team scores returned\n";
        }
    } else {
        $issues[] = "✗ API returned error";
        echo "   ✗ API returned error\n";
        if (isset($data['error'])) {
            echo "   Error: " . $data['error'] . "\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    $issues[] = "✗ API check failed: " . $e->getMessage();
    echo "   ✗ API check failed: " . $e->getMessage() . "\n\n";
}

// Check 5: Scoring Settings
echo "5. Checking scoring settings...\n";
try {
    $count = $pdo->query("SELECT COUNT(*) FROM wyandotte_scoring_settings")->fetchColumn();
    if ($count > 0) {
        $successes[] = "✓ Scoring settings configured ($count rules)";
        echo "   ✓ Scoring settings configured ($count rules)\n";
        
        // Show some key settings
        $stmt = $pdo->query("
            SELECT stat_key, points_value 
            FROM wyandotte_scoring_settings 
            WHERE stat_key IN ('pass_yards', 'pass_td', 'rush_yards', 'rush_td', 'rec_yards', 'rec_td', 'reception')
            ORDER BY stat_key
        ");
        echo "   Key scoring rules:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            printf("     %-15s = %6.2f pts\n", $row['stat_key'], $row['points_value']);
        }
    } else {
        $warnings[] = "⚠ No scoring settings found";
        echo "   ⚠ No scoring settings found\n";
    }
    echo "\n";
} catch (Exception $e) {
    $warnings[] = "⚠ Could not check scoring settings: " . $e->getMessage();
    echo "   ⚠ Could not check scoring settings: " . $e->getMessage() . "\n\n";
}

// Check 6: Rosters
echo "6. Checking rosters...\n";
try {
    $rosterCount = $pdo->query("SELECT COUNT(*) FROM wyandotte_rosters")->fetchColumn();
    $teamCount = $pdo->query("SELECT COUNT(*) FROM wyandotte_teams")->fetchColumn();
    
    echo "   Teams: $teamCount\n";
    echo "   Rostered players: $rosterCount\n";
    
    if ($teamCount > 0 && $rosterCount > 0) {
        $successes[] = "✓ Teams and rosters configured";
        
        // Check for ESPN IDs
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM wyandotte_rosters wr
            JOIN players p ON wr.player_id = p.id
            LEFT JOIN sleeper_players sp ON p.sleeper_id = sp.player_id
            WHERE sp.espn_id IS NOT NULL AND sp.espn_id != ''
        ");
        $withEspnIds = $stmt->fetchColumn();
        echo "   Players with ESPN IDs: $withEspnIds / $rosterCount\n";
        
        if ($withEspnIds < $rosterCount) {
            $warnings[] = "⚠ Some players missing ESPN IDs ($withEspnIds/$rosterCount)";
            echo "   ⚠ Warning: " . ($rosterCount - $withEspnIds) . " players missing ESPN IDs\n";
        }
    } else {
        $warnings[] = "⚠ No teams or rosters configured";
        echo "   ⚠ No teams or rosters configured\n";
    }
    echo "\n";
} catch (Exception $e) {
    $issues[] = "✗ Could not check rosters: " . $e->getMessage();
    echo "   ✗ Could not check rosters: " . $e->getMessage() . "\n\n";
}

// Summary
echo "=========================================\n";
echo "Diagnosis Summary\n";
echo "=========================================\n\n";

if (count($successes) > 0) {
    echo "Successes:\n";
    foreach ($successes as $success) {
        echo "  $success\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "Warnings:\n";
    foreach ($warnings as $warning) {
        echo "  $warning\n";
    }
    echo "\n";
}

if (count($issues) > 0) {
    echo "ISSUES FOUND:\n";
    foreach ($issues as $issue) {
        echo "  $issue\n";
    }
    echo "\n";
}

// Recommendations
echo "=========================================\n";
echo "Next Steps\n";
echo "=========================================\n\n";

if (in_array("✗ Table MISSING", $issues)) {
    echo "1. CREATE TABLE:\n";
    echo "   php wyandotte/migrations/run_playoff_stats_migration.php\n\n";
}

if (in_array("✗ Table is EMPTY - No stats recorded yet", $issues)) {
    echo "2. POPULATE DATA:\n";
    echo "   php wyandotte/admin/update-cumulative-stats.php\n\n";
}

if (count($issues) === 0 && count($warnings) === 0) {
    echo "✓ System appears to be working correctly!\n";
    echo "  View results at: https://amyofootball.com/wyandotte/rosters.php\n\n";
} elseif (count($issues) === 0) {
    echo "System is operational with minor warnings.\n";
    echo "Consider addressing warnings above.\n\n";
}

echo "3. SET UP AUTOMATION (recommended):\n";
echo "   Create a cron job to run update-cumulative-stats.php every hour\n";
echo "   Example crontab entry:\n";
echo "   0 * * * * cd /path/to/amyofootball/wyandotte/admin && php update-cumulative-stats.php\n\n";

echo "4. TEST:\n";
echo "   Visit https://amyofootball.com/wyandotte/rosters.php\n";
echo "   Check that player points are displaying\n";
echo "   Verify standings are correct\n\n";

echo "=========================================\n";
