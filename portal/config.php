<?php
/**
 * Configuration File
 * Public Wi-Fi Captive Portal
 * 
 * Environment variables are used when running in Docker
 */

// Portal Settings
define('PORTAL_NAME', getenv('PORTAL_NAME') ?: 'Community Wi-Fi');
define('SUPPORT_EMAIL', getenv('SUPPORT_EMAIL') ?: 'support@example.com');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'radius');
define('DB_USER', getenv('DB_USER') ?: 'radius');
define('DB_PASS', getenv('DB_PASS') ?: 'radius_password');
define('DB_CHARSET', 'utf8mb4');

// FreeRADIUS Configuration
define('RADIUS_HOST', getenv('RADIUS_HOST') ?: '127.0.0.1');
define('RADIUS_PORT', intval(getenv('RADIUS_PORT') ?: 1812));
define('RADIUS_SECRET', getenv('RADIUS_SECRET') ?: 'testing123');
define('RADIUS_TIMEOUT', 5);

// Security Settings
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 900); // 15 minutes in seconds
define('SESSION_TIMEOUT', 3600); // 1 hour

// SMS/OTP Settings
define('SMS_PROVIDER', getenv('SMS_PROVIDER') ?: 'simulated'); // simulated, twilio, nexmo, etc.
define('SMS_API_KEY', getenv('SMS_API_KEY') ?: '');
define('SMS_API_SECRET', getenv('SMS_API_SECRET') ?: '');
define('SMS_FROM_NUMBER', getenv('SMS_FROM_NUMBER') ?: '');
define('OTP_EXPIRY', 300); // 5 minutes in seconds
define('OTP_LENGTH', 6);

// Paths
define('BASE_PATH', dirname(__DIR__));
define('LOG_PATH', BASE_PATH . '/logs');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . '/php_errors.log');

// Timezone
date_default_timezone_set('Asia/Dhaka');

// Create logs directory if it doesn't exist
if (!file_exists(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}
