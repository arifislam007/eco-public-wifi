<?php
/**
 * User Management Page
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
    
    if ($action === 'create') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $expiry_date = $_POST['expiry_date'] ?? '';
        $session_timeout = intval($_POST['session_timeout'] ?? 3600);
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Insert into radcheck
                $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
                $stmt->execute([$username, $password]);
                
                // Add session timeout
                if ($session_timeout > 0) {
                    $stmt = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Session-Timeout', ':=', ?)");
                    $stmt->execute([$username, $session_timeout]);
                }
                
                // Add expiry date if provided
                if (!empty($expiry_date)) {
                    $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)");
                    $stmt->execute([$username, $expiry_date]);
                }
                
                $pdo->commit();
                $message = "User '$username' created successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error creating user: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $username = sanitizeInput($_POST['username'] ?? '');
        if (!empty($username)) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM radcheck WHERE username = ?")->execute([$username]);
                $pdo->prepare("DELETE FROM radreply WHERE username = ?")->execute([$username]);
                $pdo->commit();
                $message = "User '$username' deleted successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error deleting user: " . $e->getMessage();
            }
        }
    }
}

// Get all users
$users = $pdo->query("
    SELECT DISTINCT username,
           (SELECT value FROM radcheck WHERE username = u.username AND attribute = 'Expiration' LIMIT 1) as expiry,
           (SELECT value FROM radreply WHERE username = u.username AND attribute = 'Session-Timeout' LIMIT 1) as timeout
    FROM radcheck u
    ORDER BY username
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
                    <a href="users.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-people"></i> Users & Vouchers
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
                <h2 class="mb-4">User & Voucher Management</h2>

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
                        <h5>Create New User / Voucher</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Username / Voucher Code</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="text" class="form-control" name="password" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Session Timeout (seconds)</label>
                                    <input type="number" class="form-control" name="session_timeout" value="3600" min="0">
                                    <small class="text-muted">0 = no limit, 3600 = 1 hour</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Expiry Date (optional)</label>
                                    <input type="date" class="form-control" name="expiry_date">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Create User
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Existing Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Expiry Date</th>
                                        <th>Session Timeout</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo $user['expiry'] ? htmlspecialchars($user['expiry']) : 'No expiry'; ?></td>
                                            <td><?php echo $user['timeout'] ? number_format($user['timeout'] / 3600, 1) . ' hours' : 'No limit'; ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i> Delete
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
