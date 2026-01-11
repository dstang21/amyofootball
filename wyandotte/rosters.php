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
               nfl.name as nfl_team_name, nfl.abbreviation as nfl_team_abbr
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
           nfl.abbreviation as nfl_team_abbr, nfl.name as nfl_team_name
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
    SELECT nfl.name, nfl.abbreviation, COUNT(DISTINCT r.id) as player_count
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
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .navbar {
            background: rgba(15,23,42,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 2px solid rgba(249,115,22,0.3);
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-bottom: 20px;
        }
        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
        }
        .navbar-logo {
            height: 50px;
            width: auto;
        }
        .navbar-home {
            padding: 8px 16px;
            background: rgba(249,115,22,0.2);
            border: 1px solid #f97316;
            border-radius: 6px;
            color: #fbbf24;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .navbar-home:hover {
            background: #f97316;
            color: white;
        }
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 4px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
        }
        .hamburger span {
            width: 25px;
            height: 3px;
            background: #f97316;
            transition: all 0.3s;
            border-radius: 2px;
        }
        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }
        .nav-tabs {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .nav-tabs button {
            padding: 15px 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 3px solid transparent;
        }
        .nav-tabs button:hover {
            color: #fbbf24;
            border-bottom-color: rgba(249,115,22,0.5);
        }
        .nav-tabs button.active {
            color: white;
            border-bottom-color: #f97316;
            background: rgba(249,115,22,0.1);
        }
        @media (max-width: 768px) {
            .navbar-container {
                flex-wrap: wrap;
            }
            .hamburger {
                display: flex;
            }
            .nav-tabs {
                width: 100%;
                flex-direction: column;
                gap: 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease-out;
            }
            .nav-tabs.mobile-open {
                max-height: 500px;
            }
            .nav-tabs button {
                width: 100%;
                text-align: left;
                padding: 15px 20px;
                border-bottom: 1px solid rgba(249,115,22,0.2);
            }
            .navbar-logo {
                height: 40px;
            }
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
        .roster-item.has-live-stats {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
        }
        .player-stats {
            font-size: 0.75rem;
            color: #16a34a;
            font-weight: bold;
            margin-left: auto;
            padding-left: 10px;
        }
        .player-points {
            background: #16a34a;
            color: white;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-left: 10px;
        }
        .player-points.zero {
            background: #666;
        }
        .position-badge {
            background: #f97316;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(249,115,22,0.3);
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
            color: #0f172a;
            margin-bottom: 20px;
            font-size: 1.5rem;
            border-bottom: 3px solid #f97316;
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
            background: #f97316;
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
            color: #0f172a;
            margin-bottom: 15px;
        }
        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }
        .empty-state a {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: transform 0.3s;
            box-shadow: 0 4px 15px rgba(249,115,22,0.4);
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
            color: #1e3a8a;
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
            border: 2px solid #f97316;
            background: white;
            color: #f97316;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .comparison-mode button:hover {
            background: #f97316;
            color: white;
            box-shadow: 0 0 15px rgba(249,115,22,0.4);
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
        .live-games-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .game-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s;
        }
        .game-card:hover {
            transform: translateY(-3px);
        }
        .game-card.live {
            border: 2px solid #ef4444;
            background: linear-gradient(135deg, #fff 0%, #fee2e2 100%);
        }
        .game-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f4f6;
        }
        .game-status {
            font-size: 0.9rem;
            color: #666;
            font-weight: bold;
        }
        .game-status.live {
            color: #ef4444;
        }
        .game-teams {
            display: flex;
            justify-content: space-around;
            align-items: center;
            margin: 20px 0;
        }
        .game-team {
            text-align: center;
            flex: 1;
        }
        .game-team-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 10px;
        }
        .game-team-name {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        .game-team-record {
            font-size: 0.85rem;
            color: #666;
        }
        .game-score {
            font-size: 2.5rem;
            font-weight: bold;
            color: #0f172a;
        }
        .game-vs {
            font-size: 1.5rem;
            color: #999;
            margin: 0 20px;
        }
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #ef4444;
            border-radius: 50%;
            margin-right: 5px;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 50%, 100% { opacity: 1; }
            25%, 75% { opacity: 0.3; }
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: transform 0.3s;
        }
        .gallery-item:hover {
            transform: scale(1.05);
        }
        .gallery-item img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            display: block;
        }
        .gallery-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            padding: 15px;
            font-weight: bold;
            text-align: center;
        }
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        .lightbox.active {
            display: flex;
        }
        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        .team-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-y: auto;
        }
        .team-modal.active {
            display: flex;
        }
        .team-modal-content {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 20px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            border: 2px solid rgba(249,115,22,0.3);
        }
        .team-modal-header {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            padding: 30px;
            border-radius: 20px 20px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .team-modal-header h2 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 2rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .team-modal-header p {
            color: rgba(255,255,255,0.9);
            margin: 0;
            font-size: 1.1rem;
        }
        .team-modal-score {
            background: rgba(0,0,0,0.3);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            text-align: center;
        }
        .team-modal-score-label {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .team-modal-score-value {
            color: #fbbf24;
            font-size: 2.5rem;
            font-weight: bold;
            text-shadow: 0 2px 8px rgba(251,191,36,0.5);
        }
        .team-modal-body {
            padding: 30px;
        }
        .modal-player-item {
            background: rgba(0,0,0,0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #f97316;
            transition: all 0.3s;
        }
        .modal-player-item:hover {
            background: rgba(0,0,0,0.4);
            transform: translateX(5px);
        }
        .modal-player-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .modal-player-name {
            color: #ffffff;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .modal-player-position {
            background: #f97316;
            color: white;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .modal-player-points {
            color: #fbbf24;
            font-size: 1.8rem;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(251,191,36,0.3);
        }
        .modal-player-stats {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-top: 8px;
            line-height: 1.6;
        }
        .modal-player-team {
            color: #cbd5e1;
            font-size: 0.85rem;
            margin-left: 10px;
        }
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.5);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            border: 2px solid rgba(255,255,255,0.3);
            transition: all 0.3s;
        }
        .modal-close:hover {
            background: #f97316;
            border-color: #f97316;
            transform: rotate(90deg);
        }

        /* Chat styles */
        .avatar-option:hover {
            background: rgba(249,115,22,0.2);
            transform: scale(1.1);
        }
        .avatar-option[style*="border-color: rgb(249, 115, 22)"] {
            background: rgba(249,115,22,0.3);
            box-shadow: 0 0 15px rgba(249,115,22,0.5);
        }
        #chatMessages::-webkit-scrollbar {
            width: 8px;
        }
        #chatMessages::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
        }
        #chatMessages::-webkit-scrollbar-thumb {
            background: #f97316;
            border-radius: 10px;
        }
        #chatMessages::-webkit-scrollbar-thumb:hover {
            background: #ea580c;
        }
        .avatar-option-large:hover {
            background: rgba(249,115,22,0.3);
            transform: scale(1.05);
            border-color: #f97316;
        }
        #avatarModal {
            display: none;
        }
        #avatarModal.active {
            display: flex !important;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/live-ticker.php'; ?>
    
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <img src="../amyofootball_logo.png" alt="Amyofootball" class="navbar-logo">
                <a href="index.php" class="navbar-home">Home</a>
            </div>
            <button class="hamburger" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="nav-tabs" id="navTabs">
                <button class="active" onclick="showTab('live')">Live Scores</button>
                <button onclick="showTab('rosters')">Rosters</button>
                <button onclick="showTab('playerStats')">Player Stats</button>
                <button onclick="showTab('chat')">Chat</button>
                <button onclick="showTab('gallery')">Gallery</button>
                <button onclick="showTab('stats')">League Stats</button>
                <button onclick="showTab('analytics')">Analytics</button>
            </div>
        </div>
    </nav>

    <!-- Team Details Modal -->
    <div id="teamModal" class="team-modal" onclick="closeTeamModal(event)">
        <div class="team-modal-content" onclick="event.stopPropagation()">
            <span class="modal-close" onclick="closeTeamModal()">&times;</span>
            <div class="team-modal-header">
                <h2 id="modalTeamName">Team Name</h2>
                <p id="modalOwnerName">Owner Name</p>
                <div class="team-modal-score">
                    <div class="team-modal-score-label">Total Points</div>
                    <div class="team-modal-score-value" id="modalTotalScore">0.0</div>
                </div>
            </div>
            <div class="team-modal-body" id="modalPlayersList">
                Loading...
            </div>
        </div>
    </div>

    <!-- Latest Chat Preview -->
    <div id="latestChatPreview" style="max-width: 800px; margin: 0 auto 20px; background: rgba(0,0,0,0.5); border-radius: 10px; padding: 15px; border: 1px solid rgba(249,115,22,0.3); display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h3 style="color: #f97316; margin: 0; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">Latest Chat</h3>
            <a href="#" onclick="showTab('chat'); return false;" style="color: #fbbf24; text-decoration: none; font-size: 0.75rem;">View All →</a>
        </div>
        <div id="latestChatContent" style="color: #cbd5e1; font-size: 0.9rem; margin-bottom: 12px;">
            Loading...
        </div>
        <div style="display: flex; gap: 8px;">
            <input type="text" id="quickChatMessage" placeholder="Send a quick message..." style="flex: 1; padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(249,115,22,0.5); background: rgba(255,255,255,0.1); color: white; font-size: 0.9rem;" onkeypress="if(event.key==='Enter') sendQuickChat()">
            <button onclick="sendQuickChat()" style="padding: 8px 16px; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.85rem;">Send</button>
        </div>
    </div>

    <!-- Live Scores Tab -->
    <div id="live" class="tab-content active">
        <?php if (empty($teams)): ?>
            <div class="empty-state">
                <h2>No Teams Yet!</h2>
                <p>Get started by creating teams and building rosters.</p>
                <a href="manage/teams.php">Create Teams</a>
            </div>
        <?php else: ?>
            <!-- Live Scores Leaderboard -->
            <div id="scoresLeaderboard" style="margin: 0 auto 30px; max-width: 800px; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; border: 2px solid rgba(255,255,255,0.25); box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
                <h2 style="text-align: center; color: white; margin-bottom: 25px; font-size: 1.5rem; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">🏆 Current Standings</h2>
                <div id="scoresContent" style="display: flex; flex-direction: column; gap: 10px;">
                    <p style="text-align: center; color: white;">Loading scores...</p>
                </div>
            </div>
            
            <!-- NFL Games Section -->
            <div style="margin-top: 40px;">
                <h3 style="text-align: center; color: white; margin-bottom: 20px; font-size: 1.3rem; text-transform: uppercase; letter-spacing: 1px;">NFL Games</h3>
                <div class="live-games-grid" id="liveGamesGrid">
                    <div style="text-align: center; color: white; grid-column: 1/-1;">
                        <p>Loading live games...</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rosters Tab -->
    <div id="rosters" class="tab-content">
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
                    <div class="team-card" data-team-name="<?php echo strtolower($team['team_name']); ?>" data-team-id="<?php echo $team['id']; ?>">
                        <div class="team-header" style="background: linear-gradient(135deg, <?php echo $colors['primary']; ?> 0%, <?php echo $colors['secondary']; ?> 100%);">
                            <div class="team-logo"><?php echo $team_initials; ?></div>
                            <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                            <div class="team-owner">Owner: <?php echo htmlspecialchars($team['owner_name']); ?></div>
                            <div class="team-score" id="team-score-<?php echo $team['id']; ?>" style="margin-top: 10px; font-size: 1.5rem; font-weight: bold; color: #fff;">
                                0.0 pts
                            </div>
                        </div>
                        <?php if (isset($rosters[$team['id']]) && !empty($rosters[$team['id']])): ?>
                            <ul class="roster-list">
                                <?php foreach ($rosters[$team['id']] as $player): ?>
                                <li class="roster-item" data-player-name="<?php echo strtolower($player['full_name']); ?>" data-player-id="<?php echo $player['player_id']; ?>">
                                    <span class="position-badge"><?php echo htmlspecialchars($player['position']); ?></span>
                                    <span class="player-name"><?php echo htmlspecialchars($player['full_name']); ?></span>
                                    <span class="nfl-team"><?php echo htmlspecialchars($player['nfl_team_abbr'] ?? ''); ?></span>
                                    <span class="player-stats" id="stats-<?php echo $player['player_id']; ?>"></span>
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

    <!-- Live Player Stats Tab -->
    <div id="playerStats" class="tab-content">
        <div style="text-align: center; margin-bottom: 20px;">
            <p style="color: white; font-size: 1.2rem;">Real-time stats for your rostered players</p>
        </div>
        <div class="stats-container" id="livePlayerStatsGrid">
            <div style="text-align: center; color: white; grid-column: 1/-1;">
                <p>Loading player stats...</p>
            </div>
        </div>
    </div>

    <!-- Gallery Tab -->
    <div id="gallery" class="tab-content">
        <div style="text-align: center; margin-bottom: 20px;">
            <p style="color: white; font-size: 1.2rem;">League Photo Gallery</p>
        </div>
        <div class="gallery-grid">
            <?php
            $imageDir = __DIR__ . '/images/';
            $images = glob($imageDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
            foreach ($images as $imagePath):
                $filename = basename($imagePath);
                $name = ucfirst(pathinfo($filename, PATHINFO_FILENAME));
            ?>
                <div class="gallery-item" onclick="openLightbox('images/<?php echo htmlspecialchars($filename); ?>')">
                    <img src="images/<?php echo htmlspecialchars($filename); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                    <div class="gallery-caption"><?php echo htmlspecialchars($name); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
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
                        <span class="stat-label"><?php echo htmlspecialchars($nfl_team['name']); ?> <small>(<?php echo htmlspecialchars($nfl_team['abbreviation']); ?>)</small></span>
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
                                <br><small style="color: #999;"><?php echo htmlspecialchars($player['nfl_team_abbr'] ?? 'FA'); ?> - <?php echo htmlspecialchars($player['positions']); ?></small>
                            </span>
                            <span class="stat-value"><?php echo $player['selection_count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Tab -->
    <div id="chat" class="tab-content">
        <div style="max-width: 900px; margin: 0 auto;">
            <!-- Avatar Selector Modal -->
            <div id="avatarModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
                <div style="background: rgba(15,23,42,0.95); border-radius: 20px; padding: 30px; max-width: 600px; border: 2px solid #f97316; max-height: 80vh; overflow-y: auto;">
                    <h3 style="color: #f97316; margin-bottom: 20px; text-align: center;">Choose Your Avatar</h3>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                        <div class="avatar-option-large" data-avatar="football" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🏈</div>
                        <div class="avatar-option-large" data-avatar="helmet" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">⛑️</div>
                        <div class="avatar-option-large" data-avatar="trophy" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🏆</div>
                        <div class="avatar-option-large" data-avatar="fire" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🔥</div>
                        <div class="avatar-option-large" data-avatar="star" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">⭐</div>
                        <div class="avatar-option-large" data-avatar="lightning" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">⚡</div>
                        <div class="avatar-option-large" data-avatar="rocket" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🚀</div>
                        <div class="avatar-option-large" data-avatar="crown" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👑</div>
                        <div class="avatar-option-large" data-avatar="skull" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💀</div>
                        <div class="avatar-option-large" data-avatar="alien" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👽</div>
                        <div class="avatar-option-large" data-avatar="robot" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🤖</div>
                        <div class="avatar-option-large" data-avatar="ghost" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👻</div>
                        <div class="avatar-option-large" data-avatar="poop" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💩</div>
                        <div class="avatar-option-large" data-avatar="clown" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🤡</div>
                        <div class="avatar-option-large" data-avatar="demon" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">😈</div>
                        <div class="avatar-option-large" data-avatar="devil" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👿</div>
                        <div class="avatar-option-large" data-avatar="ninja" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🥷</div>
                        <div class="avatar-option-large" data-avatar="pirate" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🏴‍☠️</div>
                        <div class="avatar-option-large" data-avatar="muscle" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💪</div>
                        <div class="avatar-option-large" data-avatar="punch" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👊</div>
                        <div class="avatar-option-large" data-avatar="boom" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💥</div>
                        <div class="avatar-option-large" data-avatar="bomb" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💣</div>
                        <div class="avatar-option-large" data-avatar="dart" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🎯</div>
                        <div class="avatar-option-large" data-avatar="beer" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🍺</div>
                        <div class="avatar-option-large" data-avatar="pizza" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🍕</div>
                        <div class="avatar-option-large" data-avatar="burger" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🍔</div>
                        <div class="avatar-option-large" data-avatar="hotdog" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🌭</div>
                        <div class="avatar-option-large" data-avatar="taco" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🌮</div>
                        <div class="avatar-option-large" data-avatar="wolf" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐺</div>
                        <div class="avatar-option-large" data-avatar="lion" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦁</div>
                        <div class="avatar-option-large" data-avatar="tiger" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐯</div>
                        <div class="avatar-option-large" data-avatar="bear" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐻</div>
                        <div class="avatar-option-large" data-avatar="gorilla" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦍</div>
                        <div class="avatar-option-large" data-avatar="eagle" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦅</div>
                        <div class="avatar-option-large" data-avatar="shark" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦈</div>
                        <div class="avatar-option-large" data-avatar="trex" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦖</div>
                        <div class="avatar-option-large" data-avatar="dragon" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐉</div>
                        <div class="avatar-option-large" data-avatar="unicorn" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦄</div>
                        <div class="avatar-option-large" data-avatar="monkey" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐵</div>
                        <div class="avatar-option-large" data-avatar="pig" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐷</div>
                        <div class="avatar-option-large" data-avatar="dog" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐶</div>
                        <div class="avatar-option-large" data-avatar="cat" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐱</div>
                        <div class="avatar-option-large" data-avatar="money" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💰</div>
                        <div class="avatar-option-large" data-avatar="diamond" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💎</div>
                        <div class="avatar-option-large" data-avatar="puke" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🤮</div>
                        <div class="avatar-option-large" data-avatar="nerd" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🤓</div>
                        <div class="avatar-option-large" data-avatar="cool" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">😎</div>
                        <div class="avatar-option-large" data-avatar="eyes" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👀</div>
                    </div>
                </div>
            </div>

            <!-- Chat Form -->
            <div style="background: rgba(0,0,0,0.5); border-radius: 15px; padding: 20px; margin-bottom: 20px; border: 1px solid rgba(249,115,22,0.3);">
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span id="currentAvatar" style="font-size: 2rem;">🏈</span>
                        <button id="avatarSelectorBtn" onclick="openAvatarModal()" style="padding: 4px 8px; background: rgba(249,115,22,0.3); border: 1px solid #f97316; border-radius: 5px; color: #fbbf24; cursor: pointer; font-size: 0.75rem; transition: all 0.3s;">Change</button>
                    </div>
                    <input type="text" id="chatUsername" placeholder="Enter your name" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid rgba(249,115,22,0.5); background: rgba(255,255,255,0.1); color: white; font-size: 1rem;">
                <div style="display: flex; gap: 10px;">
                    <textarea id="chatMessage" placeholder="Type your message..." style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid rgba(249,115,22,0.5); background: rgba(255,255,255,0.1); color: white; font-size: 1rem; resize: vertical; min-height: 60px;"></textarea>
                    <button onclick="sendChatMessage()" style="padding: 12px 30px; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(249,115,22,0.4);">Send</button>
                </div>
                <p style="color: #94a3b8; font-size: 0.85rem; margin-top: 8px;">Max 500 characters</p>
            </div>

            <!-- Chat Messages -->
            <div id="chatMessages" style="background: rgba(0,0,0,0.5); border-radius: 15px; padding: 20px; min-height: 400px; max-height: 600px; overflow-y: auto; border: 1px solid rgba(249,115,22,0.3);">
                <p style="text-align: center; color: #94a3b8;">Loading messages...</p>
            </div>
        </div>
    </div>

    <script>
        let compareMode = false;
        let selectedTeams = [];
        let liveScoresInterval;
        let livePlayerStatsInterval;
        let rosterStatsInterval;
        let selectedAvatar = localStorage.getItem('chatAvatar') || 'football';
        let chatUsername = localStorage.getItem('chatUsername') || '';
        let chatUserId = localStorage.getItem('chatUserId') || '';
        let chatRefreshInterval;
        let avatarChosen = sessionStorage.getItem('avatarChosen') === 'true';

        // Default names list
        const defaultNames = [
            'Unwanted Stalker',
            'Retarded Voyeur',
            'Anonymous Dick',
            'Secret Loser',
            'Unknown Homo',
            'Unidentified Flying Penis',
            'Faceless Fred',
            'Incognito Eskimo',
            'One Dumb Fuck',
            'Nameless Moron',
            'Fart Knocker',
            'John Cena',
            'Someone That I Used To Know'
        ];

        // Generate user ID if not exists
        if (!chatUserId) {
            chatUserId = Math.random().toString(36).substring(2, 8);
            localStorage.setItem('chatUserId', chatUserId);
        }

        // Assign default name if user doesn't have one
        if (!chatUsername) {
            // Use first 6 chars of user ID as seed for name index
            const seed = parseInt(chatUserId.substring(0, 6), 36);
            const nameIndex = seed % defaultNames.length;
            chatUsername = defaultNames[nameIndex];
            localStorage.setItem('chatUsername', chatUsername);
        }

        const avatarMap = {
            'football': '🏈',
            'helmet': '⛑️',
            'trophy': '🏆',
            'fire': '🔥',
            'star': '⭐',
            'lightning': '⚡',
            'rocket': '🚀',
            'crown': '👑',
            'skull': '💀',
            'alien': '👽',
            'robot': '🤖',
            'ghost': '👻',
            'poop': '💩',
            'clown': '🤡',
            'demon': '😈',
            'devil': '👿',
            'ninja': '🥷',
            'pirate': '🏴‍☠️',
            'muscle': '💪',
            'punch': '👊',
            'boom': '💥',
            'bomb': '💣',
            'dart': '🎯',
            'beer': '🍺',
            'pizza': '🍕',
            'burger': '🍔',
            'hotdog': '🌭',
            'taco': '🌮',
            'wolf': '🐺',
            'lion': '🦁',
            'tiger': '🐯',
            'bear': '🐻',
            'gorilla': '🦍',
            'eagle': '🦅',
            'shark': '🦈',
            'trex': '🦖',
            'dragon': '🐉',
            'unicorn': '🦄',
            'monkey': '🐵',
            'pig': '🐷',
            'dog': '🐶',
            'cat': '🐱',
            'money': '💰',
            'diamond': '💎',
            'puke': '🤮',
            'nerd': '🤓',
            'cool': '😎',
            'eyes': '👀'
        };

        // Update current avatar display
        function updateAvatarDisplay() {
            document.getElementById('currentAvatar').textContent = avatarMap[selectedAvatar];
        }

        // Open avatar modal
        function openAvatarModal() {
            document.getElementById('avatarModal').classList.add('active');
        }

        // Close avatar modal
        function closeAvatarModal() {
            document.getElementById('avatarModal').classList.remove('active');
        }

        // Avatar selection from modal
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.avatar-option-large').forEach(option => {
                option.addEventListener('click', function() {
                    selectedAvatar = this.getAttribute('data-avatar');
                    localStorage.setItem('chatAvatar', selectedAvatar);
                    sessionStorage.setItem('avatarChosen', 'true');
                    avatarChosen = true;
                    updateAvatarDisplay();
                    
                    // Hide the change button
                    document.getElementById('avatarSelectorBtn').style.display = 'none';
                    
                    closeAvatarModal();
                });
            });

            // Initialize avatar display
            updateAvatarDisplay();
            
            // Hide change button if already chosen this session
            if (avatarChosen) {
                document.getElementById('avatarSelectorBtn').style.display = 'none';
            }
        });

        // Load username from localStorage
        if (chatUsername) {
            document.getElementById('chatUsername').value = chatUsername;
        }

        function toggleMobileMenu() {
            const navTabs = document.getElementById('navTabs');
            const hamburger = document.querySelector('.hamburger');
            navTabs.classList.toggle('mobile-open');
            hamburger.classList.toggle('active');
        }

        function showTab(tabName) {
            // Close mobile menu if open
            const navTabs = document.getElementById('navTabs');
            const hamburger = document.querySelector('.hamburger');
            if (navTabs.classList.contains('mobile-open')) {
                navTabs.classList.remove('mobile-open');
                hamburger.classList.remove('active');
            }

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

            // Hide/show latest chat preview based on tab
            const latestChatPreview = document.getElementById('latestChatPreview');
            if (tabName === 'chat') {
                latestChatPreview.style.display = 'none';
            } else if (latestChatPreview.innerHTML.trim() !== '') {
                // Only show if there's content and not on chat tab
                latestChatPreview.style.display = 'block';
            }

            // Live scores always run in background - just refresh when viewing tab
            if (tabName === 'live') {
                updateLiveScores();
            }

            // Load live player stats if that tab is selected
            if (tabName === 'playerStats') {
                updateLivePlayerStats();
                if (!livePlayerStatsInterval) {
                    livePlayerStatsInterval = setInterval(updateLivePlayerStats, 60000);
                }
            } else {
                if (livePlayerStatsInterval) {
                    clearInterval(livePlayerStatsInterval);
                    livePlayerStatsInterval = null;
                }
            }

            // Roster stats always run in background - just refresh when viewing tab
            if (tabName === 'rosters') {
                updateRosterStats();
            }

            // Load chat messages if chat tab is selected
            if (tabName === 'chat') {
                loadChatMessages();
                if (!chatRefreshInterval) {
                    chatRefreshInterval = setInterval(() => {
                        loadChatMessages();
                        updateLatestChat();
                    }, 60000);
                }
            } else {
                if (chatRefreshInterval) {
                    clearInterval(chatRefreshInterval);
                    chatRefreshInterval = null;
                }
            }
        }

        // Chat functions
        function loadChatMessages() {
            fetch('api/chat.php?action=get&limit=50')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayChatMessages(data.messages);
                    }
                })
                .catch(error => console.error('Error loading chat:', error));
        }

        function displayChatMessages(messages) {
            const container = document.getElementById('chatMessages');
            if (messages.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #94a3b8;">No messages yet. Be the first to chat!</p>';
                return;
            }

            // Reverse to show oldest first
            messages.reverse();

            container.innerHTML = messages.map(msg => `
                <div style="background: rgba(255,255,255,0.05); border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 3px solid #f97316;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <span style="font-size: 1.5rem;">${avatarMap[msg.avatar] || '🏈'}</span>
                        <span style="color: #fbbf24; font-weight: bold;">${escapeHtml(msg.username)}</span>
                        <span style="color: #94a3b8; font-size: 0.75rem;">#${msg.user_ip}</span>
                        <span style="color: #64748b; font-size: 0.85rem; margin-left: auto;">${timeAgo(msg.created_at)}</span>
                    </div>
                    <div style="color: #cbd5e1; line-height: 1.5;">${escapeHtml(msg.message)}</div>
                </div>
            `).join('');

            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
        }

        function sendQuickChat() {
            const message = document.getElementById('quickChatMessage').value.trim();
            
            if (!message) {
                return;
            }
            
            if (message.length > 500) {
                alert('Message is too long (max 500 characters)');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'post');
            formData.append('username', chatUsername);
            formData.append('message', message);
            formData.append('avatar', selectedAvatar);
            formData.append('user_id', chatUserId);
            
            fetch('api/chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.user_id) {
                        chatUserId = data.user_id;
                        localStorage.setItem('chatUserId', chatUserId);
                    }
                    document.getElementById('quickChatMessage').value = '';
                    updateLatestChat();
                    // Also update full chat if on that tab
                    if (document.getElementById('chat').classList.contains('active')) {
                        loadChatMessages();
                    }
                } else {
                    alert('Error sending message: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                alert('Error sending message');
            });
        }

        function sendChatMessage() {
            const username = document.getElementById('chatUsername').value.trim();
            const message = document.getElementById('chatMessage').value.trim();

            if (!username) {
                alert('Please enter your name');
                return;
            }

            if (!message) {
                alert('Please enter a message');
                return;
            }

            if (message.length > 500) {
                alert('Message is too long (max 500 characters)');
                return;
            }

            // Save username to localStorage
            localStorage.setItem('chatUsername', username);
            chatUsername = username;

            const formData = new FormData();
            formData.append('action', 'post');
            formData.append('username', username);
            formData.append('message', message);
            formData.append('avatar', selectedAvatar);
            formData.append('user_id', chatUserId);

            fetch('api/chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('chatMessage').value = '';
                    loadChatMessages();
                    updateLatestChat();
                } else {
                    alert('Error sending message: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                alert('Error sending message');
            });
        }

        function updateLatestChat() {
            fetch('api/chat.php?action=latest')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.message) {
                        const msg = data.message;
                        
                        const content = `
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                                <span style="font-size: 1.2rem;">${avatarMap[msg.avatar] || '🏈'}</span>
                                <span style="color: #fbbf24; font-weight: bold; font-size: 0.9rem;">${escapeHtml(msg.username)}</span>
                                <span style="color: #64748b; font-size: 0.75rem; margin-left: auto;">${timeAgo(msg.created_at)}</span>
                            </div>
                            <div style="color: #cbd5e1; font-size: 0.9rem;">${escapeHtml(msg.message)}</div>
                        `;
                        
                        document.getElementById('latestChatContent').innerHTML = content;
                        document.getElementById('latestChatPreview').style.display = 'block';
                    }
                })
                .catch(error => console.error('Error loading latest chat:', error));
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            if (seconds < 60) return 'just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            return Math.floor(seconds / 86400) + 'd ago';
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

        // Live scores update function
        function updateLiveScores() {
            fetch('api/live-scores.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.games || data.games.length === 0) {
                        document.getElementById('liveGamesGrid').innerHTML = 
                            '<div style="text-align: center; color: white; grid-column: 1/-1;"><p>No games scheduled today</p></div>';
                        return;
                    }

                    const gamesHtml = data.games.map(game => {
                        const liveClass = game.isLive ? 'live' : '';
                        const liveIndicator = game.isLive ? '<span class="live-indicator"></span>' : '';
                        
                        let statusText = '';
                        if (game.isLive) {
                            statusText = `<span class="game-status live">${liveIndicator}LIVE - ${game.clock} Q${game.period}</span>`;
                        } else if (game.isCompleted) {
                            statusText = '<span class="game-status">FINAL</span>';
                        } else if (game.isScheduled) {
                            const gameTime = new Date(game.date);
                            statusText = `<span class="game-status">${gameTime.toLocaleString('en-US', {
                                weekday: 'short',
                                hour: 'numeric',
                                minute: '2-digit'
                            })}</span>`;
                        }

                        return `
                            <div class="game-card ${liveClass}">
                                <div class="game-header">
                                    ${statusText}
                                </div>
                                <div class="game-teams">
                                    <div class="game-team">
                                        <img src="${game.awayTeam.logo}" alt="${game.awayTeam.name}" class="game-team-logo" onerror="this.style.display='none'">
                                        <div class="game-team-name">${game.awayTeam.name}</div>
                                        <div class="game-team-record">${game.awayTeam.record}</div>
                                        <div class="game-score">${game.awayTeam.score}</div>
                                    </div>
                                    <div class="game-vs">@</div>
                                    <div class="game-team">
                                        <img src="${game.homeTeam.logo}" alt="${game.homeTeam.name}" class="game-team-logo" onerror="this.style.display='none'">
                                        <div class="game-team-name">${game.homeTeam.name}</div>
                                        <div class="game-team-record">${game.homeTeam.record}</div>
                                        <div class="game-score">${game.homeTeam.score}</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');

                    document.getElementById('liveGamesGrid').innerHTML = gamesHtml;
                })
                .catch(error => {
                    console.error('Failed to update live scores:', error);
                    document.getElementById('liveGamesGrid').innerHTML = 
                        '<div style="text-align: center; color: white; grid-column: 1/-1;"><p>Error loading scores. Please try again.</p></div>';
                });
        }

        // Live player stats update function
        function updateLivePlayerStats() {
            fetch('api/live-player-stats.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.players || data.players.length === 0) {
                        document.getElementById('livePlayerStatsGrid').innerHTML = 
                            '<div style="text-align: center; color: white; grid-column: 1/-1;"><p>No live stats available yet</p></div>';
                        return;
                    }

                    const statsHtml = data.players.map(player => {
                        const liveIndicator = player.is_live ? '<span class="live-indicator"></span>' : '';
                        
                        let statsDisplay = [];
                        
                        // Passing stats
                        if (player.stats.passing) {
                            const p = player.stats.passing;
                            statsDisplay.push(`
                                <div class="stat-item">
                                    <span class="stat-label">Passing</span>
                                    <span class="stat-value">${p.completions}/${p.attempts}, ${p.yards} YDS, ${p.tds} TD, ${p.interceptions} INT</span>
                                </div>
                            `);
                        }
                        
                        // Rushing stats
                        if (player.stats.rushing) {
                            const r = player.stats.rushing;
                            statsDisplay.push(`
                                <div class="stat-item">
                                    <span class="stat-label">Rushing</span>
                                    <span class="stat-value">${r.attempts} CAR, ${r.yards} YDS, ${r.tds} TD</span>
                                </div>
                            `);
                        }
                        
                        // Receiving stats
                        if (player.stats.receiving) {
                            const rec = player.stats.receiving;
                            statsDisplay.push(`
                                <div class="stat-item">
                                    <span class="stat-label">Receiving</span>
                                    <span class="stat-value">${rec.receptions} REC, ${rec.yards} YDS, ${rec.tds} TD</span>
                                </div>
                            `);
                        }
                        
                        // Defensive stats
                        if (player.stats.defensive) {
                            const d = player.stats.defensive;
                            statsDisplay.push(`
                                <div class="stat-item">
                                    <span class="stat-label">Defense</span>
                                    <span class="stat-value">${d.tackles} TKL, ${d.sacks} SCK, ${d.interceptions} INT</span>
                                </div>
                            `);
                        }

                        if (statsDisplay.length === 0) {
                            statsDisplay.push('<div class="stat-item"><span class="stat-label">No stats yet</span></div>');
                        }

                        return `
                            <div class="stat-card ${player.is_live ? 'live' : ''}">
                                <h3>${liveIndicator}${player.name} <small style="color: #999;">(${player.position} - ${player.team})</small></h3>
                                ${statsDisplay.join('')}
                            </div>
                        `;
                    }).join('');

                    document.getElementById('livePlayerStatsGrid').innerHTML = statsHtml;
                })
                .catch(error => {
                    console.error('Failed to update player stats:', error);
                });
        }

        // Update roster stats inline
        function updateRosterStats() {
            // First get player stats
            fetch('api/live-player-stats.php')
                .then(response => response.json())
                .then(statsData => {
                    if (!statsData.success || !statsData.players) return;

                    // Create lookup by player ID
                    const statsLookup = {};
                    statsData.players.forEach(player => {
                        statsLookup[player.player_id] = player;
                    });

                    // Then get calculated scores
                    return fetch('api/calculate-scores.php')
                        .then(response => response.json())
                        .then(scoresData => {
                            if (!scoresData.success || !scoresData.team_scores) return;

                            // Create points lookup
                            const pointsLookup = {};
                            scoresData.team_scores.forEach(team => {
                                team.players.forEach(player => {
                                    pointsLookup[player.player_id] = player.total_points;
                                });
                            });

                            // Update each roster item
                            document.querySelectorAll('.roster-item').forEach(item => {
                                const playerId = item.getAttribute('data-player-id');
                                const statsSpan = item.querySelector('.player-stats');
                                const playerStats = statsLookup[playerId];

                                // Remove existing points badge if any
                                const existingPoints = item.querySelector('.player-points');
                                if (existingPoints) {
                                    existingPoints.remove();
                                }

                                if (!playerStats) {
                                    statsSpan.innerHTML = '';
                                    item.classList.remove('has-live-stats');
                                    return;
                                }

                                // Build compact stats string
                                let statText = [];
                                
                                if (playerStats.stats.passing) {
                                    const p = playerStats.stats.passing;
                                    statText.push(`${p.yards} PASS YDS, ${p.tds} TD`);
                                }
                                if (playerStats.stats.rushing) {
                                    const r = playerStats.stats.rushing;
                                    statText.push(`${r.yards} RUSH YDS, ${r.tds} TD`);
                                }
                                if (playerStats.stats.receiving) {
                                    const rec = playerStats.stats.receiving;
                                    statText.push(`${rec.receptions} REC, ${rec.yards} YDS, ${rec.tds} TD`);
                                }
                                if (playerStats.stats.defensive) {
                                    const d = playerStats.stats.defensive;
                                    statText.push(`${d.tackles} TKL, ${d.sacks} SCK`);
                                }

                                if (statText.length > 0) {
                                    statsSpan.innerHTML = statText.join(' | ');
                                    item.classList.add('has-live-stats');
                                    
                                    // Add points badge
                                    const points = pointsLookup[playerId] || 0;
                                    const pointsBadge = document.createElement('span');
                                    pointsBadge.className = 'player-points' + (points === 0 ? ' zero' : '');
                                    pointsBadge.textContent = points.toFixed(1) + ' pts';
                                    item.appendChild(pointsBadge);
                                }
                            });
                        });
                })
                .catch(error => {
                    console.error('Failed to update roster stats:', error);
                });
        }

        // Initialize roster stats on page load
        document.addEventListener('DOMContentLoaded', () => {
            updateRosterStats();
            rosterStatsInterval = setInterval(updateRosterStats, 60000);
            
            // Load team scores
            loadTeamScores();
            setInterval(loadTeamScores, 60000); // Update every 60 seconds

            // Load latest chat
            updateLatestChat();
            setInterval(updateLatestChat, 60000); // Update every 60 seconds

            // Always update live scores regardless of tab
            updateLiveScores();
            setInterval(updateLiveScores, 60000); // Update every 60 seconds

            // Reload ticker periodically
            setInterval(() => {
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTicker = doc.querySelector('.live-ticker-container');
                        const currentTicker = document.querySelector('.live-ticker-container');
                        if (newTicker && currentTicker) {
                            currentTicker.innerHTML = newTicker.innerHTML;
                        }
                    });
            }, 60000); // Update every 60 seconds
        });

        // Load team scores
        function loadTeamScores() {
            fetch('api/calculate-scores.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.success || !data.team_scores || data.team_scores.length === 0) {
                        document.getElementById('scoresContent').innerHTML = 
                            '<p style="text-align: center; color: white;">No scores available yet</p>';
                        return;
                    }

                    // Store for modal access
                    currentTeamScores = data.team_scores;

                    // Update leaderboard
                    const scoresHtml = data.team_scores.map((team, index) => {
                        const rank = index + 1;
                        const isFirst = rank === 1;
                        const pointsColor = team.total_points > 0 ? '#fbbf24' : '#9ca3af';
                        const bgColor = isFirst ? 'rgba(15,23,42,0.95)' : 'rgba(30,41,59,0.9)';
                        const borderColor = isFirst ? '#f97316' : 'rgba(249,115,22,0.3)';
                        
                        return `
                            <div style="background: ${bgColor}; padding: 14px 20px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid ${borderColor}; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.3); cursor: pointer;" onclick="openTeamModal(${team.team_id})" onmouseover="this.style.transform='translateX(5px)'; this.style.boxShadow='0 6px 20px rgba(249,115,22,0.4)';" onmouseout="this.style.transform='translateX(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.3)';">
                                <div style="display: flex; align-items: center; gap: 18px; flex: 1;">
                                    <span style="color: #fbbf24; font-weight: bold; font-size: 1.3rem; min-width: 35px;">${rank}</span>
                                    <div style="flex: 1;">
                                        <div style="color: #ffffff; font-weight: bold; font-size: 1.1rem; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">${team.team_name}</div>
                                        <div style="color: #cbd5e1; font-size: 0.9rem;">${team.owner_name}</div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: ${pointsColor}; font-size: 1.8rem; font-weight: bold; text-shadow: 0 2px 8px rgba(251,191,36,0.3);">${team.total_points.toFixed(1)}</div>
                                    <div style="color: #94a3b8; font-size: 0.8rem;">${team.player_count} active</div>
                                </div>
                            </div>
                        `;
                    }).join('');

                    document.getElementById('scoresContent').innerHTML = scoresHtml;
                    
                    // Update team card scores
                    data.team_scores.forEach(team => {
                        const scoreElement = document.getElementById('team-score-' + team.team_id);
                        if (scoreElement) {
                            scoreElement.textContent = team.total_points.toFixed(1) + ' pts';
                            scoreElement.style.color = team.total_points > 0 ? '#fbbf24' : '#fff';
                        }
                    });
                })
                .catch(error => {
                    console.error('Failed to load scores:', error);
                    document.getElementById('scoresContent').innerHTML = 
                        '<p style="text-align: center; color: white;">Error loading scores</p>';
                });
        }

        // Lightbox functionality
        function openLightbox(imageSrc) {
            const lightbox = document.getElementById('lightbox');
            const lightboxImg = document.getElementById('lightbox-img');
            lightboxImg.src = imageSrc;
            lightbox.classList.add('active');
        }

        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
        }

        // Team modal functionality
        let currentTeamScores = null;

        function openTeamModal(teamId) {
            // Show loading state
            document.getElementById('modalTeamName').textContent = 'Loading...';
            document.getElementById('modalOwnerName').textContent = '';
            document.getElementById('modalTotalScore').textContent = '0.0';
            document.getElementById('modalPlayersList').innerHTML = '<p style="text-align: center; color: #94a3b8;">Loading roster...</p>';
            document.getElementById('teamModal').classList.add('active');

            // Fetch full roster
            Promise.all([
                fetch(`api/team-roster.php?team_id=${teamId}`).then(r => r.json()),
                fetch('api/calculate-scores.php').then(r => r.json())
            ]).then(([rosterData, scoresData]) => {
                if (!rosterData.success) {
                    document.getElementById('modalPlayersList').innerHTML = '<p style="text-align: center; color: #ef4444;">Error loading roster</p>';
                    return;
                }

                const team = rosterData.team;
                const roster = rosterData.roster;

                // Find team scores
                const teamScores = scoresData.success && scoresData.team_scores 
                    ? scoresData.team_scores.find(t => t.team_id === teamId) 
                    : null;

                // Create lookup for player scores
                const playerScoresLookup = {};
                if (teamScores && teamScores.players) {
                    teamScores.players.forEach(player => {
                        playerScoresLookup[player.player_id] = player;
                    });
                }

                // Update modal header
                document.getElementById('modalTeamName').textContent = team.team_name;
                document.getElementById('modalOwnerName').textContent = 'Owner: ' + team.owner_name;
                document.getElementById('modalTotalScore').textContent = teamScores ? teamScores.total_points.toFixed(1) : '0.0';

                // Build players list from full roster
                let playersHtml = '';
                if (roster && roster.length > 0) {
                    roster.forEach(rosterPlayer => {
                        const playerScore = playerScoresLookup[rosterPlayer.player_id];
                        const points = playerScore ? playerScore.total_points : 0;
                        const isLive = playerScore ? playerScore.is_live : false;
                        
                        let statsHtml = [];
                        
                        if (playerScore && playerScore.breakdown) {
                            // Passing stats
                            if (playerScore.breakdown.passing) {
                                const p = playerScore.breakdown.passing;
                                statsHtml.push(`<strong>Passing:</strong> ${p.yards.value} YDS (${p.yards.points} pts), ${p.tds.value} TD (${p.tds.points} pts), ${p.ints.value} INT (${p.ints.points} pts)`);
                            }
                            
                            // Rushing stats
                            if (playerScore.breakdown.rushing) {
                                const r = playerScore.breakdown.rushing;
                                statsHtml.push(`<strong>Rushing:</strong> ${r.yards.value} YDS (${r.yards.points} pts), ${r.tds.value} TD (${r.tds.points} pts)`);
                            }
                            
                            // Receiving stats
                            if (playerScore.breakdown.receiving) {
                                const rec = playerScore.breakdown.receiving;
                                statsHtml.push(`<strong>Receiving:</strong> ${rec.receptions.value} REC (${rec.receptions.points} pts), ${rec.yards.value} YDS (${rec.yards.points} pts), ${rec.tds.value} TD (${rec.tds.points} pts)`);
                            }
                            
                            // Defensive stats
                            if (playerScore.breakdown.defensive) {
                                const d = playerScore.breakdown.defensive;
                                statsHtml.push(`<strong>Defense:</strong> ${d.tackles.value} TKL (${d.tackles.points} pts), ${d.sacks.value} SCK (${d.sacks.points} pts), ${d.ints.value} INT (${d.ints.points} pts)`);
                            }
                        }

                        const liveIndicator = isLive ? '<span style="color: #ef4444; font-weight: bold;"> 🔴 LIVE</span>' : '';
                        const pointsColor = points > 0 ? '#fbbf24' : '#9ca3af';
                        
                        playersHtml += `
                            <div class="modal-player-item">
                                <div class="modal-player-header">
                                    <div>
                                        <span class="modal-player-name">${rosterPlayer.full_name}</span>
                                        <span class="modal-player-team">${rosterPlayer.nfl_team_abbr || 'FA'} - ${rosterPlayer.position}</span>
                                        ${liveIndicator}
                                    </div>
                                    <span class="modal-player-points" style="color: ${pointsColor};">${points.toFixed(1)}</span>
                                </div>
                                ${statsHtml.length > 0 ? `<div class="modal-player-stats">${statsHtml.join('<br>')}</div>` : '<div class="modal-player-stats">No stats yet</div>'}
                            </div>
                        `;
                    });
                } else {
                    playersHtml = '<p style="text-align: center; color: #94a3b8;">No players on this roster yet</p>';
                }

                document.getElementById('modalPlayersList').innerHTML = playersHtml;
            }).catch(error => {
                console.error('Error loading team details:', error);
                document.getElementById('modalPlayersList').innerHTML = '<p style="text-align: center; color: #ef4444;">Error loading roster</p>';
            });
        }

        function closeTeamModal(event) {
            if (!event || event.target.id === 'teamModal') {
                document.getElementById('teamModal').classList.remove('active');
            }
        }

        // Close lightbox on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
                closeTeamModal();
            }
        });
    </script>

    <!-- Lightbox -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
        <img id="lightbox-img" src="" alt="Full size image">
    </div>
</body>
</html>
