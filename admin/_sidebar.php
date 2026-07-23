<?php
// Shared admin sidebar — set $active before including
$active = $active ?? '';

require_once __DIR__ . '/_badge_seen.php';

// Badge counts = only items not yet "seen" (opened)
$sbBadges = [
    'messages' => 0,
    'notifications' => 0,
    'users' => 0,
    'referrals' => 0,
    'providers' => 0,
];

if (isset($conn) && $conn instanceof PDO) {
    // Messages: still status = new (cleared when inbox is opened)
    try {
        $sbBadges['messages'] = (int)$conn->query(
            "SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"
        )->fetchColumn();
    } catch (Exception $e) {}

    // Notifications: unread for this admin
    try {
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        if ($adminId > 0) {
            $st = $conn->prepare(
                "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)"
            );
            $st->execute([$adminId]);
            $sbBadges['notifications'] = (int)$st->fetchColumn();
        }
    } catch (Exception $e) {}

    // Users: registered after last time admin opened Users page
    try {
        $since = admin_last_seen('users');
        if ($since === null) {
            // First time: only last 24h so history does not flood the badge
            $sbBadges['users'] = (int)$conn->query(
                "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
            )->fetchColumn();
        } else {
            $st = $conn->prepare("SELECT COUNT(*) FROM users WHERE created_at > ?");
            $st->execute([$since]);
            $sbBadges['users'] = (int)$st->fetchColumn();
        }
    } catch (Exception $e) {}

    // Referrals: pending created after last open of Referrals
    try {
        $since = admin_last_seen('referrals');
        if ($since === null) {
            $sbBadges['referrals'] = (int)$conn->query(
                "SELECT COUNT(*) FROM referrals WHERE status = 'pending'"
            )->fetchColumn();
        } else {
            $st = $conn->prepare(
                "SELECT COUNT(*) FROM referrals WHERE status = 'pending' AND created_at > ?"
            );
            $st->execute([$since]);
            $sbBadges['referrals'] = (int)$st->fetchColumn();
        }
    } catch (Exception $e) {}

    // Providers pending verification after last open of Verify page
    try {
        $since = admin_last_seen('providers');
        if ($since === null) {
            $sbBadges['providers'] = (int)$conn->query(
                "SELECT COUNT(*) FROM provider_profiles WHERE verification_status = 'pending'"
            )->fetchColumn();
        } else {
            // created_at or updated_at into pending
            $st = $conn->prepare(
                "SELECT COUNT(*) FROM provider_profiles
                 WHERE verification_status = 'pending'
                   AND COALESCE(updated_at, created_at) > ?"
            );
            $st->execute([$since]);
            $sbBadges['providers'] = (int)$st->fetchColumn();
        }
    } catch (Exception $e) {
        try {
            if (admin_last_seen('providers') === null) {
                $sbBadges['providers'] = (int)$conn->query(
                    "SELECT COUNT(*) FROM provider_profiles WHERE verification_status = 'pending'"
                )->fetchColumn();
            }
        } catch (Exception $e2) {}
    }
}

function admin_badge(int $n): string
{
    if ($n <= 0) return '';
    $label = $n > 99 ? '99+' : (string)$n;
    return '<span class="sb-badge" aria-label="' . $label . ' new">' . htmlspecialchars($label) . '</span>';
}
?>
<aside class="admin-sidebar" id="sidebar">
  <div class="sb-logo">
    <h1>Care<span>Connect</span> SL</h1>
    <div class="sub">Administration Panel</div>
  </div>
  <nav class="sb-nav">
    <div class="sb-section">Overview</div>
    <a href="admin-dashboard.php" class="sb-item <?= $active === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
    <a href="notifications.php" class="sb-item <?= $active === 'notifications' ? 'active' : '' ?>">
      🔔 Notifications<?= admin_badge($sbBadges['notifications']) ?>
    </a>
    <a href="payment-history.php" class="sb-item <?= $active === 'payments' ? 'active' : '' ?>">💰 Payments</a>

    <div class="sb-section">Manage</div>
    <a href="manage-referrals.php" class="sb-item <?= $active === 'referrals' ? 'active' : '' ?>">
      📋 Referrals<?= admin_badge($sbBadges['referrals']) ?>
    </a>
    <a href="manage-messages.php" class="sb-item <?= $active === 'messages' ? 'active' : '' ?>">
      💬 Messages<?= admin_badge($sbBadges['messages']) ?>
    </a>
    <a href="manage-users.php" class="sb-item <?= $active === 'users' ? 'active' : '' ?>">
      👥 Users<?= admin_badge($sbBadges['users']) ?>
    </a>
    <a href="verify-providers.php" class="sb-item <?= $active === 'providers' ? 'active' : '' ?>">
      ✅ Verify Providers<?= admin_badge($sbBadges['providers']) ?>
    </a>

    <div class="sb-section">System</div>
    <a href="test-sms.php" class="sb-item <?= $active === 'sms' ? 'active' : '' ?>">📱 SMS test</a>
    <a href="admin-settings.php" class="sb-item <?= $active === 'settings' ? 'active' : '' ?>">⚙️ Settings</a>
    <a href="../logout.php" class="sb-item danger">🚪 Logout</a>
  </nav>
</aside>
