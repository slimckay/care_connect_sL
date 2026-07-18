<?php
// Start session to get user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database
require_once 'db.php';

$user_id = $_SESSION['user_id'];

// Handle profile update (POST)
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if (empty($name)) {
        $error = 'Full name is required.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, location = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $location, $user_id]);
            $_SESSION['user_name'] = $name;
            $message = '✅ Profile updated successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to update profile.';
        }
    }
}

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $user_name = $user['name'];
    $user_email = $user['email'];
    $user_phone = $user['phone'] ?? '';
    $user_location = $user['location'] ?? '';
    $user_picture = $user['profile_picture'] ?? '';
    $user_role = $user['role'] ?? 'patient';
    $user_created = $user['created_at'] ?? date('Y-m-d H:i:s');

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM referrals WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalReferrals = $stmt->fetch()['total'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as completed FROM referrals WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completedReferrals = $stmt->fetch()['completed'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as pending FROM referrals WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pendingReferrals = $stmt->fetch()['pending'] ?? 0;

    $stmt = $conn->prepare("
        SELECT patient_name, medical_condition, status, created_at
        FROM referrals
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $recentReferrals = $stmt->fetchAll();

} catch (PDOException $e) {
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_phone = '';
    $user_location = '';
    $user_picture = '';
}

$initials = strtoupper(substr($user_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container { max-width: 1100px; margin: 0 auto; }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 28px;
            padding: 32px 36px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1EB53A, #0000CD);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 700;
            overflow: hidden;
            border: 4px solid #fff;
        }
        .profile-info h1 { font-size: 1.7rem; margin: 0 0 4px 0; }
        .profile-email { color: #64748B; margin: 0; }
        .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; }
        .profile-card {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .profile-detail { padding: 10px 0; border-bottom: 1px solid #eee; }
        .profile-detail:last-child { border-bottom: none; }
        .profile-detail label { font-size: 0.75rem; color: #94A3B8; text-transform: uppercase; }
        .activity-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; text-align: center; }
        .stat-number { font-size: 1.7rem; font-weight: 700; color: #1EB53A; }
    </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <nav>
      <ul class="nav-links">
        <li><a href="index.html">Home</a></li>
        <li><a href="dashboard/patient-dashboard.php">Dashboard</a></li>
        <li><a href="pages/doctors.php">Find Care</a></li>
        <li><a href="pages/referral.html">New Referral</a></li>
      </ul>
    </nav>
    <div class="nav-actions">
      <a href="logout.php" class="btn-ghost">Logout</a>
    </div>
  </div>
</header>

<main style="padding: 30px 20px; max-width: 1100px; margin: 0 auto;">
  <div class="profile-container">

    <?php if ($message): ?>
      <div style="background:#D1FAE5; color:#065F46; padding:12px 20px; border-radius:10px; margin-bottom:20px;"><?= $message ?></div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
      <div class="profile-avatar">
        <?= $initials ?>
      </div>
      <div class="profile-info">
        <h1><?= htmlspecialchars($user_name) ?></h1>
        <p class="profile-email"><?= htmlspecialchars($user_email) ?></p>
        <span style="background:#E0F2FE; color:#0369A1; padding:2px 10px; border-radius:20px; font-size:0.8rem;"><?= ucfirst($user_role) ?></span>
      </div>
      <div style="margin-left:auto;">
        <button onclick="document.getElementById('editForm').classList.toggle('hidden')" class="btn-primary">Edit Profile</button>
      </div>
    </div>

    <div class="profile-grid">

      <!-- Personal Info -->
      <div class="profile-card">
        <h2 style="margin-bottom:16px;">Personal Information</h2>
        <div class="profile-detail"><label>Full Name</label><p><?= htmlspecialchars($user_name) ?></p></div>
        <div class="profile-detail"><label>Email</label><p><?= htmlspecialchars($user_email) ?></p></div>
        <div class="profile-detail"><label>Phone</label><p><?= htmlspecialchars($user_phone ?: 'Not set') ?></p></div>
        <div class="profile-detail"><label>Location</label><p><?= htmlspecialchars($user_location ?: 'Not set') ?></p></div>
      </div>

      <!-- Account -->
      <div class="profile-card">
        <h2 style="margin-bottom:16px;">Account</h2>
        <div class="profile-detail"><label>Member Since</label><p><?= date('F Y', strtotime($user_created)) ?></p></div>
        <div class="profile-detail"><label>Status</label><p style="color:#16A34A; font-weight:600;">Active</p></div>
        <div style="margin-top:20px;">
          <a href="change-password.php" class="btn-primary" style="display:inline-block; padding:8px 20px; text-decoration:none;">Change Password</a>
        </div>
      </div>

      <!-- Stats -->
      <div class="profile-card">
        <h2 style="margin-bottom:16px;">Your Activity</h2>
        <div class="activity-stats">
          <div><div class="stat-number"><?= $totalReferrals ?></div><div style="font-size:0.85rem; color:#64748B;">Total Referrals</div></div>
          <div><div class="stat-number"><?= $completedReferrals ?></div><div style="font-size:0.85rem; color:#64748B;">Completed</div></div>
          <div><div class="stat-number"><?= $pendingReferrals ?></div><div style="font-size:0.85rem; color:#64748B;">Pending</div></div>
        </div>
      </div>

    </div>
  </div>
</main>

<footer class="site-footer">
  <div class="footer-grid container">
    <div><a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a></div>
    <div><h3>Quick Links</h3><ul class="footer-links"><li><a href="index.html">Home</a></li><li><a href="dashboard/patient-dashboard.php">Dashboard</a></li></ul></div>
    <div><h3>Contact</h3><p><a href="mailto:hello@careconnect.sl">hello@careconnect.sl</a></p></div>
  </div>
  <p class="footer-note">&copy; 2026 Care Connect SL</p>
</footer>

</body>
</html>