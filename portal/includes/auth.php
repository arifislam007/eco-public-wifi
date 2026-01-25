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
