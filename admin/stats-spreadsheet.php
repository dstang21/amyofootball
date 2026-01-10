<?php
require_once '../config.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../index.php');
}

// Check if source is selected
$selected_source = $_GET['source'] ?? $_SESSION['selected_source'] ?? '';
if (!$selected_source) {
    redirect('manage-stats.php');
}

// Get source info
$source_query = $pdo->prepare("SELECT * FROM sources WHERE id = ?");
$source_query->execute([$selected_source]);
$source_data = $source_query->fetch();
if (!$source_data) {
    redirect('manage-stats.php');
}

// Get current season
$current_season_query = $pdo->query("SELECT * FROM seasons ORDER BY year DESC LIMIT 1");
$current_season = $current_season_query->fetch();

if (!$current_season) {
    redirect('manage-stats.php?error=no_season');
}

// Get scoring settings for dropdown
$scoring_settings = $pdo->query("SELECT * FROM scoring_settings ORDER BY name")->fetchAll();
$selected_scoring = $_GET['scoring'] ?? 5; // Default to scoring_settings id = 5

// Determine sort order - simple name sort for now
$sort_order = "p.full_name";

// Build query based on source selection
if ($selected_source == 1) {
    // For AmyoFootball source, include fallback logic
    $sql = "
        SELECT p.id as player_id, p.full_name, p.first_name, p.last_name,
               t.abbreviation as team_abbr, pt.position,
               
               -- Current source stats
               ps_current.id as current_stats_id,
               ps_current.passing_yards as curr_pass_yds,
               ps_current.passing_tds as curr_pass_tds,
               ps_current.interceptions as curr_ints,
               ps_current.rushing_yards as curr_rush_yds,
               ps_current.rushing_tds as curr_rush_tds,
               ps_current.receptions as curr_rec,
               ps_current.receiving_yards as curr_rec_yds,
               ps_current.receiving_tds as curr_rec_tds,
               ps_current.fumbles as curr_fumbles,
               
               -- AmyoFootball stats as fallback
               ps_amyo.id as amyo_stats_id,
               ps_amyo.passing_yards as amyo_pass_yds,
               ps_amyo.passing_tds as amyo_pass_tds,
               ps_amyo.interceptions as amyo_ints,
               ps_amyo.rushing_yards as amyo_rush_yds,
               ps_amyo.rushing_tds as amyo_rush_tds,
               ps_amyo.receptions as amyo_rec,
               ps_amyo.receiving_yards as amyo_rec_yds,
               ps_amyo.receiving_tds as amyo_rec_tds,
               ps_amyo.fumbles as amyo_fumbles,
               
               -- Fantasy points and scoring
               pfp.calculated_points,
               ss.passing_yards as scoring_pass_yds,
               ss.passing_td as scoring_pass_td,
               ss.passing_int as scoring_pass_int,
               ss.rushing_yards as scoring_rush_yds,
               ss.rushing_td as scoring_rush_td,
               ss.receptions as scoring_rec,
               ss.receiving_yards as scoring_rec_yds,
               ss.receiving_tds as scoring_rec_td,
               ss.fumbles_lost as scoring_fumbles
               
        FROM players p
        LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
        LEFT JOIN teams t ON pt.team_id = t.id
        LEFT JOIN projected_stats ps_current ON p.id = ps_current.player_id AND ps_current.season_id = ? AND ps_current.source_id = ?
        LEFT JOIN projected_stats ps_amyo ON p.id = ps_amyo.player_id AND ps_amyo.season_id = ? AND ps_amyo.source_id = 1
        LEFT JOIN projected_fantasy_points pfp ON COALESCE(ps_current.id, ps_amyo.id) = pfp.projected_stats_id AND pfp.scoring_setting_id = ?
        LEFT JOIN scoring_settings ss ON ss.id = ?
        WHERE p.full_name IS NOT NULL 
          AND p.full_name != ''
        ORDER BY $sort_order
    ";
    $params = [$current_season['id'], $current_season['id'], $selected_source, $current_season['id'], $selected_scoring, $selected_scoring];
} else {
    // For other sources, show all players but only with their source-specific data
    $sql = "
        SELECT p.id as player_id, p.full_name, p.first_name, p.last_name,
               t.abbreviation as team_abbr, pt.position,
               
               -- Current source stats only (NULL if not in this source)
               ps_current.id as current_stats_id,
               ps_current.passing_yards as curr_pass_yds,
               ps_current.passing_tds as curr_pass_tds,
               ps_current.interceptions as curr_ints,
               ps_current.rushing_yards as curr_rush_yds,
               ps_current.rushing_tds as curr_rush_tds,
               ps_current.receptions as curr_rec,
               ps_current.receiving_yards as curr_rec_yds,
               ps_current.receiving_tds as curr_rec_tds,
               ps_current.fumbles as curr_fumbles,
               
               -- No AmyoFootball fallback for other sources
               NULL as amyo_stats_id,
               NULL as amyo_pass_yds,
               NULL as amyo_pass_tds,
               NULL as amyo_ints,
               NULL as amyo_rush_yds,
               NULL as amyo_rush_tds,
               NULL as amyo_rec,
               NULL as amyo_rec_yds,
               NULL as amyo_rec_tds,
               NULL as amyo_fumbles,
               
               -- Fantasy points and scoring
               pfp.calculated_points,
               ss.passing_yards as scoring_pass_yds,
               ss.passing_td as scoring_pass_td,
               ss.passing_int as scoring_pass_int,
               ss.rushing_yards as scoring_rush_yds,
               ss.rushing_td as scoring_rush_td,
               ss.receptions as scoring_rec,
               ss.receiving_yards as scoring_rec_yds,
               ss.receiving_tds as scoring_rec_td,
               ss.fumbles_lost as scoring_fumbles
               
        FROM players p
        LEFT JOIN player_teams pt ON p.id = pt.player_id AND pt.season_id = ?
        LEFT JOIN teams t ON pt.team_id = t.id
        LEFT JOIN projected_stats ps_current ON p.id = ps_current.player_id AND ps_current.season_id = ? AND ps_current.source_id = ?
        LEFT JOIN projected_fantasy_points pfp ON ps_current.id = pfp.projected_stats_id AND pfp.scoring_setting_id = ?
        LEFT JOIN scoring_settings ss ON ss.id = ?
        WHERE p.full_name IS NOT NULL 
          AND p.full_name != ''
        ORDER BY $sort_order
    ";
    $params = [$current_season['id'], $current_season['id'], $selected_source, $selected_scoring, $selected_scoring];
}

