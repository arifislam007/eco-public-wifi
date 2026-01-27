<?php
/**
 * Admin Dashboard
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

// Get current user role and reseller info
$is_admin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin');
$reseller_id = $_SESSION['reseller_id'] ?? null;

// Get statistics
try {
    $pdo = getDBConnection();
    
    // Build WHERE clause for reseller filtering
    $where_clause = '';
    $params = [];
    if (!$is_admin && $reseller_id) {
        $where_clause = ' WHERE reseller_id = ? ';
        $params = [$reseller_id];
    }
    
    // Total users (created by this reseller or all for admin)
    if ($is_admin) {
        $total_users = $pdo->query("SELECT COUNT(DISTINCT username) FROM radcheck")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT username) FROM radcheck WHERE reseller_id = ?");
        $stmt->execute([$reseller_id]);
        $total_users = $stmt->fetchColumn();
    }
    
    // Active sessions
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
    
    // Get reseller info if logged in as reseller
    $reseller_info = null;
    if (!$is_admin && $reseller_id) {
        $stmt = $pdo->prepare("SELECT * FROM resellers WHERE id = ?");
        $stmt->execute([$reseller_id]);
        $reseller_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
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
                <i class="bi bi-wifi"></i> Wi-Fi Portal <?php echo $is_admin ? 'Admin' : 'Reseller'; ?>
            </span>
            <div>
                <span class="text-white me-3">
                    Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                    <?php if (!$is_admin && $reseller_info): ?>
                        (<?php echo htmlspecialchars($reseller_info['reseller_code']); ?>)
                    <?php endif; ?>
                </span>
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
                        <i class="bi bi-people"></i> My Users
                    </a>
                    <a href="vouchers.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-ticket-perforated"></i> My Vouchers
                    </a>
                    <?php if ($is_admin): ?>
                    <a href="packages.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-box-seam"></i> WiFi Packages
                    </a>
                    <a href="resellers.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-shop"></i> Resellers
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
                    <?php endif; ?>
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
                        <a href="users.php?action=create" class="btn btn-primary me-2 mb-2">
                            <i class="bi bi-person-plus"></i> Create New User
                        </a>
                        <a href="vouchers.php" class="btn btn-success me-2 mb-2">
                            <i class="bi bi-ticket-perforated"></i> Manage Vouchers
                        </a>
                        <a href="packages.php" class="btn btn-info me-2 mb-2">
                            <i class="bi bi-box-seam"></i> WiFi Packages
                        </a>
                        <a href="groups.php" class="btn btn-warning me-2 mb-2">
                            <i class="bi bi-people-fill"></i> User Groups
                        </a>
                        <a href="nas.php" class="btn btn-secondary me-2 mb-2">
                            <i class="bi bi-router"></i> NAS / Routers
                        </a>
                        <a href="payment_gateways.php" class="btn btn-dark me-2 mb-2">
                            <i class="bi bi-credit-card"></i> Payment Gateways
                        </a>
                        <a href="sms_gateways.php" class="btn btn-info me-2 mb-2">
                            <i class="bi bi-chat-dots"></i> SMS Gateways
                        </a>
                        <a href="online.php" class="btn btn-info mb-2">
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
