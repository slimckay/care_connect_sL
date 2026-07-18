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

// Handle photo upload
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

// Get profile data
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Provider Dashboard — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
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

<main style="max-width:1000px; margin:40px auto; padding:0 20px;">
  <h1>Welcome, <?= htmlspecialchars($profile['name'] ?? 'Provider') ?></h1>

  <!-- Verification Status -->
  <div style="background:#fefce8; border-left:5px solid #eab308; padding:20px; border-radius:12px; margin:30px 0;">
    <h3 style="margin:0 0 8px 0;">Verification Status</h3>
    <?php if ($verificationStatus === 'verified'): ?>
      <p style="color:#16A34A; font-weight:600; margin:0;">✅ Verified — You can now receive referrals.</p>
    <?php elseif ($verificationStatus === 'rejected'): ?>
      <p style="color:#DC2626; font-weight:600; margin:0;">❌ Rejected. <a href="reapply.php">Reapply here</a></p>
    <?php else: ?>
      <p style="color:#CA8A04; font-weight:600; margin:0;">⏳ Pending Verification</p>
    <?php endif; ?>
  </div>

  <!-- Profile Photo -->
  <div style="background:white; padding:24px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); margin-bottom:30px;">
    <h3 style="margin-bottom:16px;">Profile Photo</h3>
    
    <?php if ($photo && file_exists('../' . $photo)): ?>
      <img src="../<?= $photo ?>" alt="Profile Photo" style="width:120px; height:120px; object-fit:cover; border-radius:50%; border:3px solid #1EB53A; margin-bottom:16px;">
    <?php else: ?>
      <div style="width:120px; height:120px; background:#e5e7eb; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:16px; color:#64748B;">
        No Photo
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="profile_photo" accept="image/*" required>
      <button type="submit" class="btn-primary" style="margin-top:12px; padding:10px 20px;">Upload Photo</button>
    </form>
  </div>

  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:20px;">
    <div class="profile-card">
      <h3>📋 My Referrals</h3>
      <p>View and manage incoming referrals.</p>
      <a href="#" class="btn-primary" style="margin-top:12px; display:inline-block; padding:10px 20px; text-decoration:none;">View Referrals</a>
    </div>

    <div class="profile-card">
      <h3>📄 My Documents</h3>
      <p>Upload or update your verification documents.</p>
      <a href="#" class="btn-ghost" style="margin-top:12px; display:inline-block; padding:10px 20px; text-decoration:none;">Manage Documents</a>
    </div>
  </div>
</main>

<script src="../js/dark-mode.js"></script>
</body>
</html>