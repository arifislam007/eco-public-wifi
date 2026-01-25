<?php
/**
 * Admin Dashboard
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

// Get statistics
try {
    $pdo = getDBConnection();
    
    // Total users
    $total_users = $pdo->query("SELECT COUNT(DISTINCT username) FROM radcheck")->fetchColumn();
    
    // Active sessions (users logged in today)
    $active_sessions = $pdo->query("
        SELECT COUNT(DISTINCT username) 
        FROM radacct 
        WHERE acctstarttime >= CURDATE() 
        AND (acctstoptime IS NULL OR acctstoptime >= NOW())
    ")->fetchColumn();
    
    // Total data usage today (in MB)
    $data_usage = $pdo->query("
        SELECT COALESCE(SUM(acctinputoctets + acctoutputoctets) / 1024 / 1024, 0) 
        FROM radacct 
        WHERE acctstarttime >= CURDATE()
    ")->fetchColumn();
    
    // Recent login attempts
    $recent_attempts = $pdo->query("
        SELECT COUNT(*) 
        FROM login_attempts 
        WHERE attempt_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $total_users = 0;
    $active_sessions = 0;
    $data_usage = 0;
    $recent_attempts = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Panel</title>
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
                <span class="text-white me-3">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="list-group">
                    <a href="dashboard.php" class="list-group-item list-group-item-action active">
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
                    <a href="online.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-circle-fill text-success"></i> Online Users
                    </a>
                    <a href="logs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-clock-history"></i> Usage Logs
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </div>
            </div>

            <div class="col-md-9">
                <h2 class="mb-4">Dashboard</h2>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <h2><?php echo number_format($total_users); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Active Sessions</h5>
                                <h2><?php echo number_format($active_sessions); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Data Usage (Today)</h5>
                                <h2><?php echo number_format($data_usage, 2); ?> MB</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Login Attempts (1h)</h5>
                                <h2><?php echo number_format($recent_attempts); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <a href="users.php?action=create" class="btn btn-primary me-2">
                            <i class="bi bi-person-plus"></i> Create New User
                        </a>
                        <a href="vouchers.php" class="btn btn-success me-2">
                            <i class="bi bi-ticket-perforated"></i> Manage Vouchers
                        </a>
                        <a href="groups.php" class="btn btn-warning me-2">
                            <i class="bi bi-people-fill"></i> User Groups
                        </a>
                        <a href="online.php" class="btn btn-info">
                            <i class="bi bi-eye"></i> View Online Users
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
