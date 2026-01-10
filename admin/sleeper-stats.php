<?php
require_once '../config.php';
require_once 'admin-header.php';
require_once 'SleeperController.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$controller = new SleeperController();

// Get filter parameters
$playerId = $_GET['player_id'] ?? '';
$season = $_GET['season'] ?? date('Y');
$week = $_GET['week'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$stats = $controller->getPlayerStats($playerId, $season, $week, $limit, $offset);

// Get available seasons and weeks for filters
$availableSeasons = range(date('Y'), 2020);
$availableWeeks = range(1, 18);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Stats - AmyoFootball Admin</title>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Player Statistics</h1>
                    <div>
                        <a href="sleeper-players.php" class="btn btn-outline-secondary me-2">Players</a>
                        <a href="sleeper-leagues.php" class="btn btn-outline-secondary">← Leagues</a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Stats Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="player_id" class="form-label">Player ID</label>
                                <input type="text" class="form-control" id="player_id" name="player_id" 
                                       value="<?= htmlspecialchars($playerId) ?>" 
                                       placeholder="e.g., 4036 (for specific player)">
                                <div class="form-text">Leave empty to show all players</div>
                            </div>
                            <div class="col-md-2">
                                <label for="season" class="form-label">Season</label>
                                <select class="form-select" id="season" name="season">
                                    <?php foreach ($availableSeasons as $yr): ?>
                                        <option value="<?= $yr ?>" <?= $season == $yr ? 'selected' : '' ?>>
                                            <?= $yr ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="week" class="form-label">Week</label>
                                <select class="form-select" id="week" name="week">
                                    <option value="">All Weeks</option>
                                    <?php foreach ($availableWeeks as $wk): ?>
                                        <option value="<?= $wk ?>" <?= $week == $wk ? 'selected' : '' ?>>
                                            Week <?= $wk ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filter</button>
                                <a href="sleeper-stats.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Stats Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Player Statistics <?= $playerId || $week ? '(Filtered)' : '' ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover" id="statsTable">
                            <thead>
                                <tr>
                                    <th>Player</th>
                                    <th>Position</th>
                                    <th>Team</th>
                                    <th>Season</th>
                                    <th>Week</th>
                                    <th>Fantasy Points</th>
                                    <th>Key Stats</th>
                                    <th>Raw Stats</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats as $stat): ?>
                                <?php 
                                $statsData = json_decode($stat['stats'], true) ?: [];
                                $fantasyPoints = $statsData['pts_ppr'] ?? $statsData['pts_std'] ?? $statsData['pts_half_ppr'] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($stat['first_name'] . ' ' . $stat['last_name']) ?></strong>
                                        <br><small class="text-muted">ID: <?= $stat['player_id'] ?></small>
                                    </td>
                                    <td>
                                        <?php if ($stat['position']): ?>
                                            <span class="badge bg-secondary"><?= $stat['position'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($stat['team']): ?>
                                            <span class="badge bg-primary"><?= $stat['team'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $stat['season'] ?></td>
                                    <td><?= $stat['week'] ?: 'Season' ?></td>
                                    <td>
                                        <strong class="text-primary"><?= number_format($fantasyPoints, 1) ?></strong>
                                    </td>
                                    <td>
                                        <small>
                                            <?php
                                            $keyStats = [];
                                            
                                            // QB stats
                                            if (isset($statsData['pass_yds'])) {
                                                $keyStats[] = $statsData['pass_yds'] . ' pass yds';
                                            }
                                            if (isset($statsData['pass_td'])) {
                                                $keyStats[] = $statsData['pass_td'] . ' pass TD';
                                            }
                                            
                                            // RB/WR stats
                                            if (isset($statsData['rush_yds'])) {
                                                $keyStats[] = $statsData['rush_yds'] . ' rush yds';
                                            }
                                            if (isset($statsData['rush_td'])) {
                                                $keyStats[] = $statsData['rush_td'] . ' rush TD';
                                            }
                                            if (isset($statsData['rec_yds'])) {
                                                $keyStats[] = $statsData['rec_yds'] . ' rec yds';
                                            }
                                            if (isset($statsData['rec'])) {
                                                $keyStats[] = $statsData['rec'] . ' rec';
                                            }
                                            if (isset($statsData['rec_td'])) {
                                                $keyStats[] = $statsData['rec_td'] . ' rec TD';
                                            }
                                            
                                            echo implode('<br>', array_slice($keyStats, 0, 3));
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#stats-<?= $stat['id'] ?>" 
                                                aria-expanded="false">
                                            View All
                                        </button>
                                        <div class="collapse mt-2" id="stats-<?= $stat['id'] ?>">
                                            <div class="card card-body">
                                                <pre class="small mb-0"><?= json_encode($statsData, JSON_PRETTY_PRINT) ?></pre>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if (count($stats) === $limit): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                Showing page <?= $page ?> (<?= count($stats) ?> stat entries)
                            </div>
                            <div>
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                       class="btn btn-outline-primary">← Previous</a>
                                <?php endif; ?>
                                
                                <?php if (count($stats) === $limit): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                       class="btn btn-outline-primary">Next →</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats Help -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h6>Common Stat Abbreviations</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Passing:</strong><br>
                                        <small>
                                            pass_yds = Passing Yards<br>
                                            pass_td = Passing Touchdowns<br>
                                            pass_int = Interceptions<br>
                                            pass_att = Pass Attempts<br>
                                            pass_cmp = Pass Completions
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Rushing:</strong><br>
                                        <small>
                                            rush_yds = Rushing Yards<br>
                                            rush_td = Rushing Touchdowns<br>
                                            rush_att = Rush Attempts<br>
                                        </small>
                                        <br>
                                        <strong>Receiving:</strong><br>
                                        <small>
                                            rec = Receptions<br>
                                            rec_yds = Receiving Yards<br>
                                            rec_td = Receiving Touchdowns
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="card-title">Export Stats</h6>
                                <p class="card-text">Download filtered statistics data.</p>
                                <button class="btn btn-outline-success" onclick="$('#statsTable').DataTable().button('.buttons-csv').trigger();">
                                    Download CSV
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#statsTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        title: 'Sleeper_Stats_' + new Date().toISOString().slice(0,10)
                    },
                    {
                        extend: 'excel',
                        title: 'Sleeper_Stats_' + new Date().toISOString().slice(0,10)
                    }
                ],
                pageLength: 50,
                order: [[5, 'desc']], // Order by fantasy points desc
                columnDefs: [
                    { targets: [7], orderable: false }, // Raw stats not sortable
                    { 
                        targets: [5], 
                        type: 'num',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                return data;
                            }
                            return parseFloat(data) || 0;
                        }
                    }
                ]
            });

            // Auto-submit form on select changes
            $('#season, #week').on('change', function() {
                $(this).closest('form').submit();
            });
        });
    </script>
</body>
</html>

<?php require_once 'admin-footer.php'; ?>
