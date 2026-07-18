<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $uploadDir = __DIR__ . '/../uploads/profile/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if ($_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename = 'provider_' . $user_id . '_' . time() . '.' . $ext;
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
                $photoPath = 'uploads/profile/' . $filename;
                $conn->prepare("UPDATE provider_profiles SET profile_photo = ? WHERE user_id = ?")
                     ->execute([$photoPath, $user_id]);
            }
        }
    }
}

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
        $user = $u->fetch();
    } catch (Exception $e) {
        $user = null;
    }
    $name = $user['name'] ?? ($_SESSION['user_name'] ?? 'Provider');
    $email = $user['email'] ?? '';
    $role = $user['role'] ?? 'doctor';
    $photo = '';
    $verificationStatus = 'pending';
    $specialty = 'Not set';
    $experience = 0;
} else {
    $name = $profile['name'] ?? 'Provider';
    $email = $profile['email'] ?? '';
    $role = $profile['role'] ?? 'doctor';
    $photo = $profile['profile_photo'] ?? '';
    $verificationStatus = $profile['verification_status'] ?? 'pending';
    $specialty = $profile['specialty'] ?? 'Not set';
    $experience = $profile['experience_years'] ?? 0;
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
    .pd-wrap { max-width: 1080px; margin: 28px auto; padding: 0 16px 40px; }

    .pd-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 24px;
    }
    .pd-header h1 {
      margin: 0 0 6px;
      font-size: 1.7rem;
      color: #0F1C3A !important;
    }
    .pd-header p {
      margin: 0;
      color: #64748B !important;
    }

    .status-banner {
      border-radius: 14px;
      padding: 16px 18px;
      margin-bottom: 22px;
      border-left: 5px solid <?= $statusBorder ?>;
      background: <?= $statusBg ?>;
    }
    .status-banner strong {
      display: block;
      margin-bottom: 4px;
      color: #0F1C3A !important;
    }
    .status-banner span {
      color: <?= $statusColor ?> !important;
      font-weight: 600;
    }

    .pd-grid {
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      gap: 18px;
      margin-bottom: 18px;
    }

    .card {
      background: #fff;
      border: 1px solid #E5E7EB;
      border-radius: 16px;
      padding: 22px;
      box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    }
    .card h3 {
      margin: 0 0 14px;
      font-size: 1.05rem;
      color: #0F1C3A !important;
    }

    .meta-row {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 11px 0;
      border-bottom: 1px solid #F1F5F9;
    }
    .meta-row:last-child { border-bottom: none; }
    .meta-row .label { color: #64748B !important; }
    .meta-row .value { color: #0F172A !important; font-weight: 600; text-align: right; }

    .photo-area { text-align: center; }
    .photo {
      width: 112px;
      height: 112px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #1EB53A;
      margin: 0 auto 12px;
      display: block;
    }
    .photo-fallback {
      width: 112px;
      height: 112px;
      border-radius: 50%;
      background: #E2E8F0;
      color: #64748B !important;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 12px;
      font-weight: 600;
    }

    .actions {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }
    .action {
      background: #fff;
      border: 1px solid #E5E7EB;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
    }
    .action h3 {
      margin: 0 0 8px;
      color: #0F1C3A !important;
      font-size: 1rem;
    }
    .action p {
      margin: 0 0 14px;
      color: #64748B !important;
      font-size: 0.92rem;
      line-height: 1.5;
    }

    .btn-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 42px;
      padding: 8px 16px;
      border-radius: 999px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9rem;
    }
    .btn-main {
      background: linear-gradient(135deg, #1EB53A, #15802A);
      color: #fff !important;
    }
    .btn-outline {
      border: 2px solid #1EB53A;
      color: #1EB53A !important;
      background: transparent;
    }

    [data-theme="dark"] body { background: #0f172a; }
    [data-theme="dark"] .pd-header h1,
    [data-theme="dark"] .card h3,
    [data-theme="dark"] .action h3,
    [data-theme="dark"] .status-banner strong { color: #F8FAFC !important; }
    [data-theme="dark"] .pd-header p,
    [data-theme="dark"] .action p,
    [data-theme="dark"] .meta-row .label { color: #94A3B8 !important; }
    [data-theme="dark"] .card,
    [data-theme="dark"] .action {
      background: #1e293b;
      border-color: #334155;
    }
    [data-theme="dark"] .meta-row { border-color: #334155; }
    [data-theme="dark"] .meta-row .value { color: #E2E8F0 !important; }

    @media (max-width: 900px) {
      .pd-grid, .actions { grid-template-columns: 1fr; }
      .pd-header { flex-direction: column; }
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

  <div class="status-banner">
    <strong>Verification Status</strong>
    <span><?= $statusIcon ?> <?= htmlspecialchars($statusLabel) ?></span>
    <?php if ($verificationStatus === 'verified'): ?>
      <p style="margin:8px 0 0; color:#166534 !important;">You can receive referrals and appear on public listings.</p>
    <?php elseif ($verificationStatus === 'rejected'): ?>
      <p style="margin:8px 0 0; color:#991B1B !important;">Your submission was rejected. Please update documents and reapply.</p>
    <?php else: ?>
      <p style="margin:8px 0 0; color:#92400E !important;">Your documents are under review. Public listing stays hidden until verified.</p>
    <?php endif; ?>
  </div>

  <div class="pd-grid">
    <div class="card">
      <h3>Profile Overview</h3>
      <div class="meta-row"><span class="label">Full Name</span><span class="value"><?= htmlspecialchars($name) ?></span></div>
      <div class="meta-row"><span class="label">Email</span><span class="value"><?= htmlspecialchars($email) ?></span></div>
      <div class="meta-row"><span class="label">Specialty</span><span class="value"><?= htmlspecialchars($specialty) ?></span></div>
      <div class="meta-row"><span class="label">Experience</span><span class="value"><?= (int)$experience ?> years</span></div>
      <div class="meta-row"><span class="label">Account Type</span><span class="value"><?= htmlspecialchars(ucfirst($role)) ?></span></div>
    </div>

    <div class="card photo-area">
      <h3>Profile Photo</h3>
      <?php if ($photo && file_exists('../' . $photo)): ?>
        <img src="../<?= htmlspecialchars($photo) ?>" alt="Profile photo" class="photo">
      <?php else: ?>
        <div class="photo-fallback">No Photo</div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <input type="file" name="profile_photo" accept="image/*" required style="margin-bottom:12px;">
        <button type="submit" class="btn-link btn-main" style="border:none; cursor:pointer; width:100%;">Upload Photo</button>
      </form>
    </div>
  </div>

  <div class="actions">
    <div class="action">
      <h3>📋 Referrals</h3>
      <p>Review incoming patient referrals and respond quickly.</p>
      <a href="#" class="btn-link btn-main">View Referrals</a>
    </div>
    <div class="action">
      <h3>📄 Documents</h3>
      <p>Upload or update your verification documents.</p>
      <a href="reapply.php" class="btn-link btn-outline">Manage Documents</a>
    </div>
    <div class="action">
      <h3>🏥 Public Listing</h3>
      <p>See the public Find Care page where verified providers appear.</p>
      <a href="../pages/doctors.php" class="btn-link btn-outline">Open Find Care</a>
    </div>
  </div>
</main>

<script src="../js/dark-mode.js"></script>
</body>
</html>