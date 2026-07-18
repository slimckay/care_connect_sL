<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$admin_name = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = 'Settings saved successfully.';
}

$active = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Settings — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="admin-styles.css">
</head>
<body class="admin-body">
<div class="admin-wrapper">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button">☰</button>
        <span class="page-title">Admin Settings</span>
      </div>
      <div class="admin-topbar-right">
        <button onclick="toggleDarkMode()" class="dark-toggle" type="button">🌓</button>
        <span class="welcome">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong></span>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($message): ?><div class="alert success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>

      <div class="admin-card">
        <div class="card-header">
          <h2>System Settings</h2>
        </div>
        <div class="card-body">
          <form method="POST" class="admin-form" style="max-width:560px;">
            <label for="site_name">Site Name</label>
            <input type="text" id="site_name" name="site_name" value="Care Connect SL">

            <label for="site_email">Support Email</label>
            <input type="email" id="site_email" name="site_email" value="hello@careconnect.sl">

            <label for="site_phone">Support Phone</label>
            <input type="text" id="site_phone" name="site_phone" value="+232 76 000 000">

            <button type="submit" class="btn-admin" style="width:100%; margin-top:6px;">Save Settings</button>
          </form>
        </div>
      </div>

      <div class="admin-card">
        <div class="card-header">
          <h2>System Information</h2>
        </div>
        <div class="card-body">
          <div class="detail-grid">
            <div class="detail-item">
              <label>PHP Version</label>
              <p><?= htmlspecialchars(phpversion()) ?></p>
            </div>
            <div class="detail-item">
              <label>Database</label>
              <p>MySQL</p>
            </div>
            <div class="detail-item">
              <label>Server Time</label>
              <p><?= date('Y-m-d H:i:s') ?></p>
            </div>
            <div class="detail-item">
              <label>Admin Logged In</label>
              <p><?= htmlspecialchars($admin_name) ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="../js/dark-mode.js"></script>
<script>
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
if (sidebarToggle && sidebar) sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
</script>
</body>
</html>
