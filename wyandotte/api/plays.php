<?php
require_once '../../config.php';

// Set JSON header
header('Content-Type: application/json');

// Start output buffering to catch any stray output
ob_start();

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get':
            // Get plays with optional limit
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $limit = max(1, min($limit, 100)); // Between 1 and 100
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.id,
                    p.play_type,
                    p.description,
                    p.points,
                    p.game_info,
                    p.play_time,
                    p.created_at,
                    pl.full_name as player_name,
                    pt.position,
                    t.abbreviation as team_abbr,
                    t.logo as team_logo
                FROM wyandotte_plays p
                LEFT JOIN players pl ON p.player_id = pl.id
                LEFT JOIN wyandotte_rosters wr ON wr.player_id = pl.id
                LEFT JOIN player_teams pt ON pt.player_id = pl.id
                LEFT JOIN teams t ON t.id = pt.team_id
                ORDER BY p.created_at DESC
                LIMIT " . $limit
            );
            $stmt->execute();
            $plays = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'plays' => $plays
            ]);
            break;
            
        case 'latest':
            // Get the most recent play
            $stmt = $pdo->prepare("
                SELECT 
                    p.id,
                    p.play_type,
                    p.description,
                    p.points,
                    p.game_info,
                    p.play_time,
                    p.created_at,
                    pl.full_name as player_name,
                    pt.position,
                    t.abbreviation as team_abbr,
                    t.logo as team_logo
                FROM wyandotte_plays p
                LEFT JOIN players pl ON p.player_id = pl.id
                LEFT JOIN player_teams pt ON pt.player_id = pl.id
                LEFT JOIN teams t ON t.id = pt.team_id
                ORDER BY p.created_at DESC
                LIMIT 1
            ");
            $stmt->execute();
            $play = $stmt->fetch(PDO::FETCH_ASSOC);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'play' => $play ?: null
            ]);
            break;
            
        case 'post':
            // Add a new play (admin only feature, but allowing for now)
            $player_id = $_POST['player_id'] ?? null;
            $play_type = $_POST['play_type'] ?? '';
            $description = $_POST['description'] ?? '';
            $points = $_POST['points'] ?? 0;
            $game_info = $_POST['game_info'] ?? null;
            $play_time = $_POST['play_time'] ?? null;
            
            if (!$player_id || !$play_type || !$description) {
                ob_clean();
                echo json_encode([
                    'success' => false,
                    'error' => 'Missing required fields'
                ]);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO wyandotte_plays (player_id, play_type, description, points, game_info, play_time)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$player_id, $play_type, $description, $points, $game_info, $play_time]);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'play_id' => $pdo->lastInsertId()
            ]);
            break;
            
        default:
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
