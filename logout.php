<?php
/**
 * Logout Handler - Care Connect SL
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout
if (isset($_SESSION['user_id'])) {
    error_log("User logged out: " . ($_SESSION['user_email'] ?? 'User ID: ' . $_SESSION['user_id']));
}

// Clear all session variables
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to home
header('Location: index.html');
exit;
?>