<?php
/**
 * OTP/SMS Authentication Functions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

/**
 * Generate and send OTP to mobile number
 * 
 * @param string $mobile_number
 * @param string $username Optional username for existing users
 * @return array ['success' => bool, 'message' => string, 'otp_id' => int|null]
 */
function generateAndSendOTP($mobile_number, $username = null) {
    try {
        $pdo = getDBConnection();
        
        // Validate mobile number (Bangladesh format: +880 or 01XXXXXXXXX)
        $mobile_number = normalizeMobileNumber($mobile_number);
        if (!$mobile_number) {
            return ['success' => false, 'message' => 'Invalid mobile number format'];
        }
        
        // Generate 6-digit OTP
        $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Set expiry (5 minutes)
        $expires_at = date('Y-m-d H:i:s', time() + 300);
        
        // Insert OTP record
        $stmt = $pdo->prepare("
            INSERT INTO otp_codes (mobile_number, otp_code, username, expires_at) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$mobile_number, $otp_code, $username, $expires_at]);
        $otp_id = $pdo->lastInsertId();
        
        // Send SMS (implement based on your SMS provider)
        $sms_result = sendSMS($mobile_number, $otp_code);
        
        // Log SMS
        $log_stmt = $pdo->prepare("
            INSERT INTO sms_logs (mobile_number, otp_code, message, status, provider) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $log_stmt->execute([
            $mobile_number, 
            $otp_code, 
            "Your OTP code is: $otp_code",
            $sms_result['success'] ? 'sent' : 'failed',
            $sms_result['provider'] ?? 'default'
        ]);
        
        if ($sms_result['success']) {
            return [
                'success' => true, 
                'message' => 'OTP sent successfully',
                'otp_id' => $otp_id
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Failed to send OTP: ' . ($sms_result['message'] ?? 'Unknown error')
            ];
        }
        
    } catch (PDOException $e) {
        error_log("OTP Generation Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error generating OTP'];
    }
}

/**
 * Verify OTP code
 * 
 * @param string $mobile_number
 * @param string $otp_code
 * @return array ['success' => bool, 'message' => string, 'username' => string|null]
 */
function verifyOTP($mobile_number, $otp_code) {
    try {
        $pdo = getDBConnection();
        
        $mobile_number = normalizeMobileNumber($mobile_number);
        
        // Find valid OTP
        $stmt = $pdo->prepare("
            SELECT id, username, expires_at, verified 
            FROM otp_codes 
            WHERE mobile_number = ? 
            AND otp_code = ? 
            AND verified = 0 
            AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$mobile_number, $otp_code]);
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$otp_record) {
            return ['success' => false, 'message' => 'Invalid or expired OTP'];
        }
        
        // Mark OTP as verified
        $update_stmt = $pdo->prepare("
            UPDATE otp_codes 
            SET verified = 1, verified_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->execute([$otp_record['id']]);
        
        // Get or create username
        $username = $otp_record['username'];
        if (!$username) {
            // Check if mobile number is already registered
            $user_stmt = $pdo->prepare("SELECT username FROM mobile_users WHERE mobile_number = ?");
            $user_stmt->execute([$mobile_number]);
            $mobile_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mobile_user) {
                $username = $mobile_user['username'];
            } else {
                // Create new user with mobile number as username
                $username = 'mobile_' . preg_replace('/[^0-9]/', '', $mobile_number);
                
                // Check if username exists, if so append random number
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM radcheck WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->fetchColumn() > 0) {
                    $username .= '_' . rand(1000, 9999);
                }
                
                // Create user in radcheck
                $create_stmt = $pdo->prepare("
                    INSERT INTO radcheck (username, attribute, op, value) 
                    VALUES (?, 'Cleartext-Password', ':=', ?)
                ");
                $random_password = bin2hex(random_bytes(16));
                $create_stmt->execute([$username, $random_password]);
                
                // Link mobile number to username
                $link_stmt = $pdo->prepare("
                    INSERT INTO mobile_users (mobile_number, username, verified) 
                    VALUES (?, ?, 1)
                ");
                $link_stmt->execute([$mobile_number, $username]);
            }
        }
        
        // Update mobile_users last login
        $update_user_stmt = $pdo->prepare("
            UPDATE mobile_users 
            SET last_login = NOW(), verified = 1 
            WHERE mobile_number = ?
        ");
        $update_user_stmt->execute([$mobile_number]);
        
        return [
            'success' => true, 
            'message' => 'OTP verified successfully',
            'username' => $username
        ];
        
    } catch (PDOException $e) {
        error_log("OTP Verification Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error verifying OTP'];
    }
}

/**
 * Normalize mobile number to standard format
 * 
 * @param string $mobile_number
 * @return string|false Normalized number or false if invalid
 */
function normalizeMobileNumber($mobile_number) {
    // Remove all non-digit characters
    $number = preg_replace('/[^0-9]/', '', $mobile_number);
    
    // Bangladesh mobile number patterns
    // +8801XXXXXXXXX or 01XXXXXXXXX or 1XXXXXXXXX
    if (preg_match('/^8801[0-9]{9}$/', $number)) {
        return '+880' . substr($number, 3);
    } elseif (preg_match('/^01[0-9]{9}$/', $number)) {
        return '+880' . substr($number, 1);
    } elseif (preg_match('/^1[0-9]{9}$/', $number)) {
        return '+880' . $number;
    }
    
    return false;
}

/**
 * Send SMS via provider
 * This is a template - implement based on your SMS gateway
 * 
 * @param string $mobile_number
 * @param string $otp_code
 * @return array ['success' => bool, 'message' => string, 'provider' => string]
 */
function sendSMS($mobile_number, $otp_code) {
    // TODO: Implement SMS gateway integration
    // Examples: Twilio, Nexmo, local SMS gateway, etc.
    
    // For now, log and return success (for testing)
    error_log("SMS would be sent to $mobile_number with OTP: $otp_code");
    
    // Example implementation structure:
    /*
    $sms_provider = getenv('SMS_PROVIDER') ?: 'default';
    
    switch ($sms_provider) {
        case 'twilio':
            return sendSMSViaTwilio($mobile_number, $otp_code);
        case 'nexmo':
            return sendSMSViaNexmo($mobile_number, $otp_code);
        default:
            // Use local gateway or API
            return sendSMSViaLocalGateway($mobile_number, $otp_code);
    }
    */
    
    return [
        'success' => true,
        'message' => 'OTP sent (simulated)',
        'provider' => 'simulated'
    ];
}

/**
 * Clean expired OTPs
 */
function cleanExpiredOTPs() {
    try {
        $pdo = getDBConnection();
        $pdo->exec("DELETE FROM otp_codes WHERE expires_at < NOW() AND verified = 0");
    } catch (PDOException $e) {
        error_log("Error cleaning expired OTPs: " . $e->getMessage());
    }
}
