<?php
/**
 * Sleeper Auto-Sync Cron Job
 * 
 * This script can be run via cron to automatically sync Sleeper data
 * 
 * Usage:
 * - Daily: php sleeper-cron.php
 * - Weekly: php sleeper-cron.php --full
 * 
 * Cron examples:
 * # Daily sync at 2 AM
 * 0 2 * * * php /path/to/amyofootball/sleeper-cron.php
 * 
 * # Weekly full sync on Sundays at 3 AM
 * 0 3 * * 0 php /path/to/amyofootball/sleeper-cron.php --full
 */

require_once 'config.php';
require_once 'SleeperSync.php';

// Configuration
$logFile = 'logs/sleeper-cron.log';
$fullSync = in_array('--full', $argv);

// Ensure log directory exists
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    echo "[$timestamp] $message\n";
}

try {
    // Database connection
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    logMessage("Starting Sleeper cron sync" . ($fullSync ? " (FULL)" : " (DAILY)"));
    
    // Get all leagues to sync
    $stmt = $pdo->query("SELECT league_id, name FROM sleeper_leagues ORDER BY season DESC");
    $leagues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($leagues) . " leagues to sync");
    
    foreach ($leagues as $league) {
        try {
            logMessage("Syncing league: {$league['name']} ({$league['league_id']})");
            
            $sync = new SleeperSync($league['league_id'], $pdo);
            
            if ($fullSync) {
                // Full sync - all data
                $result = $sync->syncAll();
            } else {
                // Daily sync - only recent data
                $sync->syncRosters();
                sleep(1);
                $sync->syncMatchups();
                sleep(1);
                $sync->syncTransactions();
                $result = ['success' => true, 'message' => 'Daily sync completed'];
            }
            
            if ($result['success']) {
                logMessage("✓ League sync successful: " . $result['message']);
            } else {
                logMessage("✗ League sync failed: " . $result['message']);
            }
            
            // Rate limiting between leagues
            sleep(2);
            
        } catch (Exception $e) {
            logMessage("✗ Error syncing league {$league['league_id']}: " . $e->getMessage());
        }
    }
    
    // Sync player data (weekly)
    if ($fullSync || date('w') == 0) { // Sunday or full sync
        try {
            logMessage("Syncing player database...");
            $sync = new SleeperSync('dummy', $pdo); // League ID not needed for players
            $sync->syncPlayers();
            logMessage("✓ Player database sync completed");
        } catch (Exception $e) {
            logMessage("✗ Player database sync failed: " . $e->getMessage());
        }
    }
    
    // Sync current week stats
    try {
        logMessage("Syncing current week stats...");
        $sync = new SleeperSync('dummy', $pdo);
        $sync->syncStats();
        logMessage("✓ Stats sync completed");
    } catch (Exception $e) {
        logMessage("✗ Stats sync failed: " . $e->getMessage());
    }
    
    logMessage("Sleeper cron sync completed successfully");
    
} catch (Exception $e) {
    logMessage("✗ Fatal error: " . $e->getMessage());
    exit(1);
}
?>
