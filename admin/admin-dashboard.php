<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    // Stats
    $totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalReferrals = $conn->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
    $pendingReferrals = $conn->query("SELECT COUNT(*) FROM referrals WHERE status = 'pending'")->fetchColumn();
    $newMessages = $conn->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();

    // Unread notifications
    $unreadNotifications = $conn->query("SELECT COUNT(*) FROM notifications WHERE is_read = FALSE")->fetchColumn();

    // Recent Referrals
    $recentReferrals = $conn->query("
        SELECT r.*, u.name as patient_name 
        FROM referrals r 
        LEFT JOIN users u ON r.user_id = u.id 
        ORDER BY r.created_at DESC LIMIT 5
    ")->fetchAll();

    // Recent Notifications
    $recentNotifications = $conn->query("
        SELECT * FROM notifications 
        ORDER BY created_at DESC LIMIT 6
    ")->fetchAll();

} catch (PDOException $e) {
    error_log("Admin error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <div class="sb-logo">
            <h1>Care<span style="color:#1EB53A">Connect</span> SL</h1>
            <div class="sub">Admin Panel</div>
        </div>
        <nav class="sb-nav">
            <a href="admin-dashboard.php" class="sb-item active">📊 Dashboard</a>
            <a href="manage-referrals.php" class="sb-item">📋 Referrals</a>
            <a href="manage-users.php" class="sb-item">👥 Users</a>
            <a href="verify-providers.php" class="sb-item">✅ Verify Providers</a>
            <a href="../logout.php" class="sb-item" style="color:#ef4444;">🚪 Logout</a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <span class="page-title">Dashboard</span>
            <div style="display:flex; align-items:center; gap:15px;">
                <div style="position:relative;">
                    <a href="#" style="font-size:1.4rem; text-decoration:none;">🔔</a>
                    <?php if ($unreadNotifications > 0): ?>
                        <span style="position:absolute; top:-5px; right:-5px; background:#ef4444; color:white; font-size:0.7rem; padding:1px 6px; border-radius:50%;">
                            <?= $unreadNotifications ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span>Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong></span>
            </div>
        </div>

        <div class="admin-content">

            <!-- Stats -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:30px;">
                <div class="stat-card"><div class="stat-number"><?= $totalUsers ?></div><div>Total Users</div></div>
                <div class="stat-card"><div class="stat-number"><?= $totalReferrals ?></div><div>Total Referrals</div></div>
                <div class="stat-card"><div class="stat-number" style="color:#f59e0b;"><?= $pendingReferrals ?></div><div>Pending</div></div>
                <div class="stat-card"><div class="stat-number" style="color:#ef4444;"><?= $newMessages ?></div><div>New Messages</div></div>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px;">

                <!-- Recent Referrals -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2>Recent Referrals</h2>
                        <a href="manage-referrals.php">Manage All →</a>
                    </div>
                    <table class="data-table" style="width:100%;">
                        <thead><tr><th>Patient</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentReferrals as $ref): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ref['patient_name'] ?? 'Guest') ?></td>
                                    <td><span class="badge <?= $ref['status'] ?>"><?= ucfirst($ref['status']) ?></span></td>
                                    <td><?= date('M d', strtotime($ref['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Notifications -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2>Notifications <?= $unreadNotifications > 0 ? "<span style='color:#ef4444;'>($unreadNotifications new)</span>" : '' ?></h2>
                    </div>
                    <div style="max-height:320px; overflow-y:auto;">
                        <?php if (!empty($recentNotifications)): ?>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <div style="padding:12px 0; border-bottom:1px solid #eee;">
                                    <strong><?= htmlspecialchars($notif['title']) ?></strong><br>
                                    <span style="font-size:0.9rem; color:#555;"><?= htmlspecialchars($notif['message']) ?></span>
                                    <div style="font-size:0.75rem; color:#999; margin-top:4px;"><?= date('M d, H:i', strtotime($notif['created_at'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:#888; padding:20px 0;">No notifications yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>
</body>
</html>