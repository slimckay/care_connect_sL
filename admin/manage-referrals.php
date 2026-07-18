<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

$adminName = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$message = '';
$error = '';

// Handle status update first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['referral_id'], $_POST['status'])) {
    $referralId = (int)$_POST['referral_id'];
    $status = $_POST['status'];
    $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];

    if (in_array($status, $validStatuses, true)) {
        try {
            $stmt = $conn->prepare("UPDATE referrals SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $referralId]);

            // Optional activity log
            try {
                $adminId = $_SESSION['user_id'] ?? null;
                $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'referral_update', ?, NOW())");
                $log->execute([$adminId, "Referral #$referralId status updated to $status"]);
            } catch (Exception $e) {
                // ignore log failures
            }

            header('Location: manage-referrals.php?message=' . urlencode('Referral updated successfully'));
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update referral status.';
        }
    } else {
        $error = 'Invalid status selected.';
    }
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Filters
$filter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT r.*, u.name AS linked_user_name
    FROM referrals r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE 1=1
";
$params = [];

if (in_array($filter, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
    $sql .= " AND r.status = ?";
    $params[] = $filter;
}

if ($search !== '') {
    $sql .= " AND (
        r.patient_name LIKE ? OR
        r.contact LIKE ? OR
        r.location LIKE ? OR
        COALESCE(r.preferred_clinic, '') LIKE ?
    )";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$sql .= " ORDER BY r.created_at DESC";

$referrals = [];
$counts = [
    'all' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $referrals = $stmt->fetchAll();

    $countRows = $conn->query("SELECT status, COUNT(*) AS total FROM referrals GROUP BY status")->fetchAll();
    foreach ($countRows as $row) {
        $statusKey = $row['status'] ?? 'pending';
        $counts[$statusKey] = (int)$row['total'];
        $counts['all'] += (int)$row['total'];
    }
} catch (PDOException $e) {
    $error = 'Could not load referrals.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Referrals — Care Connect SL Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="admin-styles.css">
  <style>
    body { background: #F8FAFC; color: #1F2937; }

    .filters {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 18px;
      align-items: center;
    }
    .filters a {
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 999px;
      border: 1px solid #E5E7EB;
      background: #fff;
      color: #1F2937 !important;
      font-size: 0.9rem;
      font-weight: 600;
    }
    .filters a.active {
      background: #1EB53A;
      border-color: #1EB53A;
      color: #fff !important;
    }

    .search-form {
      display: flex;
      gap: 8px;
      margin-left: auto;
    }
    .search-form input {
      min-width: 220px;
      padding: 10px 12px;
      border: 1px solid #E5E7EB;
      border-radius: 10px;
      color: #1F2937;
      background: #fff;
    }
    .search-form button {
      border: none;
      background: #1EB53A;
      color: #fff;
      border-radius: 10px;
      padding: 10px 16px;
      font-weight: 600;
      cursor: pointer;
    }

    .admin-card {
      background: #fff;
      border: 1px solid #E5E7EB;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 14px rgba(15,23,42,0.04);
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
      color: #0F1C3A !important;
    }
    .card-header span { color: #64748B !important; }

    .data-table {
      width: 100%;
      border-collapse: collapse;
    }
    .data-table th {
      text-align: left;
      padding: 12px 14px;
      background: #F8FAFC;
      color: #0F1C3A !important;
      font-size: 0.85rem;
      border-bottom: 1px solid #E5E7EB;
    }
    .data-table td {
      padding: 14px;
      border-bottom: 1px solid #F1F5F9;
      color: #1F2937 !important;
      vertical-align: top;
      font-size: 0.92rem;
    }

    .patient-name {
      font-weight: 700;
      color: #0F1C3A !important;
      margin-bottom: 4px;
    }
    .muted {
      color: #64748B !important;
      font-size: 0.85rem;
    }
    .condition-text {
      max-width: 260px;
      color: #334155 !important;
      line-height: 1.45;
    }

    .status-form {
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 150px;
    }
    .status-form select {
      padding: 8px 10px;
      border: 1px solid #E5E7EB;
      border-radius: 8px;
      background: #fff;
      color: #1F2937;
    }
    .status-form button {
      border: none;
      background: #0F1C3A;
      color: #fff;
      border-radius: 8px;
      padding: 8px 10px;
      font-weight: 600;
      cursor: pointer;
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 700;
    }
    .badge.pending { background: #FEF3C7; color: #B45309 !important; }
    .badge.in_progress { background: #DBEAFE; color: #1D4ED8 !important; }
    .badge.completed { background: #DCFCE7; color: #15803D !important; }
    .badge.cancelled { background: #FEE2E2; color: #B91C1C !important; }

    .alert {
      padding: 12px 14px;
      border-radius: 10px;
      margin-bottom: 16px;
      font-weight: 600;
    }
    .alert.success { background: #DCFCE7; color: #166534 !important; }
    .alert.error { background: #FEE2E2; color: #991B1B !important; }

    .empty {
      padding: 28px 18px;
      color: #64748B !important;
      text-align: center;
    }

    [data-theme="dark"] body,
    [data-theme="dark"] .admin-main { background: #0f172a; }
    [data-theme="dark"] .admin-topbar,
    [data-theme="dark"] .admin-card,
    [data-theme="dark"] .filters a,
    [data-theme="dark"] .search-form input,
    [data-theme="dark"] .status-form select {
      background: #1e293b;
      border-color: #334155;
      color: #E2E8F0 !important;
    }
    [data-theme="dark"] .card-header h2,
    [data-theme="dark"] .patient-name,
    [data-theme="dark"] .data-table th { color: #F8FAFC !important; }
    [data-theme="dark"] .data-table td,
    [data-theme="dark"] .condition-text { color: #E2E8F0 !important; }
    [data-theme="dark"] .muted,
    [data-theme="dark"] .card-header span { color: #94A3B8 !important; }
    [data-theme="dark"] .data-table th,
    [data-theme="dark"] .card-header,
    [data-theme="dark"] .data-table td { border-color: #334155; }

    @media (max-width: 900px) {
      .search-form {
        margin-left: 0;
        width: 100%;
      }
      .search-form input { width: 100%; min-width: 0; }
      .data-table { min-width: 860px; }
    }
  </style>
</head>
<body>
<div class="admin-wrapper">
  <aside class="admin-sidebar" id="sidebar">
    <div class="sb-logo">
      <h1>Care<span style="color:#1EB53A">Connect</span> SL</h1>
      <div class="sub">Admin Panel</div>
    </div>
    <nav class="sb-nav">
      <a href="admin-dashboard.php" class="sb-item">📊 Dashboard</a>
      <a href="manage-referrals.php" class="sb-item active">📋 Referrals</a>
      <a href="manage-users.php" class="sb-item">👥 Users</a>
      <a href="verify-providers.php" class="sb-item">✅ Verify Providers</a>
      <a href="../logout.php" class="sb-item" style="color:#ef4444 !important;">🚪 Logout</a>
    </nav>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div style="display:flex; align-items:center; gap:12px;">
        <button class="sidebar-toggle" id="sidebarToggle" style="background:none;border:none;font-size:1.3rem;cursor:pointer;">☰</button>
        <span class="page-title" style="color:#0F1C3A !important; font-weight:700;">Manage Referrals</span>
      </div>
      <div style="display:flex; align-items:center; gap:12px;">
        <button onclick="toggleDarkMode()" class="dark-toggle">🌓</button>
        <span style="color:#1F2937 !important;">Welcome, <strong><?= htmlspecialchars($adminName) ?></strong></span>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($message): ?>
        <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert error">❌ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="filters">
        <a href="?status=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All (<?= $counts['all'] ?>)</a>
        <a href="?status=pending" class="<?= $filter === 'pending' ? 'active' : '' ?>">Pending (<?= $counts['pending'] ?>)</a>
        <a href="?status=in_progress" class="<?= $filter === 'in_progress' ? 'active' : '' ?>">In Progress (<?= $counts['in_progress'] ?>)</a>
        <a href="?status=completed" class="<?= $filter === 'completed' ? 'active' : '' ?>">Completed (<?= $counts['completed'] ?>)</a>
        <a href="?status=cancelled" class="<?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled (<?= $counts['cancelled'] ?>)</a>

        <form method="GET" class="search-form">
          <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search patient, contact, location...">
          <button type="submit">Search</button>
        </form>
      </div>

      <div class="admin-card">
        <div class="card-header">
          <h2>All Referrals</h2>
          <span>Showing <?= count($referrals) ?> result(s)</span>
        </div>

        <?php if (empty($referrals)): ?>
          <div class="empty">No referrals found.</div>
        <?php else: ?>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Patient</th>
                  <th>Condition</th>
                  <th>Location</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Update</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($referrals as $ref): ?>
                  <?php
                    $patient = $ref['patient_name'] ?? ($ref['linked_user_name'] ?? 'Unknown');
                    $condition = $ref['condition'] ?? ($ref['medical_condition'] ?? 'Not provided');
                    $status = $ref['status'] ?? 'pending';
                  ?>
                  <tr>
                    <td>#<?= (int)$ref['id'] ?></td>
                    <td>
                      <div class="patient-name"><?= htmlspecialchars($patient) ?></div>
                      <div class="muted">
                        <?= htmlspecialchars($ref['contact'] ?? 'No contact') ?>
                        <?php if (!empty($ref['age'])): ?>
                          · Age <?= (int)$ref['age'] ?>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td>
                      <div class="condition-text"><?= htmlspecialchars($condition) ?></div>
                      <?php if (!empty($ref['preferred_clinic'])): ?>
                        <div class="muted" style="margin-top:6px;">Clinic: <?= htmlspecialchars($ref['preferred_clinic']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($ref['location'] ?? '-') ?></td>
                    <td>
                      <span class="badge <?= htmlspecialchars($status) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?></span>
                    </td>
                    <td>
                      <?= !empty($ref['created_at']) ? date('M d, Y', strtotime($ref['created_at'])) : '-' ?>
                      <div class="muted"><?= !empty($ref['created_at']) ? date('H:i', strtotime($ref['created_at'])) : '' ?></div>
                    </td>
                    <td>
                      <form method="POST" class="status-form">
                        <input type="hidden" name="referral_id" value="<?= (int)$ref['id'] ?>">
                        <select name="status">
                          <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                          <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                          <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                          <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                        <button type="submit" name="update_status" value="1">Update</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
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
  sidebarToggle.addEventListener('click', function () {
    sidebar.classList.toggle('open');
  });
}
</script>
</body>
</html>