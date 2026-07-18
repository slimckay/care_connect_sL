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

$stmt = $conn->prepare("
    SELECT p.*, u.name, u.email 
    FROM provider_profiles p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id = ?
");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

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
  </div>
</header>

<main style="max-width:1000px; margin:40px auto; padding:0 20px;">
  <h1>Welcome, <?= htmlspecialchars($profile['name'] ?? 'Provider') ?></h1>

  <!-- Verification Status -->
  <div style="background:#fefce8; border-left:5px solid #eab308; padding:20px; border-radius:12px; margin:30px 0;">
    <h3 style="margin:0 0 8px 0;">Verification Status</h3>

    <?php if ($verificationStatus === 'verified'): ?>
      <p style="color:#16A34A; font-weight:600; margin:0;">✅ Verified — You can now receive referrals and appear publicly.</p>

    <?php elseif ($verificationStatus === 'rejected'): ?>
      <p style="color:#DC2626; font-weight:600; margin:0;">❌ Your application was rejected.</p>
      <p style="margin:10px 0 0 0;">You can reapply with updated documents.</p>
      <a href="reapply.php" class="btn-primary" style="margin-top:12px; display:inline-block; padding:10px 24px; text-decoration:none;">Reapply Now</a>

    <?php else: ?>
      <p style="color:#CA8A04; font-weight:600; margin:0;">⏳ Pending Verification — Your documents are under review.</p>
      <p style="margin:8px 0 0 0; color:#64748B;">You will be notified once approved.</p>
    <?php endif; ?>
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

</body>
</html>