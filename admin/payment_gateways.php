<?php
/**
 * Payment Gateway Management
 * Manage payment gateway configurations from the admin panel
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

$pdo = getDBConnection();
$message = '';
$error = '';

// Check if payment gateways table exists, create if not
$table_exists = false;
try {
    $check = $pdo->query("SELECT 1 FROM payment_gateways LIMIT 1");
    $table_exists = true;
} catch (PDOException $e) {
    // Table doesn't exist, create it
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_gateways (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                gateway_name varchar(64) NOT NULL,
                display_name varchar(128) NOT NULL,
                gateway_type enum('mobile_banking', 'card', 'bank_transfer', 'crypto', 'other') DEFAULT 'mobile_banking',
                api_key varchar(255) DEFAULT NULL,
                api_secret varchar(255) DEFAULT NULL,
                merchant_id varchar(128) DEFAULT NULL,
                username varchar(128) DEFAULT NULL,
                password varchar(255) DEFAULT NULL,
                sandbox_mode tinyint(1) DEFAULT '1',
                status enum('active', 'disabled') DEFAULT 'disabled',
                webhook_url varchar(255) DEFAULT NULL,
                success_url varchar(255) DEFAULT NULL,
                fail_url varchar(255) DEFAULT NULL,
                cancel_url varchar(255) DEFAULT NULL,
                currency varchar(10) DEFAULT 'BDT',
                config_json text,
                description text,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by int(11) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY gateway_name (gateway_name),
                KEY status (status),
                KEY gateway_type (gateway_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Insert sample gateways
        $pdo->exec("
            INSERT INTO payment_gateways (gateway_name, display_name, gateway_type, currency, status, description) VALUES
            ('bkash', 'bKash', 'mobile_banking', 'BDT', 'disabled', 'bKash Mobile Banking - Bangladesh'),
            ('nagad', 'Nagad', 'mobile_banking', 'BDT', 'disabled', 'Nagad Mobile Banking - Bangladesh'),
            ('rocket', 'Rocket (DBBL)', 'mobile_banking', 'BDT', 'disabled', 'Rocket Mobile Banking - Bangladesh'),
            ('stripe', 'Stripe', 'card', 'USD', 'disabled', 'Stripe Card Payments'),
            ('paypal', 'PayPal', 'card', 'USD', 'disabled', 'PayPal Payments')
            ON DUPLICATE KEY UPDATE gateway_name=gateway_name
        ");
        
        $table_exists = true;
        $message = "Payment gateways table created successfully!";
    } catch (PDOException $create_error) {
        $error = "Error creating payment gateways table: " . $create_error->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_exists) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update') {
        $gateway_id = intval($_POST['gateway_id'] ?? 0);
        $display_name = trim($_POST['display_name'] ?? '');
        $gateway_type = $_POST['gateway_type'] ?? 'mobile_banking';
        $api_key = trim($_POST['api_key'] ?? '') ?: null;
        $api_secret = trim($_POST['api_secret'] ?? '') ?: null;
        $merchant_id = trim($_POST['merchant_id'] ?? '') ?: null;
        $username = trim($_POST['username'] ?? '') ?: null;
        $password = trim($_POST['password'] ?? '') ?: null;
        $sandbox_mode = isset($_POST['sandbox_mode']) ? 1 : 0;
        $status = $_POST['status'] ?? 'disabled';
        $webhook_url = trim($_POST['webhook_url'] ?? '') ?: null;
        $success_url = trim($_POST['success_url'] ?? '') ?: null;
        $fail_url = trim($_POST['fail_url'] ?? '') ?: null;
        $cancel_url = trim($_POST['cancel_url'] ?? '') ?: null;
        $currency = trim($_POST['currency'] ?? 'BDT');
        $description = trim($_POST['description'] ?? '');
        
        // Build config JSON for additional fields
        $config = [];
        if (!empty($_POST['config_fields'])) {
            foreach ($_POST['config_fields'] as $key => $value) {
                if (!empty($key) && !empty($value)) {
                    $config[$key] = $value;
                }
            }
        }
        $config_json = !empty($config) ? json_encode($config) : null;
        
        if ($gateway_id > 0 && !empty($display_name)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE payment_gateways SET 
                        display_name = ?, gateway_type = ?, api_key = ?, api_secret = ?,
                        merchant_id = ?, username = ?, password = ?, sandbox_mode = ?,
                        status = ?, webhook_url = ?, success_url = ?, fail_url = ?,
                        cancel_url = ?, currency = ?, description = ?, config_json = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $display_name, $gateway_type, $api_key, $api_secret,
                    $merchant_id, $username, $password, $sandbox_mode,
                    $status, $webhook_url, $success_url, $fail_url,
                    $cancel_url, $currency, $description, $config_json, $gateway_id
                ]);
                $message = "Payment gateway updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating gateway: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $gateway_id = intval($_POST['gateway_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'disabled';
        if ($gateway_id > 0) {
            try {
                $pdo->prepare("UPDATE payment_gateways SET status = ? WHERE id = ?")->execute([$new_status, $gateway_id]);
                $message = "Gateway status updated!";
            } catch (PDOException $e) {
                $error = "Error updating status: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_mode') {
        $gateway_id = intval($_POST['gateway_id'] ?? 0);
        $new_mode = $_POST['new_mode'] ?? 1;
        if ($gateway_id > 0) {
            try {
                $pdo->prepare("UPDATE payment_gateways SET sandbox_mode = ? WHERE id = ?")->execute([$new_mode, $gateway_id]);
                $message = "Gateway mode updated!";
            } catch (PDOException $e) {
                $error = "Error updating mode: " . $e->getMessage();
            }
        }
    }
}

// Get all payment gateways
$gateways = [];
if ($table_exists) {
    try {
        $gateways = $pdo->query("
            SELECT g.*, a.username as created_by_name
            FROM payment_gateways g
            LEFT JOIN admin_users a ON g.created_by = a.id
            ORDER BY g.status DESC, g.display_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error loading gateways: " . $e->getMessage();
    }
}

// Get gateway for editing
$edit_gateway = null;
if ($table_exists && isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM payment_gateways WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_gateway = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($edit_gateway && $edit_gateway['config_json']) {
            $edit_gateway['config'] = json_decode($edit_gateway['config_json'], true);
        }
    } catch (PDOException $e) {
        // Ignore
    }
}

// Gateway types
$gateway_types = [
    'mobile_banking' => 'Mobile Banking (bKash, Nagad, etc.)',
    'card' => 'Card Payment (Stripe, PayPal)',
    'bank_transfer' => 'Bank Transfer',
    'crypto' => 'Cryptocurrency',
    'other' => 'Other'
];

// Currency options
$currencies = [
    'BDT' => 'BDT - Bangladeshi Taka',
    'USD' => 'USD - US Dollar',
    'EUR' => 'EUR - Euro',
    'GBP' => 'GBP - British Pound',
    'INR' => 'INR - Indian Rupee'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .gateway-card {
            transition: all 0.3s ease;
        }
        .gateway-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .gateway-active {
            border-left: 4px solid #198754;
        }
        .gateway-disabled {
            border-left: 4px solid #6c757d;
            opacity: 0.7;
        }
        .sandbox-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .api-key-mask {
            font-family: monospace;
            letter-spacing: 2px;
        }
        .config-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
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
                    <a href="groups.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-people-fill"></i> User Groups
                    </a>
                    <a href="nas.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-router"></i> NAS / Routers
                    </a>
                    <a href="payment_gateways.php" class="list-group-item list-group-item-action active">
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
                    <h2><i class="bi bi-credit-card me-2"></i>Payment Gateway Management</h2>
                    <span class="badge bg-primary"><?php echo count(array_filter($gateways, fn($g) => $g['status'] === 'active')); ?> Active</span>
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

                <!-- Info Alert -->
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Configure Payment Gateways</strong> to accept payments for WiFi vouchers. 
                    Pre-configured gateways include bKash, Nagad, Rocket (Bangladesh) and Stripe, PayPal (International).
                    Always test in <strong>Sandbox Mode</strong> before going live.
                </div>

                <?php if ($edit_gateway): ?>
                <!-- Edit Gateway Form -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Payment Gateway: <?php echo htmlspecialchars($edit_gateway['display_name']); ?></h5>
                        <a href="payment_gateways.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Cancel
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="gateway_id" value="<?php echo $edit_gateway['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gateway Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_gateway['gateway_name']); ?>" disabled>
                                    <small class="text-muted">Identifier cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Display Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="display_name" required
                                           value="<?php echo htmlspecialchars($edit_gateway['display_name']); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Gateway Type</label>
                                    <select class="form-select" name="gateway_type">
                                        <?php foreach ($gateway_types as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo $edit_gateway['gateway_type'] === $value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?php echo $edit_gateway['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="disabled" <?php echo $edit_gateway['status'] === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Currency</label>
                                    <select class="form-select" name="currency">
                                        <?php foreach ($currencies as $code => $label): ?>
                                            <option value="<?php echo $code; ?>" <?php echo $edit_gateway['currency'] === $code ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- API Credentials -->
                            <div class="config-section">
                                <h6><i class="bi bi-key me-2"></i>API Credentials</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">API Key / App Key</label>
                                        <input type="text" class="form-control" name="api_key"
                                               value="<?php echo htmlspecialchars($edit_gateway['api_key'] ?? ''); ?>"
                                               placeholder="Enter API Key">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">API Secret / App Secret</label>
                                        <input type="password" class="form-control" name="api_secret"
                                               value="<?php echo htmlspecialchars($edit_gateway['api_secret'] ?? ''); ?>"
                                               placeholder="Enter API Secret">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Merchant ID / Store ID</label>
                                        <input type="text" class="form-control" name="merchant_id"
                                               value="<?php echo htmlspecialchars($edit_gateway['merchant_id'] ?? ''); ?>"
                                               placeholder="Enter Merchant ID">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username (if required)</label>
                                        <input type="text" class="form-control" name="username"
                                               value="<?php echo htmlspecialchars($edit_gateway['username'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password (if required)</label>
                                        <input type="password" class="form-control" name="password"
                                               value="<?php echo htmlspecialchars($edit_gateway['password'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- URLs -->
                            <div class="config-section">
                                <h6><i class="bi bi-link-45deg me-2"></i>URLs</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Webhook URL</label>
                                        <input type="url" class="form-control" name="webhook_url"
                                               value="<?php echo htmlspecialchars($edit_gateway['webhook_url'] ?? ''); ?>"
                                               placeholder="https://your-domain.com/webhook/payment">
                                        <small class="text-muted">For payment notifications</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Success URL</label>
                                        <input type="url" class="form-control" name="success_url"
                                               value="<?php echo htmlspecialchars($edit_gateway['success_url'] ?? ''); ?>"
                                               placeholder="https://your-domain.com/payment/success">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Fail URL</label>
                                        <input type="url" class="form-control" name="fail_url"
                                               value="<?php echo htmlspecialchars($edit_gateway['fail_url'] ?? ''); ?>"
                                               placeholder="https://your-domain.com/payment/fail">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cancel URL</label>
                                        <input type="url" class="form-control" name="cancel_url"
                                               value="<?php echo htmlspecialchars($edit_gateway['cancel_url'] ?? ''); ?>"
                                               placeholder="https://your-domain.com/payment/cancel">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Description / Notes</label>
                                    <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($edit_gateway['description'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="sandbox_mode" id="sandbox_mode" 
                                       <?php echo $edit_gateway['sandbox_mode'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sandbox_mode">
                                    <strong>Sandbox Mode</strong> (Test environment - no real transactions)
                                </label>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Gateway Cards -->
                <div class="row">
                    <?php foreach ($gateways as $gateway): ?>
                        <?php if ($edit_gateway && $edit_gateway['id'] === $gateway['id']) continue; ?>
                        <div class="col-md-6 mb-4">
                            <div class="card gateway-card <?php echo $gateway['status'] === 'active' ? 'gateway-active' : 'gateway-disabled'; ?> h-100">
                                <div class="card-body">
                                    <?php if ($gateway['sandbox_mode']): ?>
                                        <span class="badge bg-warning text-dark sandbox-badge">
                                            <i class="bi bi-bug"></i> Sandbox
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success sandbox-badge">
                                            <i class="bi bi-check-circle"></i> Live
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-credit-card-fill" style="font-size: 2rem; color: <?php echo $gateway['status'] === 'active' ? '#198754' : '#6c757d'; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($gateway['display_name']); ?></h5>
                                            <span class="badge bg-<?php echo $gateway['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($gateway['status']); ?>
                                            </span>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($gateway_types[$gateway['gateway_type']] ?? $gateway['gateway_type']); ?></span>
                                        </div>
                                    </div>

                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars($gateway['description'] ?? 'No description'); ?>
                                    </p>

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <strong>Currency:</strong> <?php echo htmlspecialchars($gateway['currency']); ?>
                                        </small>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="?edit=<?php echo $gateway['id']; ?>" class="btn btn-outline-primary" title="Configure">
                                                <i class="bi bi-gear"></i> Configure
                                            </a>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_mode">
                                                <input type="hidden" name="gateway_id" value="<?php echo $gateway['id']; ?>">
                                                <input type="hidden" name="new_mode" value="<?php echo $gateway['sandbox_mode'] ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-outline-<?php echo $gateway['sandbox_mode'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $gateway['sandbox_mode'] ? '<i class="bi bi-check-circle"></i> Go Live' : '<i class="bi bi-bug"></i> Sandbox'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="gateway_id" value="<?php echo $gateway['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $gateway['status'] === 'active' ? 'disabled' : 'active'; ?>">
                                                <button type="submit" class="btn btn-outline-<?php echo $gateway['status'] === 'active' ? 'warning' : 'success'; ?>">
                                                    <?php echo $gateway['status'] === 'active' ? '<i class="bi bi-pause"></i> Disable' : '<i class="bi bi-play"></i> Enable'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Help Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="bi bi-question-circle me-2"></i>Gateway Setup Guides</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="gatewayGuides">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bkashGuide">
                                        <i class="bi bi-phone me-2"></i> bKash Setup
                                    </button>
                                </h2>
                                <div id="bkashGuide" class="accordion-collapse collapse" data-bs-parent="#gatewayGuides">
                                    <div class="accordion-body">
                                        <ol>
                                            <li>Register as a merchant at <a href="https://www.bkash.com" target="_blank">bkash.com</a></li>
                                            <li>Apply for bKash Payment Gateway</li>
                                            <li>Get your App Key, App Secret, and Username/Password</li>
                                            <li>Enter these credentials in the bKash gateway configuration</li>
                                            <li>Always test in Sandbox mode first</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#stripeGuide">
                                        <i class="bi bi-credit-card me-2"></i> Stripe Setup
                                    </button>
                                </h2>
                                <div id="stripeGuide" class="accordion-collapse collapse" data-bs-parent="#gatewayGuides">
                                    <div class="accordion-body">
                                        <ol>
                                            <li>Create account at <a href="https://stripe.com" target="_blank">stripe.com</a></li>
                                            <li>Get your Publishable Key and Secret Key from Developers → API Keys</li>
                                            <li>Use Test keys for Sandbox mode, Live keys for production</li>
                                            <li>Configure webhook endpoint for payment notifications</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
