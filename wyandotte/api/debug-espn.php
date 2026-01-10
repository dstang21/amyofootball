<?php
// Debug ESPN API response
$gameId = isset($_GET['gameId']) ? $_GET['gameId'] : null;

if (!$gameId) {
    // Get first game ID from scoreboard
    $scoreboardUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $scoreboardUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $gameId = $data['events'][0]['id'] ?? null;
}

if ($gameId) {
    $boxScoreUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/summary?event={$gameId}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $boxScoreUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    header('Content-Type: application/json');
    echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT);
} else {
    echo "No game ID found";
}
