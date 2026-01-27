<?php
/**
 * Usage Logs Page
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

$pdo = getDBConnection();

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$username_filter = $_GET['username'] ?? '';

// Build query
$where = ["acctstarttime >= ?", "acctstarttime <= ?"];
$params = [$date_from, $date_to . ' 23:59:59'];

if (!empty($username_filter)) {
    $where[] = "username = ?";
    $params[] = $username_filter;
}

$where_clause = implode(' AND ', $where);

// Get usage logs
$logs = $pdo->prepare("
    SELECT 
        username,
        nasipaddress,
        callingstationid as mac_address,
        framedipaddress as ip_address,
        acctstarttime as start_time,
        acctstoptime as stop_time,
        acctsessiontime as session_time,
        (acctinputoctets + acctoutputoctets) / 1024 / 1024 as data_used_mb,
        acctterminatecause
    FROM radacct
    WHERE $where_clause
    ORDER BY acctstarttime DESC
    LIMIT 1000
");
$logs->execute($params);
$usage_logs = $logs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usage Logs - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-wifi"></i> Wi-Fi Portal Admin
            </span>
            <div>
                <span class="text-white me-3"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="list-group">
                    <a href="dashboard.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a href="users.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-people"></i> Users
                    </a>
                    <a href="vouchers.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-ticket-perforated"></i> Vouchers
                    </a>
                    <a href="groups.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-people-fill"></i> User Groups
                    </a>
                    <a href="nas.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-router"></i> NAS / Routers
                    </a>
                    <a href="payment_gateways.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-credit-card"></i> Payment Gateways
                    </a>
                    <a href="sms_gateways.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-chat-dots"></i> SMS Gateways
                    </a>
                    <a href="online.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-circle-fill text-success"></i> Online Users
                    </a>
                    <a href="logs.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-clock-history"></i> Usage Logs
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </div>
            </div>

            <div class="col-md-9">
                <h2 class="mb-4">Usage Logs</h2>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Username (optional)</label>
                                <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($username_filter); ?>" placeholder="Filter by username">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($usage_logs)): ?>
                            <p class="text-muted text-center py-5">No logs found for the selected period</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>IP Address</th>
                                            <th>MAC Address</th>
                                            <th>Start Time</th>
                                            <th>Stop Time</th>
                                            <th>Duration</th>
                                            <th>Data Used</th>
                                            <th>Termination</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usage_logs as $log): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($log['username']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                                <td><code><?php echo htmlspecialchars($log['mac_address']); ?></code></td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['start_time'])); ?></td>
                                                <td><?php echo $log['stop_time'] ? date('Y-m-d H:i:s', strtotime($log['stop_time'])) : 'Active'; ?></td>
                                                <td><?php echo gmdate('H:i:s', $log['session_time']); ?></td>
                                                <td><?php echo number_format($log['data_used_mb'], 2); ?> MB</td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['acctterminatecause']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">Showing up to 1000 records</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
