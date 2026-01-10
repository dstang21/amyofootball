<?php
require_once '../config.php';
require_once 'admin-header.php';
require_once 'SleeperController.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$leagueId = $_GET['id'] ?? '';
if (!$leagueId) {
    header('Location: sleeper-leagues.php');
    exit();
}

$controller = new SleeperController();
$league = $controller->getLeague($leagueId);
$users = $controller->getLeagueUsers($leagueId);
$rosters = $controller->getLeagueRosters($leagueId);
$matchups = $controller->getLeagueMatchups($leagueId);
$transactions = $controller->getLeagueTransactions($leagueId, 100);
$drafts = $controller->getLeagueDrafts($leagueId);

if (!$league) {
    echo '<div class="alert alert-danger">League not found.</div>';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($league['name']) ?> - Sleeper League Details</title>
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
                <!-- League Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1><?= htmlspecialchars($league['name']) ?></h1>
                        <p class="text-muted mb-0">
                            <?= $league['season'] ?> Season • 
                            <?= $league['total_rosters'] ?> Teams • 
                            Status: <span class="badge bg-<?= $league['status'] === 'complete' ? 'success' : ($league['status'] === 'in_season' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst($league['status']) ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <a href="sleeper-leagues.php" class="btn btn-outline-secondary">← Back to Leagues</a>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="leagueTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                            Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rosters-tab" data-bs-toggle="tab" data-bs-target="#rosters" type="button" role="tab">
                            Rosters (<?= count($rosters) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="matchups-tab" data-bs-toggle="tab" data-bs-target="#matchups" type="button" role="tab">
                            Matchups (<?= count($matchups) ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                            Transactions (<?= count($transactions) ?>)
                        </button>
                    </li>
                    <?php if (!empty($drafts)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="drafts-tab" data-bs-toggle="tab" data-bs-target="#drafts" type="button" role="tab">
                            Drafts (<?= count($drafts) ?>)
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="leagueTabContent">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>League Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row">
                                            <dt class="col-sm-4">League ID:</dt>
                                            <dd class="col-sm-8"><code><?= $league['league_id'] ?></code></dd>
                                            
                                            <dt class="col-sm-4">Season:</dt>
                                            <dd class="col-sm-8"><?= $league['season'] ?></dd>
                                            
                                            <dt class="col-sm-4">Sport:</dt>
                                            <dd class="col-sm-8"><?= strtoupper($league['sport']) ?></dd>
                                            
                                            <dt class="col-sm-4">Season Type:</dt>
                                            <dd class="col-sm-8"><?= ucfirst($league['season_type']) ?></dd>
                                            
                                            <dt class="col-sm-4">Total Rosters:</dt>
                                            <dd class="col-sm-8"><?= $league['total_rosters'] ?></dd>
                                            
                                            <?php if ($league['draft_id']): ?>
                                            <dt class="col-sm-4">Draft ID:</dt>
                                            <dd class="col-sm-8"><code><?= $league['draft_id'] ?></code></dd>
                                            <?php endif; ?>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>League Members</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Team Name</th>
                                                    <th>Role</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($user['display_name']) ?></strong><br>
                                                        <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                                                    </td>
                                                    <td><?= htmlspecialchars($user['team_name'] ?: 'N/A') ?></td>
                                                    <td>
                                                        <?php if ($user['is_owner']): ?>
                                                            <span class="badge bg-warning">Commissioner</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Member</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rosters Tab -->
                    <div class="tab-pane fade" id="rosters" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Team Rosters</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped" id="rostersTable">
                                    <thead>
                                        <tr>
                                            <th>Roster ID</th>
                                            <th>Team Name</th>
                                            <th>Owner</th>
                                            <th>Record</th>
                                            <th>Points For</th>
                                            <th>Points Against</th>
                                            <th>Moves</th>
                                            <th>Waiver</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rosters as $roster): ?>
                                        <tr>
                                            <td><?= $roster['roster_id'] ?></td>
                                            <td><?= htmlspecialchars($roster['team_name'] ?: 'Team ' . $roster['roster_id']) ?></td>
                                            <td><?= htmlspecialchars($roster['display_name']) ?></td>
                                            <td><?= $roster['wins'] ?>-<?= $roster['losses'] ?><?= $roster['ties'] > 0 ? '-' . $roster['ties'] : '' ?></td>
                                            <td><?= number_format($roster['fpts'], 2) ?></td>
                                            <td><?= number_format($roster['fpts_against'], 2) ?></td>
                                            <td><?= $roster['total_moves'] ?></td>
                                            <td>#<?= $roster['waiver_position'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Matchups Tab -->
                    <div class="tab-pane fade" id="matchups" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Weekly Matchups</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped" id="matchupsTable">
                                    <thead>
                                        <tr>
                                            <th>Week</th>
                                            <th>Matchup</th>
                                            <th>Team</th>
                                            <th>Points</th>
                                            <th>Custom Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($matchups as $matchup): ?>
                                        <tr>
                                            <td><?= $matchup['week'] ?></td>
                                            <td><?= $matchup['matchup_id'] ?></td>
                                            <td><?= htmlspecialchars($matchup['team_name'] ?: $matchup['display_name']) ?></td>
                                            <td><?= number_format($matchup['points'], 2) ?></td>
                                            <td><?= $matchup['custom_points'] ? number_format($matchup['custom_points'], 2) : '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Tab -->
                    <div class="tab-pane fade" id="transactions" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Transactions</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped" id="transactionsTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Creator</th>
                                            <th>Week</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?= date('M j, Y H:i', $transaction['created'] / 1000) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $transaction['type'] === 'trade' ? 'info' : ($transaction['type'] === 'free_agent' ? 'success' : 'secondary') ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $transaction['type'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $transaction['status'] === 'complete' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($transaction['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($transaction['creator_name'] ?: 'Unknown') ?></td>
                                            <td><?= $transaction['leg'] ?></td>
                                            <td>
                                                <?php
                                                $adds = json_decode($transaction['adds'], true) ?: [];
                                                $drops = json_decode($transaction['drops'], true) ?: [];
                                                if (!empty($adds) || !empty($drops)): ?>
                                                    <small>
                                                        <?php if (!empty($adds)): ?>
                                                            Added: <?= count($adds) ?> player(s)
                                                        <?php endif; ?>
                                                        <?php if (!empty($drops)): ?>
                                                            <?= !empty($adds) ? ' | ' : '' ?>Dropped: <?= count($drops) ?> player(s)
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Drafts Tab -->
                    <?php if (!empty($drafts)): ?>
                    <div class="tab-pane fade" id="drafts" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>League Drafts</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped" id="draftsTable">
                                    <thead>
                                        <tr>
                                            <th>Draft ID</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Start Time</th>
                                            <th>Season</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($drafts as $draft): ?>
                                        <tr>
                                            <td><code><?= $draft['draft_id'] ?></code></td>
                                            <td><?= ucfirst($draft['type']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $draft['status'] === 'complete' ? 'success' : 'secondary' ?>">
                                                    <?= ucfirst($draft['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= $draft['start_time'] ? date('M j, Y H:i', $draft['start_time'] / 1000) : 'TBD' ?>
                                            </td>
                                            <td><?= $draft['season'] ?></td>
                                            <td>
                                                <a href="sleeper-draft.php?id=<?= $draft['draft_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    View Draft
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize DataTables for each tab
            $('#rostersTable').DataTable({
                dom: 'Bfrtip',
                buttons: ['csv', 'excel'],
                order: [[4, 'desc']], // Order by points for
                pageLength: 25
            });

            $('#matchupsTable').DataTable({
                dom: 'Bfrtip',
                buttons: ['csv', 'excel'],
                order: [[0, 'desc'], [1, 'asc']], // Order by week desc, matchup asc
                pageLength: 25
            });

            $('#transactionsTable').DataTable({
                dom: 'Bfrtip',
                buttons: ['csv', 'excel'],
                order: [[0, 'desc']], // Order by date desc
                pageLength: 25
            });

            <?php if (!empty($drafts)): ?>
            $('#draftsTable').DataTable({
                dom: 'Bfrtip',
                buttons: ['csv', 'excel'],
                pageLength: 25
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php require_once 'admin-footer.php'; ?>
