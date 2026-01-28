<?php
/**
 * NAS (Network Access Server) Management
 * Manage RADIUS clients (routers/APs) from the admin panel
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
        $nasname = trim($_POST['nasname'] ?? '');
        $shortname = trim($_POST['shortname'] ?? '');
        $type = $_POST['type'] ?? 'other';
        $ports = !empty($_POST['ports']) ? intval($_POST['ports']) : null;
        $secret = trim($_POST['secret'] ?? '');
        $server = trim($_POST['server'] ?? '') ?: null;
        $community = trim($_POST['community'] ?? '') ?: null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        if (empty($nasname)) {
            $error = 'NAS IP address or hostname is required.';
        } elseif (empty($secret)) {
            $error = 'Shared secret is required.';
        } elseif (strlen($secret) < 8) {
            $error = 'Shared secret must be at least 8 characters.';
        } else {
            // Validate IP address or hostname
            if (!filter_var($nasname, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+$/', $nasname)) {
                $error = 'Invalid IP address or hostname format.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO nas (nasname, shortname, type, ports, secret, server, community, description, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $nasname, $shortname ?: null, $type, $ports, $secret, 
                        $server, $community, $description, $status, $_SESSION['admin_id']
                    ]);
                    $message = "NAS '$nasname' created successfully!";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $error = "A NAS with IP/hostname '$nasname' already exists.";
                    } else {
                        $error = "Error creating NAS: " . $e->getMessage();
                    }
                }
            }
        }
    } elseif ($action === 'update') {
        $nas_id = intval($_POST['nas_id'] ?? 0);
        $nasname = trim($_POST['nasname'] ?? '');
        $shortname = trim($_POST['shortname'] ?? '');
        $type = $_POST['type'] ?? 'other';
        $ports = !empty($_POST['ports']) ? intval($_POST['ports']) : null;
        $secret = trim($_POST['secret'] ?? '');
        $server = trim($_POST['server'] ?? '') ?: null;
        $community = trim($_POST['community'] ?? '') ?: null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if ($nas_id > 0 && !empty($nasname) && !empty($secret)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE nas SET 
                        nasname = ?, shortname = ?, type = ?, ports = ?, 
                        secret = ?, server = ?, community = ?, description = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nasname, $shortname ?: null, $type, $ports, 
                    $secret, $server, $community, $description, $status, $nas_id
                ]);
                $message = "NAS updated successfully!";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error = "A NAS with IP/hostname '$nasname' already exists.";
                } else {
                    $error = "Error updating NAS: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete') {
        $nas_id = intval($_POST['nas_id'] ?? 0);
        if ($nas_id > 0) {
            try {
                $pdo->prepare("DELETE FROM nas WHERE id = ?")->execute([$nas_id]);
                $message = "NAS deleted successfully!";
            } catch (PDOException $e) {
                $error = "Error deleting NAS: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $nas_id = intval($_POST['nas_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'active';
        if ($nas_id > 0) {
            try {
                $pdo->prepare("UPDATE nas SET status = ? WHERE id = ?")->execute([$new_status, $nas_id]);
                $message = "NAS status updated!";
            } catch (PDOException $e) {
                $error = "Error updating NAS status: " . $e->getMessage();
            }
        }
    }
}

// Check if NAS table exists, create if not
$table_exists = false;
try {
    $check = $pdo->query("SELECT 1 FROM nas LIMIT 1");
    $table_exists = true;
} catch (PDOException $e) {
    // Table doesn't exist, create it
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nas (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                nasname varchar(128) NOT NULL COMMENT 'IP address or hostname',
                shortname varchar(32) DEFAULT NULL COMMENT 'Short name/alias',
                type varchar(30) DEFAULT 'other' COMMENT 'NAS type (mikrotik, cisco, other)',
                ports int(5) DEFAULT NULL COMMENT 'Number of ports',
                secret varchar(60) NOT NULL DEFAULT 'secret' COMMENT 'Shared secret',
                server varchar(64) DEFAULT NULL COMMENT 'Virtual server name',
                community varchar(50) DEFAULT NULL COMMENT 'SNMP community',
                description varchar(200) DEFAULT NULL COMMENT 'Description',
                status enum('active', 'disabled') DEFAULT 'active',
                created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by int(11) DEFAULT NULL COMMENT 'Admin user ID',
                PRIMARY KEY (id),
                UNIQUE KEY nasname (nasname),
                KEY shortname (shortname),
                KEY status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insert default localhost entry
        $pdo->exec("
            INSERT INTO nas (nasname, shortname, type, secret, description, status) VALUES
            ('127.0.0.1', 'localhost', 'other', 'testing123', 'Local testing client', 'active')
            ON DUPLICATE KEY UPDATE nasname=nasname
        ");
        
        $table_exists = true;
        $message = "NAS table created successfully!";
    } catch (PDOException $create_error) {
        $error = "Error creating NAS table: " . $create_error->getMessage();
    }
}

// Get all NAS entries
$nas_list = [];
if ($table_exists) {
    try {
        $nas_list = $pdo->query("
            SELECT n.*, 
                   a.username as created_by_name,
                   (SELECT COUNT(*) FROM radacct WHERE nasipaddress = n.nasname AND acctstoptime IS NULL) as active_sessions
            FROM nas n
            LEFT JOIN admin_users a ON n.created_by = a.id
            ORDER BY n.status DESC, n.shortname ASC, n.nasname ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error loading NAS list: " . $e->getMessage();
    }
}

// Get NAS for editing if requested
$edit_nas = null;
if ($table_exists && isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $edit_stmt = $pdo->prepare("SELECT * FROM nas WHERE id = ?");
        $edit_stmt->execute([$_GET['edit']]);
        $edit_nas = $edit_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Ignore edit errors
    }
}

// NAS types for dropdown
$nas_types = [
    'other' => 'Other',
    'mikrotik' => 'MikroTik',
    'cisco' => 'Cisco',
    'juniper' => 'Juniper',
    'livingston' => 'Livingston',
    'computone' => 'Computone',
    'max40xx' => 'Max40xx',
    'multitech' => 'Multitech',
    'netserver' => 'Netserver',
    'pathras' => 'Pathras',
    'patton' => 'Patton',
    'portslave' => 'Portslave',
    'tc' => 'TC',
    'usrhiper' => 'USR Hiper',
    'unifi' => 'UniFi',
    'openwrt' => 'OpenWRT',
    'pfsense' => 'pfSense',
    'opnsense' => 'OPNsense'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAS Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .nas-card {
            transition: all 0.3s ease;
        }
        .nas-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .nas-disabled {
            opacity: 0.6;
        }
        .secret-field {
            font-family: monospace;
        }
        .copy-btn {
            cursor: pointer;
        }
        .copy-btn:hover {
            color: #0d6efd;
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
                    <a href="nas.php" class="list-group-item list-group-item-action active">
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
                    <h2><i class="bi bi-router me-2"></i>NAS / Router Management</h2>
                    <span class="badge bg-primary"><?php echo count($nas_list); ?> NAS Configured</span>
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
                    <strong>Note:</strong> NAS (Network Access Server) entries define which routers/access points can authenticate users through this RADIUS server. 
                    Each NAS must have a unique IP address and a shared secret that matches the router's RADIUS configuration.
                    <br><small class="text-muted">After adding a NAS here, you may need to restart FreeRADIUS for changes to take effect if not using dynamic clients.</small>
                </div>

                <!-- Create/Edit NAS Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><?php echo $edit_nas ? 'Edit NAS' : 'Add New NAS / Router'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_nas ? 'update' : 'create'; ?>">
                            <?php if ($edit_nas): ?>
                                <input type="hidden" name="nas_id" value="<?php echo $edit_nas['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">IP Address / Hostname <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nasname" required
                                           value="<?php echo $edit_nas ? htmlspecialchars($edit_nas['nasname']) : ''; ?>"
                                           placeholder="192.168.1.1 or router.example.com">
                                    <small class="text-muted">The IP address or hostname of the router/AP</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Short Name</label>
                                    <input type="text" class="form-control" name="shortname"
                                           value="<?php echo $edit_nas ? htmlspecialchars($edit_nas['shortname'] ?? '') : ''; ?>"
                                           placeholder="main-router">
                                    <small class="text-muted">A friendly name for identification</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">NAS Type</label>
                                    <select class="form-select" name="type">
                                        <?php foreach ($nas_types as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" 
                                                <?php echo ($edit_nas && $edit_nas['type'] === $value) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Shared Secret <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control secret-field" name="secret" required
                                               id="secretField"
                                               value="<?php echo $edit_nas ? htmlspecialchars($edit_nas['secret']) : ''; ?>"
                                               placeholder="Enter shared secret (min 8 chars)">
                                        <button type="button" class="btn btn-outline-secondary" onclick="generateSecret()">
                                            <i class="bi bi-shuffle"></i> Generate
                                        </button>
                                    </div>
                                    <small class="text-muted">Must match the secret configured on the router</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Ports</label>
                                    <input type="number" class="form-control" name="ports" min="0"
                                           value="<?php echo $edit_nas ? htmlspecialchars($edit_nas['ports'] ?? '') : ''; ?>"
                                           placeholder="Number of ports (optional)">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?php echo (!$edit_nas || $edit_nas['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="disabled" <?php echo ($edit_nas && $edit_nas['status'] === 'disabled') ? 'selected' : ''; ?>>Disabled</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Virtual Server</label>
                                    <input type="text" class="form-control" name="server"
                                           value="<?php echo $edit_nas ? htmlspecialchars($edit_nas['server'] ?? '') : ''; ?>"
                                           placeholder="default (optional)">
                                    <small class="text-muted">FreeRADIUS virtual server name</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">SNMP Community</label>
                                    <input type="text" class="form-control" name="community"
                                           value="<?php echo $edit_nas ? htmlspecialchars($edit_nas['community'] ?? '') : ''; ?>"
                                           placeholder="public (optional)">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="description"
                                           value="<?php echo $edit_nas ? htmlspecialchars($edit_nas['description'] ?? '') : ''; ?>"
                                           placeholder="Main office router">
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?php echo $edit_nas ? 'check-lg' : 'plus-circle'; ?>"></i> 
                                    <?php echo $edit_nas ? 'Update NAS' : 'Add NAS'; ?>
                                </button>
                                <?php if ($edit_nas): ?>
                                    <a href="nas.php" class="btn btn-secondary">
                                        <i class="bi bi-x-lg"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- NAS List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Configured NAS / Routers</h5>
                        <div>
                            <span class="badge bg-success me-2"><?php echo count(array_filter($nas_list, fn($n) => $n['status'] === 'active')); ?> Active</span>
                            <span class="badge bg-secondary"><?php echo count(array_filter($nas_list, fn($n) => $n['status'] === 'disabled')); ?> Disabled</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($nas_list)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-router text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">No NAS configured yet. Add your first router above.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>IP / Hostname</th>
                                            <th>Short Name</th>
                                            <th>Type</th>
                                            <th>Secret</th>
                                            <th>Active Sessions</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($nas_list as $nas): ?>
                                            <tr class="<?php echo $nas['status'] === 'disabled' ? 'nas-disabled' : ''; ?>">
                                                <td>
                                                    <?php if ($nas['status'] === 'active'): ?>
                                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><i class="bi bi-x-circle"></i> Disabled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($nas['nasname']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($nas['shortname'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($nas_types[$nas['type']] ?? $nas['type']); ?></span>
                                                </td>
                                                <td>
                                                    <code class="secret-display" id="secret-<?php echo $nas['id']; ?>">••••••••</code>
                                                    <i class="bi bi-eye copy-btn ms-1" onclick="toggleSecret(<?php echo $nas['id']; ?>, '<?php echo htmlspecialchars($nas['secret']); ?>')" title="Show/Hide"></i>
                                                    <i class="bi bi-clipboard copy-btn ms-1" onclick="copySecret('<?php echo htmlspecialchars($nas['secret']); ?>')" title="Copy"></i>
                                                </td>
                                                <td>
                                                    <?php if ($nas['active_sessions'] > 0): ?>
                                                        <span class="badge bg-success"><?php echo $nas['active_sessions']; ?> online</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($nas['description'] ?? '-'); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?edit=<?php echo $nas['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="nas_id" value="<?php echo $nas['id']; ?>">
                                                            <input type="hidden" name="new_status" value="<?php echo $nas['status'] === 'active' ? 'disabled' : 'active'; ?>">
                                                            <button type="submit" class="btn btn-outline-<?php echo $nas['status'] === 'active' ? 'warning' : 'success'; ?>" 
                                                                    title="<?php echo $nas['status'] === 'active' ? 'Disable' : 'Enable'; ?>">
                                                                <i class="bi bi-<?php echo $nas['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this NAS? This action cannot be undone.');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="nas_id" value="<?php echo $nas['id']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger" title="Delete">
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

                <!-- Router Configuration Help -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="bi bi-question-circle me-2"></i>Router Configuration Guide</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="routerGuide">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mikrotikGuide">
                                        <i class="bi bi-router me-2"></i> MikroTik Configuration
                                    </button>
                                </h2>
                                <div id="mikrotikGuide" class="accordion-collapse collapse" data-bs-parent="#routerGuide">
                                    <div class="accordion-body">
                                        <pre class="bg-dark text-light p-3 rounded"><code># Add RADIUS server
/radius add service=hotspot address=<?php echo $_SERVER['SERVER_ADDR'] ?? 'YOUR_RADIUS_SERVER_IP'; ?> secret=YOUR_SHARED_SECRET

# Configure Hotspot to use RADIUS
/ip hotspot profile set [find] use-radius=yes</code></pre>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#openwrtGuide">
                                        <i class="bi bi-router me-2"></i> OpenWRT Configuration
                                    </button>
                                </h2>
                                <div id="openwrtGuide" class="accordion-collapse collapse" data-bs-parent="#routerGuide">
                                    <div class="accordion-body">
                                        <pre class="bg-dark text-light p-3 rounded"><code># Edit /etc/config/radius
config radius 'auth'
    option server '<?php echo $_SERVER['SERVER_ADDR'] ?? 'YOUR_RADIUS_SERVER_IP'; ?>'
    option port '1812'
    option secret 'YOUR_SHARED_SECRET'</code></pre>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#unifiGuide">
                                        <i class="bi bi-router me-2"></i> UniFi Controller Configuration
                                    </button>
                                </h2>
                                <div id="unifiGuide" class="accordion-collapse collapse" data-bs-parent="#routerGuide">
                                    <div class="accordion-body">
                                        <ol>
                                            <li>Go to Settings → Profiles → RADIUS</li>
                                            <li>Create a new RADIUS profile</li>
                                            <li>Set Authentication Server: <code><?php echo $_SERVER['SERVER_ADDR'] ?? 'YOUR_RADIUS_SERVER_IP'; ?></code></li>
                                            <li>Set Port: <code>1812</code></li>
                                            <li>Set Shared Secret: <code>YOUR_SHARED_SECRET</code></li>
                                            <li>Apply the profile to your Guest Network</li>
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
    <script>
        // Generate random secret
        function generateSecret() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
            let secret = '';
            for (let i = 0; i < 16; i++) {
                secret += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('secretField').value = secret;
        }

        // Toggle secret visibility
        function toggleSecret(id, secret) {
            const elem = document.getElementById('secret-' + id);
            if (elem.textContent === '••••••••') {
                elem.textContent = secret;
            } else {
                elem.textContent = '••••••••';
            }
        }

        // Copy secret to clipboard
        function copySecret(secret) {
            navigator.clipboard.writeText(secret).then(() => {
                // Show toast or alert
                const toast = document.createElement('div');
                toast.className = 'position-fixed bottom-0 end-0 p-3';
                toast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-body">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Secret copied to clipboard!
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            });
        }
    </script>
</body>
</html>
