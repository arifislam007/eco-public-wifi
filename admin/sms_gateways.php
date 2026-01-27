<?php
/**
 * SMS Gateway Management
 * Manage SMS gateway configurations from the admin panel
 */

session_start();
require_once __DIR__ . '/includes/security.php';
requireAdminLogin();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

$pdo = getDBConnection();
$message = '';
$error = '';

// Check if SMS gateways table exists, create if not
$table_exists = false;
try {
    $check = $pdo->query("SELECT 1 FROM sms_gateways LIMIT 1");
    $table_exists = true;
} catch (PDOException $e) {
    // Table doesn't exist, create it
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sms_gateways (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                gateway_name varchar(64) NOT NULL,
                display_name varchar(128) NOT NULL,
                gateway_type enum('international', 'local', 'custom_api') DEFAULT 'local',
                api_key varchar(255) DEFAULT NULL,
                api_secret varchar(255) DEFAULT NULL,
                sender_id varchar(50) DEFAULT NULL,
                username varchar(128) DEFAULT NULL,
                password varchar(255) DEFAULT NULL,
                api_endpoint varchar(255) DEFAULT NULL,
                status enum('active', 'disabled') DEFAULT 'disabled',
                is_default tinyint(1) DEFAULT '0',
                rate_per_sms decimal(10,4) DEFAULT '0.0000',
                balance decimal(10,2) DEFAULT '0.00',
                config_json text,
                description text,
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by int(11) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY gateway_name (gateway_name),
                KEY status (status),
                KEY is_default (is_default)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Insert sample gateways
        $pdo->exec("
            INSERT INTO sms_gateways (gateway_name, display_name, gateway_type, status, description) VALUES
            ('twilio', 'Twilio', 'international', 'disabled', 'Twilio SMS Service'),
            ('nexmo', 'Nexmo (Vonage)', 'international', 'disabled', 'Nexmo SMS API'),
            ('banglalink', 'Banglalink API', 'local', 'disabled', 'Banglalink SMS Gateway'),
            ('grameenphone', 'Grameenphone API', 'local', 'disabled', 'Grameenphone SMS Gateway'),
            ('robi', 'Robi/Airtel API', 'local', 'disabled', 'Robi/Airtel SMS Gateway'),
            ('sslwireless', 'SSL Wireless', 'local', 'disabled', 'SSL Wireless SMS Gateway')
            ON DUPLICATE KEY UPDATE gateway_name=gateway_name
        ");
        
        $table_exists = true;
        $message = "SMS gateways table created successfully!";
    } catch (PDOException $create_error) {
        $error = "Error creating SMS gateways table: " . $create_error->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_exists) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update') {
        $gateway_id = intval($_POST['gateway_id'] ?? 0);
        $display_name = trim($_POST['display_name'] ?? '');
        $gateway_type = $_POST['gateway_type'] ?? 'local';
        $api_key = trim($_POST['api_key'] ?? '') ?: null;
        $api_secret = trim($_POST['api_secret'] ?? '') ?: null;
        $sender_id = trim($_POST['sender_id'] ?? '') ?: null;
        $username = trim($_POST['username'] ?? '') ?: null;
        $password = trim($_POST['password'] ?? '') ?: null;
        $api_endpoint = trim($_POST['api_endpoint'] ?? '') ?: null;
        $status = $_POST['status'] ?? 'disabled';
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        $rate_per_sms = floatval($_POST['rate_per_sms'] ?? 0);
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
                $pdo->beginTransaction();
                
                // If setting as default, unset other defaults
                if ($is_default && $status === 'active') {
                    $pdo->prepare("UPDATE sms_gateways SET is_default = 0 WHERE id != ?")->execute([$gateway_id]);
                }
                
                $stmt = $pdo->prepare("
                    UPDATE sms_gateways SET 
                        display_name = ?, gateway_type = ?, api_key = ?, api_secret = ?,
                        sender_id = ?, username = ?, password = ?, api_endpoint = ?,
                        status = ?, is_default = ?, rate_per_sms = ?, description = ?, config_json = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $display_name, $gateway_type, $api_key, $api_secret,
                    $sender_id, $username, $password, $api_endpoint,
                    $status, $is_default, $rate_per_sms, $description, $config_json, $gateway_id
                ]);
                
                $pdo->commit();
                $message = "SMS gateway updated successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error updating gateway: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $gateway_id = intval($_POST['gateway_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'disabled';
        if ($gateway_id > 0) {
            try {
                $pdo->prepare("UPDATE sms_gateways SET status = ? WHERE id = ?")->execute([$new_status, $gateway_id]);
                $message = "Gateway status updated!";
            } catch (PDOException $e) {
                $error = "Error updating status: " . $e->getMessage();
            }
        }
    } elseif ($action === 'set_default') {
        $gateway_id = intval($_POST['gateway_id'] ?? 0);
        if ($gateway_id > 0) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE sms_gateways SET is_default = 0")->execute();
                $pdo->prepare("UPDATE sms_gateways SET is_default = 1, status = 'active' WHERE id = ?")->execute([$gateway_id]);
                $pdo->commit();
                $message = "Default gateway updated!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error setting default: " . $e->getMessage();
            }
        }
    } elseif ($action === 'test') {
        $gateway_id = intval($_POST['gateway_id'] ?? 0);
        $test_number = trim($_POST['test_number'] ?? '');
        $test_message = trim($_POST['test_message'] ?? 'This is a test message from WiFi Portal.');
        
        if ($gateway_id > 0 && !empty($test_number)) {
            // Get gateway details
            $stmt = $pdo->prepare("SELECT * FROM sms_gateways WHERE id = ?");
            $stmt->execute([$gateway_id]);
            $gateway = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($gateway) {
                // TODO: Implement actual SMS sending based on gateway type
                // For now, just log it
                $message = "Test SMS would be sent via {$gateway['display_name']} to {$test_number}. Implement sendSMS() function for actual sending.";
            } else {
                $error = "Gateway not found.";
            }
        }
    }
}

