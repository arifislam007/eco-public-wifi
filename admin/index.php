<?php
/**
 * Admin Panel - Login
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/../portal/includes/auth.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $username = sanitizeInput($username);
        
        try {
            $pdo = getDBConnection();
            
            // Check if the new columns exist
            $columns_exist = true;
            try {
                $pdo->query("SELECT role, status FROM admin_users LIMIT 1");
            } catch (PDOException $e) {
                $columns_exist = false;
            }
            
            if ($columns_exist) {
                $stmt = $pdo->prepare("SELECT id, username, password_hash, role, reseller_id, status FROM admin_users WHERE username = ?");
            } else {
                $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
            }
            
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && verifyPassword($password, $admin['password_hash'])) {
                // Check if account is active (if status column exists)
                if ($columns_exist && isset($admin['status']) && $admin['status'] !== 'active') {
                    $error = 'Your account is inactive. Please contact administrator.';
                } else {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    // Set role and reseller_id if columns exist
                    if ($columns_exist) {
                        $_SESSION['admin_role'] = $admin['role'] ?? 'admin';
                        $_SESSION['reseller_id'] = $admin['reseller_id'] ?? null;
                    } else {
                        $_SESSION['admin_role'] = 'admin';
                        $_SESSION['reseller_id'] = null;
                    }
                    
                    // Update last login
                    $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
                    
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login error. Please try again.';
            error_log("Admin Login Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Wi-Fi Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
                            <h3 class="mt-3">Admin Login</h3>
                            <p class="text-muted">Wi-Fi Portal Management</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
