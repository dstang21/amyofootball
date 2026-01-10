<?php
header('Content-Type: application/json');

// ESPN's public scoreboard API
$week = isset($_GET['week']) ? intval($_GET['week']) : 'current';
$season = isset($_GET['season']) ? intval($_GET['season']) : date('Y');

// Cache file to avoid hitting API too frequently
$cacheFile = __DIR__ . '/cache/scores_' . $season . '_' . $week . '.json';
$cacheTime = 60; // Cache for 60 seconds during live games

// Check if cache exists and is fresh
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    exit;
}

// Fetch from ESPN API
$url = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode(['error' => 'Failed to fetch scores', 'code' => $httpCode]);
    exit;
}

$data = json_decode($response, true);

// Extract relevant game data
$games = [];
if (isset($data['events']) && is_array($data['events'])) {
    foreach ($data['events'] as $event) {
        $competition = $event['competitions'][0] ?? null;
        if (!$competition) continue;

        $homeTeam = null;
        $awayTeam = null;

        foreach ($competition['competitors'] as $competitor) {
            $teamData = [
                'id' => $competitor['id'] ?? '',
                'name' => $competitor['team']['displayName'] ?? 'Unknown',
                'abbreviation' => $competitor['team']['abbreviation'] ?? '',
                'logo' => $competitor['team']['logo'] ?? '',
                'score' => $competitor['score'] ?? '0',
                'record' => $competitor['records'][0]['summary'] ?? '',
            ];

            if ($competitor['homeAway'] === 'home') {
                $homeTeam = $teamData;
            } else {
                $awayTeam = $teamData;
            }
        }

        $status = $competition['status'] ?? [];
        $statusType = $status['type']['name'] ?? 'Unknown';
        $statusDetail = $status['type']['detail'] ?? '';
        $clock = $status['displayClock'] ?? '';
        $period = $status['period'] ?? 0;

        $games[] = [
            'id' => $event['id'] ?? '',
            'name' => $event['name'] ?? '',
            'shortName' => $event['shortName'] ?? '',
            'date' => $event['date'] ?? '',
            'homeTeam' => $homeTeam,
            'awayTeam' => $awayTeam,
            'status' => $statusType,
            'statusDetail' => $statusDetail,
            'clock' => $clock,
            'period' => $period,
            'isLive' => in_array($statusType, ['STATUS_IN_PROGRESS', 'STATUS_HALFTIME']),
            'isCompleted' => $statusType === 'STATUS_FINAL',
            'isScheduled' => $statusType === 'STATUS_SCHEDULED',
        ];
    }
}

$result = [
    'success' => true,
    'timestamp' => time(),
    'games' => $games,
    'week' => $data['week']['number'] ?? null,
    'season' => $data['season']['year'] ?? null,
];

// Create cache directory if it doesn't exist
if (!is_dir(__DIR__ . '/cache')) {
    mkdir(__DIR__ . '/cache', 0755, true);
}

// Save to cache
file_put_contents($cacheFile, json_encode($result));

echo json_encode($result);
