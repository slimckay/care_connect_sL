<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// Ensure provider profile row exists
try {
    $check = $conn->prepare("SELECT id FROM provider_profiles WHERE user_id = ? LIMIT 1");
    $check->execute([$user_id]);
    if (!$check->fetch()) {
        $conn->prepare("INSERT INTO provider_profiles (user_id, verification_status, created_at) VALUES (?, 'pending', NOW())")
             ->execute([$user_id]);
    }
} catch (Exception $e) {
    // table/columns may vary; continue
}

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($ext, $allowed)) {
            $error = 'Photo must be JPG, PNG, WEBP, or GIF.';
        } elseif ($_FILES['profile_photo']['size'] > $maxSize) {
            $error = 'Photo is too large. Max size is 5MB.';
        } else {
            $filename = 'provider_' . $user_id . '_' . time() . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
                $photoPath = 'uploads/profile/' . $filename;
                try {
                    $conn->prepare("UPDATE provider_profiles SET profile_photo = ?, updated_at = NOW() WHERE user_id = ?")
                         ->execute([$photoPath, $user_id]);
                    $message = 'Profile photo updated successfully.';
                } catch (Exception $e) {
                    $error = 'Photo saved but database update failed.';
                }
            } else {
                $error = 'Could not upload photo. Please try again.';
            }
        }
    } else {
        $error = 'Please choose a photo to upload.';
    }
}

// Handle profile details update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $specialty = trim($_POST['specialty'] ?? '');
    $qualifications = trim($_POST['qualifications'] ?? '');
    $experience = (int)($_POST['experience_years'] ?? 0);
    $clinic_name = trim($_POST['clinic_name'] ?? '');
    $clinic_address = trim($_POST['clinic_address'] ?? '');
    $clinic_phone = trim($_POST['clinic_phone'] ?? '');
    $consultation_fee = $_POST['consultation_fee'] !== '' ? (float)$_POST['consultation_fee'] : null;
    $is_accepting = isset($_POST['is_accepting_patients']) ? 1 : 0;

    if ($specialty === '') {
        $error = 'Specialty is required for verification.';
    } else {
        try {
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
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([
                $specialty,
                $qualifications,
                $experience,
                $clinic_name,
                $clinic_address,
                $clinic_phone,
                $consultation_fee,
                $is_accepting,
                $user_id
            ]);
            $message = 'Profile details saved successfully.';
        } catch (Exception $e) {
            error_log('Provider profile update: ' . $e->getMessage());
            $error = 'Could not save profile. Please try again.';
        }
    }
}

