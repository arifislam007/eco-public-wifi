<?php
/**
 * Voucher Management Functions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

/**
 * Validate and activate voucher
 * 
 * @param string $voucher_code
 * @return array ['success' => bool, 'message' => string, 'voucher' => array|null]
 */
function validateVoucher($voucher_code) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT v.*, 
                   (SELECT COUNT(*) FROM active_sessions WHERE username = v.username) as active_sessions
            FROM vouchers v
            WHERE v.voucher_code = ?
            AND v.status = 'active'
        ");
        $stmt->execute([$voucher_code]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$voucher) {
            return ['success' => false, 'message' => 'Invalid or inactive voucher'];
        }
        
        // Check expiry
        if ($voucher['expires_at'] && strtotime($voucher['expires_at']) < time()) {
            // Mark as expired
            $pdo->prepare("UPDATE vouchers SET status = 'expired' WHERE id = ?")->execute([$voucher['id']]);
            return ['success' => false, 'message' => 'Voucher has expired'];
        }
        
        // Check concurrent sessions
        if ($voucher['max_sessions'] > 0 && $voucher['active_sessions'] >= $voucher['max_sessions']) {
            return ['success' => false, 'message' => 'Maximum concurrent sessions reached'];
        }
        
        // Check if already used (for single-use vouchers)
        if ($voucher['status'] === 'used') {
            return ['success' => false, 'message' => 'Voucher has already been used'];
        }
        
        return [
            'success' => true,
            'message' => 'Voucher is valid',
            'voucher' => $voucher
        ];
        
    } catch (PDOException $e) {
        error_log("Voucher Validation Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error validating voucher'];
    }
}

/**
 * Activate voucher and create user session
 * 
 * @param string $voucher_code
 * @return array ['success' => bool, 'message' => string, 'username' => string|null]
 */
function activateVoucher($voucher_code) {
    $validation = validateVoucher($voucher_code);
    
    if (!$validation['success']) {
        return $validation;
    }
    
    $voucher = $validation['voucher'];
    
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Mark voucher as activated if not already
        if (!$voucher['activated_at']) {
            $update_stmt = $pdo->prepare("
                UPDATE vouchers 
                SET activated_at = NOW(), status = 'used' 
                WHERE id = ?
            ");
            $update_stmt->execute([$voucher['id']]);
        }
        
        // Ensure user exists in radcheck
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM radcheck WHERE username = ?");
        $check_stmt->execute([$voucher['username']]);
        
        if ($check_stmt->fetchColumn() == 0) {
            $create_stmt = $pdo->prepare("
                INSERT INTO radcheck (username, attribute, op, value) 
                VALUES (?, 'Cleartext-Password', ':=', ?)
            ");
            $create_stmt->execute([$voucher['username'], $voucher['password']]);
        }
        
        // Add session timeout if specified
        if ($voucher['time_limit']) {
            $timeout_stmt = $pdo->prepare("
                INSERT INTO radreply (username, attribute, op, value) 
                VALUES (?, 'Session-Timeout', ':=', ?)
                ON DUPLICATE KEY UPDATE value = ?
            ");
            $timeout_stmt->execute([$voucher['username'], $voucher['time_limit'], $voucher['time_limit']]);
        }
        
        // Add data limits if specified
        if ($voucher['data_limit']) {
            $data_limit_bytes = $voucher['data_limit'];
            $data_limit_stmt = $pdo->prepare("
                INSERT INTO radreply (username, attribute, op, value) 
                VALUES (?, 'Max-Monthly-Byte-Total', ':=', ?)
                ON DUPLICATE KEY UPDATE value = ?
            ");
            $data_limit_stmt->execute([$voucher['username'], $data_limit_bytes, $data_limit_bytes]);
        }
        
        // Add expiry date
        if ($voucher['expiry_date']) {
            $expiry_stmt = $pdo->prepare("
                INSERT INTO radcheck (username, attribute, op, value) 
                VALUES (?, 'Expiration', ':=', ?)
                ON DUPLICATE KEY UPDATE value = ?
            ");
            $expiry_stmt->execute([$voucher['username'], $voucher['expiry_date'], $voucher['expiry_date']]);
        }
        
        // Add to voucher group
        $group_stmt = $pdo->prepare("
            INSERT INTO radusergroup (username, groupname, priority) 
            VALUES (?, 'voucher', 1)
            ON DUPLICATE KEY UPDATE groupname = 'voucher'
        ");
        $group_stmt->execute([$voucher['username']]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Voucher activated successfully',
            'username' => $voucher['username']
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Voucher Activation Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error activating voucher'];
    }
}

/**
 * Check voucher usage limits
 * 
 * @param string $username
 * @param array $voucher
 * @return array ['allowed' => bool, 'message' => string]
 */
function checkVoucherLimits($username, $voucher) {
    try {
        $pdo = getDBConnection();
        
        // Check time limit
        if ($voucher['time_limit']) {
            $usage_stmt = $pdo->prepare("
                SELECT COALESCE(SUM(time_used), 0) as total_time 
                FROM voucher_usage 
                WHERE username = ?
            ");
            $usage_stmt->execute([$username]);
            $total_time = $usage_stmt->fetchColumn();
            
            if ($total_time >= $voucher['time_limit']) {
                return ['allowed' => false, 'message' => 'Time limit reached'];
            }
        }
        
        // Check data limit
        if ($voucher['data_limit']) {
            $data_stmt = $pdo->prepare("
                SELECT COALESCE(SUM(bytes_used), 0) as total_bytes 
                FROM voucher_usage 
                WHERE username = ?
            ");
            $data_stmt->execute([$username]);
            $total_bytes = $data_stmt->fetchColumn();
            
            if ($total_bytes >= $voucher['data_limit']) {
                return ['allowed' => false, 'message' => 'Data limit reached'];
            }
        }
        
        // Check daily limit
        if ($voucher['daily_limit']) {
            $daily_stmt = $pdo->prepare("
                SELECT COALESCE(SUM(bytes_in + bytes_out), 0) as daily_bytes 
                FROM daily_usage 
                WHERE username = ? AND usage_date = CURDATE()
            ");
            $daily_stmt->execute([$username]);
            $daily_bytes = $daily_stmt->fetchColumn();
            
            if ($daily_bytes >= $voucher['daily_limit']) {
                return ['allowed' => false, 'message' => 'Daily limit reached'];
            }
        }
        
        return ['allowed' => true, 'message' => 'Limits OK'];
        
    } catch (PDOException $e) {
        error_log("Voucher Limits Check Error: " . $e->getMessage());
        return ['allowed' => true, 'message' => 'Could not verify limits'];
    }
}
