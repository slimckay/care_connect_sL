<?php
/**
 * Password Reset Handler - Care Connect SL
 * Handle password reset requests
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
require_once __DIR__ . '/db.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot-password.html');
    exit;
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('reset_' . $ip, 3, 3600)) {
    header('Location: forgot-password.html?error=too_many_attempts');
    exit;
}

// CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    header('Location: forgot-password.html?error=csrf');
    exit;
}

// Get email
$email = sanitizeInput($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: forgot-password.html?error=invalid_email');
    exit;
}

try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Don't reveal if email exists for security
        header('Location: forgot-password.html?sent=1');
        exit;
    }

    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Store reset token
    $updateStmt = $conn->prepare("
        UPDATE users 
        SET reset_token = ?, reset_token_expires = ? 
        WHERE id = ?
    ");
    $updateStmt->execute([$resetToken, $expires, $user['id']]);

    // Build reset link
    $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $resetToken;

    // Log
    error_log("Password reset requested for: " . $email);

    // Send email (implement your email function)
    // sendResetEmail($email, $user['name'], $resetLink);

    header('Location: forgot-password.html?sent=1');
    exit;

} catch (PDOException $e) {
    error_log("Password reset error: " . $e->getMessage());
    header('Location: forgot-password.html?error=server_error');
    exit;
}
?>