<?php
/**
 * Referral Handler - Care Connect SL
 * Process and store referral submissions securely
 */

// Start session for user identification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
require_once __DIR__ . '/db.php';

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pages/referral.html');
    exit;
}

// Rate limiting for submissions
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit('referral_' . $ip, 10, 3600)) {
    header('Location: pages/referral.html?error=too_many_submissions');
    exit;
}

// CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    header('Location: pages/referral.html?error=csrf');
    exit;
}

// Get and sanitize all inputs
$patient_name = sanitizeInput($_POST['patient_name'] ?? '');
$age = isset($_POST['age']) ? (int)$_POST['age'] : null;
$contact = sanitizeInput($_POST['contact'] ?? '');
$location = sanitizeInput($_POST['location'] ?? '');
$preferred_clinic = sanitizeInput($_POST['preferred_clinic'] ?? '');
$condition = sanitizeInput($_POST['condition'] ?? '');
$referrer = sanitizeInput($_POST['referrer'] ?? 'self');
$user_id = $_SESSION['user_id'] ?? null;

// Validate inputs
$errors = [];

// Patient name validation
if (empty($patient_name)) {
    $errors[] = 'patient_name_required';
} elseif (strlen($patient_name) < 2) {
    $errors[] = 'patient_name_too_short';
} elseif (strlen($patient_name) > 100) {
    $errors[] = 'patient_name_too_long';
} elseif (!preg_match("/^[A-Za-z\s\-']+$/", $patient_name)) {
    $errors[] = 'patient_name_invalid';
}

// Age validation
if (!empty($age)) {
    if ($age < 0 || $age > 150) {
        $errors[] = 'age_invalid';
    }
}

// Contact validation
if (empty($contact)) {
    $errors[] = 'contact_required';
} elseif (strlen($contact) > 100) {
    $errors[] = 'contact_too_long';
}
// Allow email or phone format
if (!empty($contact) && !filter_var($contact, FILTER_VALIDATE_EMAIL) && 
    !preg_match("/^[\+\d\s\-()]{8,20}$/", $contact)) {
    $errors[] = 'contact_invalid';
}

// Location validation
if (empty($location)) {
    $errors[] = 'location_required';
} elseif (strlen($location) < 3) {
    $errors[] = 'location_too_short';
} elseif (strlen($location) > 200) {
    $errors[] = 'location_too_long';
}

// Condition validation
if (empty($condition)) {
    $errors[] = 'condition_required';
} elseif (strlen($condition) < 5) {
    $errors[] = 'condition_too_short';
} elseif (strlen($condition) > 1000) {
    $errors[] = 'condition_too_long';
}

// Preferred clinic validation (optional)
if (!empty($preferred_clinic) && strlen($preferred_clinic) > 100) {
    $errors[] = 'clinic_too_long';
}

// Check for errors
if (!empty($errors)) {
    $_SESSION['referral_errors'] = $errors;
    $_SESSION['referral_data'] = [
        'patient_name' => $patient_name,
        'age' => $age,
        'contact' => $contact,
        'location' => $location,
        'preferred_clinic' => $preferred_clinic,
        'condition' => $condition,
        'referrer' => $referrer
    ];
    header('Location: pages/referral.html?error=' . implode(',', $errors));
    exit;
}

try {
    // Begin transaction
    $conn->beginTransaction();

    // Insert referral - Using medical_condition instead of condition
    $stmt = $conn->prepare("
        INSERT INTO referrals (
            patient_name, age, contact, location, preferred_clinic, 
            medical_condition, referrer, user_id, status, ip_address, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, 'pending', ?, NOW()
        )
    ");
    
    $result = $stmt->execute([
        $patient_name,
        $age,
        $contact,
        $location,
        $preferred_clinic,
        $condition,  // Note: 'condition' from form maps to 'medical_condition' in DB
        $referrer,
        $user_id,
        $ip
    ]);

    if (!$result) {
        throw new Exception("Failed to insert referral");
    }

    $referralId = $conn->lastInsertId();

    // Log referral
    error_log("New referral submitted: ID " . $referralId . " for patient " . $patient_name . " from IP: " . $ip);

    // Commit transaction
    $conn->commit();

    // Clear session data
    unset($_SESSION['referral_errors']);
    unset($_SESSION['referral_data']);

    // Redirect with success
    header('Location: pages/referral.html?sent=1');
    exit;

} catch (PDOException $e) {
    // Rollback transaction
    $conn->rollBack();
    error_log("Referral error: " . $e->getMessage());
    header('Location: pages/referral.html?error=server_error');
    exit;
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Referral error: " . $e->getMessage());
    header('Location: pages/referral.html?error=server_error');
    exit;
}
?>