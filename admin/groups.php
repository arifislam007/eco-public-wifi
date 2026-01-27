<?php
/**
 * User Groups Management
 * For policy-based access control and bandwidth management
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $groupname = sanitizeInput($_POST['groupname'] ?? '');
        $description = $_POST['description'] ?? '';
        $max_sessions = intval($_POST['max_sessions'] ?? 1);
        $session_timeout = intval($_POST['session_timeout'] ?? 3600);
        $idle_timeout = intval($_POST['idle_timeout'] ?? 600);
        $daily_limit = !empty($_POST['daily_limit']) ? intval($_POST['daily_limit']) * 1024 * 1024 : null;
        $monthly_limit = !empty($_POST['monthly_limit']) ? intval($_POST['monthly_limit']) * 1024 * 1024 : null;
        $download_speed = !empty($_POST['download_speed']) ? intval($_POST['download_speed']) : null;
        $upload_speed = !empty($_POST['upload_speed']) ? intval($_POST['upload_speed']) : null;
        $burst_download = !empty($_POST['burst_download']) ? intval($_POST['burst_download']) : null;
        $burst_upload = !empty($_POST['burst_upload']) ? intval($_POST['burst_upload']) : null;
        $fup_enabled = isset($_POST['fup_enabled']) ? 1 : 0;
        $fup_threshold = !empty($_POST['fup_threshold']) ? intval($_POST['fup_threshold']) * 1024 * 1024 : null;
        $fup_speed = !empty($_POST['fup_speed']) ? intval($_POST['fup_speed']) : null;
        
        if (empty($groupname)) {
            $error = 'Group name is required.';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_groups (
                            groupname, description, max_sessions, session_timeout, idle_timeout,
                            daily_limit, monthly_limit, download_speed, upload_speed,
                            burst_download, burst_upload, fup_enabled, fup_threshold, fup_speed
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $groupname, $description, $max_sessions, $session_timeout, $idle_timeout,
                        $daily_limit, $monthly_limit, $download_speed, $upload_speed,
                        $burst_download, $burst_upload, $fup_enabled, $fup_threshold, $fup_speed
                    ]);
                    $message = "Group '$groupname' created successfully!";
                } else {
                    $group_id = intval($_POST['group_id'] ?? 0);
                    $stmt = $pdo->prepare("
                        UPDATE user_groups SET
                            description = ?, max_sessions = ?, session_timeout = ?, idle_timeout = ?,
                            daily_limit = ?, monthly_limit = ?, download_speed = ?, upload_speed = ?,
                            burst_download = ?, burst_upload = ?, fup_enabled = ?, fup_threshold = ?, fup_speed = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $description, $max_sessions, $session_timeout, $idle_timeout,
                        $daily_limit, $monthly_limit, $download_speed, $upload_speed,
                        $burst_download, $burst_upload, $fup_enabled, $fup_threshold, $fup_speed,
                        $group_id
                    ]);
                    $message = "Group updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error saving group: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $group_id = intval($_POST['group_id'] ?? 0);
        if ($group_id > 0) {
            try {
                $pdo->prepare("DELETE FROM user_groups WHERE id = ?")->execute([$group_id]);
                $message = "Group deleted successfully!";
            } catch (PDOException $e) {
                $error = "Error deleting group: " . $e->getMessage();
            }
        }
    }
}

// Get all groups
$groups = $pdo->query("
    SELECT g.*, COUNT(ug.username) as user_count
    FROM user_groups g
    LEFT JOIN radusergroup ug ON g.groupname = ug.groupname
    GROUP BY g.id
    ORDER BY g.groupname
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Groups - Admin Panel</title>
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
                    <a href="groups.php" class="list-group-item list-group-item-action active">
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
                    <a href="logs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-clock-history"></i> Usage Logs
                    </a>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </div>
            </div>

            <div class="col-md-9">
                <h2 class="mb-4">User Groups & Policies</h2>

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

                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Create New Group</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Group Name</label>
                                    <input type="text" class="form-control" name="groupname" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="description">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Max Sessions</label>
                                    <input type="number" class="form-control" name="max_sessions" value="1" min="1">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Session Timeout (sec)</label>
                                    <input type="number" class="form-control" name="session_timeout" value="3600">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Idle Timeout (sec)</label>
                                    <input type="number" class="form-control" name="idle_timeout" value="600">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Daily Limit (MB)</label>
                                    <input type="number" class="form-control" name="daily_limit" min="0">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Monthly Limit (MB)</label>
                                    <input type="number" class="form-control" name="monthly_limit" min="0">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Download Speed (kbps)</label>
                                    <input type="number" class="form-control" name="download_speed" min="0">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Upload Speed (kbps)</label>
                                    <input type="number" class="form-control" name="upload_speed" min="0">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Burst Download (kbps)</label>
                                    <input type="number" class="form-control" name="burst_download" min="0">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Burst Upload (kbps)</label>
                                    <input type="number" class="form-control" name="burst_upload" min="0">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">FUP Enabled</label>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" name="fup_enabled" id="fup_enabled">
                                        <label class="form-check-label" for="fup_enabled">Enable FUP</label>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">FUP Threshold (MB)</label>
                                    <input type="number" class="form-control" name="fup_threshold" min="0">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">FUP Speed (kbps)</label>
                                    <input type="number" class="form-control" name="fup_speed" min="0">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Create Group
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Existing Groups</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Group Name</th>
                                        <th>Description</th>
                                        <th>Users</th>
                                        <th>Speed Limits</th>
                                        <th>Usage Limits</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groups as $group): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($group['groupname']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($group['description'] ?? ''); ?></td>
                                            <td><?php echo $group['user_count']; ?></td>
                                            <td>
                                                <?php if ($group['download_speed']): ?>
                                                    ↓ <?php echo $group['download_speed']; ?> kbps<br>
                                                    ↑ <?php echo $group['upload_speed']; ?> kbps
                                                <?php else: ?>
                                                    No limit
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                Daily: <?php echo $group['daily_limit'] ? number_format($group['daily_limit'] / 1024 / 1024, 0) . ' MB' : 'Unlimited'; ?><br>
                                                Monthly: <?php echo $group['monthly_limit'] ? number_format($group['monthly_limit'] / 1024 / 1024, 0) . ' MB' : 'Unlimited'; ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this group?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
