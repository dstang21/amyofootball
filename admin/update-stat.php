<?php
require_once '../config.php';

// Only allow admin users (user_id = 1)
if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'update_stat') {
    try {
        // Debug logging
        error_log('Update stat request: ' . print_r($_POST, true));
        
        $player_id = (int)$_POST['player_id'];
        $stats_id = $_POST['stats_id'] && $_POST['stats_id'] !== '' ? (int)$_POST['stats_id'] : null;
        $field = $_POST['field'];
        $value = $_POST['value'] === '' ? null : $_POST['value'];
        $source_id = (int)$_POST['source_id'];
        $season_id = (int)$_POST['season_id'];
        
        error_log("Parsed values: player_id=$player_id, stats_id=$stats_id, field=$field, value=$value, source_id=$source_id, season_id=$season_id");
        
        // Validate field name to prevent injection
        $allowed_fields = [
            'passing_yards', 'passing_tds', 'interceptions',
            'rushing_yards', 'rushing_tds', 'fumbles',
            'receptions', 'receiving_yards', 'receiving_tds'
        ];
        
        if (!in_array($field, $allowed_fields)) {
            throw new Exception('Invalid field name');
        }
        
        if (!$player_id || !$source_id || !$season_id) {
            throw new Exception('Missing required parameters');
        }
        
        $pdo->beginTransaction();
        
        // Check if we need to create or update the stats record
        if ($stats_id) {
            // First verify the stats record belongs to this player and source
            $verify_stmt = $pdo->prepare("SELECT id FROM projected_stats WHERE id = ? AND player_id = ? AND source_id = ?");
            $verify_stmt->execute([$stats_id, $player_id, $source_id]);
            
            if ($verify_stmt->fetch()) {
                // Update existing record that belongs to this player and source
                $stmt = $pdo->prepare("UPDATE projected_stats SET {$field} = ? WHERE id = ?");
                $stmt->execute([$value, $stats_id]);
                $response_stats_id = $stats_id;
            } else {
                // Stats ID doesn't belong to this player/source, fall back to upsert
                $stmt = $pdo->prepare("INSERT INTO projected_stats (player_id, season_id, source_id, {$field}) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE {$field} = VALUES({$field}), source_id = VALUES(source_id)");
                $stmt->execute([$player_id, $season_id, $source_id, $value]);
                
                // Get the record ID
                $id_stmt = $pdo->prepare("SELECT id FROM projected_stats WHERE player_id = ? AND season_id = ?");
                $id_stmt->execute([$player_id, $season_id]);
                $response_stats_id = $id_stmt->fetchColumn();
            }
        } else {
            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle the constraint
            $stmt = $pdo->prepare("INSERT INTO projected_stats (player_id, season_id, source_id, {$field}) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE {$field} = VALUES({$field}), source_id = VALUES(source_id)");
            $stmt->execute([$player_id, $season_id, $source_id, $value]);
            
            // Get the record ID (either newly inserted or existing updated)
            $id_stmt = $pdo->prepare("SELECT id FROM projected_stats WHERE player_id = ? AND season_id = ?");
            $id_stmt->execute([$player_id, $season_id]);
            $response_stats_id = $id_stmt->fetchColumn();
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'stats_id' => $response_stats_id,
            'message' => 'Stat updated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
} elseif ($action === 'update_team') {
    try {
        $player_id = (int)$_POST['player_id'];
        $team_abbr = $_POST['team_abbr'];
        $season_id = (int)$_POST['season_id'];
        
        if (!$player_id || !$season_id) {
            throw new Exception('Missing required parameters');
        }
        
        // Get current season
        $current_season = $pdo->prepare("SELECT id FROM seasons WHERE is_current = 1 LIMIT 1");
        $current_season->execute();
        $season = $current_season->fetch();
        
        if (!$season) {
            throw new Exception('No current season found');
        }
        
        // Find team ID by abbreviation
        $team_id = null;
        if ($team_abbr !== 'FA') {
            $team_stmt = $pdo->prepare("SELECT id FROM teams WHERE abbreviation = ?");
            $team_stmt->execute([$team_abbr]);
            $team = $team_stmt->fetch();
            if ($team) {
                $team_id = $team['id'];
            }
        }
        
        $pdo->beginTransaction();
        
        // Update or insert player_teams record
        $check_stmt = $pdo->prepare("SELECT id FROM player_teams WHERE player_id = ? AND season_id = ?");
        $check_stmt->execute([$player_id, $season_id]);
        $existing = $check_stmt->fetch();
        
        if ($existing) {
            // Update existing record
            $update_stmt = $pdo->prepare("UPDATE player_teams SET team_id = ? WHERE player_id = ? AND season_id = ?");
            $update_stmt->execute([$team_id, $player_id, $season_id]);
        } else {
            // Insert new record
            $insert_stmt = $pdo->prepare("INSERT INTO player_teams (player_id, team_id, season_id) VALUES (?, ?, ?)");
            $insert_stmt->execute([$player_id, $team_id, $season_id]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Team updated successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
} elseif ($action === 'update_position') {
    try {
        $player_id = (int)$_POST['player_id'];
        $position = $_POST['position'];
        $season_id = isset($_POST['season_id']) ? (int)$_POST['season_id'] : null;

        if (!$player_id || !$position || !$season_id) {
            throw new Exception('Missing required parameters');
        }

        // Validate position
        $allowed_positions = ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'];
        if (!in_array($position, $allowed_positions)) {
            throw new Exception('Invalid position: ' . $position);
        }

        $pdo->beginTransaction();

        // Check if player_teams record exists for this player and season
        $check_stmt = $pdo->prepare("SELECT id, team_id FROM player_teams WHERE player_id = ? AND season_id = ?");
        $check_stmt->execute([$player_id, $season_id]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            // Update existing record
            $update_stmt = $pdo->prepare("UPDATE player_teams SET position = ? WHERE player_id = ? AND season_id = ?");
            $update_stmt->execute([$position, $player_id, $season_id]);
        } else {
            // Insert new record with Free Agent team_id (33)
            $insert_stmt = $pdo->prepare("INSERT INTO player_teams (player_id, team_id, season_id, position) VALUES (?, 33, ?, ?)");
            $insert_stmt->execute([$player_id, $season_id, $position]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Position updated successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
