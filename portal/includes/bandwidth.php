<?php
/**
 * Bandwidth Management Functions
 * Speed limits, FUP, burst speeds
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

/**
 * Get bandwidth limits for user
 * 
 * @param string $username
 * @return array ['download' => int, 'upload' => int, 'burst_download' => int, 'burst_upload' => int, 'fup' => array]
 */
function getBandwidthLimits($username) {
    try {
        $pdo = getDBConnection();
        
        // Check user policy first
        $stmt = $pdo->prepare("
            SELECT download_speed, upload_speed, burst_download, burst_upload,
                   fup_enabled, fup_threshold, fup_speed
            FROM user_policies 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($policy) {
            return [
                'download' => (int)($policy['download_speed'] ?? 0),
                'upload' => (int)($policy['upload_speed'] ?? 0),
                'burst_download' => (int)($policy['burst_download'] ?? 0),
                'burst_upload' => (int)($policy['burst_upload'] ?? 0),
                'fup' => [
                    'enabled' => (bool)($policy['fup_enabled'] ?? false),
                    'threshold' => (int)($policy['fup_threshold'] ?? 0),
                    'speed' => (int)($policy['fup_speed'] ?? 0)
                ]
            ];
        }
        
        // Check group policy
        $group_stmt = $pdo->prepare("
            SELECT g.download_speed, g.upload_speed, g.burst_download, g.burst_upload,
                   g.fup_enabled, g.fup_threshold, g.fup_speed
            FROM user_groups g
            INNER JOIN radusergroup ug ON g.groupname = ug.groupname
            WHERE ug.username = ?
            ORDER BY ug.priority DESC
            LIMIT 1
        ");
        $group_stmt->execute([$username]);
        $group_policy = $group_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($group_policy) {
            return [
                'download' => (int)($group_policy['download_speed'] ?? 0),
                'upload' => (int)($group_policy['upload_speed'] ?? 0),
                'burst_download' => (int)($group_policy['burst_download'] ?? 0),
                'burst_upload' => (int)($group_policy['burst_upload'] ?? 0),
                'fup' => [
                    'enabled' => (bool)($group_policy['fup_enabled'] ?? false),
                    'threshold' => (int)($group_policy['fup_threshold'] ?? 0),
                    'speed' => (int)($group_policy['fup_speed'] ?? 0)
                ]
            ];
        }
        
        // Default: no limits
        return [
            'download' => 0,
            'upload' => 0,
            'burst_download' => 0,
            'burst_upload' => 0,
            'fup' => ['enabled' => false, 'threshold' => 0, 'speed' => 0]
        ];
        
    } catch (PDOException $e) {
        error_log("Get Bandwidth Limits Error: " . $e->getMessage());
        return [
            'download' => 0,
            'upload' => 0,
            'burst_download' => 0,
            'burst_upload' => 0,
            'fup' => ['enabled' => false, 'threshold' => 0, 'speed' => 0]
        ];
    }
}

/**
 * Apply bandwidth limits to FreeRADIUS reply attributes
 * 
 * @param string $username
 * @return bool
 */
function applyBandwidthLimits($username) {
    try {
        $pdo = getDBConnection();
        $limits = getBandwidthLimits($username);
        
        // Check FUP status
        $fup_active = false;
        if ($limits['fup']['enabled']) {
            $usage = getMonthlyUsage($username);
            if ($usage >= $limits['fup']['threshold']) {
                $fup_active = true;
            }
        }
        
        // Use FUP speed if active, otherwise normal speed
        $download_speed = $fup_active ? $limits['fup']['speed'] : $limits['download'];
        $upload_speed = $fup_active ? $limits['fup']['speed'] : $limits['upload'];
        
        $pdo->beginTransaction();
        
        // Apply download speed (MikroTik format: Mikrotik-Rate-Limit)
        if ($download_speed > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO radreply (username, attribute, op, value) 
                VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)
                ON DUPLICATE KEY UPDATE value = ?
            ");
            $rate_limit = $download_speed . 'k/' . $upload_speed . 'k';
            $stmt->execute([$username, $rate_limit, $rate_limit]);
        }
        
        // Apply burst speeds if configured
        if ($limits['burst_download'] > 0 && !$fup_active) {
            $burst_stmt = $pdo->prepare("
                INSERT INTO radreply (username, attribute, op, value) 
                VALUES (?, 'Mikrotik-Burst-Limit', ':=', ?)
                ON DUPLICATE KEY UPDATE value = ?
            ");
            $burst_limit = $limits['burst_download'] . 'k/' . $limits['burst_upload'] . 'k';
            $burst_stmt->execute([$username, $burst_limit, $burst_limit]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("Apply Bandwidth Limits Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get monthly usage for user
 * 
 * @param string $username
 * @return int Bytes used this month
 */
function getMonthlyUsage($username) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(total_bytes, 0) 
            FROM monthly_usage 
            WHERE username = ? AND usage_month = DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $stmt->execute([$username]);
        $usage = $stmt->fetchColumn();
        
        return (int)$usage;
        
    } catch (PDOException $e) {
        error_log("Get Monthly Usage Error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if FUP is active for user
 * 
 * @param string $username
 * @return array ['active' => bool, 'usage' => int, 'threshold' => int, 'speed' => int]
 */
function checkFUPStatus($username) {
    $limits = getBandwidthLimits($username);
    
    if (!$limits['fup']['enabled']) {
        return [
            'active' => false,
            'usage' => 0,
            'threshold' => 0,
            'speed' => 0
        ];
    }
    
    $usage = getMonthlyUsage($username);
    $active = $usage >= $limits['fup']['threshold'];
    
    return [
        'active' => $active,
        'usage' => $usage,
        'threshold' => $limits['fup']['threshold'],
        'speed' => $active ? $limits['fup']['speed'] : $limits['download']
    ];
}
