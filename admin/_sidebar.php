<?php
// Shared admin sidebar — set $active before including
$active = $active ?? '';
?>
<aside class="admin-sidebar" id="sidebar">
  <div class="sb-logo">
    <h1>Care<span>Connect</span> SL</h1>
    <div class="sub">Administration Panel</div>
  </div>
  <nav class="sb-nav">
    <div class="sb-section">Overview</div>
    <a href="admin-dashboard.php" class="sb-item <?= $active === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
    <a href="notifications.php" class="sb-item <?= $active === 'notifications' ? 'active' : '' ?>">🔔 Notifications</a>
    <a href="payment-history.php" class="sb-item <?= $active === 'payments' ? 'active' : '' ?>">💰 Payments</a>

    <div class="sb-section">Manage</div>
    <a href="manage-referrals.php" class="sb-item <?= $active === 'referrals' ? 'active' : '' ?>">📋 Referrals</a>
    <a href="manage-messages.php" class="sb-item <?= $active === 'messages' ? 'active' : '' ?>">💬 Messages</a>
    <a href="manage-users.php" class="sb-item <?= $active === 'users' ? 'active' : '' ?>">👥 Users</a>
    <a href="verify-providers.php" class="sb-item <?= $active === 'providers' ? 'active' : '' ?>">✅ Verify Providers</a>

    <div class="sb-section">System</div>
    <a href="test-sms.php" class="sb-item <?= $active === 'sms' ? 'active' : '' ?>">📱 SMS test</a>
    <a href="admin-settings.php" class="sb-item <?= $active === 'settings' ? 'active' : '' ?>">⚙️ Settings</a>
    <a href="../logout.php" class="sb-item danger">🚪 Logout</a>
  </nav>
</aside>
