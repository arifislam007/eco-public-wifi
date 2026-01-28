<?php
/**
 * Voucher Management Page
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $voucher_code = sanitizeInput($_POST['voucher_code'] ?? '');
        $voucher_type = $_POST['voucher_type'] ?? 'time';
        $time_limit = !empty($_POST['time_limit']) ? intval($_POST['time_limit']) : null;
        $data_limit = !empty($_POST['data_limit']) ? intval($_POST['data_limit']) * 1024 * 1024 : null; // Convert MB to bytes
        $daily_limit = !empty($_POST['daily_limit']) ? intval($_POST['daily_limit']) * 1024 * 1024 : null;
        $monthly_limit = !empty($_POST['monthly_limit']) ? intval($_POST['monthly_limit']) * 1024 * 1024 : null;
        $expiry_date = $_POST['expiry_date'] ?? null;
        $max_sessions = intval($_POST['max_sessions'] ?? 1);
        $notes = $_POST['notes'] ?? '';
        
        if (empty($voucher_code)) {
            $error = 'Voucher code is required.';
        } else {
            try {
                // Generate username and password
                $username = 'voucher_' . $voucher_code;
                $password = bin2hex(random_bytes(8));
                
                $pdo->beginTransaction();
                
                // Create voucher record
                $stmt = $pdo->prepare("
                    INSERT INTO vouchers (
                        voucher_code, username, password, voucher_type,
                        time_limit, data_limit, daily_limit, monthly_limit,
                        expiry_date, max_sessions, status, created_by, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
                ");
                $stmt->execute([
                    $voucher_code, $username, $password, $voucher_type,
                    $time_limit, $data_limit, $daily_limit, $monthly_limit,
                    $expiry_date, $max_sessions, $_SESSION['admin_id'], $notes
                ]);
                
                // Create user in radcheck
                $user_stmt = $pdo->prepare("
                    INSERT INTO radcheck (username, attribute, op, value) 
                    VALUES (?, 'Cleartext-Password', ':=', ?)
                ");
                $user_stmt->execute([$username, $password]);
                
                // Add to voucher group
                $group_stmt = $pdo->prepare("
                    INSERT INTO radusergroup (username, groupname, priority) 
                    VALUES (?, 'voucher', 1)
                ");
                $group_stmt->execute([$username]);
                
                $pdo->commit();
                $message = "Voucher '$voucher_code' created successfully! Password: $password";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error creating voucher: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $voucher_id = intval($_POST['voucher_id'] ?? 0);
        if ($voucher_id > 0) {
            try {
                $pdo->beginTransaction();
                
                // Get voucher info
                $voucher_stmt = $pdo->prepare("SELECT username FROM vouchers WHERE id = ?");
                $voucher_stmt->execute([$voucher_id]);
                $voucher = $voucher_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($voucher) {
                    // Delete user
                    $pdo->prepare("DELETE FROM radcheck WHERE username = ?")->execute([$voucher['username']]);
                    $pdo->prepare("DELETE FROM radreply WHERE username = ?")->execute([$voucher['username']]);
                    $pdo->prepare("DELETE FROM radusergroup WHERE username = ?")->execute([$voucher['username']]);
                }
                
                // Delete voucher
                $pdo->prepare("DELETE FROM vouchers WHERE id = ?")->execute([$voucher_id]);
                
                $pdo->commit();
                $message = "Voucher deleted successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error deleting voucher: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_status') {
        $voucher_id = intval($_POST['voucher_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        if ($voucher_id > 0) {
            try {
                $pdo->prepare("UPDATE vouchers SET status = ? WHERE id = ?")->execute([$status, $voucher_id]);
                $message = "Voucher status updated!";
            } catch (PDOException $e) {
                $error = "Error updating voucher: " . $e->getMessage();
            }
        }
    }
}

// Get all vouchers
$vouchers = $pdo->query("
    SELECT v.*, 
           COALESCE(SUM(vu.bytes_used), 0) as total_bytes_used,
           COALESCE(SUM(vu.time_used), 0) as total_time_used,
           COUNT(vu.id) as usage_count
    FROM vouchers v
    LEFT JOIN voucher_usage vu ON v.id = vu.voucher_id
    GROUP BY v.id
    ORDER BY v.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Management - Admin Panel</title>
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
                    <a href="vouchers.php" class="list-group-item list-group-item-action active">
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
                </div>
            </div>

            <div class="col-md-9">
                <h2 class="mb-4">Voucher Management</h2>

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
                        <h5>Create New Voucher</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Voucher Code</label>
                                    <input type="text" class="form-control" name="voucher_code" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Voucher Type</label>
                                    <select class="form-select" name="voucher_type" required>
                                        <option value="time">Time Limited</option>
                                        <option value="data">Data Limited</option>
                                        <option value="unlimited">Unlimited</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Time Limit (seconds)</label>
                                    <input type="number" class="form-control" name="time_limit" min="0" placeholder="3600 = 1 hour">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Data Limit (MB)</label>
                                    <input type="number" class="form-control" name="data_limit" min="0" placeholder="1024 = 1 GB">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Max Concurrent Sessions</label>
                                    <input type="number" class="form-control" name="max_sessions" value="1" min="1">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Daily Limit (MB)</label>
                                    <input type="number" class="form-control" name="daily_limit" min="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Monthly Limit (MB)</label>
                                    <input type="number" class="form-control" name="monthly_limit" min="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" class="form-control" name="expiry_date">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Create Voucher
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>All Vouchers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Type</th>
                                        <th>Limits</th>
                                        <th>Status</th>
                                        <th>Usage</th>
                                        <th>Expiry</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vouchers as $voucher): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($voucher['voucher_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($voucher['voucher_type']); ?></td>
                                            <td>
                                                <?php if ($voucher['time_limit']): ?>
                                                    Time: <?php echo gmdate('H:i:s', $voucher['time_limit']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($voucher['data_limit']): ?>
                                                    Data: <?php echo number_format($voucher['data_limit'] / 1024 / 1024, 0); ?> MB<br>
                                                <?php endif; ?>
                                                Used: <?php echo number_format($voucher['total_bytes_used'] / 1024 / 1024, 2); ?> MB
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $voucher['status'] === 'active' ? 'success' : ($voucher['status'] === 'used' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo htmlspecialchars($voucher['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $voucher['usage_count']; ?> times</td>
                                            <td><?php echo $voucher['expiry_date'] ? date('Y-m-d', strtotime($voucher['expiry_date'])) : 'No expiry'; ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this voucher?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="voucher_id" value="<?php echo $voucher['id']; ?>">
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
