<?php
require_once dirname(__DIR__) . '/config.php';

$page_title = 'Wyandotte Football League - Rosters';

// Get all teams with rosters
$teams = $pdo->query("
    SELECT t.*, COUNT(r.id) as player_count
    FROM wyandotte_teams t
    LEFT JOIN wyandotte_rosters r ON t.id = r.team_id
    GROUP BY t.id
    ORDER BY t.team_name
")->fetchAll();

// Get all rosters with player details
$rosters = [];
foreach ($teams as $team) {
    $stmt = $pdo->prepare("
        SELECT r.*, p.full_name, p.id as player_id, pt.position, pt.team_id as nfl_team_id, 
               nfl.name as nfl_team_name, nfl.city as nfl_team_city
        FROM wyandotte_rosters r
        JOIN players p ON r.player_id = p.id
        JOIN player_teams pt ON p.id = pt.player_id
        LEFT JOIN teams nfl ON pt.team_id = nfl.id
        WHERE r.team_id = ?
        ORDER BY r.slot_number
    ");
    $stmt->execute([$team['id']]);
    $rosters[$team['id']] = $stmt->fetchAll();
}

// Get player selection stats
$player_stats = $pdo->query("
    SELECT p.id, p.full_name, COUNT(r.id) as selection_count, 
           GROUP_CONCAT(DISTINCT pt.position) as positions,
           nfl.city as nfl_team_city, nfl.name as nfl_team_name
    FROM wyandotte_rosters r
    JOIN players p ON r.player_id = p.id
    LEFT JOIN player_teams pt ON p.id = pt.player_id
    LEFT JOIN teams nfl ON pt.team_id = nfl.id
    GROUP BY p.id
    ORDER BY selection_count DESC, p.full_name
")->fetchAll();

// Get position distribution
$position_dist = $pdo->query("
    SELECT r.position, COUNT(*) as count
    FROM wyandotte_rosters r
    GROUP BY r.position
    ORDER BY count DESC
")->fetchAll();

// Get NFL team distribution
$nfl_team_dist = $pdo->query("
    SELECT nfl.city, nfl.name, COUNT(DISTINCT r.id) as player_count
    FROM wyandotte_rosters r
    JOIN players p ON r.player_id = p.id
    JOIN player_teams pt ON p.id = pt.player_id
    JOIN teams nfl ON pt.team_id = nfl.id
    GROUP BY nfl.id
    ORDER BY player_count DESC
    LIMIT 10
")->fetchAll();

// Team color schemes (can be customized per team by team name)
$team_colors = [];
foreach ($teams as $team) {
    $team_id = $team['id'];
    $team_name = strtolower($team['team_name']);
    
    // Assign colors based on team name keywords or use random vibrant colors
    if (strpos($team_name, 'red') !== false || strpos($team_name, 'fire') !== false) {
        $team_colors[$team_id] = ['primary' => '#dc2626', 'secondary' => '#b91c1c'];
    } elseif (strpos($team_name, 'blue') !== false || strpos($team_name, 'ocean') !== false) {
        $team_colors[$team_id] = ['primary' => '#2563eb', 'secondary' => '#1e40af'];
    } elseif (strpos($team_name, 'green') !== false || strpos($team_name, 'forest') !== false) {
        $team_colors[$team_id] = ['primary' => '#059669', 'secondary' => '#047857'];
    } elseif (strpos($team_name, 'purple') !== false || strpos($team_name, 'royal') !== false) {
        $team_colors[$team_id] = ['primary' => '#7c3aed', 'secondary' => '#6d28d9'];
    } elseif (strpos($team_name, 'orange') !== false || strpos($team_name, 'sun') !== false) {
        $team_colors[$team_id] = ['primary' => '#ea580c', 'secondary' => '#c2410c'];
    } elseif (strpos($team_name, 'gold') !== false || strpos($team_name, 'yellow') !== false) {
        $team_colors[$team_id] = ['primary' => '#d97706', 'secondary' => '#b45309'];
    } elseif (strpos($team_name, 'black') !== false || strpos($team_name, 'dark') !== false) {
        $team_colors[$team_id] = ['primary' => '#1f2937', 'secondary' => '#111827'];
    } elseif (strpos($team_name, 'silver') !== false || strpos($team_name, 'gray') !== false) {
        $team_colors[$team_id] = ['primary' => '#6b7280', 'secondary' => '#4b5563'];
    } else {
        // Random vibrant colors for teams without keyword matches
        $colors = [
            ['primary' => '#ec4899', 'secondary' => '#db2777'], // Pink
            ['primary' => '#8b5cf6', 'secondary' => '#7c3aed'], // Violet
            ['primary' => '#06b6d4', 'secondary' => '#0891b2'], // Cyan
            ['primary' => '#10b981', 'secondary' => '#059669'], // Emerald
            ['primary' => '#f59e0b', 'secondary' => '#d97706'], // Amber
            ['primary' => '#ef4444', 'secondary' => '#dc2626'], // Red
            ['primary' => '#3b82f6', 'secondary' => '#2563eb'], // Blue
            ['primary' => '#14b8a6', 'secondary' => '#0d9488'], // Teal
        ];
        $team_colors[$team_id] = $colors[$team_id % count($colors)];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            padding: 40px 20px;
            background: rgba(0,0,0,0.2);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        .header h1 {
            font-size: 3.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .nav-tabs button {
            padding: 12px 30px;
            background: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .nav-tabs button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }
        .nav-tabs button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .team-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        .team-card:hover {
            transform: translateY(-5px);
        }
        .team-header {
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .team-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(255,255,255,.5) 10px, rgba(255,255,255,.5) 20px);
        }
        .team-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border: 3px solid rgba(255,255,255,0.3);
        }
        .team-name {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .team-owner {
            font-size: 1rem;
            opacity: 0.9;
        }
        .roster-list {
            list-style: none;
        }
        .roster-item {
            padding: 12px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        .roster-item:hover {
            background: #e9ecef;
        }
        .position-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
        }
        .player-name {
            flex-grow: 1;
            margin: 0 15px;
            font-weight: 500;
        }
        .nfl-team {
            font-size: 0.85rem;
            color: #666;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .stat-card h3 {
            color: #1e3c72;
            margin-bottom: 20px;
            font-size: 1.5rem;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 8px;
            align-items: center;
        }
        .stat-label {
            font-weight: 500;
        }
        .stat-value {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: bold;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .empty-state h2 {
            color: #1e3c72;
            margin-bottom: 15px;
        }
        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }
        .empty-state a {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: transform 0.3s;
        }
        .empty-state a:hover {
            transform: scale(1.05);
        }
        .admin-link {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            padding: 15px 25px;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            text-decoration: none;
            color: #1e3c72;
            font-weight: bold;
            transition: all 0.3s;
        }
        .admin-link:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }
        .highlight {
            background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
            color: white;
        }
        .top-pick {
            border-left: 4px solid #28a745;
        }
        .search-box {
            margin-bottom: 20px;
            text-align: center;
        }
        .search-box input {
            padding: 12px 20px;
            width: 100%;
            max-width: 500px;
            border: 2px solid white;
            border-radius: 25px;
            font-size: 16px;
            background: rgba(255,255,255,0.9);
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.9);
            border: 2px solid white;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .filter-btn:hover, .filter-btn.active {
            background: white;
            transform: scale(1.05);
        }
        .comparison-mode {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .comparison-mode button {
            padding: 10px 20px;
            margin: 5px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .comparison-mode button:hover {
            background: #667eea;
            color: white;
        }
        .team-card.compare-selected {
            border: 4px solid #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .team-card {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>&#127944; WYANDOTTE FOOTBALL LEAGUE</h1>
        <p>Elite Playoff Competition - <?php echo count($teams); ?> Teams Battle</p>
    </div>

    <div class="nav-tabs">
        <button class="active" onclick="showTab('rosters')">All Rosters</button>
        <button onclick="showTab('stats')">Player Stats</button>
        <button onclick="showTab('analytics')">League Analytics</button>
    </div>

    <!-- Rosters Tab -->
    <div id="rosters" class="tab-content active">
        <?php if (empty($teams)): ?>
            <div class="empty-state">
                <h2>No Teams Yet!</h2>
                <p>Get started by creating teams and building rosters.</p>
                <a href="manage/teams.php">Create Teams</a>
            </div>
        <?php else: ?>
            <div class="search-box">
                <input type="text" id="searchPlayer" placeholder="Search for a player..." onkeyup="searchPlayers()">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterByPosition('all')">All Players</button>
                <button class="filter-btn" onclick="filterByPosition('QB')">QBs</button>
                <button class="filter-btn" onclick="filterByPosition('RB')">RBs</button>
                <button class="filter-btn" onclick="filterByPosition('WR')">WRs</button>
                <button class="filter-btn" onclick="filterByPosition('TE')">TEs</button>
                <button class="filter-btn" onclick="filterByPosition('DB')">DBs</button>
                <button class="filter-btn" onclick="filterByPosition('LB')">LBs</button>
                <button class="filter-btn" onclick="filterByPosition('DL')">DLs</button>
            </div>
            <div class="comparison-mode">
                <strong>Compare Teams:</strong>
                <button onclick="enableCompareMode()">Enable Comparison</button>
                <button onclick="clearComparison()">Clear Selection</button>
            </div>
            <div class="teams-grid" id="teamsGrid">
                <?php foreach ($teams as $team): 
                    $colors = $team_colors[$team['id']];
                    $team_initials = strtoupper(substr($team['team_name'], 0, 1) . substr($team['owner_name'], 0, 1));
                ?>
                    <div class="team-card" data-team-name="<?php echo strtolower($team['team_name']); ?>">
                        <div class="team-header" style="background: linear-gradient(135deg, <?php echo $colors['primary']; ?> 0%, <?php echo $colors['secondary']; ?> 100%);">
                            <div class="team-logo"><?php echo $team_initials; ?></div>
                            <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                            <div class="team-owner">Owner: <?php echo htmlspecialchars($team['owner_name']); ?></div>
                        </div>
                        <?php if (isset($rosters[$team['id']]) && !empty($rosters[$team['id']])): ?>
                            <ul class="roster-list">
                                <?php foreach ($rosters[$team['id']] as $player): ?>
                                    <li class="roster-item" data-player-name="<?php echo strtolower($player['full_name']); ?>">
                                        <span class="position-badge"><?php echo htmlspecialchars($player['position']); ?></span>
                                        <span class="player-name"><?php echo htmlspecialchars($player['full_name']); ?></span>
                                        <span class="nfl-team"><?php echo htmlspecialchars($player['nfl_team_city'] ?? ''); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="text-align: center; color: #999; padding: 20px;">No roster yet</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stats Tab -->
    <div id="stats" class="tab-content">
        <div class="stats-container">
            <div class="stat-card">
                <h3>Most Selected Players</h3>
                <?php 
                $top_players = array_slice($player_stats, 0, 10);
                foreach ($top_players as $player): 
                ?>
                    <div class="stat-item <?php echo $player['selection_count'] > 1 ? 'top-pick' : ''; ?>">
                        <span class="stat-label">
                            <?php echo htmlspecialchars($player['full_name']); ?>
                            <small style="color: #999;">(<?php echo htmlspecialchars($player['positions']); ?>)</small>
                        </span>
                        <span class="stat-value"><?php echo $player['selection_count']; ?>x</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="stat-card">
                <h3>Position Distribution</h3>
                <?php foreach ($position_dist as $pos): ?>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo htmlspecialchars($pos['position']); ?></span>
                        <span class="stat-value"><?php echo $pos['count']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="stat-card">
                <h3>Top NFL Teams</h3>
                <?php foreach ($nfl_team_dist as $nfl_team): ?>
                    <div class="stat-item">
                        <span class="stat-label"><?php echo htmlspecialchars($nfl_team['city'] . ' ' . $nfl_team['name']); ?></span>
                        <span class="stat-value"><?php echo $nfl_team['player_count']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Analytics Tab -->
    <div id="analytics" class="tab-content">
        <div class="stats-container">
            <div class="stat-card">
                <h3>League Overview</h3>
                <div class="stat-item">
                    <span class="stat-label">Total Teams</span>
                    <span class="stat-value"><?php echo count($teams); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Total Players Rostered</span>
                    <span class="stat-value"><?php echo count($player_stats); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Unique Players</span>
                    <span class="stat-value"><?php echo count($player_stats); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Roster Spots Filled</span>
                    <span class="stat-value"><?php echo array_sum(array_column($position_dist, 'count')); ?>/<?php echo count($teams) * 9; ?></span>
                </div>
            </div>

            <div class="stat-card">
                <h3>All Rostered Players</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($player_stats as $player): ?>
                        <div class="stat-item">
                            <span class="stat-label">
                                <?php echo htmlspecialchars($player['full_name']); ?>
                                <br><small style="color: #999;"><?php echo htmlspecialchars($player['nfl_team_city'] ?? 'Unknown'); ?> - <?php echo htmlspecialchars($player['positions']); ?></small>
                            </span>
                            <span class="stat-value"><?php echo $player['selection_count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isLoggedIn()): ?>
        <a href="manage/teams.php" class="admin-link">⚙️ Manage League</a>
    <?php endif; ?>

    <script>
        let compareMode = false;
        let selectedTeams = [];

        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active from all buttons
            document.querySelectorAll('.nav-tabs button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Add active to clicked button
            event.target.classList.add('active');
        }

        function searchPlayers() {
            const searchTerm = document.getElementById('searchPlayer').value.toLowerCase();
            const teamCards = document.querySelectorAll('.team-card');
            
            teamCards.forEach(card => {
                const players = card.querySelectorAll('.roster-item');
                let hasMatch = false;
                
                players.forEach(player => {
                    const playerName = player.getAttribute('data-player-name');
                    if (playerName.includes(searchTerm)) {
                        player.style.display = 'flex';
                        hasMatch = true;
                        player.style.background = searchTerm ? '#fff3cd' : '#f8f9fa';
                    } else {
                        player.style.display = searchTerm ? 'none' : 'flex';
                        player.style.background = '#f8f9fa';
                    }
                });
                
                // Show/hide entire card based on matches
                if (searchTerm) {
                    card.style.display = hasMatch ? 'block' : 'none';
                } else {
                    card.style.display = 'block';
                }
            });
        }

        function filterByPosition(position) {
            // Update button states
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            const teamCards = document.querySelectorAll('.team-card');
            
            teamCards.forEach(card => {
                const players = card.querySelectorAll('.roster-item');
                let hasMatch = false;
                
                players.forEach(player => {
                    const positionBadge = player.querySelector('.position-badge');
                    const playerPosition = positionBadge.textContent.trim();
                    
                    if (position === 'all' || playerPosition === position) {
                        player.style.display = 'flex';
                        hasMatch = true;
                        player.style.background = position !== 'all' ? '#e7f3ff' : '#f8f9fa';
                    } else {
                        player.style.display = 'none';
                    }
                });
                
                // Always show card, just filter players within
                card.style.display = 'block';
            });
        }

        function enableCompareMode() {
            compareMode = true;
            alert('Click on teams to compare! Select 2-3 teams.');
            
            document.querySelectorAll('.team-card').forEach(card => {
                card.style.cursor = 'pointer';
                card.onclick = function() {
                    if (compareMode) {
                        toggleTeamSelection(this);
                    }
                };
            });
        }

        function toggleTeamSelection(card) {
            if (card.classList.contains('compare-selected')) {
                card.classList.remove('compare-selected');
                const index = selectedTeams.indexOf(card);
                if (index > -1) {
                    selectedTeams.splice(index, 1);
                }
            } else {
                if (selectedTeams.length < 3) {
                    card.classList.add('compare-selected');
                    selectedTeams.push(card);
                } else {
                    alert('Maximum 3 teams for comparison!');
                }
            }

            if (selectedTeams.length >= 2) {
                showComparison();
            }
        }

        function showComparison() {
            // Hide non-selected teams
            document.querySelectorAll('.team-card').forEach(card => {
                if (!card.classList.contains('compare-selected')) {
                    card.style.opacity = '0.3';
                }
            });
        }

        function clearComparison() {
            compareMode = false;
            selectedTeams = [];
            
            document.querySelectorAll('.team-card').forEach(card => {
                card.classList.remove('compare-selected');
                card.style.cursor = 'default';
                card.style.opacity = '1';
                card.onclick = null;
            });
        }

        // Add animation on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = 1;
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.team-card').forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>
</html>
