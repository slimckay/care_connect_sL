<?php
/**
 * Provider Registration - Care Connect SL
 * For doctors and clinics to register their professional details
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user is a doctor or hospital
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'hospital') {
    header('Location: ../index.html');
    exit;
}

// Include database
require_once '../db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Check if provider profile already exists
$stmt = $conn->prepare("SELECT * FROM provider_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$existingProfile = $stmt->fetch();

$isEdit = ($existingProfile !== false);
$profile = $existingProfile ?: [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Protection
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        
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

        if (empty($specialty)) {
            $errors[] = 'Please select your specialty.';
        }
        if (empty($qualifications)) {
            $errors[] = 'Please enter your qualifications.';
        }
        if ($experience_years < 0) {
            $errors[] = 'Please enter a valid number of years experience.';
        }
        if (empty($clinic_name)) {
            $errors[] = 'Please enter your clinic name.';
        }
        if (empty($clinic_address)) {
            $errors[] = 'Please enter your clinic address.';
        }
        if (!empty($consultation_fee) && $consultation_fee < 0) {
            $errors[] = 'Please enter a valid consultation fee.';
        }
        if (empty($national_id)) {
            $errors[] = 'Please enter your National ID number.';
        }
        if (empty($medical_license)) {
            $errors[] = 'Please enter your medical license number.';
        }

        // Handle file uploads
        $profile_photo = null;
        $id_photo = null;
        $license_photo = null;

        // Upload directory
        $uploadDir = '../uploads/providers/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

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

        if (empty($errors)) {
            try {
                if ($isEdit) {
                    // Update existing profile
                    $sql = "
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
                            bio = ?
                    ";
                    $params = [
                        $specialty,
                        $qualifications,
                        $experience_years,
                        $clinic_name,
                        $clinic_address,
                        $clinic_phone,
                        $consultation_fee,
                        $is_accepting_patients,
                        $national_id,
                        $medical_license,
                        $bio
                    ];

                    // Add photo updates if provided
                    if ($profile_photo) {
                        $sql .= ", profile_photo = ?";
                        $params[] = $profile_photo;
                    }
                    if ($id_photo) {
                        $sql .= ", id_photo = ?";
                        $params[] = $id_photo;
                    }
                    if ($license_photo) {
                        $sql .= ", license_photo = ?";
                        $params[] = $license_photo;
                    }

                    $sql .= " WHERE user_id = ?";
                    $params[] = $user_id;

                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    $success = "Profile updated successfully!";

                } else {
                    // Insert new profile
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
                        $user_id,
                        $specialty,
                        $qualifications,
                        $experience_years,
                        $clinic_name,
                        $clinic_address,
                        $clinic_phone,
                        $consultation_fee,
                        $is_accepting_patients,
                        $national_id,
                        $medical_license,
                        $bio,
                        $profile_photo,
                        $id_photo,
                        $license_photo
                    ]);
                    $success = "Profile submitted for verification! Our team will review your credentials.";
                }

                // Update user role if doctor/hospital
                $roleStmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $roleStmt->execute([$_SESSION['role'], $user_id]);

                // Refresh profile data
                $stmt = $conn->prepare("SELECT * FROM provider_profiles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $profile = $stmt->fetch();
                $isEdit = true;

            } catch (PDOException $e) {
                error_log("Provider registration error: " . $e->getMessage());
                $error = "A server error occurred. Please try again.";
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Get updated profile if it exists
if (!isset($profile) || empty($profile)) {
    $stmt = $conn->prepare("SELECT * FROM provider_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    $isEdit = ($profile !== false);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Edit' : 'Complete'; ?> Provider Profile — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../dashboard/dashboard.css">
    <style>
        .form-section {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            margin-bottom: 24px;
            background: var(--light);
        }
        .form-section h3 {
            margin-bottom: 16px;
            font-size: 1.1rem;
            color: var(--dark);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-row .full-width {
            grid-column: 1 / -1;
        }
        .file-upload-box {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 24px;
            text-align: center;
            transition: border-color var(--transition);
            cursor: pointer;
        }
        .file-upload-box:hover {
            border-color: var(--primary);
        }
        .file-upload-box input[type="file"] {
            display: none;
        }
        .file-upload-box .upload-icon {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 8px;
        }
        .file-upload-box .upload-text {
            color: var(--muted);
            font-size: 0.95rem;
        }
        .file-preview {
            margin-top: 12px;
            padding: 8px 12px;
            background: var(--white);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .file-preview img {
            max-width: 100px;
            max-height: 60px;
            border-radius: 4px;
            object-fit: cover;
        }
        .file-preview .file-name {
            flex: 1;
            color: var(--dark);
            font-size: 0.9rem;
        }
        .verification-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 0.85rem;
        }
        .verification-badge.verified {
            background: rgba(0, 200, 150, 0.12);
            color: var(--primary-dark);
        }
        .verification-badge.pending {
            background: rgba(245, 158, 11, 0.12);
            color: #B45309;
        }
        .verification-badge.rejected {
            background: rgba(239, 68, 68, 0.12);
            color: #B91C1C;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div id="preloader" role="status" aria-label="Loading">
    <div class="pulse-ring"></div>
    <svg class="heartbeat-svg" viewBox="0 0 300 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <polyline points="0,40 60,40 80,10 100,70 120,5 140,75 160,40 300,40" fill="none" stroke="#00C896" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <p class="preload-text">Care Connect SL</p>
</div>

<header role="banner">
    <div class="nav-inner">
        <a href="../index.html" class="logo" aria-label="Care Connect SL Home">
            <span class="logo-icon" aria-hidden="true">❤️</span> Care<span class="accent">Connect</span> SL
        </a>
        <nav aria-label="Main navigation">
            <ul class="nav-links" role="menubar">
                <li><a href="../index.html" role="menuitem">Home</a></li>
                <li><a href="../pages/doctors.php" role="menuitem">Find Care</a></li>
                <li><a href="../pages/hospitals.html" role="menuitem">Clinics</a></li>
                <li><a href="../pages/referral.html" role="menuitem">New Referral</a></li>
            </ul>
        </nav>
        <div class="nav-actions">
            <span style="color: var(--muted); font-size: 0.9rem;">👨‍⚕️ <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="btn-ghost">Logout</a>
        </div>
    </div>
</header>

<main class="dashboard-main" role="main">
    <div class="dashboard-container">
        <div class="profile-container" style="max-width: 900px; margin: 0 auto;">

            <!-- Status Banner -->
            <?php if (isset($success)): ?>
                <div class="form-message success">✅ <?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="form-message error">❌ <?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Header -->
            <section class="profile-header" style="margin-bottom: 32px;">
                <div>
                    <h1><?php echo $isEdit ? 'Edit Your' : 'Complete Your'; ?> Provider Profile</h1>
                    <p style="color: var(--muted);">
                        <?php if ($isEdit && isset($profile['verification_status'])): ?>
                            Current Status: 
                            <span class="verification-badge <?php echo $profile['verification_status']; ?>">
                                <?php echo ucfirst($profile['verification_status']); ?>
                            </span>
                        <?php else: ?>
                            Please provide your professional details for verification.
                        <?php endif; ?>
                    </p>
                    <?php if ($isEdit && isset($profile['verification_status']) && $profile['verification_status'] === 'verified'): ?>
                        <p style="color: var(--success); margin-top: 8px;">
                            ✅ Your profile is verified! Patients can now find you.
                        </p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Form -->
            <form action="provider-registration.php" method="POST" enctype="multipart/form-data" class="form-card" style="padding: 36px 40px;">

                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <!-- Professional Information -->
                <div class="form-section">
                    <h3>👨‍⚕️ Professional Information</h3>

                    <div class="form-row">
                        <div>
                            <label for="specialty">Specialty *</label>
                            <select id="specialty" name="specialty" required>
                                <option value="">Select your specialty</option>
                                <option value="General Medicine" <?php echo (isset($profile['specialty']) && $profile['specialty'] === 'General Medicine') ? 'selected' : ''; ?>>General Medicine</option>
                                <option value="Maternal & Child Health" <?php echo (isset($profile['specialty']) && $profile['specialty'] === 'Maternal & Child Health') ? 'selected' : ''; ?>>Maternal & Child Health</option>
                                <option value="Pediatrics" <?php echo (isset($profile['specialty']) && $profile['specialty'] === 'Pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                                <option value="Emergency Medicine" <?php echo (isset($profile['specialty']) && $profile['specialty'] === 'Emergency Medicine') ? 'selected' : ''; ?>>Emergency Medicine</option>
                                <option value="Surgery" <?php echo (isset($profile['specialty']) && $profile['specialty'] === 'Surgery') ? 'selected' : ''; ?>>Surgery</option>
                                <option value="Community Health" <?php echo (isset($profile['specialty']) && $profile['specialty'] === 'Community Health') ? 'selected' : ''; ?>>Community Health</option>
                                <option value="Nursing" <?php echo (isset($profile['specialty']) && $profile['specialty'] === 'Nursing') ? 'selected' : ''; ?>>Nursing</option>
                                <option value="Pharmacy" <?php echo (isset($profile['specialty']) && $profile['specialty'] === 'Pharmacy') ? 'selected' : ''; ?>>Pharmacy</option>
                                <option value="Other" <?php echo (isset($profile['specialty']) && $profile['specialty'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div>
                            <label for="experience_years">Years of Experience *</label>
                            <input type="number" 
                                   id="experience_years" 
                                   name="experience_years" 
                                   placeholder="e.g., 5" 
                                   min="0" 
                                   max="60"
                                   value="<?php echo $profile['experience_years'] ?? ''; ?>"
                                   required>
                        </div>
                    </div>

                    <div>
                        <label for="qualifications">Qualifications / Certifications *</label>
                        <textarea id="qualifications" 
                                  name="qualifications" 
                                  placeholder="e.g., MBBS, MPH, RN, BSc Nursing, etc."
                                  rows="3"
                                  required><?php echo htmlspecialchars($profile['qualifications'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label for="bio">Professional Bio / About You</label>
                        <textarea id="bio" 
                                  name="bio" 
                                  placeholder="Tell patients about yourself, your experience, and approach to care..."
                                  rows="4"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Clinic Information -->
                <div class="form-section">
                    <h3>🏥 Clinic / Practice Information</h3>

                    <div class="form-row">
                        <div>
                            <label for="clinic_name">Clinic / Practice Name *</label>
                            <input type="text" 
                                   id="clinic_name" 
                                   name="clinic_name" 
                                   placeholder="e.g., Freetown Medical Centre"
                                   value="<?php echo htmlspecialchars($profile['clinic_name'] ?? ''); ?>"
                                   required>
                        </div>

                        <div>
                            <label for="clinic_phone">Clinic Phone</label>
                            <input type="tel" 
                                   id="clinic_phone" 
                                   name="clinic_phone" 
                                   placeholder="e.g., +232 76 123 456"
                                   value="<?php echo htmlspecialchars($profile['clinic_phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label for="clinic_address">Clinic Address *</label>
                        <input type="text" 
                               id="clinic_address" 
                               name="clinic_address" 
                               placeholder="e.g., 123 Main Street, Freetown"
                               value="<?php echo htmlspecialchars($profile['clinic_address'] ?? ''); ?>"
                               required>
                    </div>

                    <div class="form-row">
                        <div>
                            <label for="consultation_fee">Consultation Fee (SLL)</label>
                            <input type="number" 
                                   id="consultation_fee" 
                                   name="consultation_fee" 
                                   placeholder="e.g., 150000"
                                   min="0"
                                   value="<?php echo $profile['consultation_fee'] ?? ''; ?>">
                        </div>

                        <div style="display: flex; align-items: center; gap: 12px; padding-top: 24px;">
                            <input type="checkbox" 
                                   id="is_accepting_patients" 
                                   name="is_accepting_patients" 
                                   value="1"
                                   <?php echo (isset($profile['is_accepting_patients']) && $profile['is_accepting_patients']) ? 'checked' : 'checked'; ?>>
                            <label for="is_accepting_patients" style="margin: 0; cursor: pointer;">
                                ✅ Accepting new patients
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Verification Documents -->
                <div class="form-section">
                    <h3>📋 Verification Documents</h3>
                    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 16px;">
                        These documents help us verify your identity and credentials. Your information is kept secure and confidential.
                    </p>

                    <div class="form-row">
                        <div>
                            <label for="national_id">National ID Number *</label>
                            <input type="text" 
                                   id="national_id" 
                                   name="national_id" 
                                   placeholder="e.g., SL-12345678"
                                   value="<?php echo htmlspecialchars($profile['national_id'] ?? ''); ?>"
                                   required>
                            <small style="color: var(--muted); font-size: 0.8rem;">National ID or Passport Number</small>
                        </div>

                        <div>
                            <label for="medical_license">Medical License Number *</label>
                            <input type="text" 
                                   id="medical_license" 
                                   name="medical_license" 
                                   placeholder="e.g., MLC-2024-12345"
                                   value="<?php echo htmlspecialchars($profile['medical_license'] ?? ''); ?>"
                                   required>
                            <small style="color: var(--muted); font-size: 0.8rem;">Registration number with medical council</small>
                        </div>
                    </div>

                    <!-- File Uploads -->
                    <div class="form-row">
                        <div class="full-width">
                            <label>Profile Photo</label>
                            <div class="file-upload-box" onclick="document.getElementById('profile_photo').click()">
                                <span class="upload-icon">📸</span>
                                <span class="upload-text">Click to upload profile photo</span>
                                <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
                            </div>
                            <?php if (!empty($profile['profile_photo'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo $profile['profile_photo']; ?>" alt="Profile photo">
                                    <span class="file-name">Current photo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-row">
                        <div>
                            <label>National ID / Passport Photo *</label>
                            <div class="file-upload-box" onclick="document.getElementById('id_photo').click()">
                                <span class="upload-icon">🪪</span>
                                <span class="upload-text">Click to upload ID photo</span>
                                <input type="file" id="id_photo" name="id_photo" accept="image/*" required <?php echo (!empty($profile['id_photo'])) ? '' : 'required'; ?>>
                            </div>
                            <?php if (!empty($profile['id_photo'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo $profile['id_photo']; ?>" alt="ID photo">
                                    <span class="file-name">Current ID photo</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label>Medical License / Certificate Photo *</label>
                            <div class="file-upload-box" onclick="document.getElementById('license_photo').click()">
                                <span class="upload-icon">📜</span>
                                <span class="upload-text">Click to upload license photo</span>
                                <input type="file" id="license_photo" name="license_photo" accept="image/*" required <?php echo (!empty($profile['license_photo'])) ? '' : 'required'; ?>>
                            </div>
                            <?php if (!empty($profile['license_photo'])): ?>
                                <div class="file-preview">
                                    <img src="../<?php echo $profile['license_photo']; ?>" alt="License photo">
                                    <span class="file-name">Current license photo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 16px; font-size: 1.1rem;">
                    <?php echo $isEdit ? 'Update Profile' : 'Submit for Verification'; ?>
                </button>

                <?php if ($isEdit && isset($profile['verification_status']) && $profile['verification_status'] === 'pending'): ?>
                    <p style="text-align: center; margin-top: 12px; color: var(--muted); font-size: 0.9rem;">
                        ⏳ Your profile is pending review. You'll receive an email once verified.
                    </p>
                <?php endif; ?>
            </form>
        </div>
    </div>
</main>

<footer class="site-footer" role="contentinfo">
    <div class="footer-grid container">
        <div>
            <a href="../index.html" class="logo" aria-label="Care Connect SL Home">Care<span class="accent">Connect</span> SL</a>
            <p>Home-based care referrals and clinic coordination across Sierra Leone.</p>
        </div>
        <div>
            <h3>Quick links</h3>
            <ul class="footer-links">
                <li><a href="../index.html">Home</a></li>
                <li><a href="../pages/referral.html">New Referral</a></li>
                <li><a href="../privacy.html">Privacy Policy</a></li>
            </ul>
        </div>
        <div>
            <h3>Contact</h3>
            <p><a href="mailto:hello@careconnect.sl">hello@careconnect.sl</a></p>
            <p><a href="tel:+23276000000">+232 76 000 000</a></p>
        </div>
    </div>
    <p class="footer-note">&copy; 2026 Care Connect SL. All rights reserved.</p>
</footer>

<script src="../js/main.js"></script>

<script>
// File upload preview
document.querySelectorAll('.file-upload-box input[type="file"]').forEach(function(input) {
    input.addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = this.parentElement.parentElement.querySelector('.file-preview');
                if (preview) {
                    preview.querySelector('img').src = e.target.result;
                    preview.querySelector('.file-name').textContent = file.name;
                } else {
                    const newPreview = document.createElement('div');
                    newPreview.className = 'file-preview';
                    newPreview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <span class="file-name">${file.name}</span>
                    `;
                    this.parentElement.parentElement.appendChild(newPreview);
                }
            }.bind(this);
            reader.readAsDataURL(file);
        }
    });
});
</script>

</body>
</html>