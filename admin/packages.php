<?php
/**
 * WiFi Package Management
 * Manage internet packages for users to purchase
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

$pdo = getDBConnection();
$message = '';
$error = '';

// Check if packages table exists, create if not
$table_exists = false;
try {
    $check = $pdo->query("SELECT 1 FROM wifi_packages LIMIT 1");
    $table_exists = true;
} catch (PDOException $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wifi_packages (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                package_name varchar(128) NOT NULL,
                package_type enum('hourly', 'daily', 'custom_hourly', 'custom_daily') NOT NULL,
                duration_value int(11) NOT NULL,
                duration_unit enum('hour', 'day') NOT NULL,
                base_price decimal(10,2) NOT NULL,
                price_per_unit decimal(10,2) DEFAULT NULL,
                data_limit_mb int(11) DEFAULT NULL,
                speed_limit_mbps int(11) DEFAULT NULL,
                description text,
                is_active tinyint(1) DEFAULT '1',
                is_custom tinyint(1) DEFAULT '0',
                display_order int(11) DEFAULT '0',
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY package_type (package_type),
                KEY is_active (is_active),
                KEY display_order (display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS package_orders (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                order_code varchar(32) NOT NULL,
                package_id int(11) unsigned NOT NULL,
                user_identifier varchar(128) NOT NULL,
                quantity int(11) DEFAULT '1',
                total_amount decimal(10,2) NOT NULL,
                duration_granted int(11) NOT NULL,
                payment_gateway varchar(64) DEFAULT NULL,
                payment_status enum('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
                payment_transaction_id varchar(255) DEFAULT NULL,
                voucher_code varchar(64) DEFAULT NULL,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                paid_at timestamp NULL DEFAULT NULL,
                expires_at timestamp NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY order_code (order_code),
                KEY package_id (package_id),
                KEY payment_status (payment_status),
                KEY user_identifier (user_identifier)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Insert default packages
        $pdo->exec("
            INSERT INTO wifi_packages (package_name, package_type, duration_value, duration_unit, base_price, price_per_unit, description, is_active, is_custom, display_order) VALUES
            ('2 Hours Internet', 'hourly', 2, 'hour', 15.00, NULL, '2 hours of high-speed internet access', 1, 0, 1),
            ('1 Day Internet', 'daily', 1, 'day', 30.00, NULL, '24 hours of high-speed internet access', 1, 0, 2),
            ('Custom Hours', 'custom_hourly', 1, 'hour', 0.00, 10.00, 'Choose how many hours you need. BDT 10 per hour', 1, 1, 3),
            ('Custom Days', 'custom_daily', 1, 'day', 0.00, 30.00, 'Choose how many days you need. BDT 30 per day', 1, 1, 4)
            ON DUPLICATE KEY UPDATE package_name=package_name
        ");
        
        $table_exists = true;
        $message = "Package tables created successfully!";
    } catch (PDOException $create_error) {
        $error = "Error creating package tables: " . $create_error->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_exists) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $package_name = trim($_POST['package_name'] ?? '');
        $package_type = $_POST['package_type'] ?? 'hourly';
        $duration_value = intval($_POST['duration_value'] ?? 1);
        $duration_unit = $_POST['duration_unit'] ?? 'hour';
        $base_price = floatval($_POST['base_price'] ?? 0);
        $price_per_unit = !empty($_POST['price_per_unit']) ? floatval($_POST['price_per_unit']) : null;
        $data_limit_mb = !empty($_POST['data_limit_mb']) ? intval($_POST['data_limit_mb']) : null;
        $speed_limit_mbps = !empty($_POST['speed_limit_mbps']) ? intval($_POST['speed_limit_mbps']) : null;
        $description = trim($_POST['description'] ?? '');
        $is_custom = isset($_POST['is_custom']) ? 1 : 0;
        $display_order = intval($_POST['display_order'] ?? 0);
        
        if (empty($package_name)) {
            $error = 'Package name is required.';
        } elseif ($duration_value < 1) {
            $error = 'Duration must be at least 1.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO wifi_packages 
                    (package_name, package_type, duration_value, duration_unit, base_price, price_per_unit, 
                     data_limit_mb, speed_limit_mbps, description, is_custom, display_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $package_name, $package_type, $duration_value, $duration_unit, $base_price, $price_per_unit,
                    $data_limit_mb, $speed_limit_mbps, $description, $is_custom, $display_order
                ]);
                $message = "Package '$package_name' created successfully!";
            } catch (PDOException $e) {
                $error = "Error creating package: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $package_id = intval($_POST['package_id'] ?? 0);
        $package_name = trim($_POST['package_name'] ?? '');
        $package_type = $_POST['package_type'] ?? 'hourly';
        $duration_value = intval($_POST['duration_value'] ?? 1);
        $duration_unit = $_POST['duration_unit'] ?? 'hour';
        $base_price = floatval($_POST['base_price'] ?? 0);
        $price_per_unit = !empty($_POST['price_per_unit']) ? floatval($_POST['price_per_unit']) : null;
        $data_limit_mb = !empty($_POST['data_limit_mb']) ? intval($_POST['data_limit_mb']) : null;
        $speed_limit_mbps = !empty($_POST['speed_limit_mbps']) ? intval($_POST['speed_limit_mbps']) : null;
        $description = trim($_POST['description'] ?? '');
        $is_custom = isset($_POST['is_custom']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_order = intval($_POST['display_order'] ?? 0);
        
        if ($package_id > 0 && !empty($package_name)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE wifi_packages SET 
                        package_name = ?, package_type = ?, duration_value = ?, duration_unit = ?,
                        base_price = ?, price_per_unit = ?, data_limit_mb = ?, speed_limit_mbps = ?,
                        description = ?, is_custom = ?, is_active = ?, display_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $package_name, $package_type, $duration_value, $duration_unit, $base_price, $price_per_unit,
                    $data_limit_mb, $speed_limit_mbps, $description, $is_custom, $is_active, $display_order, $package_id
                ]);
                $message = "Package updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating package: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $package_id = intval($_POST['package_id'] ?? 0);
        if ($package_id > 0) {
            try {
                $pdo->prepare("DELETE FROM wifi_packages WHERE id = ?")->execute([$package_id]);
                $message = "Package deleted successfully!";
            } catch (PDOException $e) {
                $error = "Error deleting package: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $package_id = intval($_POST['package_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 0;
        if ($package_id > 0) {
            try {
                $pdo->prepare("UPDATE wifi_packages SET is_active = ? WHERE id = ?")->execute([$new_status, $package_id]);
                $message = "Package status updated!";
            } catch (PDOException $e) {
                $error = "Error updating status: " . $e->getMessage();
            }
        }
    }
}

// Get all packages
$packages = [];
if ($table_exists) {
    try {
        $packages = $pdo->query("
            SELECT * FROM wifi_packages 
            ORDER BY display_order ASC, id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error loading packages: " . $e->getMessage();
    }
}

// Get package stats
$stats = ['total' => 0, 'active' => 0, 'orders_today' => 0, 'revenue_today' => 0];
if ($table_exists) {
    try {
        $stats['total'] = $pdo->query("SELECT COUNT(*) FROM wifi_packages")->fetchColumn();
        $stats['active'] = $pdo->query("SELECT COUNT(*) FROM wifi_packages WHERE is_active = 1")->fetchColumn();
        $stats['orders_today'] = $pdo->query("SELECT COUNT(*) FROM package_orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $stats['revenue_today'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM package_orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'")->fetchColumn();
    } catch (PDOException $e) {
        // Ignore
    }
}

// Get package for editing
$edit_package = null;
if ($table_exists && isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM wifi_packages WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_package = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore
    }
}

$package_types = [
    'hourly' => 'Fixed Hours',
    'daily' => 'Fixed Days',
    'custom_hourly' => 'Custom Hours (User selects quantity)',
    'custom_daily' => 'Custom Days (User selects quantity)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Package Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .package-card {
            transition: all 0.3s ease;
        }
        .package-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .price-tag {
            font-size: 1.5rem;
            font-weight: bold;
            color: #198754;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                    <a href="packages.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-box-seam"></i> WiFi Packages
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
                    <h2><i class="bi bi-box-seam me-2"></i>WiFi Package Management</h2>
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

                <!-- Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h6>Total Packages</h6>
                                <h3><?php echo $stats['total']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6>Active Packages</h6>
                                <h3><?php echo $stats['active']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6>Orders Today</h6>
                                <h3><?php echo $stats['orders_today']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <h6>Revenue Today</h6>
                                <h3>৳<?php echo number_format($stats['revenue_today'], 2); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Create/Edit Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><?php echo $edit_package ? 'Edit Package' : 'Create New Package'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_package ? 'update' : 'create'; ?>">
                            <?php if ($edit_package): ?>
                                <input type="hidden" name="package_id" value="<?php echo $edit_package['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Package Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="package_name" required
                                           value="<?php echo $edit_package ? htmlspecialchars($edit_package['package_name']) : ''; ?>"
                                           placeholder="e.g., 2 Hours Internet">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Package Type</label>
                                    <select class="form-select" name="package_type" id="package_type">
                                        <?php foreach ($package_types as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" 
                                                <?php echo ($edit_package && $edit_package['package_type'] === $value) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Duration Value</label>
                                    <input type="number" class="form-control" name="duration_value" min="1" required
                                           value="<?php echo $edit_package ? $edit_package['duration_value'] : '1'; ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Duration Unit</label>
                                    <select class="form-select" name="duration_unit">
                                        <option value="hour" <?php echo ($edit_package && $edit_package['duration_unit'] === 'hour') ? 'selected' : ''; ?>>Hour(s)</option>
                                        <option value="day" <?php echo ($edit_package && $edit_package['duration_unit'] === 'day') ? 'selected' : ''; ?>>Day(s)</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Base Price (৳)</label>
                                    <input type="number" class="form-control" name="base_price" step="0.01" min="0" required
                                           value="<?php echo $edit_package ? $edit_package['base_price'] : '0'; ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Price Per Unit (৳)</label>
                                    <input type="number" class="form-control" name="price_per_unit" step="0.01" min="0"
                                           value="<?php echo $edit_package ? ($edit_package['price_per_unit'] ?? '') : ''; ?>"
                                           placeholder="For custom packages">
                                    <small class="text-muted">For custom packages only</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Data Limit (MB)</label>
                                    <input type="number" class="form-control" name="data_limit_mb" min="0"
                                           value="<?php echo $edit_package ? ($edit_package['data_limit_mb'] ?? '') : ''; ?>"
                                           placeholder="Unlimited if empty">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Speed Limit (Mbps)</label>
                                    <input type="number" class="form-control" name="speed_limit_mbps" min="0"
                                           value="<?php echo $edit_package ? ($edit_package['speed_limit_mbps'] ?? '') : ''; ?>"
                                           placeholder="Unlimited if empty">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" class="form-control" name="display_order" min="0"
                                           value="<?php echo $edit_package ? $edit_package['display_order'] : '0'; ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"><?php echo $edit_package ? htmlspecialchars($edit_package['description'] ?? '') : ''; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_custom" id="is_custom"
                                               <?php echo ($edit_package && $edit_package['is_custom']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_custom">
                                            Allow user to customize quantity (custom packages)
                                        </label>
                                    </div>
                                </div>
                                <?php if ($edit_package): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                                               <?php echo ($edit_package && $edit_package['is_active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Package is active and available for purchase
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?php echo $edit_package ? 'check-lg' : 'plus-circle'; ?>"></i>
                                    <?php echo $edit_package ? 'Update Package' : 'Create Package'; ?>
                                </button>
                                <?php if ($edit_package): ?>
                                    <a href="packages.php" class="btn btn-secondary">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Packages List -->
                <div class="card">
                    <div class="card-header">
                        <h5>All Packages</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($packages)): ?>
                            <p class="text-muted text-center py-4">No packages created yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Package Name</th>
                                            <th>Type</th>
                                            <th>Duration</th>
                                            <th>Price</th>
                                            <th>Limits</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($packages as $package): ?>
                                            <tr>
                                                <td><?php echo $package['display_order']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($package['package_name']); ?></strong>
                                                    <?php if ($package['is_custom']): ?>
                                                        <span class="badge bg-info">Custom</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($package_types[$package['package_type']] ?? $package['package_type']); ?></td>
                                                <td>
                                                    <?php echo $package['duration_value']; ?> 
                                                    <?php echo $package['duration_unit']; ?>(s)
                                                </td>
                                                <td class="price-tag">
                                                    ৳<?php echo number_format($package['base_price'], 2); ?>
                                                    <?php if ($package['price_per_unit']): ?>
                                                        <small class="text-muted">(+ ৳<?php echo $package['price_per_unit']; ?>/unit)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($package['data_limit_mb']): ?>
                                                        <small>Data: <?php echo $package['data_limit_mb']; ?> MB</small><br>
                                                    <?php endif; ?>
                                                    <?php if ($package['speed_limit_mbps']): ?>
                                                        <small>Speed: <?php echo $package['speed_limit_mbps']; ?> Mbps</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($package['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?edit=<?php echo $package['id']; ?>" class="btn btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                                                            <input type="hidden" name="new_status" value="<?php echo $package['is_active'] ? 0 : 1; ?>">
                                                            <button type="submit" class="btn btn-outline-<?php echo $package['is_active'] ? 'warning' : 'success'; ?>">
                                                                <i class="bi bi-<?php echo $package['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this package?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="package_id" value="<?php echo $package['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
