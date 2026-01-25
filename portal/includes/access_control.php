<?php
/**
 * Access Control Functions
 * Session timeout, usage limits, concurrent login control
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

/**
 * Check if user can start a new session (concurrent login control)
 * 
 * @param string $username
 * @return array ['allowed' => bool, 'message' => string, 'current_sessions' => int]
 */
function checkConcurrentSessions($username) {
    try {
        $pdo = getDBConnection();
        
        // Get user's max sessions from policy or group
        $max_sessions = getMaxSessions($username);
        
        // Count active sessions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM active_sessions 
            WHERE username = ? 
            AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute([$username]);
        $current_sessions = $stmt->fetchColumn();
        
        if ($max_sessions > 0 && $current_sessions >= $max_sessions) {
            return [
                'allowed' => false,
                'message' => "Maximum concurrent sessions ($max_sessions) reached",
                'current_sessions' => $current_sessions
            ];
        }
        
        return [
            'allowed' => true,
            'message' => 'Session allowed',
            'current_sessions' => $current_sessions
        ];
        
    } catch (PDOException $e) {
        error_log("Concurrent Session Check Error: " . $e->getMessage());
        return ['allowed' => true, 'message' => 'Could not verify sessions', 'current_sessions' => 0];
    }
}

/**
 * Get maximum concurrent sessions for user
 * 
 * @param string $username
 * @return int
 */
