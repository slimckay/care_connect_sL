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
  <style>
    .perm-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .perm-box {
      border: 1px solid #E5E7EB;
      border-radius: 14px;
      padding: 16px 18px;
      background: #F8FAFC;
    }
    .perm-box.can { border-color: #BBF7D0; background: #F0FDF4; }
    .perm-box.cannot { border-color: #FECACA; background: #FEF2F2; }
    .perm-box h3 {
      margin: 0 0 12px;
      font-size: 0.98rem;
      color: #0F1C3A !important;
    }
    .perm-box ul {
      margin: 0;
      padding-left: 18px;
      color: #334155 !important;
      font-size: 0.92rem;
      line-height: 1.55;
    }
    .perm-box li { margin-bottom: 6px; }
    .perm-note {
      margin-top: 14px;
      font-size: 0.88rem;
      color: #64748B !important;
      line-height: 1.5;
    }
    [data-theme="dark"] .perm-box { background: #0f172a; border-color: #334155; }
    [data-theme="dark"] .perm-box.can { background: #052e16; border-color: #166534; }
    [data-theme="dark"] .perm-box.cannot { background: #450a0a; border-color: #991b1b; }
    [data-theme="dark"] .perm-box h3 { color: #F8FAFC !important; }
    [data-theme="dark"] .perm-box ul { color: #E2E8F0 !important; }
    @media (max-width: 800px) {
      .perm-grid { grid-template-columns: 1fr; }
    }
  </style>
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
          <h2>🔐 Admin Permissions</h2>
        </div>
        <div class="card-body">
          <p class="muted" style="margin:0 0 14px;">
            Only accounts with the <strong>admin</strong> role can open this panel.
            Logged in as <strong><?= htmlspecialchars($admin_name) ?></strong>.
          </p>

          <div class="perm-grid">
            <div class="perm-box can">
              <h3>✅ Admins can</h3>
              <ul>
                <li>View dashboard stats and notifications</li>
                <li>Update referral status (pending, in progress, completed, cancelled)</li>
                <li>Set and edit patient follow-up dates and notes</li>
                <li>Activate, deactivate, or ban user accounts</li>
                <li>Verify or reject provider applications</li>
                <li>Update basic system settings (site name, support contact)</li>
              </ul>
            </div>

            <div class="perm-box cannot">
              <h3>🚫 Admins cannot</h3>
              <ul>
                <li>Edit a provider’s full clinic profile the way the provider does</li>
                <li>Upload provider verification documents for them</li>
                <li>Change another user’s password from this panel</li>
                <li>Access provider payment wallets beyond operational records</li>
                <li>Open admin pages without an admin role session</li>
              </ul>
            </div>
          </div>

          <p class="perm-note">
            <strong>Roles at a glance:</strong>
            Admin = control panel ·
            Doctor / Hospital = own cases, profile, follow-ups ·
            Patient = submit referrals and view own activity.
          </p>
        </div>
      </div>

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
