<?php
/**
 * Test OTP Generation
 * Usage: php scripts/test-otp.php [mobile_number]
 */

require_once __DIR__ . '/../portal/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';
require_once __DIR__ . '/../portal/includes/otp.php';

$mobile_number = $argv[1] ?? '01712345678';

echo "=== Testing OTP Generation ===\n\n";
echo "Mobile Number: $mobile_number\n\n";

$result = generateAndSendOTP($mobile_number);

if ($result['success']) {
    echo "✓ OTP generated successfully!\n";
    echo "  OTP ID: " . $result['otp_id'] . "\n\n";
    
    // Get the OTP code from database
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT otp_code FROM otp_codes WHERE id = ?");
        $stmt->execute([$result['otp_id']]);
        $otp = $stmt->fetchColumn();
        
        echo "OTP Code: $otp\n";
        echo "Expires in: 5 minutes\n\n";
        
        echo "To verify, use:\n";
        echo "  php scripts/test-otp-verify.php $mobile_number $otp\n";
        
    } catch (PDOException $e) {
        echo "Error retrieving OTP: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ Failed: " . $result['message'] . "\n";
    exit(1);
}
