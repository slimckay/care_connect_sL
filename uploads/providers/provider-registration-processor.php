<?php
/**
 * Provider Registration Processor - Care Connect SL
 */

session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: provider-registration.php');
    exit;
}

// CSRF Protection
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($csrf_token)) {
    header('Location: provider-registration.php?error=csrf');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get and sanitize inputs
$specialty = sanitizeInput($_POST['specialty'] ?? '');
$qualifications = sanitizeInput($_POST['qualifications'] ?? '');
$experience_years = (int)($_POST['experience_years'] ?? 0);
$clinic_name = sanitizeInput($_POST['clinic_name'] ?? '');
$clinic_address = sanitizeInput($_POST['clinic_address'] ?? '');
$clinic_phone = sanitizeInput($_POST['clinic_phone'] ?? '');
$consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
$is_accepting_patients = isset($_POST['is_accepting_patients']) ? 1 : 0;
$national_id = sanitizeInput($_POST['national_id'] ?? '');
$medical_license = sanitizeInput($_POST['medical_license'] ?? '');
$bio = sanitizeInput($_POST['bio'] ?? '');

// Validate
$errors = [];
if (empty($specialty)) $errors[] = 'Specialty is required.';
if (empty($qualifications)) $errors[] = 'Qualifications are required.';
if (empty($clinic_name)) $errors[] = 'Clinic name is required.';
if (empty($clinic_address)) $errors[] = 'Clinic address is required.';
if (empty($national_id)) $errors[] = 'National ID is required.';
if (empty($medical_license)) $errors[] = 'Medical license is required.';

// Handle file uploads
$uploadDir = '../uploads/providers/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$profile_photo = null;
$id_photo = null;
$license_photo = null;

// Profile photo
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $filename)) {
        $profile_photo = 'uploads/providers/' . $filename;
    }
}

// ID photo
if (isset($_FILES['id_photo']) && $_FILES['id_photo']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION);
    $filename = 'id_' . $user_id . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['id_photo']['tmp_name'], $uploadDir . $filename)) {
        $id_photo = 'uploads/providers/' . $filename;
    }
}

// License photo
if (isset($_FILES['license_photo']) && $_FILES['license_photo']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['license_photo']['name'], PATHINFO_EXTENSION);
    $filename = 'license_' . $user_id . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['license_photo']['tmp_name'], $uploadDir . $filename)) {
        $license_photo = 'uploads/providers/' . $filename;
    }
}

if (!empty($errors)) {
    $_SESSION['provider_errors'] = $errors;
    header('Location: provider-registration.php?error=validation');
    exit;
}

try {
    // Check if profile exists
    $check = $conn->prepare("SELECT id FROM provider_profiles WHERE user_id = ?");
    $check->execute([$user_id]);
    $exists = $check->fetch();

    if ($exists) {
        // Update
        $stmt = $conn->prepare("
            UPDATE provider_profiles SET
                specialty = ?,
                qualifications = ?,
                experience_years = ?,
                clinic_name = ?,
                clinic_address = ?,
                clinic_phone = ?,
                consultation_fee = ?,
                is_accepting_patients = ?,
                national_id = ?,
                medical_license = ?,
                bio = ?,
                profile_photo = COALESCE(?, profile_photo),
                id_photo = COALESCE(?, id_photo),
                license_photo = COALESCE(?, license_photo),
                verification_status = 'pending'
            WHERE user_id = ?
        ");
        $stmt->execute([
            $specialty, $qualifications, $experience_years,
            $clinic_name, $clinic_address, $clinic_phone,
            $consultation_fee, $is_accepting_patients,
            $national_id, $medical_license, $bio,
            $profile_photo, $id_photo, $license_photo,
            $user_id
        ]);
    } else {
        // Insert
        $stmt = $conn->prepare("
            INSERT INTO provider_profiles (
                user_id, specialty, qualifications, experience_years,
                clinic_name, clinic_address, clinic_phone, consultation_fee,
                is_accepting_patients, national_id, medical_license, bio,
                profile_photo, id_photo, license_photo, verification_status, created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, 'pending', NOW()
            )
        ");
        $stmt->execute([
            $user_id, $specialty, $qualifications, $experience_years,
            $clinic_name, $clinic_address, $clinic_phone, $consultation_fee,
            $is_accepting_patients, $national_id, $medical_license, $bio,
            $profile_photo, $id_photo, $license_photo
        ]);
    }

    header('Location: provider-registration.php?success=1');
    exit;

} catch (PDOException $e) {
    error_log("Provider registration error: " . $e->getMessage());
    header('Location: provider-registration.php?error=server');
    exit;
}
?>