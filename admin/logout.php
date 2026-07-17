<?php
/**
 * Admin Logout - Care Connect SL
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all admin session variables
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_login_time']);

// Destroy session
session_destroy();

// Redirect to admin login
header('Location: login.php?logout=success');
exit;
?>