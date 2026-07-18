<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

$admin_name = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');

$totalUsers = 0;
$totalReferrals = 0;
$pendingReferrals = 0;
$newMessages = 0;
$unreadNotifications = 0;
$pendingProviders = 0;
$recentReferrals = [];
$recentNotifications = [];

try {
    $totalUsers = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalReferrals = (int)$conn->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
    $pendingReferrals = (int)$conn->query("SELECT COUNT(*) FROM referrals WHERE status = 'pending'")->fetchColumn();

    try {
        $newMessages = (int)$conn->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
    } catch (Exception $e) {
        $newMessages = 0;
    }

    try {
        $unreadNotifications = (int)$conn->query("SELECT COUNT(*) FROM notifications WHERE is_read = FALSE")->fetchColumn();
    } catch (Exception $e) {
        $unreadNotifications = 0;
    }

    try {
        $pendingProviders = (int)$conn->query("SELECT COUNT(*) FROM provider_profiles WHERE verification_status = 'pending'")->fetchColumn();
    } catch (Exception $e) {
        $pendingProviders = 0;
    }

    try {
        $recentReferrals = $conn->query("
            SELECT r.*, u.name as patient_name
            FROM referrals r
            LEFT JOIN users u ON r.user_id = u.id
            ORDER BY r.created_at DESC
            LIMIT 6
        ")->fetchAll();
    } catch (Exception $e) {
        $recentReferrals = [];
    }

    try {
        $recentNotifications = $conn->query("
            SELECT * FROM notifications
            ORDER BY created_at DESC
            LIMIT 6
        ")->fetchAll();
    } catch (Exception $e) {
        $recentNotifications = [];
    }
} catch (PDOException $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="admin-styles.css">
  <style>
    body { background: #F8FAFC; color: #1F2937; }

    .admin-content h2,
    .admin-topbar .page-title,
    .stat-number {
      color: #0F1C3A !important;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 14px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: #fff;
      border: 1px solid #E5E7EB;
      border-radius: 14px;
      padding: 18px;
      box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    }
    .stat-card .stat-number {
      font-size: 1.75rem;
      font-weight: 700;
      line-height: 1.1;
    }
    .stat-card .stat-label {
      margin-top: 6px;
      color: #64748B !important;
      font-size: 0.9rem;
    }

    .dash-grid {
      display: grid;
      grid-template-columns: 1.6fr 1fr;
      gap: 18px;
    }

    .admin-card {
      background: #fff;
      border: 1px solid #E5E7EB;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
    }
    .card-header {
      padding: 16px 20px;
      border-bottom: 1px solid #E5E7EB;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .card-header h2 {
      margin: 0;
      font-size: 1.05rem;
    }
    .card-header a {
      color: #1EB53A !important;
      font-weight: 600;
      text-decoration: none;
      font-size: 0.9rem;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
    }
    .data-table th {
      text-align: left;
      padding: 12px 16px;
      background: #F8FAFC;
      color: #0F1C3A !important;
      font-size: 0.85rem;
      border-bottom: 1px solid #E5E7EB;
    }
    .data-table td {
      padding: 12px 16px;
      border-bottom: 1px solid #F1F5F9;
      color: #1F2937 !important;
      font-size: 0.92rem;
    }

    .notif-item {
      padding: 14px 18px;
      border-bottom: 1px solid #F1F5F9;
    }
    .notif-item strong {
      display: block;
      color: #0F1C3A !important;
      margin-bottom: 4px;
    }
    .notif-item p {
      margin: 0;
      color: #475569 !important;
      font-size: 0.9rem;
    }
    .notif-item .time {
      margin-top: 6px;
      color: #94A3B8 !important;
      font-size: 0.78rem;
    }

    .empty {
      padding: 24px 18px;
      color: #64748B !important;
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .badge.pending { background: #FEF3C7; color: #B45309 !important; }
    .badge.completed, .badge.verified, .badge.active { background: #DCFCE7; color: #15803D !important; }
    .badge.rejected, .badge.cancelled { background: #FEE2E2; color: #B91C1C !important; }

    [data-theme="dark"] body,
    [data-theme="dark"] .admin-main { background: #0f172a; }
    [data-theme="dark"] .admin-topbar,
    [data-theme="dark"] .stat-card,
    [data-theme="dark"] .admin-card { background: #1e293b; border-color: #334155; }
    [data-theme="dark"] .admin-content h2,
    [data-theme="dark"] .admin-topbar .page-title,
    [data-theme="dark"] .stat-number,
    [data-theme="dark"] .notif-item strong,
    [data-theme="dark"] .data-table th { color: #F8FAFC !important; }
    [data-theme="dark"] .stat-card .stat-label,
    [data-theme="dark"] .empty,
    [data-theme="dark"] .notif-item p { color: #94A3B8 !important; }
    [data-theme="dark"] .data-table td { color: #E2E8F0 !important; border-color: #334155; }
    [data-theme="dark"] .data-table th,
    [data-theme="dark"] .card-header,
    [data-theme="dark"] .notif-item { border-color: #334155; }

    @media (max-width: 980px) {
      .dash-grid { grid-template-columns: 1fr; }
    }
  </style>
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
      <a href="../logout.php" class="sb-item" style="color:#ef4444 !important;">🚪 Logout</a>
    </nav>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <span class="page-title">Dashboard Overview</span>
      <div style="display:flex; align-items:center; gap:14px;">
        <button onclick="toggleDarkMode()" class="dark-toggle">🌓</button>
        <span style="color:#1F2937 !important;">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong></span>
      </div>
    </div>

    <div class="admin-content">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-number"><?= $totalUsers ?></div>
          <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= $totalReferrals ?></div>
          <div class="stat-label">Total Referrals</div>
        </div>
        <div class="stat-card">
          <div class="stat-number" style="color:#D97706 !important;"><?= $pendingReferrals ?></div>
          <div class="stat-label">Pending Referrals</div>
        </div>
        <div class="stat-card">
          <div class="stat-number" style="color:#2563EB !important;"><?= $pendingProviders ?></div>
          <div class="stat-label">Providers to Verify</div>
        </div>
        <div class="stat-card">
          <div class="stat-number" style="color:#DC2626 !important;"><?= $newMessages ?></div>
          <div class="stat-label">New Messages</div>
        </div>
      </div>

      <div class="dash-grid">
        <div class="admin-card">
          <div class="card-header">
            <h2>Recent Referrals</h2>
            <a href="manage-referrals.php">Manage all →</a>
          </div>
          <?php if (!empty($recentReferrals)): ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Patient</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentReferrals as $ref): ?>
                  <tr>
                    <td><?= htmlspecialchars($ref['patient_name'] ?? ($ref['patient_full_name'] ?? 'Guest')) ?></td>
                    <td><span class="badge <?= htmlspecialchars($ref['status'] ?? 'pending') ?>"><?= htmlspecialchars(ucfirst($ref['status'] ?? 'pending')) ?></span></td>
                    <td><?= !empty($ref['created_at']) ? date('M d, Y', strtotime($ref['created_at'])) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty">No referrals yet.</div>
          <?php endif; ?>
        </div>

        <div class="admin-card">
          <div class="card-header">
            <h2>Notifications <?= $unreadNotifications > 0 ? '(' . $unreadNotifications . ')' : '' ?></h2>
            <a href="verify-providers.php">Verify providers →</a>
          </div>
          <?php if (!empty($recentNotifications)): ?>
            <?php foreach ($recentNotifications as $notif): ?>
              <div class="notif-item">
                <strong><?= htmlspecialchars($notif['title'] ?? 'Notification') ?></strong>
                <p><?= htmlspecialchars($notif['message'] ?? '') ?></p>
                <div class="time"><?= !empty($notif['created_at']) ? date('M d, H:i', strtotime($notif['created_at'])) : '' ?></div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty">No notifications yet.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="../js/dark-mode.js"></script>
</body>
</html>