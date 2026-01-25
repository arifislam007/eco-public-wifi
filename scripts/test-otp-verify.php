<?php
/**
 * Test OTP Verification
 * Usage: php scripts/test-otp-verify.php [mobile_number] [otp_code]
 */

require_once __DIR__ . '/../portal/config.php';
require_once __DIR__ . '/../portal/includes/auth.php';
require_once __DIR__ . '/../portal/includes/otp.php';

$mobile_number = $argv[1] ?? '01712345678';
$otp_code = $argv[2] ?? '';

if (empty($otp_code)) {
    echo "Usage: php test-otp-verify.php [mobile_number] [otp_code]\n";
    exit(1);
}

echo "=== Testing OTP Verification ===\n\n";
echo "Mobile Number: $mobile_number\n";
echo "OTP Code: $otp_code\n\n";

$result = verifyOTP($mobile_number, $otp_code);

if ($result['success']) {
    echo "✓ OTP verified successfully!\n";
    echo "  Username: " . $result['username'] . "\n";
    echo "  Message: " . $result['message'] . "\n";
} else {
    echo "✗ Verification failed: " . $result['message'] . "\n";
    exit(1);
}
