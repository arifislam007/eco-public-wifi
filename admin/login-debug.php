<?php
/**
 * Admin Login Debug Script
 * This helps diagnose login issues
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/security.php';

$error = '';
$debug_info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $debug_info[] = "Username entered: $username";
    $debug_info[] = "Password length: " . strlen($password);
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $username = sanitizeInput($username);
        
        try {
            $pdo = getDBConnection();
            $debug_info[] = "Database connection successful";
            
            // Check if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
            if ($stmt->rowCount() === 0) {
                $error = "admin_users table does not exist!";
                $debug_info[] = "ERROR: admin_users table not found";
            } else {
                $debug_info[] = "admin_users table exists";
                
                // Get user
                $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = ?");
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($admin) {
                    $debug_info[] = "User found: " . $admin['username'];
                    $debug_info[] = "Stored hash: " . $admin['password_hash'];
                    
                    // Test password verification
                    $verify_result = password_verify($password, $admin['password_hash']);
                    $debug_info[] = "password_verify result: " . ($verify_result ? 'TRUE' : 'FALSE');
                    
                    // Test with a fresh hash
                    $fresh_hash = password_hash('admin123', PASSWORD_BCRYPT);
                    $debug_info[] = "Fresh hash for 'admin123': $fresh_hash";
                    $debug_info[] = "Test verify 'admin123' with fresh hash: " . (password_verify('admin123', $fresh_hash) ? 'TRUE' : 'FALSE');
                    
                    if ($verify_result) {
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        
                        $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
                        
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = 'Invalid username or password.';
                    }
                } else {
                    $error = 'Invalid username or password.';
                    $debug_info[] = "User not found in database";
                    
                    // List all users
                    $stmt = $pdo->query("SELECT username FROM admin_users");
                    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $debug_info[] = "Available users: " . implode(', ', $users);
                }
            }
        } catch (PDOException $e) {
            $error = 'Login error. Please try again.';
            $debug_info[] = "PDOException: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login Debug - Wi-Fi Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
                            <h3 class="mt-3">Admin Login (Debug Mode)</h3>
                            <p class="text-muted">Wi-Fi Portal Management</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required autofocus value="admin">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required value="admin123">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>

                        <?php if (!empty($debug_info)): ?>
                            <div class="mt-4">
                                <h5>Debug Information:</h5>
                                <pre class="bg-dark text-light p-3 rounded" style="font-size: 0.8rem;"><code><?php foreach ($debug_info as $info): echo htmlspecialchars($info) . "\n"; endforeach; ?></code></pre>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 text-center">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">Back to Normal Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
