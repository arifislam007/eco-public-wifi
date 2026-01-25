<?php
/**
 * CLI Script - Create Voucher
 * Usage: php scripts/create-voucher.php <code> <type> [time_limit] [data_limit_mb]
 * Example: php create-voucher.php VOUCHER001 time 3600 1024
 */

if ($argc < 3) {
    echo "Usage: php create-voucher.php <code> <type> [time_limit] [data_limit_mb]\n";
    echo "Types: time, data, unlimited\n";
    echo "Example: php create-voucher.php VOUCHER001 time 3600 1024\n";
    exit(1);
}

require_once __DIR__ . '/../portal/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';
require_once __DIR__ . '/../portal/includes/voucher.php';

$voucher_code = sanitizeInput($argv[1]);
$voucher_type = $argv[2];
$time_limit = isset($argv[3]) ? intval($argv[3]) : null;
$data_limit = isset($argv[4]) ? intval($argv[4]) * 1024 * 1024 : null;

try {
    $pdo = getDBConnection();
    
    // Generate username and password
    $username = 'voucher_' . $voucher_code;
    $password = bin2hex(random_bytes(8));
    
    $pdo->beginTransaction();
    
    // Create voucher
    $stmt = $pdo->prepare("
        INSERT INTO vouchers (voucher_code, username, password, voucher_type, time_limit, data_limit, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$voucher_code, $username, $password, $voucher_type, $time_limit, $data_limit]);
    
    // Create user
    $user_stmt = $pdo->prepare("
        INSERT INTO radcheck (username, attribute, op, value) 
        VALUES (?, 'Cleartext-Password', ':=', ?)
    ");
    $user_stmt->execute([$username, $password]);
    
    // Add to voucher group
    $group_stmt = $pdo->prepare("
        INSERT INTO radusergroup (username, groupname, priority) 
        VALUES (?, 'voucher', 1)
    ");
    $group_stmt->execute([$username]);
    
    $pdo->commit();
    
    echo "✓ Voucher created successfully!\n";
    echo "  Code: $voucher_code\n";
    echo "  Type: $voucher_type\n";
    if ($time_limit) {
        echo "  Time Limit: " . gmdate('H:i:s', $time_limit) . "\n";
    }
    if ($data_limit) {
        echo "  Data Limit: " . number_format($data_limit / 1024 / 1024, 0) . " MB\n";
    }
    echo "  Username: $username\n";
    echo "  Password: $password\n";
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
