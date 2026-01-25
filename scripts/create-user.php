<?php
/**
 * CLI Script - Create RADIUS User
 * Usage: php scripts/create-user.php username password [expiry_date] [session_timeout]
 */

if ($argc < 3) {
    echo "Usage: php create-user.php <username> <password> [expiry_date] [session_timeout]\n";
    echo "Example: php create-user.php voucher001 pass123 2024-12-31 3600\n";
    exit(1);
}

require_once __DIR__ . '/../portal/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';
require_once __DIR__ . '/../portal/includes/security.php';

$username = sanitizeInput($argv[1]);
$password = $argv[2];
$expiry_date = $argv[3] ?? null;
$session_timeout = isset($argv[4]) ? intval($argv[4]) : 3600;

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Insert into radcheck
    $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
    $stmt->execute([$username, $password]);
    
    // Add session timeout
    if ($session_timeout > 0) {
        $stmt = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Session-Timeout', ':=', ?)");
        $stmt->execute([$username, $session_timeout]);
    }
    
    // Add expiry date if provided
    if (!empty($expiry_date)) {
        $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Expiration', ':=', ?)");
        $stmt->execute([$username, $expiry_date]);
    }
    
    $pdo->commit();
    
    echo "✓ User '$username' created successfully!\n";
    echo "  Password: $password\n";
    if ($expiry_date) {
        echo "  Expiry: $expiry_date\n";
    }
    echo "  Session Timeout: " . ($session_timeout / 3600) . " hours\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
