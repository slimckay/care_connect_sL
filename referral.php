<?php
/**
 * Referral Handler - Care Connect SL
 * Process and store referral submissions securely
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pages/referral.html');
    exit;
}

// Rate limiting for submissions
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('referral_' . $ip, 10, 3600)) {
    header('Location: pages/referral.html?error=too_many');
    exit;
}

// Get and sanitize inputs
$patient_name = sanitizeInput($_POST['patient_name'] ?? '');
$age = isset($_POST['age']) && $_POST['age'] !== '' ? (int)$_POST['age'] : null;
$contact = sanitizeInput($_POST['contact'] ?? '');
$location = sanitizeInput($_POST['location'] ?? '');
$preferred_clinic = sanitizeInput($_POST['preferred_clinic'] ?? '');
$condition = sanitizeInput($_POST['condition'] ?? '');
$referrer = sanitizeInput($_POST['referrer'] ?? 'self');
$user_id = $_SESSION['user_id'] ?? null;

// Validate
$errors = [];

if ($patient_name === '' || strlen($patient_name) < 2) {
    $errors[] = 'name';
}
if ($contact === '') {
    $errors[] = 'contact';
}
if ($location === '' || strlen($location) < 3) {
    $errors[] = 'location';
}
if ($condition === '' || strlen($condition) < 5) {
    $errors[] = 'condition';
}
if ($age !== null && ($age < 0 || $age > 150)) {
    $errors[] = 'age';
}

if (!empty($errors)) {
    header('Location: pages/referral.html?error=validation');
    exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO referrals (
            patient_name, age, contact, location, preferred_clinic,
            `condition`, referrer, user_id, status, ip_address, created_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, 'pending', ?, NOW()
        )
    ");

    $stmt->execute([
        $patient_name,
        $age,
        $contact,
        $location,
        $preferred_clinic !== '' ? $preferred_clinic : null,
        $condition,
        $referrer,
        $user_id,
        $ip
    ]);

    $referralId = $conn->lastInsertId();
    error_log('New referral submitted: ID ' . $referralId . ' for ' . $patient_name);

    // Success — go back to form with success flag
    header('Location: pages/referral.html?sent=1');
    exit;

} catch (PDOException $e) {
    error_log('Referral error: ' . $e->getMessage());
    header('Location: pages/referral.html?error=server');
    exit;
} catch (Exception $e) {
    error_log('Referral error: ' . $e->getMessage());
    header('Location: pages/referral.html?error=server');
    exit;
}
