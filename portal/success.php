<?php
/**
 * Success Page - User authenticated
 */

session_start();

// Check if authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access_control.php';
require_once __DIR__ . '/includes/bandwidth.php';
require_once __DIR__ . '/includes/i18n.php';

$username = $_SESSION['username'] ?? 'User';
$login_time = $_SESSION['login_time'] ?? time();
$session_duration = time() - $login_time;
$login_method = $_SESSION['login_method'] ?? 'username';

// Get usage information
$daily_usage = checkDailyLimit($username);
$monthly_usage = checkMonthlyLimit($username);
$bandwidth = getBandwidthLimits($username);
$fup_status = checkFUPStatus($username);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connected - <?php echo htmlspecialchars(PORTAL_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <div class="success-icon mb-3">
                                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </div>
                            <h2 class="text-success mb-2">Successfully Connected!</h2>
                            <p class="text-muted">You now have internet access</p>
                        </div>

                        <div class="alert alert-info">
                            <strong>Welcome, <?php echo htmlspecialchars($username); ?>!</strong><br>
                            <small>You are connected to <?php echo htmlspecialchars(PORTAL_NAME); ?></small>
                        </div>

                        <div class="mb-4">
                            <p class="text-muted small mb-2"><?php echo t('session_info'); ?>:</p>
                            <ul class="list-unstyled">
                                <li><strong><?php echo t('username'); ?>:</strong> <?php echo htmlspecialchars($username); ?></li>
                                <li><strong>IP Address:</strong> <?php echo htmlspecialchars($_SESSION['ip_address'] ?? 'N/A'); ?></li>
                                <li><strong><?php echo t('connected'); ?>:</strong> <?php echo date('Y-m-d H:i:s', $login_time); ?></li>
                                <li><strong>Login Method:</strong> <?php echo ucfirst($login_method); ?></li>
                            </ul>
                        </div>

                        <?php if (!empty($daily_usage['usage'])): ?>
                        <div class="alert alert-info mb-3">
                            <strong>Daily Usage:</strong> 
                            <?php echo number_format($daily_usage['usage']['used'] / 1024 / 1024, 2); ?> MB / 
                            <?php echo number_format($daily_usage['usage']['limit'] / 1024 / 1024, 0); ?> MB
                            (<?php echo $daily_usage['usage']['percentage']; ?>%)
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($monthly_usage['usage'])): ?>
                        <div class="alert alert-info mb-3">
                            <strong>Monthly Usage:</strong> 
                            <?php echo number_format($monthly_usage['usage']['used'] / 1024 / 1024, 2); ?> MB / 
                            <?php echo number_format($monthly_usage['usage']['limit'] / 1024 / 1024, 0); ?> MB
                            (<?php echo $monthly_usage['usage']['percentage']; ?>%)
                        </div>
                        <?php endif; ?>

                        <?php if ($bandwidth['download'] > 0): ?>
                        <div class="alert alert-secondary mb-3">
                            <strong>Speed Limits:</strong>
                            Download: <?php echo $bandwidth['download']; ?> kbps | 
                            Upload: <?php echo $bandwidth['upload']; ?> kbps
                            <?php if ($fup_status['active']): ?>
                                <br><small class="text-warning">⚠ FUP Active: Speed reduced to <?php echo $fup_status['speed']; ?> kbps</small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <a href="http://www.google.com" class="btn btn-primary btn-lg" target="_blank">
                                <i class="bi bi-globe me-2"></i>
                                <?php echo t('go_to_internet'); ?>
                            </a>
                            <button onclick="window.location.reload()" class="btn btn-outline-secondary">
                                <?php echo t('refresh_status'); ?>
                            </button>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <small class="text-muted">
                                If you experience any issues, please contact support at 
                                <a href="mailto:<?php echo htmlspecialchars(SUPPORT_EMAIL); ?>">
                                    <?php echo htmlspecialchars(SUPPORT_EMAIL); ?>
                                </a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .success-icon {
            color: #28a745;
        }
        .success-icon svg {
            width: 80px;
            height: 80px;
        }
    </style>
</body>
</html>
