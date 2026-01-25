<?php
/**
 * Online Users Page
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

$pdo = getDBConnection();

// Get currently online users
$online_users = $pdo->query("
    SELECT 
        username,
        nasipaddress,
        callingstationid as mac_address,
        framedipaddress as ip_address,
        acctstarttime as start_time,
        acctsessiontime as session_time,
        (acctinputoctets + acctoutputoctets) / 1024 / 1024 as data_used_mb
    FROM radacct
    WHERE acctstoptime IS NULL
    ORDER BY acctstarttime DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Users - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <meta http-equiv="refresh" content="30">
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
                        <i class="bi bi-people"></i> Users & Vouchers
                    </a>
                    <a href="online.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-circle-fill text-success"></i> Online Users
                    </a>
                    <a href="logs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-clock-history"></i> Usage Logs
                    </a>
                </div>
            </div>

            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Online Users</h2>
                    <span class="badge bg-success"><?php echo count($online_users); ?> Active</span>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($online_users)): ?>
                            <p class="text-muted text-center py-5">No users currently online</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>IP Address</th>
                                            <th>MAC Address</th>
                                            <th>NAS IP</th>
                                            <th>Start Time</th>
                                            <th>Session Time</th>
                                            <th>Data Used</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($online_users as $user): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($user['ip_address']); ?></td>
                                                <td><code><?php echo htmlspecialchars($user['mac_address']); ?></code></td>
                                                <td><?php echo htmlspecialchars($user['nasipaddress']); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($user['start_time'])); ?></td>
                                                <td><?php echo gmdate('H:i:s', $user['session_time']); ?></td>
                                                <td><?php echo number_format($user['data_used_mb'], 2); ?> MB</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <small class="text-muted">Page auto-refreshes every 30 seconds</small>
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
