<?php
require_once '../config.php';
require_once 'admin-header.php';

// Check if user is admin (user_id = 1)
if (!isAdmin()) {
    redirect('../login.php');
}

require_once 'SleeperController.php';

$controller = new SleeperController();
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['scrape_league_history'])) {
        $leagueId = trim($_POST['league_id']);
        if ($leagueId) {
            try {
                $result = $controller->scrapeLeagueHistory($leagueId);
                $message = $result['message'];
            } catch (Exception $e) {
                $error = "Error scraping league history: " . $e->getMessage();
            }
        } else {
            $error = "Please provide a League ID";
        }
    } elseif (isset($_POST['scrape_all_histories'])) {
        try {
            $result = $controller->scrapeAllLeagueHistories();
            $message = $result['message'];
        } catch (Exception $e) {
            $error = "Error scraping all league histories: " . $e->getMessage();
        }
    }
}

// Get current leagues
$leagues = $controller->getLeagues();
$historyData = $controller->getLeagueHistorySummary();
?>

<!DOCTYPE html>
<html>
<head>
    <title>League History Management - AmyoFootball Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #2a5298;
        }
        .league-history-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #ff6b35;
        }
        .btn-scrape {
            background: #28a745;
            border-color: #28a745;
        }
        .btn-scrape:hover {
            background: #218838;
            border-color: #1e7e34;
        }
    </style>
</head>
<body>
    <?php include 'admin-nav.php'; ?>
    
    <div class="admin-header">
        <div class="container">
            <h1><i class="fas fa-history"></i> League History Management</h1>
            <p class="mb-0">Scrape and manage historical data for Sleeper leagues</p>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Overview Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3 class="text-primary"><?php echo count($leagues); ?></h3>
                    <p class="mb-0">Total Leagues</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3 class="text-success"><?php echo count($historyData); ?></h3>
                    <p class="mb-0">Histories Tracked</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3 class="text-warning">
                        <?php 
                        $totalSeasons = 0;
                        foreach ($historyData as $history) {
                            $totalSeasons += $history['season_count'];
                        }
                        echo $totalSeasons;
                        ?>
                    </h3>
                    <p class="mb-0">Total Seasons</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3 class="text-info">
                        <?php 
                        $champions = 0;
                        foreach ($historyData as $history) {
                            $champions += $history['champions_tracked'];
                        }
                        echo $champions;
                        ?>
                    </h3>
                    <p class="mb-0">Champions Tracked</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Scraping Tools -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-download"></i> Scrape League History</h4>
                    </div>
                    <div class="card-body">
                        <!-- Single League Scraping -->
                        <form method="POST" class="mb-4">
                            <div class="mb-3">
                                <label for="league_id" class="form-label">League ID</label>
                                <input type="text" class="form-control" id="league_id" name="league_id" 
                                       placeholder="Enter Sleeper League ID" required>
                                <div class="form-text">Enter a Sleeper League ID to scrape its complete history</div>
                            </div>
                            <button type="submit" name="scrape_league_history" class="btn btn-scrape btn-primary">
                                <i class="fas fa-search"></i> Scrape Single League History
                            </button>
                        </form>

                        <hr>

                        <!-- Bulk Scraping -->
                        <form method="POST">
                            <div class="mb-3">
                                <h6>Scrape All Known Leagues</h6>
                                <p class="text-muted small">This will scrape historical data for all leagues currently in the database. This may take several minutes.</p>
                            </div>
                            <button type="submit" name="scrape_all_histories" class="btn btn-warning" 
                                    onclick="return confirm('This will scrape all league histories and may take a long time. Continue?')">
                                <i class="fas fa-history"></i> Scrape All League Histories
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Current League History Data -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-list"></i> Current League Histories</h4>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <?php if (!empty($historyData)): ?>
                            <?php foreach ($historyData as $history): ?>
                                <div class="league-history-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($history['league_name']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $history['league_id']; ?></small>
                                        </div>
                                        <span class="badge bg-primary"><?php echo $history['season_count']; ?> seasons</span>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-success">
                                            <i class="fas fa-crown"></i> <?php echo $history['champions_tracked']; ?> champions tracked
                                        </small>
                                    </div>
                                    <?php if ($history['last_updated']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                Last updated: <?php echo date('M j, Y g:i A', strtotime($history['last_updated'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No league history data available yet.</p>
                                <p>Use the scraping tools to import league histories.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Leagues Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h4><i class="fas fa-trophy"></i> Available Leagues</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($leagues)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>League Name</th>
                                    <th>Season</th>
                                    <th>Teams</th>
                                    <th>Status</th>
                                    <th>History Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leagues as $league): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($league['name']); ?></strong></td>
                                        <td><?php echo $league['season']; ?></td>
                                        <td><?php echo $league['total_rosters']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $league['status'] === 'complete' ? 'success' : 'primary'; ?>">
                                                <?php echo ucfirst($league['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $hasHistory = false;
                                            foreach ($historyData as $history) {
                                                if ($history['league_id'] === $league['league_id']) {
                                                    $hasHistory = true;
                                                    break;
                                                }
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $hasHistory ? 'success' : 'secondary'; ?>">
                                                <?php echo $hasHistory ? 'Tracked' : 'Not Tracked'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="league_id" value="<?php echo $league['league_id']; ?>">
                                                <button type="submit" name="scrape_league_history" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-sync"></i> <?php echo $hasHistory ? 'Update' : 'Scrape'; ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No leagues found. Import some leagues first.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
