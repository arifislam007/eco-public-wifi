<?php
/**
 * Setup Script - Create/Update Admin User
 * Run: php scripts/setup-admin.php
 */

require_once __DIR__ . '/../portal/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';
require_once __DIR__ . '/../portal/includes/security.php';

echo "=== Wi-Fi Portal Admin Setup ===\n\n";

// Get admin credentials
echo "Enter admin username [admin]: ";
$username = trim(fgets(STDIN)) ?: 'admin';

echo "Enter admin password: ";
$password = trim(fgets(STDIN));

if (empty($password)) {
    echo "Error: Password cannot be empty!\n";
    exit(1);
}

echo "Enter admin email [admin@example.com]: ";
$email = trim(fgets(STDIN)) ?: 'admin@example.com';

try {
    $pdo = getDBConnection();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $existing = $stmt->fetch();
    
    $password_hash = hashPassword($password);
    
    // Ensure admin_users table has all required columns
    ensureDatabaseTables();
    
    if ($existing) {
        // Update existing user - also set role to admin
        $stmt = $pdo->prepare("UPDATE admin_users SET password_hash = ?, email = ?, role = 'admin', status = 'active' WHERE username = ?");
        $stmt->execute([$password_hash, $email, $username]);
        echo "✓ Admin user '$username' updated successfully!\n";
    } else {
        // Create new user with role and status
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email, role, status) VALUES (?, ?, ?, 'admin', 'active')");
        $stmt->execute([$username, $password_hash, $email]);
        echo "✓ Admin user '$username' created successfully!\n";
    }
    
    echo "\nYou can now login at: http://your-domain/admin\n";
    echo "Username: $username\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
