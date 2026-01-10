<?php
require_once '../config.php';
require_once 'admin-header.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

// Only load SleeperController when needed to avoid syntax errors
$leagues = [];
try {
    require_once 'SleeperController.php';
    $controller = new SleeperController();
    $leagues = $controller->getLeagues();
} catch (Exception $e) {
    // Handle case where SleeperSync.php has syntax errors
    error_log("SleeperController error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sleeper Leagues - AmyoFootball Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
</head>
<body>
    <div class="container-fluid">
        <?php include 'admin-nav.php'; ?>
        
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Sleeper Leagues Management</h1>
                
                <?php if (empty($leagues) && !class_exists('SleeperController')): ?>
                <div class="alert alert-warning" role="alert">
                    <h5>⚠️ Sleeper Integration Setup Required</h5>
                    <p>The SleeperSync.php file needs to be updated on the server to enable Sleeper functionality. 
                       Please upload the corrected version of SleeperSync.php to resolve syntax errors.</p>
                </div>
                <?php endif; ?>
                
                <!-- Sync League Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Sync New League</h5>
                    </div>
                    <div class="card-body">
                        <form id="syncForm" class="row g-3">
                            <div class="col-md-8">
                                <input type="text" name="league_id" class="form-control" 
                                       placeholder="Enter Sleeper League ID (e.g., 123456789012345678)" required>
                                <div class="form-text">
                                    You can find the League ID in your Sleeper league URL: 
                                    sleeper.app/leagues/<strong>LEAGUE_ID</strong>/team
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100" id="syncBtn">
                                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                    Sync League Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Leagues Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Synced Leagues</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover" id="leaguesTable">
                            <thead>
                                <tr>
                                    <th>League ID</th>
                                    <th>Name</th>
                                    <th>Season</th>
                                    <th>Status</th>
                                    <th>Teams</th>
                                    <th>Sport</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leagues as $league): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($league['league_id']) ?></code></td>
                                    <td><?= htmlspecialchars($league['name']) ?></td>
                                    <td><?= htmlspecialchars($league['season']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $league['status'] === 'complete' ? 'success' : ($league['status'] === 'in_season' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($league['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $league['total_rosters'] ?></td>
                                    <td><?= strtoupper($league['sport']) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($league['updated_at'])) ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="sleeper-league.php?id=<?= $league['league_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">View Details</a>
                                            <button class="btn btn-sm btn-outline-secondary sync-league-btn" 
                                                    data-league-id="<?= $league['league_id'] ?>">Re-sync</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Player Database</h5>
                                <p class="card-text">View and search all NFL players from Sleeper's database.</p>
                                <a href="sleeper-players.php" class="btn btn-outline-primary">View Players</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Player Stats</h5>
                                <p class="card-text">Browse weekly and seasonal player statistics.</p>
                                <a href="sleeper-stats.php" class="btn btn-outline-primary">View Stats</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#leaguesTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        title: 'Sleeper_Leagues_' + new Date().toISOString().slice(0,10)
                    },
                    {
                        extend: 'excel',
                        title: 'Sleeper_Leagues_' + new Date().toISOString().slice(0,10)
                    }
                ],
                order: [[6, 'desc']], // Order by last updated
                pageLength: 25
            });

            // Handle sync form submission
            $('#syncForm').on('submit', function(e) {
                e.preventDefault();
                
                const leagueId = $('input[name="league_id"]').val();
                const $btn = $('#syncBtn');
                const $spinner = $btn.find('.spinner-border');
                
                // Show loading state
                $btn.prop('disabled', true);
                $spinner.removeClass('d-none');
                
                $.ajax({
                    url: 'sleeper-api.php',
                    method: 'POST',
                    data: {
                        action: 'sync',
                        league_id: leagueId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Success: ' + response.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Sync failed: ' + error);
                        console.error(xhr.responseText);
                    },
                    complete: function() {
                        // Hide loading state
                        $btn.prop('disabled', false);
                        $spinner.addClass('d-none');
                    }
                });
            });

            // Handle re-sync buttons
            $('.sync-league-btn').on('click', function() {
                const leagueId = $(this).data('league-id');
                const $btn = $(this);
                
                if (!confirm('Re-sync league data? This may take a few minutes.')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Syncing...');
                
                $.ajax({
                    url: 'sleeper-api.php',
                    method: 'POST',
                    data: {
                        action: 'sync',
                        league_id: leagueId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Success: ' + response.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Re-sync failed: ' + error);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Re-sync');
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php require_once 'admin-footer.php'; ?>
