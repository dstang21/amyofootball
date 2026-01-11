<?php
require_once '../config.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../index.php');
}

$page_title = 'Match Sleeper Players';

// Run auto-matching if requested
$autoMatchResults = null;
if (isset($_POST['auto_match'])) {
    $autoMatchResults = [
        'matched' => 0,
        'skipped' => 0,
        'total' => 0
    ];
    
    try {
        $pdo->beginTransaction();
        
        // Get all players without sleeper_id
        $stmt = $pdo->query("
            SELECT id, first_name, last_name, full_name 
            FROM players 
            WHERE sleeper_id IS NULL OR sleeper_id = ''
        ");
        $players = $stmt->fetchAll();
        $autoMatchResults['total'] = count($players);
        
        foreach ($players as $player) {
            $matched = false;
            
            // Try exact full name match (case insensitive)
            $sleeper_stmt = $pdo->prepare("
                SELECT player_id, first_name, last_name, position, team
                FROM sleeper_players 
                WHERE LOWER(CONCAT(first_name, ' ', last_name)) = LOWER(?)
                LIMIT 1
            ");
            $sleeper_stmt->execute([$player['full_name']]);
            $sleeper_player = $sleeper_stmt->fetch();
            
            if ($sleeper_player) {
                $update = $pdo->prepare("UPDATE players SET sleeper_id = ? WHERE id = ?");
                $update->execute([$sleeper_player['player_id'], $player['id']]);
                $autoMatchResults['matched']++;
                $matched = true;
            }
            
            // If no match, try removing Jr, II, III, IV suffixes
            if (!$matched) {
                $cleanName = preg_replace('/\s+(Jr\.?|II|III|IV|Sr\.?)$/i', '', trim($player['full_name']));
                if ($cleanName !== $player['full_name']) {
                    // Search for players with this cleaned name
                    $sleeper_stmt = $pdo->prepare("
                        SELECT player_id, first_name, last_name, position, team
                        FROM sleeper_players 
                        WHERE LOWER(CONCAT(first_name, ' ', last_name)) = LOWER(?)
                    ");
                    $sleeper_stmt->execute([$cleanName]);
                    $matches = $sleeper_stmt->fetchAll();
                    
                    // Only auto-match if there's exactly one match
                    if (count($matches) === 1) {
                        $update = $pdo->prepare("UPDATE players SET sleeper_id = ? WHERE id = ?");
                        $update->execute([$matches[0]['player_id'], $player['id']]);
                        $autoMatchResults['matched']++;
                        $matched = true;
                    }
                }
            }
            
            if (!$matched) {
                $autoMatchResults['skipped']++;
            }
        }
        
        $pdo->commit();
        $success = "Auto-matching complete! Matched {$autoMatchResults['matched']} out of {$autoMatchResults['total']} players.";
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Auto-matching failed: " . $e->getMessage();
    }
}

// Manual match submission - handle batch
if (isset($_POST['batch_match'])) {
    $matched_count = 0;
    $error_count = 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['matches'] as $player_id => $sleeper_id) {
            if (!empty($sleeper_id) && $sleeper_id !== 'SKIP') {
                $stmt = $pdo->prepare("UPDATE players SET sleeper_id = ? WHERE id = ?");
                if ($stmt->execute([$sleeper_id, $player_id])) {
                    $matched_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        $pdo->commit();
        $success = "Matched $matched_count players successfully!";
        if ($error_count > 0) {
            $success .= " ($error_count errors)";
        }
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Get unmatched players - Show 1 at a time
$unmatched_query = $pdo->query("
    SELECT p.id, p.first_name, p.last_name, p.full_name, p.birth_date,
           pt.position, t.abbreviation as team_abbr
    FROM players p
    LEFT JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams t ON pt.team_id = t.id
    WHERE p.sleeper_id IS NULL OR p.sleeper_id = ''
    GROUP BY p.id
    ORDER BY p.first_name, p.last_name
    LIMIT 1
");
$unmatched_players = $unmatched_query->fetchAll();

// Get count of remaining unmatched
$remaining_count = $pdo->query("
    SELECT COUNT(DISTINCT p.id) 
    FROM players p 
    WHERE p.sleeper_id IS NULL OR p.sleeper_id = ''
")->fetchColumn();

// Get all sleeper players for dropdowns
$sleeper_players_query = $pdo->query("
    SELECT player_id, first_name, last_name, 
           CONCAT(first_name, ' ', last_name) as full_name,
           position, team, college, age
    FROM sleeper_players
    WHERE position IS NOT NULL
    ORDER BY last_name, first_name
");
$sleeper_players = $sleeper_players_query->fetchAll();

// Helper function to clean name (remove Jr, II, III, IV)
function cleanName($name) {
    return preg_replace('/\s+(Jr\.?|II|III|IV|Sr\.?)$/i', '', trim($name));
}

// Helper function to find best match
function findBestMatch($playerName, $sleeperPlayers) {
    $cleanedPlayerName = strtolower(cleanName($playerName));
    
    foreach ($sleeperPlayers as $sp) {
        $cleanedSleeperName = strtolower(cleanName($sp['full_name']));
        if ($cleanedPlayerName === $cleanedSleeperName) {
            return $sp['player_id'];
        }
    }
    
    return null;
}

// Get matched count
$matched_count = $pdo->query("SELECT COUNT(*) FROM players WHERE sleeper_id IS NOT NULL AND sleeper_id != ''")->fetchColumn();
$total_count = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();

include 'admin-header.php';
include 'admin-nav.php';
?>

<div class="container">
    <div class="admin-header" style="background: var(--dark-color); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h1>🔗 Match Sleeper Players</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">
            Link your players to Sleeper's database for better stats matching
        </p>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <a href="manage-players.php" class="btn btn-secondary">← Back to Players</a>
            <a href="sleeper-players.php" class="btn btn-primary">View Sleeper Players</a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="card" style="margin-bottom: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="text-align: center;">
                    <div style="font-size: 2.5em; font-weight: bold;"><?php echo $matched_count; ?></div>
                    <div style="opacity: 0.9;">Matched Players</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2.5em; font-weight: bold;"><?php echo $remaining_count; ?></div>
                    <div style="opacity: 0.9;">Players Remaining</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2.5em; font-weight: bold;">
                        <?php echo $total_count > 0 ? round(($matched_count / $total_count) * 100) : 0; ?>%
                    </div>
                    <div style="opacity: 0.9;">Completion Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-Match Section -->
    <div class="card" style="margin-bottom: 30px;">
        <div class="card-header">
            <h3>🤖 Automatic Matching</h3>
        </div>
        <div class="card-body">
            <p>This will automatically match players by comparing full names (first + last name) with Sleeper's database, including handling Jr, II, III, IV suffixes.</p>
            <form method="POST" style="margin-top: 15px;">
                <button type="submit" name="auto_match" class="btn btn-primary" 
                        onclick="return confirm('This will attempt to match all unmatched players. Continue?')">
                    Run Auto-Match
                </button>
            </form>
            
            <?php if ($autoMatchResults): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #0284c7; border-radius: 5px;">
                    <strong>Results:</strong><br>
                    Total Players: <?php echo $autoMatchResults['total']; ?><br>
                    Successfully Matched: <span style="color: #16a34a; font-weight: bold;"><?php echo $autoMatchResults['matched']; ?></span><br>
                    Skipped (No Match): <span style="color: #dc2626; font-weight: bold;"><?php echo $autoMatchResults['skipped']; ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Manual Matching Section -->
    <?php if (count($unmatched_players) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3>👉 Match Players (<?php echo $remaining_count; ?> remaining)</h3>
            </div>
            <div class="card-body">
                <p style="margin-bottom: 20px; padding: 15px; background: #dbeafe; border-radius: 5px;">
                    <strong>📋 Instructions:</strong> Review the suggested match below. The system auto-selects the closest match. Adjust if needed, then click "Submit Match" at the bottom.
                </p>
                
                <form method="POST" id="batchMatchForm">
                    <input type="hidden" name="batch_match" value="1">
                    
                    <?php foreach ($unmatched_players as $index => $player): 
                        $bestMatch = findBestMatch($player['full_name'], $sleeper_players);
                    ?>
                        <div class="match-player-card" style="border: 2px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 20px; background: #f9fafb;">
                            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start;">
                                <!-- Player Info -->
                                <div>
                                    <h4 style="margin: 0 0 10px 0; color: #1f2937;">
                                        <?php echo htmlspecialchars($player['full_name']); ?>
                                    </h4>
                                    <div style="display: flex; flex-direction: column; gap: 5px; font-size: 0.9em; color: #6b7280;">
                                        <?php if ($player['position']): ?>
                                            <span class="position-badge position-<?php echo strtolower($player['position']); ?>">
                                                <?php echo $player['position']; ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($player['team_abbr']): ?>
                                            <span><strong>Team:</strong> <?php echo $player['team_abbr']; ?></span>
                                        <?php endif; ?>
                                        <?php if ($player['birth_date']): ?>
                                            <span><strong>Born:</strong> <?php echo date('Y', strtotime($player['birth_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Sleeper Player Dropdown -->
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #374151;">
                                        Select Sleeper Player:
                                    </label>
                                    <select name="matches[<?php echo $player['id']; ?>]" 
                                            class="sleeper-select"
                                            style="width: 100%; padding: 10px; border: 2px solid #d1d5db; border-radius: 5px; font-size: 0.95em; background: white;">
                                        <option value="">-- Select Player --</option>
                                        <option value="SKIP">⏭️ Skip This Player</option>
                                        <?php foreach ($sleeper_players as $sp): 
                                            $selected = ($bestMatch && $bestMatch === $sp['player_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $sp['player_id']; ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($sp['first_name'] . ' ' . $sp['last_name']); ?>
                                                - <?php echo $sp['position'] ?: '??'; ?>
                                                - <?php echo $sp['team'] ?: 'FA'; ?>
                                                <?php if ($sp['college']): ?>
                                                    (<?php echo htmlspecialchars(substr($sp['college'], 0, 20)); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="position: sticky; bottom: 0; background: white; padding: 20px; border-top: 3px solid #3b82f6; box-shadow: 0 -4px 6px rgba(0,0,0,0.1); border-radius: 10px; margin-top: 20px;">
                        <div style="display: flex; gap: 10px; justify-content: center;">
                            <button type="submit" class="btn btn-primary" style="font-size: 1.1em; padding: 15px 40px;">
                                ✅ Submit Match
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="selectAllSkip()" style="padding: 15px 30px;">
                                ⏭️ Skip This Player
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 40px;">
                <div style="font-size: 4em; margin-bottom: 20px;">🎉</div>
                <h3 style="color: #16a34a; margin-bottom: 10px;">All Players Matched!</h3>
                <p style="color: #6b7280;">Every player in your database is now linked to Sleeper.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function selectAllSkip() {
    const selects = document.querySelectorAll('.sleeper-select');
    selects.forEach(select => {
        select.value = 'SKIP';
    });
}

// Make dropdowns searchable by typing
document.addEventListener('DOMContentLoaded', function() {
    const selects = document.querySelectorAll('.sleeper-select');
    let searchTerm = '';
    let searchTimeout;
    
    selects.forEach(select => {
        select.addEventListener('keypress', function(e) {
            clearTimeout(searchTimeout);
            searchTerm += e.key.toLowerCase();
            
            // Find matching option
            for (let i = 0; i < this.options.length; i++) {
                const optionText = this.options[i].text.toLowerCase();
                if (optionText.includes(searchTerm)) {
                    this.selectedIndex = i;
                    break;
                }
            }
            
            // Clear search term after 1 second
            searchTimeout = setTimeout(() => {
                searchTerm = '';
            }, 1000);
        });
    });
});
</script>

<style>
.position-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.85em;
    font-weight: bold;
    color: white;
}
.position-qb { background: #dc2626; }
.position-rb { background: #16a34a; }
.position-wr { background: #2563eb; }
.position-te { background: #d97706; }
.position-k { background: #7c3aed; }
.position-dst { background: #0891b2; }
.position-dl { background: #64748b; }
.position-lb { background: #64748b; }
.position-db { background: #64748b; }
.position-na { background: #9ca3af; }
</style>

<?php 
include 'admin-nav-footer.php';
include 'admin-footer.php'; 
?>
