<?php
require_once '../config.php';
require_once 'admin-header.php';
require_once 'SleeperController.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$draftId = $_GET['id'] ?? '';
if (!$draftId) {
    header('Location: sleeper-leagues.php');
    exit();
}

$controller = new SleeperController();
$picks = $controller->getDraftPicks($draftId);

if (empty($picks)) {
    echo '<div class="alert alert-warning">No draft picks found for this draft.</div>';
    exit();
}

// Get draft info from first pick
$draftInfo = null;
if (!empty($picks)) {
    $stmt = $controller->pdo->prepare("SELECT * FROM sleeper_drafts WHERE draft_id = ?");
    $stmt->execute([$draftId]);
    $draftInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draft Details - AmyoFootball Admin</title>
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
                <!-- Draft Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1>Draft Results</h1>
                        <?php if ($draftInfo): ?>
                        <p class="text-muted mb-0">
                            Draft ID: <code><?= $draftInfo['draft_id'] ?></code> • 
                            Type: <?= ucfirst($draftInfo['type']) ?> • 
                            Status: <span class="badge bg-<?= $draftInfo['status'] === 'complete' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($draftInfo['status']) ?>
                            </span>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($draftInfo && $draftInfo['league_id']): ?>
                            <a href="sleeper-league.php?id=<?= $draftInfo['league_id'] ?>" class="btn btn-outline-primary me-2">← Back to League</a>
                        <?php endif; ?>
                        <a href="sleeper-leagues.php" class="btn btn-outline-secondary">All Leagues</a>
                    </div>
                </div>

                <!-- Draft Picks Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Draft Picks (<?= count($picks) ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover" id="draftTable">
                            <thead>
                                <tr>
                                    <th>Pick #</th>
                                    <th>Round</th>
                                    <th>Pick in Round</th>
                                    <th>Player</th>
                                    <th>Position</th>
                                    <th>Team</th>
                                    <th>Drafted By</th>
                                    <th>Keeper</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($picks as $pick): ?>
                                <tr>
                                    <td>
                                        <strong><?= $pick['pick_no'] ?></strong>
                                    </td>
                                    <td><?= $pick['round'] ?></td>
                                    <td><?= $pick['draft_slot'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($pick['first_name'] . ' ' . $pick['last_name']) ?></strong>
                                        <br><small class="text-muted">ID: <?= $pick['player_id'] ?></small>
                                    </td>
                                    <td>
                                        <?php if ($pick['position']): ?>
                                            <span class="badge bg-secondary"><?= $pick['position'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pick['team']): ?>
                                            <span class="badge bg-primary"><?= $pick['team'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($pick['picked_by_name'] ?: 'Unknown') ?>
                                        <br><small class="text-muted">Roster <?= $pick['roster_id'] ?></small>
                                    </td>
                                    <td>
                                        <?php if ($pick['is_keeper']): ?>
                                            <span class="badge bg-warning text-dark">Keeper</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Draft Analysis -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Position Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $positionCounts = [];
                                foreach ($picks as $pick) {
                                    $pos = $pick['position'] ?: 'Unknown';
                                    $positionCounts[$pos] = ($positionCounts[$pos] ?? 0) + 1;
                                }
                                arsort($positionCounts);
                                ?>
                                <div class="row">
                                    <?php foreach ($positionCounts as $position => $count): ?>
                                    <div class="col-6 mb-2">
                                        <strong><?= $position ?>:</strong> <?= $count ?> picks
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar" style="width: <?= ($count / count($picks)) * 100 ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Round Summary</h6>
                            </div>
                            <div class="card-body">
                                <?php
                                $roundCounts = [];
                                foreach ($picks as $pick) {
                                    $round = $pick['round'];
                                    $roundCounts[$round] = ($roundCounts[$round] ?? 0) + 1;
                                }
                                ksort($roundCounts);
                                ?>
                                <div class="row">
                                    <?php foreach ($roundCounts as $round => $count): ?>
                                    <div class="col-6 mb-2">
                                        <strong>Round <?= $round ?>:</strong> <?= $count ?> picks
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if ($draftInfo && $draftInfo['start_time']): ?>
                                <hr>
                                <small class="text-muted">
                                    <strong>Draft Started:</strong> <?= date('M j, Y H:i', $draftInfo['start_time'] / 1000) ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6>Export Draft Results</h6>
                                <button class="btn btn-outline-success me-2" onclick="$('#draftTable').DataTable().button('.buttons-csv').trigger();">
                                    Download CSV
                                </button>
                                <button class="btn btn-outline-primary" onclick="$('#draftTable').DataTable().button('.buttons-excel').trigger();">
                                    Download Excel
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
            $('#draftTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        title: 'Draft_Results_<?= $draftId ?>_' + new Date().toISOString().slice(0,10)
                    },
                    {
                        extend: 'excel',
                        title: 'Draft_Results_<?= $draftId ?>_' + new Date().toISOString().slice(0,10)
                    }
                ],
                pageLength: 50,
                order: [[0, 'asc']], // Order by pick number
                columnDefs: [
                    { targets: [7], orderable: false } // Keeper column not sortable
                ]
            });
        });
    </script>
</body>
</html>

<?php require_once 'admin-footer.php'; ?>
