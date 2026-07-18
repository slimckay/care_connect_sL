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
        $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $filename = 'provider_' . $user_id . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
            $photoPath = 'uploads/profile/' . $filename;
            $conn->prepare("UPDATE provider_profiles SET profile_photo = ? WHERE user_id = ?")
                 ->execute([$photoPath, $user_id]);
        }
    }
}

$stmt = $conn->prepare("
    SELECT p.*, u.name, u.email 
    FROM provider_profiles p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id = ?
");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

$photo = $profile['profile_photo'] ?? '';
$verificationStatus = $profile['verification_status'] ?? 'pending';
$name = $profile['name'] ?? ($_SESSION['user_name'] ?? 'Provider');
$email = $profile['email'] ?? '';
$specialty = $profile['specialty'] ?? 'Not set';
$experience = $profile['experience_years'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Provider Dashboard — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    .dash-wrap { max-width: 1100px; margin: 30px auto; padding: 0 20px; color: #1F2937; }
    .dash-wrap h1 { color: #0F1C3A !important; margin-bottom: 8px; }
    .dash-subtitle { color: #64748B !important; margin-bottom: 28px; }

    .status-card {
      border-radius: 14px;
      padding: 20px 22px;
      margin-bottom: 24px;
      border-left: 5px solid #eab308;
      background: #fefce8;
    }
    .status-card h3 { margin: 0 0 8px 0; color: #0F1C3A !important; }
    .status-card p { margin: 0; color: #1F2937 !important; }

    .grid-2 {
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      gap: 20px;
      margin-bottom: 24px;
    }

    .panel {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.05);
    }
    .panel h3 {
      margin: 0 0 16px 0;
      color: #0F1C3A !important;
      font-size: 1.1rem;
    }
    .panel p { color: #475569 !important; }

    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid #f1f5f9;
      color: #1F2937 !important;
    }
    .info-row span:first-child { color: #64748B !important; }

    .action-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
    }
    .action-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 14px;
      padding: 22px;
    }
    .action-card h3 { color: #0F1C3A !important; margin: 0 0 8px 0; }
    .action-card p { color: #64748B !important; margin: 0 0 14px 0; }

    .photo-box {
      width: 110px;
      height: 110px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #1EB53A;
      margin-bottom: 14px;
    }
    .photo-placeholder {
      width: 110px;
      height: 110px;
      border-radius: 50%;
      background: #e5e7eb;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748B !important;
      margin-bottom: 14px;
    }

    [data-theme="dark"] .dash-wrap,
    [data-theme="dark"] .dash-wrap h1 { color: #f8fafc !important; }
    [data-theme="dark"] .dash-subtitle { color: #94A3B8 !important; }
    [data-theme="dark"] .panel,
    [data-theme="dark"] .action-card {
      background: #1e293b !important;
      border-color: #334155 !important;
    }
    [data-theme="dark"] .panel h3,
    [data-theme="dark"] .action-card h3,
    [data-theme="dark"] .status-card h3 { color: #f8fafc !important; }
    [data-theme="dark"] .panel p,
    [data-theme="dark"] .action-card p,
    [data-theme="dark"] .info-row { color: #e2e8f0 !important; }
    [data-theme="dark"] .status-card { background: #422006; }

    @media (max-width: 768px) {
      .grid-2 { grid-template-columns: 1fr; }
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

<main class="dash-wrap">
  <h1>Welcome, <?= htmlspecialchars($name) ?></h1>
  <p class="dash-subtitle">Manage your provider profile, verification, and referrals</p>

  <div class="status-card">
    <h3>Verification Status</h3>
    <?php if ($verificationStatus === 'verified'): ?>
      <p style="color:#16A34A !important; font-weight:600;">✅ Verified — You can receive referrals and appear publicly.</p>
    <?php elseif ($verificationStatus === 'rejected'): ?>
      <p style="color:#DC2626 !important; font-weight:600;">❌ Rejected. <a href="reapply.php" style="color:#1EB53A;">Reapply here</a></p>
    <?php else: ?>
      <p style="color:#CA8A04 !important; font-weight:600;">⏳ Pending Verification — Your documents are under review.</p>
    <?php endif; ?>
  </div>

  <div class="grid-2">
    <div class="panel">
      <h3>Profile Overview</h3>
      <div class="info-row"><span>Name</span><span><?= htmlspecialchars($name) ?></span></div>
      <div class="info-row"><span>Email</span><span><?= htmlspecialchars($email) ?></span></div>
      <div class="info-row"><span>Specialty</span><span><?= htmlspecialchars($specialty) ?></span></div>
      <div class="info-row"><span>Experience</span><span><?= (int)$experience ?> years</span></div>
      <div class="info-row" style="border-bottom:none;"><span>Status</span><span><?= htmlspecialchars(ucfirst($verificationStatus)) ?></span></div>
    </div>

    <div class="panel">
      <h3>Profile Photo</h3>
      <?php if ($photo && file_exists('../' . $photo)): ?>
        <img src="../<?= htmlspecialchars($photo) ?>" alt="Profile Photo" class="photo-box">
      <?php else: ?>
        <div class="photo-placeholder">No Photo</div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <input type="file" name="profile_photo" accept="image/*" required>
        <button type="submit" class="btn-primary" style="margin-top:12px; padding:10px 18px;">Upload Photo</button>
      </form>
    </div>
  </div>

  <div class="action-grid">
    <div class="action-card">
      <h3>📋 My Referrals</h3>
      <p>View and manage incoming patient referrals.</p>
      <a href="#" class="btn-primary" style="padding:10px 18px; text-decoration:none; display:inline-block;">View Referrals</a>
    </div>

    <div class="action-card">
      <h3>📄 Documents</h3>
      <p>Update verification documents if needed.</p>
      <a href="reapply.php" class="btn-ghost" style="padding:10px 18px; text-decoration:none; display:inline-block;">Manage Documents</a>
    </div>

    <div class="action-card">
      <h3>🏠 Public Profile</h3>
      <p>See how you appear after verification.</p>
      <a href="../pages/doctors.php" class="btn-ghost" style="padding:10px 18px; text-decoration:none; display:inline-block;">Find Care Page</a>
    </div>
  </div>
</main>

<script src="../js/dark-mode.js"></script>
</body>
</html>