// Get all SMS gateways
$gateways = [];
if ($table_exists) {
    try {
        $gateways = $pdo->query("
            SELECT g.*, a.username as created_by_name
            FROM sms_gateways g
            LEFT JOIN admin_users a ON g.created_by = a.id
            ORDER BY g.is_default DESC, g.display_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error loading gateways: " . $e->getMessage();
    }
}

// Get gateway for editing
$edit_gateway = null;
if ($table_exists && isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM sms_gateways WHERE id = ?");
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
    'international' => 'International (Twilio, Nexmo, etc.)',
    'local' => 'Local Provider (Bangladesh)',
    'custom_api' => 'Custom API'
];

// Predefined gateway templates
$gateway_templates = [
    'twilio' => [
        'name' => 'Twilio',
        'fields' => ['api_key' => 'Account SID', 'api_secret' => 'Auth Token', 'sender_id' => 'From Number'],
        'help' => 'Get your credentials from https://console.twilio.com'
    ],
    'nexmo' => [
        'name' => 'Nexmo (Vonage)',
        'fields' => ['api_key' => 'API Key', 'api_secret' => 'API Secret', 'sender_id' => 'From'],
        'help' => 'Get your credentials from https://dashboard.nexmo.com'
    ],
    'banglalink' => [
        'name' => 'Banglalink API',
        'fields' => ['username' => 'Username', 'password' => 'Password', 'api_key' => 'API Key'],
        'help' => 'Contact Banglalink for API access'
    ],
    'sslwireless' => [
        'name' => 'SSL Wireless',
        'fields' => ['api_key' => 'API Token', 'sender_id' => 'SID', 'api_endpoint' => 'API URL'],
        'help' => 'Contact SSL Wireless for credentials'
    ],
    'grameenphone' => [
        'name' => 'Grameenphone',
        'fields' => ['username' => 'Username', 'password' => 'Password', 'api_key' => 'API Key'],
        'help' => 'Contact Grameenphone for API access'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Gateway Management - Admin Panel</title>
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
        .default-badge {
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
                    <a href="payment_gateways.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-credit-card"></i> Payment Gateways
                    </a>
                    <a href="sms_gateways.php" class="list-group-item list-group-item-action active">
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
                    <h2><i class="bi bi-chat-dots me-2"></i>SMS Gateway Management</h2>
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
                    <strong>Configure SMS Gateways</strong> to send OTP codes and notifications to users. 
                    Set one gateway as <strong>Default</strong> for automatic OTP sending. Supported providers include Twilio, Nexmo, and local Bangladesh providers.
                </div>

                <?php if ($edit_gateway): ?>
                <!-- Edit Gateway Form -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit SMS Gateway: <?php echo htmlspecialchars($edit_gateway['display_name']); ?></h5>
                        <a href="sms_gateways.php" class="btn btn-sm btn-outline-secondary">
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
                                    <label class="form-label">Rate per SMS</label>
                                    <div class="input-group">
                                        <span class="input-group-text">৳</span>
                                        <input type="number" class="form-control" name="rate_per_sms" step="0.0001" min="0"
                                               value="<?php echo $edit_gateway['rate_per_sms']; ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- API Credentials -->
                            <div class="config-section">
                                <h6><i class="bi bi-key me-2"></i>API Credentials</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">API Key / Account SID</label>
                                        <input type="text" class="form-control" name="api_key"
                                               value="<?php echo htmlspecialchars($edit_gateway['api_key'] ?? ''); ?>"
                                               placeholder="Enter API Key">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">API Secret / Auth Token</label>
                                        <input type="password" class="form-control" name="api_secret"
                                               value="<?php echo htmlspecialchars($edit_gateway['api_secret'] ?? ''); ?>"
                                               placeholder="Enter API Secret">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Sender ID / From Number</label>
                                        <input type="text" class="form-control" name="sender_id"
                                               value="<?php echo htmlspecialchars($edit_gateway['sender_id'] ?? ''); ?>"
                                               placeholder="e.g., +8801XXXXXXXXX or YourBrand">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">API Endpoint URL (if custom)</label>
                                        <input type="url" class="form-control" name="api_endpoint"
                                               value="<?php echo htmlspecialchars($edit_gateway['api_endpoint'] ?? ''); ?>"
                                               placeholder="https://api.example.com/send">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username (if required)</label>
                                        <input type="text" class="form-control" name="username"
                                               value="<?php echo htmlspecialchars($edit_gateway['username'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password (if required)</label>
                                        <input type="password" class="form-control" name="password"
                                               value="<?php echo htmlspecialchars($edit_gateway['password'] ?? ''); ?>">
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
                                <input class="form-check-input" type="checkbox" name="is_default" id="is_default" 
                                       <?php echo $edit_gateway['is_default'] ? 'checked' : ''; ?>
                                       <?php echo $edit_gateway['status'] === 'disabled' ? 'disabled' : ''; ?>>
                                <label class="form-check-label" for="is_default">
                                    <strong>Set as Default Gateway</strong> for OTP sending
                                </label>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Save Changes
                                </button>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#testModal<?php echo $edit_gateway['id']; ?>">
                                    <i class="bi bi-send"></i> Test SMS
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
                                    <?php if ($gateway['is_default']): ?>
                                        <span class="badge bg-success default-badge">
                                            <i class="bi bi-check-circle"></i> Default
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <i class="bi bi-chat-dots-fill" style="font-size: 2rem; color: <?php echo $gateway['status'] === 'active' ? '#198754' : '#6c757d'; ?>"></i>
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
                                            <strong>Rate:</strong> ৳<?php echo number_format($gateway['rate_per_sms'], 4); ?> per SMS
                                        </small>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="?edit=<?php echo $gateway['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i> Configure
                                            </a>
                                            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#testModal<?php echo $gateway['id']; ?>" title="Test">
                                                <i class="bi bi-send"></i> Test
                                            </button>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$gateway['is_default'] && $gateway['status'] === 'active'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="set_default">
                                                    <input type="hidden" name="gateway_id" value="<?php echo $gateway['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-success" title="Set as Default">
                                                        <i class="bi bi-check-circle"></i> Set Default
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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

                        <!-- Test SMS Modal -->
                        <div class="modal fade" id="testModal<?php echo $gateway['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Test <?php echo htmlspecialchars($gateway['display_name']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="test">
                                            <input type="hidden" name="gateway_id" value="<?php echo $gateway['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Test Phone Number</label>
                                                <input type="tel" class="form-control" name="test_number" required
                                                       placeholder="01XXXXXXXXX or +8801XXXXXXXXX">
                                                <small class="text-muted">Enter a valid Bangladesh mobile number</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Test Message</label>
                                                <textarea class="form-control" name="test_message" rows="3">This is a test message from WiFi Portal.</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-send"></i> Send Test SMS
                                            </button>
                                        </div>
                                    </form>
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
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#twilioGuide">
                                        <i class="bi bi-chat-dots me-2"></i> Twilio Setup
                                    </button>
                                </h2>
                                <div id="twilioGuide" class="accordion-collapse collapse" data-bs-parent="#gatewayGuides">
                                    <div class="accordion-body">
                                        <ol>
                                            <li>Create account at <a href="https://www.twilio.com" target="_blank">twilio.com</a></li>
                                            <li>Get your Account SID and Auth Token from the console</li>
                                            <li>Buy a phone number or verify your caller ID</li>
                                            <li>Enter these credentials in the Twilio gateway configuration</li>
                                            <li>Set the "From Number" to your Twilio phone number</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#localGuide">
                                        <i class="bi bi-phone me-2"></i> Bangladesh Local Providers
                                    </button>
                                </h2>
                                <div id="localGuide" class="accordion-collapse collapse" data-bs-parent="#gatewayGuides">
                                    <div class="accordion-body">
                                        <p>For Bangladesh mobile operators (Banglalink, Grameenphone, Robi, etc.):</p>
                                        <ol>
                                            <li>Contact your mobile operator's enterprise sales team</li>
                                            <li>Request Bulk SMS API access</li>
                                            <li>They will provide: API Key, Sender ID (Masking name)</li>
                                            <li>Some providers require IP whitelisting</li>
                                            <li>Enter the provided credentials in the respective gateway</li>
                                        </ol>
                                        <div class="alert alert-info mt-3">
                                            <strong>Popular Providers:</strong> SSL Wireless, Banglalink API, Grameenphone API, Robi/Airtel API
                                        </div>
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
