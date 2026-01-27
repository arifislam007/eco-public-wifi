<?php
/**
 * Security Functions for Admin Panel
 */

require_once __DIR__ . '/../../portal/includes/security.php';

/**
 * Check if admin is logged in
 */
function requireAdminLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Check if user is admin (not reseller)
 */
function requireAdminRole() {
    requireAdminLogin();
    
    // Handle legacy sessions without admin_role
    if (!isset($_SESSION['admin_role'])) {
        $_SESSION['admin_role'] = 'admin';
        $_SESSION['reseller_id'] = null;
    }
    
    if ($_SESSION['admin_role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Check if user is admin or reseller (any role)
 */
function requireAdminOrResellerRole() {
    requireAdminLogin();
    if (!isset($_SESSION['admin_role']) || ($_SESSION['admin_role'] !== 'admin' && $_SESSION['admin_role'] !== 'reseller')) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Get current reseller ID if logged in as reseller
 */
function getCurrentResellerId() {
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'reseller' && isset($_SESSION['reseller_id'])) {
        return $_SESSION['reseller_id'];
    }
    return null;
}

/**
 * Check if user is admin or specific reseller
 */
function requireAdminOrReseller($reseller_id = null) {
    requireAdminLogin();
    
    // Handle legacy sessions without admin_role
    if (!isset($_SESSION['admin_role'])) {
        $_SESSION['admin_role'] = 'admin';
        $_SESSION['reseller_id'] = null;
    }
    
    if ($_SESSION['admin_role'] === 'admin') {
        return true;
    }
    if ($_SESSION['admin_role'] === 'reseller') {
        if ($reseller_id === null || $_SESSION['reseller_id'] == $reseller_id) {
            return true;
        }
    }
    header('Location: dashboard.php');
    exit;
}
