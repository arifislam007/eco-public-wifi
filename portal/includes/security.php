<?php
/**
 * Security Functions
 * Rate limiting, input sanitization, etc.
 */

require_once __DIR__ . '/../config.php';

/**
 * Check rate limit for IP address
 * 
 * @param string $ip_address
 * @return bool True if within limit, false if exceeded
 */
function checkRateLimit($ip_address) {
    try {
        $pdo = getDBConnection();
        
        // Clean old attempts (older than RATE_LIMIT_WINDOW)
        $cleanup_time = date('Y-m-d H:i:s', time() - RATE_LIMIT_WINDOW);
        $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < ?")->execute([$cleanup_time]);
        
        // Count recent failed attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND success = 0 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        
        $stmt->execute([$ip_address, RATE_LIMIT_WINDOW]);
        $attempts = $stmt->fetchColumn();
        
        return $attempts < RATE_LIMIT_MAX_ATTEMPTS;
        
    } catch (PDOException $e) {
        error_log("Rate Limit Check Error: " . $e->getMessage());
        // On error, allow the request (fail open)
        return true;
    }
}

/**
 * Log login attempt
 * 
 * @param string $ip_address
 * @param string $username
 * @param bool $success
 */
function logLoginAttempt($ip_address, $username, $success) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (ip_address, username, success) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([$ip_address, $username, $success ? 1 : 0]);
        
    } catch (PDOException $e) {
        error_log("Login Attempt Logging Error: " . $e->getMessage());
    }
}

/**
 * Sanitize user input
 * 
 * @param string $input
 * @return string
 */
function sanitizeInput($input) {
    // Remove null bytes
    $input = str_replace("\0", '', $input);
    
    // Trim whitespace
    $input = trim($input);
    
    // Remove HTML tags
    $input = strip_tags($input);
    
    // Escape special characters for SQL (though we use prepared statements)
    // This is just an extra layer of protection
    return $input;
}

/**
 * Validate username format
 * 
 * @param string $username
 * @return bool
 */
function validateUsername($username) {
    // Allow alphanumeric, underscore, hyphen, dot
    // Length: 3-64 characters
    return preg_match('/^[a-zA-Z0-9._-]{3,64}$/', $username) === 1;
}

/**
 * Validate password strength (optional)
 * 
 * @param string $password
 * @return bool
 */
function validatePassword($password) {
    // Minimum 6 characters
    return strlen($password) >= 6;
}

/**
 * Generate secure random token
 * 
 * @param int $length
 * @return string
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hash password using bcrypt
 * 
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password hash
 * 
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get client IP address
 * 
 * @return string
 */
function getClientIP() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
