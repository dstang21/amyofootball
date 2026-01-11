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
                // Update player with sleeper_id
                $update = $pdo->prepare("UPDATE players SET sleeper_id = ? WHERE id = ?");
                $update->execute([$sleeper_player['player_id'], $player['id']]);
                $autoMatchResults['matched']++;
            } else {
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

// Manual match submission
if (isset($_POST['manual_match'])) {
    $player_id = $_POST['player_id'];
    $sleeper_id = $_POST['sleeper_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE players SET sleeper_id = ? WHERE id = ?");
        if ($stmt->execute([$sleeper_id, $player_id])) {
            $success = "Player matched successfully!";
        } else {
            $error = "Failed to update player.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get unmatched players
$unmatched_query = $pdo->query("
    SELECT p.id, p.first_name, p.last_name, p.full_name, p.birth_date,
           pt.position, t.abbreviation as team_abbr
    FROM players p
    LEFT JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams t ON pt.team_id = t.id
    WHERE p.sleeper_id IS NULL OR p.sleeper_id = ''
    GROUP BY p.id
    ORDER BY p.full_name
");
$unmatched_players = $unmatched_query->fetchAll();

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
                    <div style="font-size: 2.5em; font-weight: bold;"><?php echo count($unmatched_players); ?></div>
                    <div style="opacity: 0.9;">Unmatched Players</div>
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
            <p>This will automatically match players by comparing full names (first + last name) with Sleeper's database.</p>
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
                <h3>👉 Manual Matching (<?php echo count($unmatched_players); ?> remaining)</h3>
            </div>
            <div class="card-body">
                <p style="margin-bottom: 20px;">
                    Match these players manually. Search for the player in Sleeper's database and enter their Player ID.
                </p>
                
                <?php foreach ($unmatched_players as $index => $player): ?>
                    <div class="match-player-card" style="border: 2px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 20px; background: #f9fafb;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: center;">
                            <!-- Player Info -->
                            <div>
                                <h4 style="margin: 0 0 10px 0; color: #1f2937;">
                                    <?php echo htmlspecialchars($player['full_name']); ?>
                                </h4>
                                <div style="display: flex; gap: 15px; font-size: 0.9em; color: #6b7280;">
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
                            
                            <!-- Matching Form -->
                            <div>
                                <form method="POST" class="match-form" style="display: flex; gap: 10px; align-items: flex-start;">
                                    <input type="hidden" name="manual_match" value="1">
                                    <input type="hidden" name="player_id" value="<?php echo $player['id']; ?>">
                                    
                                    <div style="flex: 1;">
                                        <input type="text" 
                                               name="sleeper_id" 
                                               id="sleeper_id_<?php echo $player['id']; ?>"
                                               placeholder="Enter Sleeper Player ID"
                                               class="sleeper-search-input"
                                               style="width: 100%; padding: 10px; border: 2px solid #d1d5db; border-radius: 5px;"
                                               required
                                               autocomplete="off">
                                        <div id="suggestions_<?php echo $player['id']; ?>" 
                                             class="search-suggestions" 
                                             style="display: none; position: absolute; z-index: 1000; background: white; border: 2px solid #d1d5db; border-top: none; border-radius: 0 0 5px 5px; max-height: 300px; overflow-y: auto; width: calc(100% - 120px);"></div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
                                        Match Player
                                    </button>
                                </form>
                                
                                <div style="margin-top: 10px;">
                                    <button type="button" 
                                            class="btn btn-secondary btn-sm" 
                                            onclick="searchSleeper('<?php echo addslashes($player['full_name']); ?>', <?php echo $player['id']; ?>)">
                                        🔍 Search Sleeper
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
function searchSleeper(playerName, playerId) {
    const searchTerm = playerName;
    const suggestionsDiv = document.getElementById('suggestions_' + playerId);
    const inputField = document.getElementById('sleeper_id_' + playerId);
    
    if (searchTerm.length < 2) {
        suggestionsDiv.style.display = 'none';
        return;
    }
    
    // Show loading
    suggestionsDiv.innerHTML = '<div style="padding: 10px; color: #6b7280;">Searching...</div>';
    suggestionsDiv.style.display = 'block';
    
    // Fetch suggestions
    fetch(`sleeper-api.php?action=search_players&query=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            if (data.players && data.players.length > 0) {
                let html = '';
                data.players.forEach(player => {
                    const fullName = (player.first_name || '') + ' ' + (player.last_name || '');
                    const team = player.team || 'FA';
                    const pos = player.position || '??';
                    
                    html += `
                        <div class="suggestion-item" 
                             style="padding: 10px; cursor: pointer; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;"
                             onclick="selectSleeperPlayer('${player.player_id}', '${fullName.replace(/'/g, "\\'")}', ${playerId})"
                             onmouseover="this.style.background='#f3f4f6'"
                             onmouseout="this.style.background='white'">
                            <div>
                                <strong>${fullName}</strong>
                                <span style="margin-left: 10px; color: #6b7280;">${team} - ${pos}</span>
                            </div>
                            <code style="background: #e5e7eb; padding: 2px 6px; border-radius: 3px; font-size: 0.85em;">${player.player_id}</code>
                        </div>
                    `;
                });
                suggestionsDiv.innerHTML = html;
            } else {
                suggestionsDiv.innerHTML = '<div style="padding: 10px; color: #dc2626;">No matches found</div>';
            }
        })
        .catch(error => {
            suggestionsDiv.innerHTML = '<div style="padding: 10px; color: #dc2626;">Error searching</div>';
            console.error('Search error:', error);
        });
}

function selectSleeperPlayer(sleeperId, playerName, playerId) {
    document.getElementById('sleeper_id_' + playerId).value = sleeperId;
    document.getElementById('suggestions_' + playerId).style.display = 'none';
}

// Close suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('sleeper-search-input')) {
        document.querySelectorAll('.search-suggestions').forEach(el => {
            el.style.display = 'none';
        });
    }
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
