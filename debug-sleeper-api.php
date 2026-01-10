<?php
require_once 'config.php';

echo "<h2>Debug Sleeper API Responses</h2>";

// Test the 2024 league that's not working
$testLeagueId = '1062950707059302400'; // A Few Wiseguys and Some Idiots 2024

echo "<h3>Testing League ID: $testLeagueId</h3>";

function testApiCall($endpoint) {
    $url = 'https://api.sleeper.app/v1' . $endpoint;
    echo "<h4>Testing: $url</h4>";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'AmyoFootball/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "<p style='color: red;'>❌ API call failed</p>";
        return false;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p style='color: red;'>❌ Invalid JSON response</p>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
        return false;
    }
    
    if (empty($data)) {
        echo "<p style='color: orange;'>⚠️ Empty response (league may be archived)</p>";
        return false;
    }
    
    echo "<p style='color: green;'>✅ Success - " . count($data) . " items returned</p>";
    
    if (is_array($data) && count($data) > 0) {
        echo "<details><summary>First item preview:</summary>";
        echo "<pre>" . htmlspecialchars(json_encode($data[0], JSON_PRETTY_PRINT)) . "</pre>";
        echo "</details>";
    } else {
        echo "<details><summary>Response preview:</summary>";
        echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
        echo "</details>";
    }
    
    return $data;
}

// Test each API endpoint
echo "<h3>API Tests:</h3>";

$league = testApiCall("/league/$testLeagueId");
$users = testApiCall("/league/$testLeagueId/users");
$rosters = testApiCall("/league/$testLeagueId/rosters");

// Test with a working 2025 league for comparison
echo "<hr><h3>Comparison with 2025 league (1180273607582396416):</h3>";
$workingLeagueId = '1180273607582396416';

$workingLeague = testApiCall("/league/$workingLeagueId");
$workingUsers = testApiCall("/league/$workingLeagueId/users");
$workingRosters = testApiCall("/league/$workingLeagueId/rosters");

echo "<h3>Summary:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Endpoint</th><th>2024 League</th><th>2025 League</th></tr>";
echo "<tr><td>League Info</td><td>" . ($league ? "✅" : "❌") . "</td><td>" . ($workingLeague ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>Users</td><td>" . ($users ? "✅" : "❌") . "</td><td>" . ($workingUsers ? "✅" : "❌") . "</td></tr>";
echo "<tr><td>Rosters</td><td>" . ($rosters ? "✅" : "❌") . "</td><td>" . ($workingRosters ? "✅" : "❌") . "</td></tr>";
echo "</table>";

if (!$rosters) {
    echo "<h3>🔍 Possible Issues:</h3>";
    echo "<ul>";
    echo "<li><strong>League Archived:</strong> Sleeper may have archived 2024 data</li>";
    echo "<li><strong>Private League:</strong> League may be private and not accessible via API</li>";
    echo "<li><strong>API Changes:</strong> Sleeper may have changed how historical data is accessed</li>";
    echo "</ul>";
    
    echo "<h3>💡 Recommendations:</h3>";
    echo "<ul>";
    echo "<li>Try syncing more recent leagues (2023 or current 2025)</li>";
    echo "<li>Check if the league owner needs to make it public</li>";
    echo "<li>Use only current season leagues for standings</li>";
    echo "</ul>";
}
?>
