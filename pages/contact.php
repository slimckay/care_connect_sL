<?php
/**
 * Contact Form Handler - Care Connect SL
 * Process contact form submissions securely
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
require_once __DIR__ . '/db.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pages/contact.html');
    exit;
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('contact_' . $ip, 5, 3600)) {
    header('Location: pages/contact.html?error=too_many_messages');
    exit;
}

// CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    header('Location: pages/contact.html?error=csrf');
    exit;
}

// Get and sanitize inputs
$name = sanitizeInput($_POST['name'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');
$message = sanitizeInput($_POST['message'] ?? '');

// Validate inputs
$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = 'name_invalid';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'email_invalid';
}

if (empty($message) || strlen($message) < 5) {
    $errors[] = 'message_short';
} elseif (strlen($message) > 1000) {
    $errors[] = 'message_long';
}

if (!empty($errors)) {
    $_SESSION['contact_errors'] = $errors;
    $_SESSION['contact_data'] = ['name' => $name, 'email' => $email, 'phone' => $phone];
    header('Location: pages/contact.html?error=' . implode(',', $errors));
    exit;
}

try {
    // Store contact message
    $stmt = $conn->prepare("
        INSERT INTO contact_messages (name, email, phone, message, ip_address, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$name, $email, $phone, $message, $ip]);

    // Log contact submission
    error_log("Contact message from: " . $email . " (IP: " . $ip . ")");

    // Send email notification (implement your email function)
    // sendContactNotification($name, $email, $phone, $message);

    // Clear session data
    unset($_SESSION['contact_errors']);
    unset($_SESSION['contact_data']);

    header('Location: pages/contact.html?sent=1');
    exit;

} catch (PDOException $e) {
    error_log("Contact form error: " . $e->getMessage());
    header('Location: pages/contact.html?error=server_error');
    exit;
}
?>