// Handle verification document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_docs') {
    $uploadDir = __DIR__ . '/../uploads/verification/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $documentPaths = [];
    if (!empty($_FILES['documents']['name'][0])) {
        foreach ($_FILES['documents']['name'] as $key => $filename) {
            if ($_FILES['documents']['error'][$key] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            if (!in_array($ext, $allowed)) continue;
            if ($_FILES['documents']['size'][$key] > 8 * 1024 * 1024) continue;

            $newName = 'doc_' . $user_id . '_' . time() . '_' . $key . '.' . $ext;
            $destination = $uploadDir . $newName;
            if (move_uploaded_file($_FILES['documents']['tmp_name'][$key], $destination)) {
                $documentPaths[] = 'uploads/verification/' . $newName;
            }
        }
    }

    if (empty($documentPaths)) {
        $error = 'Please upload at least one valid document (JPG, PNG, or PDF, max 8MB each).';
    } else {
        try {
            // Keep previous docs if any
            $prev = $conn->prepare("SELECT verification_documents FROM provider_profiles WHERE user_id = ?");
            $prev->execute([$user_id]);
            $row = $prev->fetch();
            $existing = array_filter(array_map('trim', explode(',', $row['verification_documents'] ?? '')));
            $all = array_values(array_unique(array_merge($existing, $documentPaths)));
            $documentsString = implode(',', $all);

            $conn->prepare("
                UPDATE provider_profiles
                SET verification_documents = ?,
                    verification_status = 'pending',
                    updated_at = NOW()
                WHERE user_id = ?
            ")->execute([$documentsString, $user_id]);

            $message = 'Documents uploaded. Your verification is now pending review.';
        } catch (Exception $e) {
            error_log('Doc upload: ' . $e->getMessage());
            $error = 'Could not save documents. Please try again.';
        }
    }
}

// Load profile
$profile = null;
try {
    $stmt = $conn->prepare("
        SELECT p.*, u.name, u.email, u.role
        FROM provider_profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
} catch (Exception $e) {
    $profile = null;
}

if (!$profile) {
    try {
        $u = $conn->prepare("SELECT name, email, role FROM users WHERE id = ? LIMIT 1");
        $u->execute([$user_id]);
        $user = $u->fetch() ?: [];
    } catch (Exception $e) {
        $user = [];
    }
    $name = $user['name'] ?? ($_SESSION['user_name'] ?? 'Provider');
    $email = $user['email'] ?? '';
    $role = $user['role'] ?? 'doctor';
    $photo = '';
    $verificationStatus = 'pending';
    $specialty = '';
    $experience = 0;
    $qualifications = '';
    $clinic_name = '';
    $clinic_address = '';
    $clinic_phone = '';
    $consultation_fee = '';
    $is_accepting = 1;
    $docs = [];
} else {
    $name = $profile['name'] ?? 'Provider';
    $email = $profile['email'] ?? '';
    $role = $profile['role'] ?? 'doctor';
    $photo = $profile['profile_photo'] ?? '';
    $verificationStatus = $profile['verification_status'] ?? 'pending';
    $specialty = $profile['specialty'] ?? '';
    $experience = (int)($profile['experience_years'] ?? 0);
    $qualifications = $profile['qualifications'] ?? '';
    $clinic_name = $profile['clinic_name'] ?? '';
    $clinic_address = $profile['clinic_address'] ?? '';
    $clinic_phone = $profile['clinic_phone'] ?? '';
    $consultation_fee = $profile['consultation_fee'] ?? '';
    $is_accepting = (int)($profile['is_accepting_patients'] ?? 1);
    $docs = array_filter(array_map('trim', explode(',', $profile['verification_documents'] ?? '')));
}

$statusLabel = ucfirst($verificationStatus);
$statusColor = match ($verificationStatus) {
    'verified' => '#16A34A',
    'rejected' => '#DC2626',
    default => '#CA8A04',
};
$statusBg = match ($verificationStatus) {
    'verified' => '#F0FDF4',
    'rejected' => '#FEF2F2',
    default => '#FFFBEB',
};
$statusBorder = match ($verificationStatus) {
    'verified' => '#16A34A',
    'rejected' => '#DC2626',
    default => '#EAB308',
};
$statusIcon = match ($verificationStatus) {
    'verified' => '✅',
    'rejected' => '❌',
    default => '⏳',
};

$photoUrl = ($photo && file_exists(__DIR__ . '/../' . $photo)) ? '../' . $photo : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Provider Dashboard — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    body { background: #F8FAFC; }
    .pd-wrap { max-width: 1080px; margin: 28px auto; padding: 0 16px 48px; }
    .pd-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:20px; }
    .pd-header h1 { margin:0 0 6px; font-size:1.7rem; color:#0F1C3A !important; }
    .pd-header p { margin:0; color:#64748B !important; }

    .alert {
      display:flex; gap:10px; align-items:flex-start;
      padding:14px 16px; border-radius:12px; margin-bottom:18px;
      border:1px solid transparent;
    }
    .alert.success { background:#ECFDF5; border-color:#A7F3D0; color:#065F46; }
    .alert.error { background:#FEF2F2; border-color:#FECACA; color:#991B1B; }

    .status-banner {
      border-radius:14px; padding:16px 18px; margin-bottom:22px;
      border-left:5px solid <?= $statusBorder ?>;
      background: <?= $statusBg ?>;
    }
    .status-banner strong { display:block; margin-bottom:4px; color:#0F1C3A !important; }
    .status-banner span { color: <?= $statusColor ?> !important; font-weight:600; }

    .pd-grid { display:grid; grid-template-columns:1.15fr 0.85fr; gap:18px; margin-bottom:18px; }
    .card {
      background:#fff; border:1px solid #E5E7EB; border-radius:16px;
      padding:22px; box-shadow:0 6px 18px rgba(15,23,42,0.04);
    }
    .card h3 { margin:0 0 14px; font-size:1.05rem; color:#0F1C3A !important; }

    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-group { margin-bottom:14px; }
    .form-group.full { grid-column:1 / -1; }
    .form-group label { display:block; font-weight:600; font-size:0.88rem; margin-bottom:6px; color:#334155 !important; }
    .form-group input, .form-group textarea, .form-group select {
      width:100%; padding:11px 13px; border:2px solid #E5E7EB; border-radius:10px;
      font:inherit; color:#0F172A; background:#fff;
    }
    .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
      outline:none; border-color:#1EB53A;
    }
    .check-row { display:flex; align-items:center; gap:8px; margin-top:4px; }
    .check-row input { width:auto; }

    .photo-area { text-align:center; }
    .photo {
      width:120px; height:120px; border-radius:50%; object-fit:cover;
      border:3px solid #1EB53A; margin:0 auto 12px; display:block; background:#E2E8F0;
    }
    .photo-fallback {
      width:120px; height:120px; border-radius:50%; background:#E2E8F0; color:#64748B !important;
      display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-weight:600;
    }
    .file-hint { font-size:0.8rem; color:#64748B !important; margin:6px 0 12px; }

    .docs-list { list-style:none; padding:0; margin:0 0 14px; }
    .docs-list li {
      display:flex; justify-content:space-between; gap:10px; align-items:center;
      padding:10px 12px; border:1px solid #E5E7EB; border-radius:10px; margin-bottom:8px;
      font-size:0.9rem;
    }
    .docs-list a { color:#1EB53A; font-weight:600; text-decoration:none; }

    .btn-main {
      display:inline-flex; align-items:center; justify-content:center;
      min-height:42px; padding:10px 18px; border-radius:999px; border:none;
      background:linear-gradient(135deg,#1EB53A,#15802A); color:#fff !important;
      font-weight:600; font-size:0.92rem; cursor:pointer; text-decoration:none;
    }
    .btn-outline {
      display:inline-flex; align-items:center; justify-content:center;
      min-height:42px; padding:10px 18px; border-radius:999px;
      border:2px solid #1EB53A; color:#1EB53A !important; background:transparent;
      font-weight:600; font-size:0.92rem; cursor:pointer; text-decoration:none;
    }
    .btn-main:disabled { opacity:0.7; cursor:not-allowed; }

    .actions { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-top:18px; }
    .action {
      background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:20px;
      box-shadow:0 6px 18px rgba(15,23,42,0.04);
    }
    .action h3 { margin:0 0 8px; color:#0F1C3A !important; font-size:1rem; }
    .action p { margin:0 0 14px; color:#64748B !important; font-size:0.92rem; line-height:1.5; }

    [data-theme="dark"] body { background:#0f172a; }
    [data-theme="dark"] .pd-header h1,
    [data-theme="dark"] .card h3,
    [data-theme="dark"] .action h3,
    [data-theme="dark"] .status-banner strong { color:#F8FAFC !important; }
    [data-theme="dark"] .pd-header p,
    [data-theme="dark"] .action p,
    [data-theme="dark"] .file-hint,
    [data-theme="dark"] .form-group label { color:#94A3B8 !important; }
    [data-theme="dark"] .card,
    [data-theme="dark"] .action { background:#1e293b; border-color:#334155; }
    [data-theme="dark"] .form-group input,
    [data-theme="dark"] .form-group textarea,
    [data-theme="dark"] .form-group select {
      background:#0f172a; color:#e2e8f0; border-color:#334155;
    }
    [data-theme="dark"] .docs-list li { border-color:#334155; color:#e2e8f0; }

    @media (max-width: 900px) {
      .pd-grid, .actions, .form-grid { grid-template-columns:1fr; }
      .pd-header { flex-direction:column; }
    }
  </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="../index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <button onclick="toggleDarkMode()" class="dark-toggle">🌓</button>
      <a href="../logout.php" class="btn-ghost">Logout</a>
    </div>
  </div>
</header>

<main class="pd-wrap">
  <div class="pd-header">
    <div>
      <h1>Provider Dashboard</h1>
      <p>Welcome back, <?= htmlspecialchars($name) ?> · <?= htmlspecialchars(ucfirst($role)) ?></p>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="status-banner">
    <strong>Verification Status</strong>
    <span><?= $statusIcon ?> <?= htmlspecialchars($statusLabel) ?></span>
    <?php if ($verificationStatus === 'verified'): ?>
      <p style="margin:8px 0 0; color:#166534 !important;">You can receive referrals and appear on public listings.</p>
    <?php elseif ($verificationStatus === 'rejected'): ?>
      <p style="margin:8px 0 0; color:#991B1B !important;">Your submission was rejected. Update your profile and documents, then resubmit below.</p>
    <?php else: ?>
      <p style="margin:8px 0 0; color:#92400E !important;">Complete your profile and upload documents. Public listing stays hidden until verified.</p>
    <?php endif; ?>
  </div>

  <div class="pd-grid">
    <!-- Profile form -->
    <div class="card">
      <h3>Profile & Practice Details</h3>
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-grid">
          <div class="form-group">
            <label for="specialty">Specialty / Services *</label>
            <input type="text" id="specialty" name="specialty" required value="<?= htmlspecialchars($specialty) ?>" placeholder="e.g. General Medicine">
          </div>
          <div class="form-group">
            <label for="experience_years">Years of Experience</label>
            <input type="number" id="experience_years" name="experience_years" min="0" max="60" value="<?= (int)$experience ?>">
          </div>
          <div class="form-group full">
            <label for="qualifications">Qualifications</label>
            <input type="text" id="qualifications" name="qualifications" value="<?= htmlspecialchars($qualifications) ?>" placeholder="e.g. MBBS, MPH">
          </div>
          <div class="form-group">
            <label for="clinic_name">Clinic / Hospital Name</label>
            <input type="text" id="clinic_name" name="clinic_name" value="<?= htmlspecialchars($clinic_name) ?>" placeholder="e.g. Kissy Health Centre">
          </div>
          <div class="form-group">
            <label for="clinic_phone">Clinic Phone</label>
            <input type="text" id="clinic_phone" name="clinic_phone" value="<?= htmlspecialchars($clinic_phone) ?>" placeholder="+232 ...">
          </div>
          <div class="form-group full">
            <label for="clinic_address">Clinic Address</label>
            <input type="text" id="clinic_address" name="clinic_address" value="<?= htmlspecialchars($clinic_address) ?>" placeholder="Street, area, district">
          </div>
          <div class="form-group">
            <label for="consultation_fee">Consultation Fee (SLL, optional)</label>
            <input type="number" id="consultation_fee" name="consultation_fee" min="0" step="1000" value="<?= htmlspecialchars((string)$consultation_fee) ?>">
          </div>
          <div class="form-group">
            <label>Accepting Patients</label>
            <div class="check-row">
              <input type="checkbox" id="is_accepting_patients" name="is_accepting_patients" <?= $is_accepting ? 'checked' : '' ?>>
              <label for="is_accepting_patients" style="margin:0; font-weight:500;">Yes, I am accepting new patients</label>
            </div>
          </div>
        </div>
        <button type="submit" class="btn-main">Save Profile</button>
      </form>
    </div>

    <!-- Photo upload -->
    <div class="card photo-area">
      <h3>Profile Photo</h3>
      <?php if ($photoUrl): ?>
        <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Profile photo" class="photo" id="photoPreview">
      <?php else: ?>
        <div class="photo-fallback" id="photoFallback">No Photo</div>
        <img src="" alt="Preview" class="photo" id="photoPreview" style="display:none;">
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="photoForm">
        <input type="hidden" name="action" value="upload_photo">
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required>
        <p class="file-hint">JPG, PNG or WEBP · max 5MB</p>
        <button type="submit" class="btn-main" style="width:100%;" id="photoBtn">Upload Photo</button>
      </form>
    </div>
  </div>

  <!-- Documents -->
  <div class="card" style="margin-bottom:18px;">
    <h3>Verification Documents</h3>
    <p class="file-hint" style="margin-top:0;">Upload license, national ID, certificates, or clinic registration. Multiple files allowed.</p>

    <?php if (!empty($docs)): ?>
      <ul class="docs-list">
        <?php foreach ($docs as $i => $doc): ?>
          <li>
            <span>📄 Document <?= $i + 1 ?></span>
            <a href="../<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener">View</a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="file-hint">No documents uploaded yet.</p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_docs">
      <div class="form-group">
        <label for="documents">Add documents</label>
        <input type="file" id="documents" name="documents[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" required>
        <p class="file-hint">PDF or images · max 8MB each</p>
      </div>
      <button type="submit" class="btn-main">Upload & Submit for Review</button>
    </form>
  </div>

  <div class="actions">
    <div class="action">
      <h3>📋 Referrals</h3>
      <p>Review incoming patient referrals assigned to you.</p>
      <a href="../admin/manage-referrals.php" class="btn-main">View Referrals</a>
    </div>
    <div class="action">
      <h3>🏥 Public Listing</h3>
      <p>See the Find Care page where verified providers appear.</p>
      <a href="../pages/doctors.php" class="btn-outline">Open Find Care</a>
    </div>
    <div class="action">
      <h3>💳 Payments</h3>
      <p>Manage how patients can pay for consultations.</p>
      <a href="../provider-payment-settings.php" class="btn-outline">Payment Settings</a>
    </div>
  </div>
</main>

<script src="../js/dark-mode.js"></script>
<script>
  // Live photo preview before upload
  const input = document.getElementById('profile_photo');
  const preview = document.getElementById('photoPreview');
  const fallback = document.getElementById('photoFallback');
  const photoBtn = document.getElementById('photoBtn');

  if (input) {
    input.addEventListener('change', function () {
      const file = this.files && this.files[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      if (preview) {
        preview.src = url;
        preview.style.display = 'block';
      }
      if (fallback) fallback.style.display = 'none';
    });
  }

  const photoForm = document.getElementById('photoForm');
  if (photoForm) {
    photoForm.addEventListener('submit', function () {
      if (photoBtn) {
        photoBtn.disabled = true;
        photoBtn.textContent = 'Uploading...';
      }
    });
  }
</script>
</body>
</html>
