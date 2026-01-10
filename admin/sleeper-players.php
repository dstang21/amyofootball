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
$search = $_GET['search'] ?? '';
$position = $_GET['position'] ?? '';
$team = $_GET['team'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$players = $controller->getPlayers($search, $position, $team, $limit, $offset);
$teams = $controller->getTeams();
$positions = $controller->getPositions();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sleeper Players - AmyoFootball Admin</title>
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
                    <h1>Sleeper Players Database</h1>
                    <a href="sleeper-leagues.php" class="btn btn-outline-secondary">← Back to Leagues</a>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Player Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Players</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="First name, last name, or full name">
                            </div>
                            <div class="col-md-2">
                                <label for="position" class="form-label">Position</label>
                                <select class="form-select" id="position" name="position">
                                    <option value="">All Positions</option>
                                    <?php foreach ($positions as $pos): ?>
                                        <option value="<?= $pos ?>" <?= $position === $pos ? 'selected' : '' ?>>
                                            <?= $pos ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="team" class="form-label">Team</label>
                                <select class="form-select" id="team" name="team">
                                    <option value="">All Teams</option>
                                    <?php foreach ($teams as $code => $name): ?>
                                        <option value="<?= $code ?>" <?= $team === $code ? 'selected' : '' ?>>
                                            <?= $code ?> - <?= $name ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Filter</button>
                                <a href="sleeper-players.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Players Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Players <?= $search || $position || $team ? '(Filtered)' : '' ?></h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover" id="playersTable">
                            <thead>
                                <tr>
                                    <th>Player ID</th>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Team</th>
                                    <th>Jersey #</th>
                                    <th>Age</th>
                                    <th>Experience</th>
                                    <th>College</th>
                                    <th>Status</th>
                                    <th>Injury</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($players as $player): ?>
                                <tr>
                                    <td><code><?= $player['player_id'] ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></strong>
                                        <?php if ($player['height'] || $player['weight']): ?>
                                            <br><small class="text-muted">
                                                <?= $player['height'] ? $player['height'] : '' ?>
                                                <?= $player['height'] && $player['weight'] ? ', ' : '' ?>
                                                <?= $player['weight'] ? $player['weight'] . ' lbs' : '' ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($player['position']): ?>
                                            <span class="badge bg-secondary"><?= $player['position'] ?></span>
                                        <?php endif; ?>
                                        <?php 
                                        $fantasyPos = json_decode($player['fantasy_positions'], true);
                                        if ($fantasyPos && count($fantasyPos) > 1): ?>
                                            <br><small class="text-muted"><?= implode(', ', $fantasyPos) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($player['team']): ?>
                                            <span class="badge bg-primary"><?= $player['team'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">FA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $player['number'] ?: '-' ?></td>
                                    <td><?= $player['age'] ?: '-' ?></td>
                                    <td><?= $player['years_exp'] !== null ? $player['years_exp'] . ' yrs' : '-' ?></td>
                                    <td><?= htmlspecialchars($player['college'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($player['status']): ?>
                                            <span class="badge bg-<?= $player['status'] === 'Active' ? 'success' : 'secondary' ?>">
                                                <?= $player['status'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($player['injury_status']): ?>
                                            <span class="badge bg-warning text-dark"><?= $player['injury_status'] ?></span>
                                            <?php if ($player['practice_participation']): ?>
                                                <br><small class="text-muted"><?= $player['practice_participation'] ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Healthy</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if (count($players) === $limit): ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                Showing page <?= $page ?> (<?= count($players) ?> players)
                            </div>
                            <div>
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                       class="btn btn-outline-primary">← Previous</a>
                                <?php endif; ?>
                                
                                <?php if (count($players) === $limit): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                       class="btn btn-outline-primary">Next →</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Player Stats</h5>
                                <p class="card-text">View detailed weekly and seasonal player statistics.</p>
                                <a href="sleeper-stats.php" class="btn btn-outline-primary">View Stats</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">League Management</h5>
                                <p class="card-text">Manage Sleeper leagues and sync data.</p>
                                <a href="sleeper-leagues.php" class="btn btn-outline-primary">Manage Leagues</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Export Data</h5>
                                <p class="card-text">Export player data in various formats.</p>
                                <button class="btn btn-outline-success" onclick="$('#playersTable').DataTable().button('.buttons-csv').trigger();">
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
            $('#playersTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        title: 'Sleeper_Players_' + new Date().toISOString().slice(0,10)
                    },
                    {
                        extend: 'excel',
                        title: 'Sleeper_Players_' + new Date().toISOString().slice(0,10)
                    }
                ],
                pageLength: 50,
                order: [[1, 'asc']], // Order by name
                columnDefs: [
                    { targets: [0], orderable: false }, // Player ID not sortable
                    { targets: [9], orderable: false }  // Injury status not sortable
                ]
            });

            // Auto-submit form on select changes
            $('#position, #team').on('change', function() {
                $(this).closest('form').submit();
            });
        });
    </script>
</body>
</html>

<?php require_once 'admin-footer.php'; ?>
