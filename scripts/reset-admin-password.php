<?php
/**
 * Quick Admin Password Reset Script
 * Run: php scripts/reset-admin-password.php
 * 
 * This script resets the admin password without interactive prompts
 */

require_once __DIR__ . '/../portal/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';
require_once __DIR__ . '/../portal/includes/security.php';

echo "=== Wi-Fi Portal Admin Password Reset ===\n\n";

// Default credentials
$username = 'admin';
$password = 'admin123';
$email = 'admin@example.com';

echo "Resetting admin user...\n";
echo "Username: $username\n";
echo "Password: $password\n";
echo "Email: $email\n\n";

try {
    $pdo = getDBConnection();
    
    // Ensure all tables and columns exist
    ensureDatabaseTables();
    
    // Check if admin_users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->rowCount() === 0) {
        // Create admin_users table
        $pdo->exec("CREATE TABLE admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100) NOT NULL,
            role ENUM('admin', 'reseller') DEFAULT 'admin',
            reseller_id INT DEFAULT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            last_login DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✓ Created admin_users table\n";
    }
    
    $password_hash = hashPassword($password);
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing admin
        $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ?, email = ?, role = 'admin', status = 'active', reseller_id = NULL WHERE username = ?");
        $stmt->execute([$password_hash, $email, $username]);
        echo "✓ Admin user '$username' password reset successfully!\n";
    } else {
        // Create new admin
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $stmt->execute([$username, $password_hash, $email]);
        echo "✓ Admin user '$username' created successfully!\n";
    }
    
    echo "\n========================================\n";
    echo "Login URL: http://your-server-ip/admin\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    echo "========================================\n\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
