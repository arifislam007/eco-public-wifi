<?php
/**
 * Reseller Management
 * Admin can create and manage resellers
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

// Check if user is admin
if ($_SESSION['admin_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $reseller_name = trim($_POST['reseller_name'] ?? '');
        $reseller_code = trim($_POST['reseller_code'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $commission_rate = floatval($_POST['commission_rate'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validation
        if (empty($reseller_name) || empty($reseller_code) || empty($username) || empty($password)) {
            $error = 'Reseller name, code, username and password are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (!preg_match('/^[A-Z0-9]{3,10}$/', $reseller_code)) {
            $error = 'Reseller code must be 3-10 uppercase letters/numbers (e.g., RSL001).';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Create reseller
                $stmt = $pdo->prepare("
                    INSERT INTO resellers (reseller_name, reseller_code, contact_person, email, phone, address, commission_rate, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $reseller_name, strtoupper($reseller_code), $contact_person, $email, 
                    $phone, $address, $commission_rate, $_SESSION['admin_id']
                ]);
                $reseller_id = $pdo->lastInsertId();
                
                // Create reseller login
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO admin_users (username, password_hash, email, role, reseller_id, status, created_by)
                    VALUES (?, ?, ?, 'reseller', ?, 'active', ?)
                ");
                $stmt->execute([$username, $password_hash, $email, $reseller_id, $_SESSION['admin_id']]);
                
                $pdo->commit();
                $message = "Reseller '$reseller_name' created successfully! Login: $username";
            } catch (PDOException $e) {
                $pdo->rollBack();
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    if (strpos($e->getMessage(), 'reseller_code') !== false) {
                        $error = "Reseller code '$reseller_code' already exists.";
                    } elseif (strpos($e->getMessage(), 'username') !== false) {
                        $error = "Username '$username' already exists.";
                    } elseif (strpos($e->getMessage(), 'email') !== false) {
                        $error = "Email '$email' already exists.";
                    } else {
                        $error = "Duplicate entry found.";
                    }
                } else {
                    $error = "Error creating reseller: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update') {
        $reseller_id = intval($_POST['reseller_id'] ?? 0);
        $reseller_name = trim($_POST['reseller_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $commission_rate = floatval($_POST['commission_rate'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        if ($reseller_id > 0 && !empty($reseller_name)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE resellers SET 
                        reseller_name = ?, contact_person = ?, email = ?, phone = ?, 
                        address = ?, commission_rate = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $reseller_name, $contact_person, $email, $phone, 
                    $address, $commission_rate, $status, $reseller_id
                ]);
                $message = "Reseller updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating reseller: " . $e->getMessage();
            }
        }
    } elseif ($action === 'add_balance') {
        $reseller_id = intval($_POST['reseller_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? 'Balance added by admin');
        
        if ($reseller_id > 0 && $amount > 0) {
            try {
                $pdo->beginTransaction();
                
                // Update reseller balance
                $pdo->prepare("UPDATE resellers SET balance = balance + ? WHERE id = ?")
                    ->execute([$amount, $reseller_id]);
                
                // Record transaction
                $pdo->prepare("
                    INSERT INTO reseller_transactions (reseller_id, transaction_type, amount, description, created_by)
                    VALUES (?, 'credit', ?, ?, ?)
                ")->execute([$reseller_id, $amount, $description, $_SESSION['admin_id']]);
                
                $pdo->commit();
                $message = "Balance added successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error adding balance: " . $e->getMessage();
            }
        }
    }
}

// Get all resellers with stats
$resellers = [];
try {
    $resellers = $pdo->query("
        SELECT r.*, 
               (SELECT COUNT(*) FROM admin_users WHERE reseller_id = r.id) as user_count,
               (SELECT COUNT(*) FROM vouchers WHERE reseller_id = r.id) as voucher_count,
               (SELECT COUNT(*) FROM radcheck WHERE reseller_id = r.id) as customer_count,
               a.username as login_username
        FROM resellers r
        LEFT JOIN admin_users a ON r.id = a.reseller_id AND a.role = 'reseller'
        ORDER BY r.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading resellers: " . $e->getMessage();
}

// Get reseller for editing
$edit_reseller = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, a.username as login_username, a.email as login_email
            FROM resellers r
            LEFT JOIN admin_users a ON r.id = a.reseller_id AND a.role = 'reseller'
            WHERE r.id = ?
        ");
        $stmt->execute([$_GET['edit']]);
        $edit_reseller = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore
    }
}

// Get transactions for a reseller
$transactions = [];
$view_reseller_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
if ($view_reseller_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, au.username as created_by_name
            FROM reseller_transactions t
            LEFT JOIN admin_users au ON t.created_by = au.id
            WHERE t.reseller_id = ?
            ORDER BY t.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$view_reseller_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .reseller-code {
            font-family: monospace;
            font-weight: bold;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-wifi"></i> Wi-Fi Portal Admin
            </span>
            <div>
                <span class="text-white me-3"><?php echo htmlspecialchars($_SESSION['admin_username']); ?> (Admin)</span>
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
                    <a href="packages.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-box-seam"></i> WiFi Packages
                    </a>
                    <a href="resellers.php" class="list-group-item list-group-item-action active">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-shop me-2"></i>Reseller Management</h2>
                    <span class="badge bg-primary"><?php echo count($resellers); ?> Resellers</span>
                </div>

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

                <?php if (!$view_reseller_id): ?>
                <!-- Create Reseller Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><?php echo $edit_reseller ? 'Edit Reseller' : 'Create New Reseller'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_reseller ? 'update' : 'create'; ?>">
                            <?php if ($edit_reseller): ?>
                                <input type="hidden" name="reseller_id" value="<?php echo $edit_reseller['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Reseller Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="reseller_name" required
                                           value="<?php echo $edit_reseller ? htmlspecialchars($edit_reseller['reseller_name']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Reseller Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="reseller_code" required
                                           pattern="[A-Z0-9]{3,10}" placeholder="RSL001"
                                           value="<?php echo $edit_reseller ? htmlspecialchars($edit_reseller['reseller_code']) : ''; ?>"
                                           <?php echo $edit_reseller ? 'disabled' : ''; ?>>
                                    <small class="text-muted">3-10 uppercase letters/numbers. Used for voucher prefix.</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" name="contact_person"
                                           value="<?php echo $edit_reseller ? htmlspecialchars($edit_reseller['contact_person'] ?? '') : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email"
                                           value="<?php echo $edit_reseller ? htmlspecialchars($edit_reseller['email'] ?? '') : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone"
                                           value="<?php echo $edit_reseller ? htmlspecialchars($edit_reseller['phone'] ?? '') : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Commission Rate (%)</label>
                                    <input type="number" class="form-control" name="commission_rate" step="0.01" min="0" max="100"
                                           value="<?php echo $edit_reseller ? $edit_reseller['commission_rate'] : '10'; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"><?php echo $edit_reseller ? htmlspecialchars($edit_reseller['address'] ?? '') : ''; ?></textarea>
                            </div>

                            <?php if (!$edit_reseller): ?>
                            <hr>
                            <h6>Login Credentials</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password" required minlength="6">
                                    <small class="text-muted">Min 6 characters</small>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo $edit_reseller['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $edit_reseller['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $edit_reseller['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?php echo $edit_reseller ? 'check-lg' : 'plus-circle'; ?>"></i>
                                    <?php echo $edit_reseller ? 'Update Reseller' : 'Create Reseller'; ?>
                                </button>
                                <?php if ($edit_reseller): ?>
                                    <a href="resellers.php" class="btn btn-secondary">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Resellers List or Transactions -->
                <div class="card">
                    <div class="card-header">
                        <h5><?php echo $view_reseller_id ? 'Transaction History' : 'All Resellers'; ?></h5>
                        <?php if ($view_reseller_id): ?>
                            <a href="resellers.php" class="btn btn-sm btn-outline-secondary">Back to Resellers</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($view_reseller_id): ?>
                            <!-- Transactions Table -->
                            <?php if (empty($transactions)): ?>
                                <p class="text-muted text-center py-4">No transactions found.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Description</th>
                                                <th>By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $t): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($t['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $t['transaction_type'] === 'credit' ? 'success' : ($t['transaction_type'] === 'debit' ? 'danger' : 'info'); ?>">
                                                            <?php echo ucfirst($t['transaction_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>৳<?php echo number_format($t['amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($t['description']); ?></td>
                                                    <td><?php echo htmlspecialchars($t['created_by_name'] ?? 'System'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Resellers Table -->
                            <?php if (empty($resellers)): ?>
                                <p class="text-muted text-center py-4">No resellers created yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Name</th>
                                                <th>Contact</th>
                                                <th>Balance</th>
                                                <th>Stats</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($resellers as $r): ?>
                                                <tr>
                                                    <td><span class="reseller-code"><?php echo htmlspecialchars($r['reseller_code']); ?></span></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($r['reseller_name']); ?></strong>
                                                        <br><small class="text-muted">@<?php echo htmlspecialchars($r['login_username']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($r['phone'] ?? '-'); ?>
                                                        <br><small><?php echo htmlspecialchars($r['email'] ?? '-'); ?></small>
                                                    </td>
                                                    <td>
                                                        <strong>৳<?php echo number_format($r['balance'], 2); ?></strong>
                                                        <br><small><?php echo $r['commission_rate']; ?>% commission</small>
                                                    </td>
                                                    <td>
                                                        <small>
                                                            <?php echo $r['customer_count']; ?> customers<br>
                                                            <?php echo $r['voucher_count']; ?> vouchers
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $r['status'] === 'active' ? 'success' : ($r['status'] === 'suspended' ? 'danger' : 'secondary'); ?>">
                                                            <?php echo ucfirst($r['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="?edit=<?php echo $r['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="?view=<?php echo $r['id']; ?>" class="btn btn-outline-info" title="Transactions">
                                                                <i class="bi bi-list"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#balanceModal<?php echo $r['id']; ?>">
                                                                <i class="bi bi-cash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>

                                                <!-- Add Balance Modal -->
                                                <div class="modal fade" id="balanceModal<?php echo $r['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Add Balance - <?php echo htmlspecialchars($r['reseller_name']); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="action" value="add_balance">
                                                                    <input type="hidden" name="reseller_id" value="<?php echo $r['id']; ?>">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Current Balance</label>
                                                                        <input type="text" class="form-control" value="৳<?php echo number_format($r['balance'], 2); ?>" disabled>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Amount to Add (৳)</label>
                                                                        <input type="number" class="form-control" name="amount" step="0.01" min="1" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Description</label>
                                                                        <textarea class="form-control" name="description" rows="2">Balance added by admin</textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-success">Add Balance</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
