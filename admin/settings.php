<?php
/**
 * Settings Page
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

$message = '';
$error = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && verifyPassword($current_password, $admin['password_hash'])) {
                $new_hash = hashPassword($new_password);
                $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $_SESSION['admin_id']]);
                $message = 'Password changed successfully!';
            } else {
                $error = 'Current password is incorrect.';
            }
        } catch (PDOException $e) {
            $error = 'Error changing password: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
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
                        <i class="bi bi-people"></i> Users & Vouchers
                    </a>
                    <a href="online.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-circle-fill text-success"></i> Online Users
                    </a>
                    <a href="logs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-clock-history"></i> Usage Logs
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </div>
            </div>

            <div class="col-md-9">
                <h2 class="mb-4">Settings</h2>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required minlength="8">
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="8">
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="bi bi-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5>System Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <th>PHP Version</th>
                                <td><?php echo PHP_VERSION; ?></td>
                            </tr>
                            <tr>
                                <th>Database</th>
                                <td><?php echo DB_NAME; ?> @ <?php echo DB_HOST; ?></td>
                            </tr>
                            <tr>
                                <th>FreeRADIUS</th>
                                <td><?php echo RADIUS_HOST . ':' . RADIUS_PORT; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
