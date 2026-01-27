<?php
/**
 * Test Admin Login - Debug Script
 * Run this to test database connection and login functionality
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';

echo "=== Admin Login Test ===\n\n";

try {
    $pdo = getDBConnection();
    echo "✓ Database connection successful\n";
    
    // Check admin_users table
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->rowCount() === 0) {
        echo "✗ admin_users table does not exist!\n";
        exit;
    }
    echo "✓ admin_users table exists\n";
    
    // Check columns
    $stmt = $pdo->query("DESCRIBE admin_users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Columns: " . implode(', ', $columns) . "\n";
    
    // Check for admin user
    $stmt = $pdo->query("SELECT * FROM admin_users WHERE username = 'admin'");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "\n✓ Admin user found:\n";
        echo "  ID: " . $admin['id'] . "\n";
        echo "  Username: " . $admin['username'] . "\n";
        echo "  Email: " . ($admin['email'] ?? 'N/A') . "\n";
        echo "  Role: " . ($admin['role'] ?? 'N/A') . "\n";
        echo "  Status: " . ($admin['status'] ?? 'N/A') . "\n";
        echo "  Password Hash: " . substr($admin['password_hash'], 0, 20) . "...\n";
        
        // Test password verification
        $test_password = 'admin123';
        if (verifyPassword($test_password, $admin['password_hash'])) {
            echo "\n✓ Password 'admin123' matches!\n";
        } else {
            echo "\n✗ Password 'admin123' does NOT match!\n";
            echo "  The password hash may be different.\n";
        }
    } else {
        echo "\n✗ Admin user not found!\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
