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
$pendingProviders = 0;
$recentReferrals = [];

try {
    $totalUsers = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalReferrals = (int)$conn->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
    $pendingReferrals = (int)$conn->query("SELECT COUNT(*) FROM referrals WHERE status = 'pending'")->fetchColumn();

    try {
        $newMessages = (int)$conn->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
    } catch (Exception $e) {}

    try {
        $pendingProviders = (int)$conn->query("SELECT COUNT(*) FROM provider_profiles WHERE verification_status = 'pending'")->fetchColumn();
    } catch (Exception $e) {}

    try {
        $recentReferrals = $conn->query("
            SELECT r.*
            FROM referrals r
            ORDER BY r.created_at DESC
            LIMIT 8
        ")->fetchAll();
    } catch (Exception $e) {}
} catch (PDOException $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
}

$active = 'dashboard';
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
</head>
<body class="admin-body">
<div class="admin-wrapper">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button">☰</button>
        <span class="page-title">Dashboard Overview</span>
      </div>
      <div class="admin-topbar-right">
        <button onclick="toggleDarkMode()" class="dark-toggle" type="button">🌓</button>
        <span class="welcome">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong></span>
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

      <div class="admin-card">
        <div class="card-header">
          <h2>Recent Referrals</h2>
          <a href="manage-referrals.php">Manage all →</a>
        </div>
        <?php if (!empty($recentReferrals)): ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Patient</th>
                  <th>Location</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentReferrals as $ref): ?>
                  <tr>
                    <td>#<?= (int)$ref['id'] ?></td>
                    <td><?= htmlspecialchars($ref['patient_name'] ?? 'Guest') ?></td>
                    <td><?= htmlspecialchars($ref['location'] ?? '-') ?></td>
                    <td><span class="badge <?= htmlspecialchars($ref['status'] ?? 'pending') ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ', $ref['status'] ?? 'pending'))) ?></span></td>
                    <td><?= !empty($ref['created_at']) ? date('M d, Y', strtotime($ref['created_at'])) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty">No referrals yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script src="../js/dark-mode.js"></script>
<script>
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
}
</script>
</body>
</html>
