<?php
require_once '../../config.php';

header('Content-Type: application/json');
ob_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $team_id = $_POST['team_id'] ?? null;
    $team_name = trim($_POST['team_name'] ?? '');
    $logo = $_POST['logo'] ?? '';

    if (!$team_id || !$team_name) {
        throw new Exception('Missing required fields');
    }

    // Handle file upload if provided
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = $_FILES['logo_file']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
        $filename = 'team_' . $team_id . '_' . time() . '.' . $extension;
        $upload_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $upload_path)) {
            $logo = 'uploads/' . $filename;
        } else {
            throw new Exception('Failed to upload file');
        }
    }

    // Update team
    $stmt = $pdo->prepare("UPDATE wyandotte_teams SET team_name = ?, logo = ? WHERE id = ?");
    $stmt->execute([$team_name, $logo, $team_id]);

    ob_clean();
    echo json_encode([
        'success' => true,
        'logo' => $logo
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