$players_query = $pdo->prepare($sql);
$players_query->execute($params);
$players = $players_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projected Stats Spreadsheet - <?php echo htmlspecialchars($source_data['name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            overflow-x: auto;
            min-width: 1400px;
        }
        
        .header-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .header-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-primary { background: #1e40af; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn:hover { transform: translateY(-1px); opacity: 0.9; }
        
        .notification {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1001;
            padding: 12px 20px;
            border-radius: 6px;
            color: white;
            font-weight: bold;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .notification.show { transform: translateX(0); }
        .notification.success { background: #10b981; }
        .notification.error { background: #ef4444; }
        
        .spreadsheet-container {
            margin-top: 80px;
            padding: 20px;
            position: relative;
        }
        
        .table-wrapper {
            position: relative;
            height: calc(100vh - 120px);
            overflow: auto;
        }
        
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #374151;
            border-radius: 10px 10px 0 0;
        }
        
        .sticky-header-table,
        .spreadsheet-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 1400px;
            table-layout: fixed;
        }
        
        .sticky-header-table th,
        .spreadsheet-table th,
        .spreadsheet-table td {
            width: auto;
        }
        
        .sticky-header-table th:nth-child(1),
        .spreadsheet-table td:nth-child(1) {
            width: 150px;
            min-width: 150px;
        }
        
        .sticky-header-table th:nth-child(2),
        .spreadsheet-table td:nth-child(2) {
            width: 80px;
            min-width: 80px;
        }
        
        .sticky-header-table th:nth-child(3),
        .spreadsheet-table td:nth-child(3) {
            width: 60px;
            min-width: 60px;
        }
        
        .sticky-header-table th:nth-child(4),
        .spreadsheet-table td:nth-child(4) {
            width: 90px;
            min-width: 90px;
        }
        
        .sticky-header-table th:nth-child(5),
        .spreadsheet-table td:nth-child(5) {
            width: 80px;
            min-width: 80px;
        }
        
        .sticky-header-table th:nth-child(6),
        .spreadsheet-table td:nth-child(6) {
            width: 60px;
            min-width: 60px;
        }
        
        .sticky-header-table th:nth-child(7),
        .spreadsheet-table td:nth-child(7) {
            width: 100px;
            min-width: 100px;
        }
        
        .sticky-header-table th:nth-child(8),
        .spreadsheet-table td:nth-child(8) {
            width: 80px;
            min-width: 80px;
        }
        
        .sticky-header-table th:nth-child(9),
        .spreadsheet-table td:nth-child(9) {
            width: 60px;
            min-width: 60px;
        }
        
        .sticky-header-table th:nth-child(10),
        .spreadsheet-table td:nth-child(10) {
            width: 100px;
            min-width: 100px;
        }
        
        .sticky-header-table th:nth-child(11),
        .spreadsheet-table td:nth-child(11) {
            width: 80px;
            min-width: 80px;
        }
        
        .sticky-header-table th:nth-child(12),
        .spreadsheet-table td:nth-child(12) {
            width: 80px;
            min-width: 80px;
        }
        
        .sticky-header-table th:nth-child(13),
        .spreadsheet-table td:nth-child(13) {
            width: 120px;
            min-width: 120px;
        }
        
        .sticky-header-table {
            border-collapse: separate;
            border-spacing: 0;
            min-width: 1400px;
        }
        
        .sticky-header-table th {
            background: #374151 !important;
            color: white;
            padding: 12px 8px;
            font-weight: bold;
            font-size: 13px;
            text-align: center;
            border: 1px solid #4b5563;
        }
        
        .sticky-header-table th:first-child {
            border-radius: 10px 0 0 0;
        }
        
        .sticky-header-table th:last-child {
            border-radius: 0 10px 0 0;
        }
        
        .spreadsheet-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 0 0 10px 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            min-width: 1400px;
            margin-top: 0;
        }
        
        .spreadsheet-table thead {
            display: none;
        }
        
        .spreadsheet-table th {
            background: #374151 !important;
            color: white;
            padding: 12px 8px;
            font-weight: bold;
            font-size: 13px;
            text-align: center;
            border: 1px solid #4b5563;
        }
        
        .sortable-header:hover {
            background: #4b5563 !important;
        }
        
        .spreadsheet-table td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
            position: relative;
        }
        
        .spreadsheet-table tr:nth-child(even) { background: #f9fafb; }
        .spreadsheet-table tr:hover { background: #f3f4f6; }
        
        .player-name {
            text-align: left;
            font-weight: bold;
            min-width: 150px;
            background: #f8f9fa !important;
        }
        
        .editable-cell {
            min-width: 70px;
            cursor: pointer;
        }
        
        .editable-cell:hover {
            background: #dbeafe !important;
        }
        
        .team-cell:hover,
        .position-cell:hover {
            background: #fef3c7 !important;
            cursor: pointer;
        }
        
        .team-cell,
        .position-cell {
            cursor: pointer;
        }
        
        .editable-cell.editing {
            background: #fef3c7 !important;
        }
        
        .cell-input {
            width: 100%;
            border: 2px solid #3b82f6;
            padding: 4px 6px;
            text-align: center;
            font-size: 14px;
            background: white;
        }
        
        .position-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }
        
        .position-qb { background: #dc2626; }
        .position-rb { background: #059669; }
        .position-wr { background: #2563eb; }
        .position-te { background: #7c3aed; }
        .position-k { background: #d97706; }
        .position-def { background: #374151; }
        .position-na { background: #6b7280; }
        
        .fantasy-points-cell {
            font-weight: bold;
            color: #0277bd;
            cursor: pointer;
        }
        
        .fantasy-points-cell.header {
            background: #374151 !important;
            color: white !important;
        }
        
        .sortable-header {
            cursor: pointer;
            position: relative;
        }
        
        .sortable-header:hover {
            background: #4b5563 !important;
        }
        
        .sort-indicator {
            font-size: 10px;
            margin-left: 4px;
        }
        
        .fantasy-points-cell:hover {
            background: #b3e5fc !important;
        }
        
        .scoring-dropdown {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            font-size: 13px;
            margin-right: 10px;
        }
        
        .loading {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .undo-redo {
            display: flex;
            gap: 5px;
        }
        
        /* Ensure first row displays properly */
        tbody tr:first-child td {
            border-top: none !important;
        }
        
        tbody tr td:first-child,
        tbody tr td:nth-child(2),
        tbody tr td:nth-child(3) {
            font-weight: 600 !important;
            visibility: visible !important;
            display: table-cell !important;
        }
        
        .player-name {
            min-width: 150px !important;
            max-width: none !important;
            white-space: nowrap !important;
        }
        
        .stats-summary {
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <div class="header-title">
            📊 Projected Stats Spreadsheet - <?php echo htmlspecialchars($source_data['name']); ?>
            <div class="stats-summary"><?php echo count($players); ?> players • <?php echo $current_season['year']; ?> Season</div>
        </div>
        <div class="header-controls">
            <select id="positionFilter" class="scoring-dropdown" onchange="filterByPosition()">
                <option value="all">All Positions</option>
                <option value="QB">QB</option>
                <option value="RB">RB</option>
                <option value="WR">WR</option>
                <option value="TE">TE</option>
                <option value="flex">Flex (RB/WR/TE)</option>
            </select>
            <select id="scoringSelect" class="scoring-dropdown" onchange="changeScoringSystem()">
                <?php foreach ($scoring_settings as $setting): ?>
                    <option value="<?php echo $setting['id']; ?>" <?php echo $setting['id'] == $selected_scoring ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($setting['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="undo-redo">
                <button id="undoBtn" class="btn btn-warning" disabled>↶ Undo</button>
                <button id="redoBtn" class="btn btn-warning" disabled>↷ Redo</button>
            </div>
            <a href="manage-stats.php?source=<?php echo $selected_source; ?>" class="btn btn-secondary">← Back to Manage Stats</a>
        </div>
    </div>
    
    <div id="notification" class="notification"></div>
    
    <div class="spreadsheet-container">
        <div class="table-wrapper">
            <div class="sticky-header">
                <table class="sticky-header-table">
                    <thead>
                        <tr>
                            <th class="sortable-header" onclick="sortTable('name')">Player <span class="sort-indicator" id="sort-name"></span></th>
                            <th class="sortable-header" onclick="sortTable('team')">Team <span class="sort-indicator" id="sort-team"></span></th>
                            <th class="sortable-header" onclick="sortTable('position')">Pos <span class="sort-indicator" id="sort-position"></span></th>
                            <th class="sortable-header" onclick="sortTable('passing_yards')">Pass Yards <span class="sort-indicator" id="sort-passing_yards"></span></th>
                            <th class="sortable-header" onclick="sortTable('passing_tds')">Pass TDs <span class="sort-indicator" id="sort-passing_tds"></span></th>
                            <th class="sortable-header" onclick="sortTable('interceptions')">INTs <span class="sort-indicator" id="sort-interceptions"></span></th>
                            <th class="sortable-header" onclick="sortTable('rushing_yards')">Rush Yards <span class="sort-indicator" id="sort-rushing_yards"></span></th>
                            <th class="sortable-header" onclick="sortTable('rushing_tds')">Rush TDs <span class="sort-indicator" id="sort-rushing_tds"></span></th>
                            <th class="sortable-header" onclick="sortTable('receptions')">Rec <span class="sort-indicator" id="sort-receptions"></span></th>
                            <th class="sortable-header" onclick="sortTable('receiving_yards')">Rec Yards <span class="sort-indicator" id="sort-receiving_yards"></span></th>
                            <th class="sortable-header" onclick="sortTable('receiving_tds')">Rec TDs <span class="sort-indicator" id="sort-receiving_tds"></span></th>
                            <th class="sortable-header" onclick="sortTable('fumbles')">Fumbles <span class="sort-indicator" id="sort-fumbles"></span></th>
                            <th class="fantasy-points-cell header sortable-header" onclick="sortTable('fantasy_points')">Fantasy Points <span class="sort-indicator" id="sort-fantasy_points"></span></th>
                        </tr>
                    </thead>
                </table>
            </div>
            
            <table class="spreadsheet-table">
                <thead style="display: none;">
                    <tr>
                        <th>Player</th>
                        <th>Team</th>
                        <th>Pos</th>
                        <th>Pass Yards</th>
                        <th>Pass TDs</th>
                        <th>INTs</th>
                        <th>Rush Yards</th>
                        <th>Rush TDs</th>
                        <th>Rec</th>
                        <th>Rec Yards</th>
                        <th>Rec TDs</th>
                        <th>Fumbles</th>
                        <th>Fantasy Points</th>
                    </tr>
                </thead>
            <tbody><?php 
                foreach ($players as $player): 
                    // Determine which stats to show (current source or AmyoFootball fallback)
                    $stats_id = $player['current_stats_id'] ?: $player['amyo_stats_id'];
                    $pass_yds = $player['curr_pass_yds'] ?? $player['amyo_pass_yds'];
                    $pass_tds = $player['curr_pass_tds'] ?? $player['amyo_pass_tds'];
                    $ints = $player['curr_ints'] ?? $player['amyo_ints'];
                    $rush_yds = $player['curr_rush_yds'] ?? $player['amyo_rush_yds'];
                    $rush_tds = $player['curr_rush_tds'] ?? $player['amyo_rush_tds'];
                    $rec = $player['curr_rec'] ?? $player['amyo_rec'];
                    $rec_yds = $player['curr_rec_yds'] ?? $player['amyo_rec_yds'];
                    $rec_tds = $player['curr_rec_tds'] ?? $player['amyo_rec_tds'];
                    $fumbles = $player['curr_fumbles'] ?? $player['amyo_fumbles'];
                    
                    // Calculate fantasy points using current scoring system
                    $fantasy_points = 0;
                    if ($player['scoring_pass_yds']) {
                        $fantasy_points += ($pass_yds ?? 0) * $player['scoring_pass_yds'];
                        $fantasy_points += ($pass_tds ?? 0) * $player['scoring_pass_td'];
                        $fantasy_points += ($ints ?? 0) * $player['scoring_pass_int'];
                        $fantasy_points += ($rush_yds ?? 0) * $player['scoring_rush_yds'];
                        $fantasy_points += ($rush_tds ?? 0) * $player['scoring_rush_td'];
                        $fantasy_points += ($rec ?? 0) * $player['scoring_rec'];
                        $fantasy_points += ($rec_yds ?? 0) * $player['scoring_rec_yds'];
                        $fantasy_points += ($rec_tds ?? 0) * $player['scoring_rec_td'];
                        $fantasy_points += ($fumbles ?? 0) * $player['scoring_fumbles'];
                    }
                ?>
                <tr data-player-id="<?php echo $player['player_id']; ?>" data-stats-id="<?php echo $stats_id ?: ''; ?>" data-fantasy-points="<?php echo round($fantasy_points, 2); ?>">
                    <td class="player-name"><?php echo htmlspecialchars($player['full_name']); ?></td>
                    <td class="team-cell" data-field="team" onclick="editTeam(this, <?php echo $player['player_id']; ?>)"><?php echo htmlspecialchars($player['team_abbr'] ?? 'FA'); ?></td>
                    <td class="position-cell" data-field="position" onclick="editPosition(this, <?php echo $player['player_id']; ?>)">
                        <span class="position-badge position-<?php echo strtolower($player['position'] ?? 'na'); ?>">
                            <?php echo htmlspecialchars($player['position'] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td class="editable-cell" data-field="passing_yards"><?php echo $pass_yds ?: ''; ?></td>
                    <td class="editable-cell" data-field="passing_tds"><?php echo $pass_tds ?: ''; ?></td>
                    <td class="editable-cell" data-field="interceptions"><?php echo $ints ?: ''; ?></td>
                    <td class="editable-cell" data-field="rushing_yards"><?php echo $rush_yds ?: ''; ?></td>
                    <td class="editable-cell" data-field="rushing_tds"><?php echo $rush_tds ?: ''; ?></td>
                    <td class="editable-cell" data-field="receptions"><?php echo $rec ?: ''; ?></td>
                    <td class="editable-cell" data-field="receiving_yards"><?php echo $rec_yds ?: ''; ?></td>
                    <td class="editable-cell" data-field="receiving_tds"><?php echo $rec_tds ?: ''; ?></td>
                    <td class="editable-cell" data-field="fumbles"><?php echo $fumbles ?: ''; ?></td>
                    <td class="fantasy-points-cell" onclick="sortByFantasyPoints()"><?php echo $fantasy_points > 0 ? number_format($fantasy_points, 1) : ''; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <script>
    let undoStack = [];
    let redoStack = [];
    let currentlyEditing = null;
    let isLoading = false;

    const sourceId = <?php echo $selected_source; ?>;
    const seasonId = <?php echo $current_season['id']; ?>;
    
    // Scoring multipliers for fantasy points calculation
    const scoringSettings = <?php echo json_encode(array_column($scoring_settings, null, 'id')); ?>;
    let currentScoring = <?php echo $selected_scoring; ?>;

    // Initialize event listeners
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Spreadsheet script loaded'); // Debug line
        initializeSpreadsheet();
        updateUndoRedoButtons();
    });

    function initializeSpreadsheet() {
        const editableCells = document.querySelectorAll('.editable-cell');
        
        editableCells.forEach(cell => {
            cell.addEventListener('click', function(e) {
                if (currentlyEditing && currentlyEditing !== this) {
                    cancelEdit();
                }
                startEdit(this);
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && currentlyEditing) {
                cancelEdit();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
            }
            if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
                e.preventDefault();
                redo();
            }
        });

        // Undo/Redo buttons
        document.getElementById('undoBtn').addEventListener('click', undo);
        document.getElementById('redoBtn').addEventListener('click', redo);
    }

    function changeScoringSystem() {
        const select = document.getElementById('scoringSelect');
        const newScoring = select.value;
        
        // Update URL to include scoring parameter
        const url = new URL(window.location);
        url.searchParams.set('scoring', newScoring);
        window.location.href = url.toString();
    }
    
    let sortState = {
        column: null,
        ascending: true
    };

    function sortTable(column) {
        const table = document.querySelector('.spreadsheet-table tbody');
        const rows = Array.from(table.querySelectorAll('tr'));
        
        // Clear all sort indicators
        document.querySelectorAll('.sort-indicator').forEach(indicator => {
            indicator.textContent = '';
        });
        
        // Determine sort direction
        if (sortState.column === column) {
            sortState.ascending = !sortState.ascending;
        } else {
            sortState.ascending = true;
            sortState.column = column;
        }
        
        // Show sort indicator
        const indicator = document.getElementById('sort-' + column);
        indicator.textContent = sortState.ascending ? '▲' : '▼';
        
        // Sort rows
        rows.sort((a, b) => {
            let aValue, bValue;
            
            switch(column) {
                case 'name':
                    aValue = a.querySelector('.player-name').textContent.trim();
                    bValue = b.querySelector('.player-name').textContent.trim();
                    break;
                case 'team':
                    aValue = a.cells[1].textContent.trim();
                    bValue = b.cells[1].textContent.trim();
                    break;
                case 'position':
                    aValue = a.cells[2].textContent.trim();
                    bValue = b.cells[2].textContent.trim();
                    break;
                case 'fantasy_points':
                    aValue = parseFloat(a.dataset.fantasyPoints) || 0;
                    bValue = parseFloat(b.dataset.fantasyPoints) || 0;
                    break;
                default:
                    // For stat columns, find the corresponding cell
                    const fieldMapping = {
                        'passing_yards': 3,
                        'passing_tds': 4,
                        'interceptions': 5,
                        'rushing_yards': 6,
                        'rushing_tds': 7,
                        'receptions': 8,
                        'receiving_yards': 9,
                        'receiving_tds': 10,
                        'fumbles': 11
                    };
                    const cellIndex = fieldMapping[column];
                    aValue = parseFloat(a.cells[cellIndex].textContent) || 0;
                    bValue = parseFloat(b.cells[cellIndex].textContent) || 0;
            }
            
            if (typeof aValue === 'string') {
                return sortState.ascending ? 
                    aValue.localeCompare(bValue) : 
                    bValue.localeCompare(aValue);
            } else {
                return sortState.ascending ? aValue - bValue : bValue - aValue;
            }
        });
        
        // Re-append sorted rows
        rows.forEach(row => table.appendChild(row));
        
        showNotification(`Sorted by ${column} ${sortState.ascending ? '▲' : '▼'}`, 'success');
    }

    function sortByFantasyPoints() {
        sortTable('fantasy_points');
    }

    function filterByPosition() {
        const filterValue = document.getElementById('positionFilter').value;
        const rows = document.querySelectorAll('.spreadsheet-table tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const positionCell = row.cells[2]; // Position is the 3rd column (index 2)
            const position = positionCell.textContent.trim();
            
            let shouldShow = false;
            
            switch(filterValue) {
                case 'all':
                    shouldShow = true;
                    break;
                case 'QB':
                case 'RB':
                case 'WR':
                case 'TE':
                    shouldShow = position === filterValue;
                    break;
                case 'flex':
                    shouldShow = ['RB', 'WR', 'TE'].includes(position);
                    break;
            }
            
            if (shouldShow) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        const filterText = filterValue === 'all' ? 'All Positions' : 
                          filterValue === 'flex' ? 'Flex (RB/WR/TE)' : filterValue;
        showNotification(`Filtered to ${filterText} - ${visibleCount} players`, 'success');
    }

    function editTeam(cell, playerId) {
        if (currentlyEditing) return;
        
        // Check if player has stats in current source
        const row = cell.closest('tr');
        const statsId = row.dataset.statsId;
        
        currentlyEditing = cell;
        const currentValue = cell.textContent.trim();
        
        // Create team dropdown
        const select = document.createElement('select');
        select.className = 'cell-input';
        select.style.width = '100%';
        
        // Add teams - you might want to fetch this dynamically
        const teams = ['FA', 'ARI', 'ATL', 'BAL', 'BUF', 'CAR', 'CHI', 'CIN', 'CLE', 'DAL', 'DEN', 'DET', 'GB', 'HOU', 'IND', 'JAX', 'KC', 'LV', 'LAC', 'LAR', 'MIA', 'MIN', 'NE', 'NO', 'NYG', 'NYJ', 'PHI', 'PIT', 'SF', 'SEA', 'TB', 'TEN', 'WAS'];
        
        teams.forEach(team => {
            const option = document.createElement('option');
            option.value = team;
            option.textContent = team;
            if (team === currentValue) option.selected = true;
            select.appendChild(option);
        });
        
        cell.innerHTML = '';
        cell.appendChild(select);
        cell.classList.add('editing');
        select.focus();
        
        const saveTeam = () => {
            const newValue = select.value;
            cell.classList.remove('editing');
            cell.textContent = newValue;
            currentlyEditing = null;
            
            // Save to database
            saveTeamToDatabase(playerId, newValue);
        };
        
        select.addEventListener('change', saveTeam);
        select.addEventListener('blur', saveTeam);
        select.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') saveTeam();
            if (e.key === 'Escape') {
                cell.classList.remove('editing');
                cell.textContent = currentValue;
                currentlyEditing = null;
            }
        });
    }

    function editPosition(cell, playerId) {
        if (currentlyEditing) return;
        
        // Check if player has stats in current source
        const row = cell.closest('tr');
        const statsId = row.dataset.statsId;
        
        currentlyEditing = cell;
        let currentValue = 'QB'; // Default value
        
        // Try to get current position value
        const positionBadge = cell.querySelector('.position-badge');
        if (positionBadge) {
            let badgeText = positionBadge.textContent.trim();
            // Handle N/A case - default to QB
            if (badgeText && badgeText !== 'N/A' && badgeText !== 'NA') {
                currentValue = badgeText;
            }
        }
        
        // Create position dropdown
        const select = document.createElement('select');
        select.className = 'cell-input';
        select.style.width = '100%';
        
        const positions = ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'];
        
        positions.forEach(pos => {
            const option = document.createElement('option');
            option.value = pos;
            option.textContent = pos;
            if (pos === currentValue) option.selected = true;
            select.appendChild(option);
        });
        
        cell.innerHTML = '';
        cell.appendChild(select);
        cell.classList.add('editing');
        select.focus();
        
        let isProcessing = false;
        
        const savePosition = () => {
            if (isProcessing) return;
            isProcessing = true;
            
            const newValue = select.value;
            cell.classList.remove('editing');
            
            // Recreate position badge
            cell.innerHTML = `<span class="position-badge position-${newValue.toLowerCase()}">${newValue}</span>`;
            currentlyEditing = null;
            
            // Save to database
            savePositionToDatabase(playerId, newValue);
        };
        
        const cancelPosition = () => {
            if (isProcessing) return;
            isProcessing = true;
            
            cell.classList.remove('editing');
            // Restore original content, handling case where there might be no position badge
            if (positionBadge) {
                cell.innerHTML = positionBadge.outerHTML;
            } else {
                cell.innerHTML = `<span class="position-badge position-${currentValue.toLowerCase()}">${currentValue}</span>`;
            }
            currentlyEditing = null;
        };
        
        select.addEventListener('change', savePosition);
        select.addEventListener('blur', savePosition);
        select.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') savePosition();
            if (e.key === 'Escape') cancelPosition();
        });
    }

    function saveTeamToDatabase(playerId, teamAbbr) {
        if (isLoading) return;
        isLoading = true;
        document.body.classList.add('loading');
        
        const formData = new FormData();
        formData.append('action', 'update_team');
        formData.append('player_id', playerId);
        formData.append('team_abbr', teamAbbr);
        formData.append('season_id', seasonId);
        
        fetch('update-stat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(`Updated team to ${teamAbbr}`, 'success');
            } else {
                showNotification(`Error: ${data.error}`, 'error');
            }
        })
        .catch(error => {
            showNotification('Save failed: ' + error.message, 'error');
        })
        .finally(() => {
            isLoading = false;
            document.body.classList.remove('loading');
        });
    }

    function savePositionToDatabase(playerId, position) {
        if (isLoading) return;
        isLoading = true;
        document.body.classList.add('loading');

        console.log('Saving position:', position, 'for player:', playerId); // Debug line

        const formData = new FormData();
        formData.append('action', 'update_position');
        formData.append('player_id', playerId);
        formData.append('position', position);
        formData.append('season_id', seasonId);

        fetch('update-stat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Handle non-JSON responses
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP ${response.status}: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Position update response:', data); // Debug line
            if (data.success) {
                showNotification(`Updated position to ${position}`, 'success');
            } else {
                showNotification(`Error: ${data.error}`, 'error');
            }
        })
        .catch(error => {
            console.error('Position update error:', error); // Debug line
            showNotification('Save failed: ' + error.message, 'error');
        })
        .finally(() => {
            isLoading = false;
            document.body.classList.remove('loading');
        });
    }
    
    function recalculateFantasyPoints(row) {
        // Use requestAnimationFrame to make this non-blocking
        requestAnimationFrame(() => {
            try {
                const scoring = scoringSettings[currentScoring];
                if (!scoring) return 0;
                
                const getStatValue = (field) => {
                    const cell = row.querySelector(`[data-field="${field}"]`);
                    return parseFloat(cell.textContent.trim()) || 0;
                };
                
                let points = 0;
                points += getStatValue('passing_yards') * parseFloat(scoring.passing_yards);
                points += getStatValue('passing_tds') * parseFloat(scoring.passing_td);
                points += getStatValue('interceptions') * parseFloat(scoring.passing_int);
                points += getStatValue('rushing_yards') * parseFloat(scoring.rushing_yards);
                points += getStatValue('rushing_tds') * parseFloat(scoring.rushing_td);
                points += getStatValue('receptions') * parseFloat(scoring.receptions);
                points += getStatValue('receiving_yards') * parseFloat(scoring.receiving_yards);
                points += getStatValue('receiving_tds') * parseFloat(scoring.receiving_tds);
                points += getStatValue('fumbles') * parseFloat(scoring.fumbles_lost);
                
                // Update the fantasy points cell
                const fantasyCell = row.querySelector('.fantasy-points-cell');
                if (fantasyCell) {
                    fantasyCell.textContent = points > 0 ? points.toFixed(1) : '';
                    row.dataset.fantasyPoints = points.toFixed(2);
                }
                
                return points;
            } catch (error) {
                console.error('Error calculating fantasy points:', error);
                return 0;
            }
        });
    }

    function startEdit(cell) {
        currentlyEditing = cell;
        const currentValue = cell.textContent.trim();
        
        cell.classList.add('editing');
        cell.innerHTML = `<input type="number" class="cell-input" value="${currentValue}" step="0.1">`;
        
        const input = cell.querySelector('.cell-input');
        input.focus();
        input.select();
        
        let isNavigating = false;
        
        input.addEventListener('blur', function(e) {
            // Don't process blur if we're navigating via tab/enter
            if (isNavigating) return;
            finishEdit(cell, currentValue);
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                isNavigating = true;
                finishEditAndNavigate(cell, currentValue, 'next');
            } else if (e.key === 'Tab') {
                e.preventDefault();
                isNavigating = true;
                if (e.shiftKey) {
                    finishEditAndNavigate(cell, currentValue, 'prev');
                } else {
                    finishEditAndNavigate(cell, currentValue, 'next');
                }
            } else if (e.key === 'Escape') {
                isNavigating = true;
                cancelEdit();
            }
        });
    }

    function finishEditAndNavigate(cell, originalValue, direction) {
        if (!currentlyEditing) return;
        
        const input = cell.querySelector('.cell-input');
        const newValue = input.value.trim();
        const playerId = cell.closest('tr').dataset.playerId;
        const statsId = cell.closest('tr').dataset.statsId;
        const field = cell.dataset.field;
        
        cell.classList.remove('editing');
        cell.innerHTML = newValue;
        currentlyEditing = null;
        
        if (newValue !== originalValue) {
            // Add to undo stack
            undoStack.push({
                playerId: playerId,
                statsId: statsId,
                field: field,
                oldValue: originalValue,
                newValue: newValue,
                cell: cell
            });
            redoStack = []; // Clear redo stack
            updateUndoRedoButtons();
            
            // Recalculate fantasy points in the background (async)
            const row = cell.closest('tr');
            setTimeout(() => recalculateFantasyPoints(row), 0);
            
            // Save to database (async - don't wait)
            setTimeout(() => saveToDatabase(playerId, statsId, field, newValue), 0);
        }
        
        // Navigate immediately without waiting for anything
        if (direction === 'next') {
            moveToNextCell(cell);
        } else if (direction === 'prev') {
            moveToPrevCell(cell);
        }
    }

    function finishEdit(cell, originalValue) {
        if (!currentlyEditing) return;
        
        const input = cell.querySelector('.cell-input');
        const newValue = input.value.trim();
        const playerId = cell.closest('tr').dataset.playerId;
        const statsId = cell.closest('tr').dataset.statsId;
        const field = cell.dataset.field;
        
        cell.classList.remove('editing');
        cell.innerHTML = newValue;
        currentlyEditing = null;
        
        if (newValue !== originalValue) {
            // Add to undo stack
            undoStack.push({
                playerId: playerId,
                statsId: statsId,
                field: field,
                oldValue: originalValue,
                newValue: newValue,
                cell: cell
            });
            redoStack = []; // Clear redo stack
            updateUndoRedoButtons();
            
            // Recalculate fantasy points for this row (async)
            const row = cell.closest('tr');
            setTimeout(() => recalculateFantasyPoints(row), 0);
            
            // Save to database
            saveToDatabase(playerId, statsId, field, newValue);
        }
    }

    function moveToNextCell(currentCell) {
        const row = currentCell.closest('tr');
        const cells = row.querySelectorAll('.editable-cell');
        const currentIndex = Array.from(cells).indexOf(currentCell);
        
        if (currentIndex < cells.length - 1) {
            // Move to next cell in same row
            const nextCell = cells[currentIndex + 1];
            setTimeout(() => startEdit(nextCell), 50);
        } else {
            // Move to first cell of next row
            const nextRow = row.nextElementSibling;
            if (nextRow) {
                const firstCell = nextRow.querySelector('.editable-cell');
                if (firstCell) {
                    setTimeout(() => startEdit(firstCell), 50);
                }
            }
        }
    }
    
    function moveToPrevCell(currentCell) {
        const row = currentCell.closest('tr');
        const cells = row.querySelectorAll('.editable-cell');
        const currentIndex = Array.from(cells).indexOf(currentCell);
        
        if (currentIndex > 0) {
            // Move to previous cell in same row
            const prevCell = cells[currentIndex - 1];
            setTimeout(() => startEdit(prevCell), 50);
        } else {
            // Move to last cell of previous row
            const prevRow = row.previousElementSibling;
            if (prevRow) {
                const editableCells = prevRow.querySelectorAll('.editable-cell');
                const lastCell = editableCells[editableCells.length - 1];
                if (lastCell) {
                    setTimeout(() => startEdit(lastCell), 50);
                }
            }
        }
    }

    function cancelEdit() {
        if (!currentlyEditing) return;
        
        const input = currentlyEditing.querySelector('.cell-input');
        const originalValue = input.defaultValue;
        
        currentlyEditing.classList.remove('editing');
        currentlyEditing.innerHTML = originalValue;
        currentlyEditing = null;
    }

    function saveToDatabase(playerId, statsId, field, value) {
        console.log('Saving to database:', { playerId, statsId, field, value, sourceId, seasonId });
        
        const formData = new FormData();
        formData.append('action', 'update_stat');
        formData.append('player_id', playerId);
        formData.append('stats_id', statsId || ''); // Ensure empty string instead of null/undefined
        formData.append('field', field);
        formData.append('value', value);
        formData.append('source_id', sourceId);
        formData.append('season_id', seasonId);
        
        // Use setTimeout to make this truly asynchronous and non-blocking
        setTimeout(() => {
            isLoading = true;
            document.body.classList.add('loading');
            
            fetch('update-stat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showNotification(`Updated ${field} for player`, 'success');
                    // Update stats_id if a new record was created
                    if (data.stats_id) {
                        const row = document.querySelector(`tr[data-player-id="${playerId}"]`);
                        const currentStatsId = row.dataset.statsId;
                        
                        // Always update the stats_id, especially if current is empty or different
                        if (!currentStatsId || currentStatsId === '' || currentStatsId != data.stats_id) {
                            row.dataset.statsId = data.stats_id;
                            console.log(`Updated stats_id for player ${playerId}: ${currentStatsId} -> ${data.stats_id}`);
                        }
                    }
                } else {
                    showNotification(`Error: ${data.error}`, 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showNotification('Save failed: ' + error.message, 'error');
            })
            .finally(() => {
                isLoading = false;
                document.body.classList.remove('loading');
            });
        }, 0);
    }

    function undo() {
        if (undoStack.length === 0) return;
        
        const action = undoStack.pop();
        redoStack.push(action);
        
        action.cell.innerHTML = action.oldValue;
        
        // Recalculate fantasy points (async)
        const row = action.cell.closest('tr');
        setTimeout(() => recalculateFantasyPoints(row), 0);
        
        saveToDatabase(action.playerId, action.statsId, action.field, action.oldValue);
        updateUndoRedoButtons();
        
        showNotification('Undid last change', 'success');
    }

    function redo() {
        if (redoStack.length === 0) return;
        
        const action = redoStack.pop();
        undoStack.push(action);
        
        action.cell.innerHTML = action.newValue;
        
        // Recalculate fantasy points (async)
        const row = action.cell.closest('tr');
        setTimeout(() => recalculateFantasyPoints(row), 0);
        
        saveToDatabase(action.playerId, action.statsId, action.field, action.newValue);
        updateUndoRedoButtons();
        
        showNotification('Redid last change', 'success');
    }

    function updateUndoRedoButtons() {
        document.getElementById('undoBtn').disabled = undoStack.length === 0;
        document.getElementById('redoBtn').disabled = redoStack.length === 0;
    }

    function showNotification(message, type) {
        const notification = document.getElementById('notification');
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.classList.add('show');
        
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }

    // Make functions globally accessible
    window.editTeam = editTeam;
    window.editPosition = editPosition;
    </script>
</body>
</html>
