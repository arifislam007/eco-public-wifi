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
