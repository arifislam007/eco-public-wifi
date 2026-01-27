<?php
/**
 * FreeRADIUS Authentication Functions
 */

require_once __DIR__ . '/../config.php';

/**
 * Authenticate user with FreeRADIUS using radclient or PECL radius extension
 * Falls back to direct MySQL authentication if FreeRADIUS is unavailable
 * 
 * @param string $username
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function authenticateUser($username, $password) {
    // Method 1: Using PECL radius extension (if available)
    if (extension_loaded('radius')) {
        $result = authenticateWithPECL($username, $password);
        if ($result['success']) {
            return $result;
        }
    }
    
    // Method 2: Using radclient command
    $result = authenticateWithRadClient($username, $password);
    if ($result['success']) {
        return $result;
    }
    
    // Method 3: Fallback to direct MySQL authentication
    // This is useful if FreeRADIUS service is temporarily unavailable
    return authenticateWithMySQL($username, $password);
}

/**
 * Authenticate using PECL radius extension
 */
function authenticateWithPECL($username, $password) {
    $radius = radius_auth_open();
    
    if (!$radius) {
        return ['success' => false, 'message' => 'RADIUS connection failed'];
    }
    
    radius_add_server($radius, RADIUS_HOST, RADIUS_PORT, RADIUS_SECRET, RADIUS_TIMEOUT, 3);
    radius_create_request($radius, RADIUS_ACCESS_REQUEST);
    radius_put_attr($radius, RADIUS_USER_NAME, $username);
    radius_put_attr($radius, RADIUS_USER_PASSWORD, $password);
    
    $result = radius_send_request($radius);
    
    radius_close($radius);
    
    if ($result == RADIUS_ACCESS_ACCEPT) {
        return ['success' => true, 'message' => 'Authentication successful'];
    } else {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
}

/**
 * Authenticate using radclient command (fallback method)
 */
function authenticateWithRadClient($username, $password) {
    // Create temporary file with RADIUS request
    $temp_file = sys_get_temp_dir() . '/radius_' . uniqid() . '.txt';
    
    $request = "User-Name = \"$username\"\n";
    $request .= "User-Password = \"$password\"\n";
    $request .= "NAS-IP-Address = 127.0.0.1\n";
    $request .= "NAS-Port = 0\n";
    
    file_put_contents($temp_file, $request);
    
    // Execute radclient
    $command = sprintf(
        'echo "%s" | radclient -x %s:%d auth %s 2>&1',
        escapeshellarg($request),
        RADIUS_HOST,
        RADIUS_PORT,
        escapeshellarg(RADIUS_SECRET)
    );
    
    $output = shell_exec($command);
    $return_code = 0;
    exec($command . '; echo $?', $output_lines, $return_code);
    
    // Clean up
    @unlink($temp_file);
    
    // Check if authentication was successful
    // radclient returns 0 on success (Access-Accept)
    if (isset($output) && (strpos($output, 'Access-Accept') !== false || $return_code === 0)) {
        return ['success' => true, 'message' => 'Authentication successful'];
    }
    
    return ['success' => false, 'message' => 'Invalid username or password'];
}

/**
 * Alternative: Direct MySQL authentication (if FreeRADIUS is not accessible)
 * This checks the radcheck table directly
 */
function authenticateWithMySQL($username, $password) {
    try {
        $pdo = getDBConnection();
        
        // Get user password from radcheck table
        $stmt = $pdo->prepare("
            SELECT attribute, value 
            FROM radcheck 
            WHERE username = :username 
            AND attribute IN ('Cleartext-Password', 'MD5-Password', 'SHA-Password', 'NT-Password')
            ORDER BY 
                CASE attribute
                    WHEN 'Cleartext-Password' THEN 1
                    WHEN 'MD5-Password' THEN 2
                    WHEN 'SHA-Password' THEN 3
                    WHEN 'NT-Password' THEN 4
                END
            LIMIT 1
        ");
        
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify password based on attribute type
        $verified = false;
        
        switch ($user['attribute']) {
            case 'Cleartext-Password':
                $verified = ($user['value'] === $password);
                break;
                
            case 'MD5-Password':
                $verified = (md5($password) === $user['value']);
                break;
                
            case 'SHA-Password':
                $verified = (sha1($password) === $user['value']);
                break;
                
            case 'NT-Password':
                $verified = (strtoupper(bin2hex(mhash(MHASH_MD4, iconv('UTF-8', 'UTF-16LE', $password)))) === strtoupper($user['value']));
                break;
        }
        
        if ($verified) {
            // Check if user has expired
            $expiry_stmt = $pdo->prepare("
                SELECT value 
                FROM radcheck 
                WHERE username = :username 
                AND attribute = 'Expiration'
            ");
            $expiry_stmt->execute(['username' => $username]);
            $expiry = $expiry_stmt->fetchColumn();
            
            if ($expiry && strtotime($expiry) < time()) {
                return ['success' => false, 'message' => 'Account has expired'];
            }
            
            return ['success' => true, 'message' => 'Authentication successful'];
        }
        
        return ['success' => false, 'message' => 'Invalid password'];
        
    } catch (PDOException $e) {
        error_log("MySQL Authentication Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Authentication service error'];
    }
}

/**
 * Get database connection
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    return $pdo;
}

/**
 * Check if required database tables exist, create them if not
 * This ensures backward compatibility when new features are added
 */
function ensureDatabaseTables() {
    try {
        $pdo = getDBConnection();
        
        // List of required tables and their creation SQL
        $tables = [
            'nas' => "
                CREATE TABLE IF NOT EXISTS nas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nasname VARCHAR(128) NOT NULL,
                    shortname VARCHAR(32) DEFAULT NULL,
                    type VARCHAR(30) DEFAULT 'other',
                    ports INT DEFAULT NULL,
                    secret VARCHAR(60) NOT NULL DEFAULT 'secret',
                    server VARCHAR(64) DEFAULT NULL,
                    community VARCHAR(50) DEFAULT NULL,
                    description VARCHAR(200) DEFAULT 'RADIUS Client',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY nasname (nasname)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'payment_gateways' => "
                CREATE TABLE IF NOT EXISTS payment_gateways (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(50) NOT NULL,
                    code VARCHAR(20) NOT NULL UNIQUE,
                    gateway_type VARCHAR(20) NOT NULL,
                    is_active TINYINT(1) DEFAULT 0,
                    config JSON,
                    test_mode TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'sms_gateways' => "
                CREATE TABLE IF NOT EXISTS sms_gateways (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(50) NOT NULL,
                    code VARCHAR(20) NOT NULL UNIQUE,
                    gateway_type VARCHAR(20) NOT NULL,
                    is_active TINYINT(1) DEFAULT 0,
                    config JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'wifi_packages' => "
                CREATE TABLE IF NOT EXISTS wifi_packages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    package_type ENUM('hourly', 'daily', 'custom_hours', 'custom_days') NOT NULL,
                    duration_value INT NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    sort_order INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'package_orders' => "
                CREATE TABLE IF NOT EXISTS package_orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id VARCHAR(50) NOT NULL UNIQUE,
                    package_id INT NOT NULL,
                    username VARCHAR(64) DEFAULT NULL,
                    phone VARCHAR(20) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    payment_method VARCHAR(20) NOT NULL,
                    payment_status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
                    transaction_id VARCHAR(100) DEFAULT NULL,
                    voucher_code VARCHAR(50) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (package_id) REFERENCES wifi_packages(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'resellers' => "
                CREATE TABLE IF NOT EXISTS resellers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    phone VARCHAR(20) DEFAULT NULL,
                    address TEXT,
                    voucher_prefix VARCHAR(10) DEFAULT NULL,
                    commission_rate DECIMAL(5,2) DEFAULT 0.00,
                    balance DECIMAL(10,2) DEFAULT 0.00,
                    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'reseller_transactions' => "
                CREATE TABLE IF NOT EXISTS reseller_transactions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    reseller_id INT NOT NULL,
                    transaction_type ENUM('credit', 'debit') NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    description TEXT,
                    reference_type VARCHAR(50) DEFAULT NULL,
                    reference_id INT DEFAULT NULL,
                    created_by INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];
        
        // Check and create each table
        foreach ($tables as $tableName => $createSql) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec($createSql);
                error_log("Created missing table: $tableName");
            }
        }
        
        // Check if admin_users table needs new columns
        $columnsToCheck = [
            'role' => "ALTER TABLE admin_users ADD COLUMN role ENUM('admin', 'reseller') DEFAULT 'admin'",
            'reseller_id' => "ALTER TABLE admin_users ADD COLUMN reseller_id INT DEFAULT NULL",
            'status' => "ALTER TABLE admin_users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'"
        ];
        
        foreach ($columnsToCheck as $column => $alterSql) {
            try {
                $pdo->query("SELECT $column FROM admin_users LIMIT 1");
            } catch (PDOException $e) {
                // Column doesn't exist, add it
                $pdo->exec($alterSql);
                error_log("Added missing column to admin_users: $column");
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Database setup error: " . $e->getMessage());
        return false;
    }
}
