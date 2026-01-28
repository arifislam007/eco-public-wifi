<?php
/**
 * Reset Admin Password Script
 * Run this to reset the admin password to a known value
 * 
 * Usage: php scripts/reset-admin.php
 */

require_once __DIR__ . '/../portal/config.php';

// New admin credentials
$username = 'admin';
$password = 'admin123';
$email = 'admin@example.com';

echo "=== Wi-Fi Portal Admin Password Reset ===\n\n";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Generate new password hash using bcrypt
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    echo "Generated password hash: $password_hash\n\n";
    
    // Check if admin_users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->rowCount() === 0) {
        echo "Creating admin_users table...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(64) NOT NULL,
            password_hash varchar(255) NOT NULL,
            email varchar(255) DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login timestamp NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "Table created.\n\n";
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing user
        $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ?, email = ? WHERE username = ?");
        $stmt->execute([$password_hash, $email, $username]);
        echo "✓ Admin user '$username' password updated successfully!\n";
    } else {
        // Create new user
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password_hash, $email]);
        echo "✓ Admin user '$username' created successfully!\n";
    }
    
    echo "\n========================================\n";
    echo "Username: $username\n";
    echo "Password: $password\n";
    echo "========================================\n";
    echo "\nYou can now login at: http://localhost/admin\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "1. Database container is running\n";
    echo "2. Database credentials in portal/config.php are correct\n";
    echo "3. Database 'radius' exists\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
