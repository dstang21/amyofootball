<?php
header('Content-Type: application/json');

if (!isset($_GET['name'])) {
    echo json_encode(['success' => false, 'error' => 'No filename provided']);
    exit;
}

$filename = $_GET['name'];
// Sanitize filename
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);

$imageDir = dirname(__DIR__) . '/chat-images/';
$extensions = ['png', 'jpg', 'jpeg', 'gif'];

foreach ($extensions as $ext) {
    $filepath = $imageDir . $filename . '.' . $ext;
    if (file_exists($filepath)) {
        echo json_encode([
            'success' => true,
            'url' => 'chat-images/' . $filename . '.' . $ext,
            'extension' => $ext
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Image not found']);