function getMaxSessions($username) {
    try {
        $pdo = getDBConnection();
        
        // Check user policy first
        $stmt = $pdo->prepare("SELECT max_sessions FROM user_policies WHERE username = ?");
        $stmt->execute([$username]);
        $max = $stmt->fetchColumn();
        
        if ($max !== false && $max !== null) {
            return (int)$max;
        }
        
        // Check group policy
        $group_stmt = $pdo->prepare("
            SELECT g.max_sessions 
            FROM user_groups g
            INNER JOIN radusergroup ug ON g.groupname = ug.groupname
            WHERE ug.username = ?
            ORDER BY ug.priority DESC
            LIMIT 1
        ");
        $group_stmt->execute([$username]);
        $group_max = $group_stmt->fetchColumn();
        
        if ($group_max !== false && $group_max !== null) {
            return (int)$group_max;
        }
        
        // Default
        return 1;
        
    } catch (PDOException $e) {
        error_log("Get Max Sessions Error: " . $e->getMessage());
        return 1;
    }
}

/**
 * Check daily usage limit
 * 
 * @param string $username
 * @return array ['allowed' => bool, 'message' => string, 'usage' => array]
 */
function checkDailyLimit($username) {
    try {
        $pdo = getDBConnection();
        
        $daily_limit = getDailyLimit($username);
        
        if ($daily_limit === null || $daily_limit <= 0) {
            return ['allowed' => true, 'message' => 'No daily limit', 'usage' => []];
        }
        
        $stmt = $pdo->prepare("
            SELECT bytes_in, bytes_out, total_bytes 
            FROM daily_usage 
            WHERE username = ? AND usage_date = CURDATE()
        ");
        $stmt->execute([$username]);
        $usage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usage) {
            return ['allowed' => true, 'message' => 'No usage today', 'usage' => ['used' => 0, 'limit' => $daily_limit]];
        }
        
        $used = $usage['total_bytes'];
        $remaining = $daily_limit - $used;
        
        if ($used >= $daily_limit) {
            return [
                'allowed' => false,
                'message' => 'Daily limit reached',
                'usage' => [
                    'used' => $used,
                    'limit' => $daily_limit,
                    'remaining' => 0,
                    'percentage' => 100
                ]
            ];
        }
        
        return [
            'allowed' => true,
            'message' => 'Daily limit OK',
            'usage' => [
                'used' => $used,
                'limit' => $daily_limit,
                'remaining' => $remaining,
                'percentage' => round(($used / $daily_limit) * 100, 2)
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Daily Limit Check Error: " . $e->getMessage());
        return ['allowed' => true, 'message' => 'Could not verify limit', 'usage' => []];
    }
}

/**
 * Check monthly usage limit
 * 
 * @param string $username
 * @return array ['allowed' => bool, 'message' => string, 'usage' => array]
 */
function checkMonthlyLimit($username) {
    try {
        $pdo = getDBConnection();
        
        $monthly_limit = getMonthlyLimit($username);
        
        if ($monthly_limit === null || $monthly_limit <= 0) {
            return ['allowed' => true, 'message' => 'No monthly limit', 'usage' => []];
        }
        
        $stmt = $pdo->prepare("
            SELECT bytes_in, bytes_out, total_bytes 
            FROM monthly_usage 
            WHERE username = ? AND usage_month = DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $stmt->execute([$username]);
        $usage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usage) {
            return ['allowed' => true, 'message' => 'No usage this month', 'usage' => ['used' => 0, 'limit' => $monthly_limit]];
        }
        
        $used = $usage['total_bytes'];
        $remaining = $monthly_limit - $used;
        
        if ($used >= $monthly_limit) {
            return [
                'allowed' => false,
                'message' => 'Monthly limit reached',
                'usage' => [
                    'used' => $used,
                    'limit' => $monthly_limit,
                    'remaining' => 0,
                    'percentage' => 100
                ]
            ];
        }
        
        return [
            'allowed' => true,
            'message' => 'Monthly limit OK',
            'usage' => [
                'used' => $used,
                'limit' => $monthly_limit,
                'remaining' => $remaining,
                'percentage' => round(($used / $monthly_limit) * 100, 2)
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Monthly Limit Check Error: " . $e->getMessage());
        return ['allowed' => true, 'message' => 'Could not verify limit', 'usage' => []];
    }
}

/**
 * Get daily limit for user
 * 
 * @param string $username
 * @return int|null
 */
function getDailyLimit($username) {
    try {
        $pdo = getDBConnection();
        
        // Check user policy
        $stmt = $pdo->prepare("SELECT daily_limit FROM user_policies WHERE username = ?");
        $stmt->execute([$username]);
        $limit = $stmt->fetchColumn();
        
        if ($limit !== false && $limit !== null) {
            return (int)$limit;
        }
        
        // Check group policy
        $group_stmt = $pdo->prepare("
            SELECT g.daily_limit 
            FROM user_groups g
            INNER JOIN radusergroup ug ON g.groupname = ug.groupname
            WHERE ug.username = ?
            ORDER BY ug.priority DESC
            LIMIT 1
        ");
        $group_stmt->execute([$username]);
        $group_limit = $group_stmt->fetchColumn();
        
        return $group_limit !== false ? (int)$group_limit : null;
        
    } catch (PDOException $e) {
        error_log("Get Daily Limit Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get monthly limit for user
 * 
 * @param string $username
 * @return int|null
 */
function getMonthlyLimit($username) {
    try {
        $pdo = getDBConnection();
        
        // Check user policy
        $stmt = $pdo->prepare("SELECT monthly_limit FROM user_policies WHERE username = ?");
        $stmt->execute([$username]);
        $limit = $stmt->fetchColumn();
        
        if ($limit !== false && $limit !== null) {
            return (int)$limit;
        }
        
        // Check group policy
        $group_stmt = $pdo->prepare("
            SELECT g.monthly_limit 
            FROM user_groups g
            INNER JOIN radusergroup ug ON g.groupname = ug.groupname
            WHERE ug.username = ?
            ORDER BY ug.priority DESC
            LIMIT 1
        ");
        $group_stmt->execute([$username]);
        $group_limit = $group_stmt->fetchColumn();
        
        return $group_limit !== false ? (int)$group_limit : null;
        
    } catch (PDOException $e) {
        error_log("Get Monthly Limit Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Create or update active session
 * 
 * @param string $username
 * @param string $session_id
 * @param string $ip_address
 * @param string $mac_address
 */
function createActiveSession($username, $session_id, $ip_address, $mac_address = null) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO active_sessions (username, session_id, ip_address, mac_address, start_time, last_activity)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                last_activity = NOW(),
                ip_address = VALUES(ip_address)
        ");
        $stmt->execute([$username, $session_id, $ip_address, $mac_address]);
        
    } catch (PDOException $e) {
        error_log("Create Active Session Error: " . $e->getMessage());
    }
}

/**
 * Update session activity
 * 
 * @param string $session_id
 * @param int $bytes_in
 * @param int $bytes_out
 */
function updateSessionActivity($session_id, $bytes_in = 0, $bytes_out = 0) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            UPDATE active_sessions 
            SET last_activity = NOW(),
                bytes_in = bytes_in + ?,
                bytes_out = bytes_out + ?
            WHERE session_id = ?
        ");
        $stmt->execute([$bytes_in, $bytes_out, $session_id]);
        
    } catch (PDOException $e) {
        error_log("Update Session Activity Error: " . $e->getMessage());
    }
}

/**
 * Clean inactive sessions (older than 5 minutes)
 */
function cleanInactiveSessions() {
    try {
        $pdo = getDBConnection();
        $pdo->exec("
            DELETE FROM active_sessions 
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
    } catch (PDOException $e) {
        error_log("Clean Inactive Sessions Error: " . $e->getMessage());
    }
}
