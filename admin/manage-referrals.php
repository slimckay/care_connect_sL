<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';
require_once __DIR__ . '/_badge_seen.php';
admin_mark_seen('referrals'); // opening page clears badge

// Ensure follow-up columns exist
try {
    if (!$conn->query("SHOW COLUMNS FROM referrals LIKE 'follow_up_date'")->fetch()) {
        $conn->exec("ALTER TABLE referrals ADD COLUMN follow_up_date DATE NULL");
    }
    if (!$conn->query("SHOW COLUMNS FROM referrals LIKE 'follow_up_notes'")->fetch()) {
        $conn->exec("ALTER TABLE referrals ADD COLUMN follow_up_notes TEXT NULL");
    }
} catch (Exception $e) {}

$adminName = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $referralId = (int)($_POST['referral_id'] ?? 0);

    if ($referralId > 0 && isset($_POST['update_status'], $_POST['status'])) {
        $status = $_POST['status'];
        $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (in_array($status, $validStatuses, true)) {
            try {
                $conn->prepare("UPDATE referrals SET status = ?, updated_at = NOW() WHERE id = ?")
                     ->execute([$status, $referralId]);
                header('Location: manage-referrals.php?message=' . urlencode('Referral updated successfully'));
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to update referral status.';
            }
        } else {
            $error = 'Invalid status selected.';
        }
    }

    if ($referralId > 0 && isset($_POST['save_follow_up'])) {
        $followUpDate = trim($_POST['follow_up_date'] ?? '');
        $followUpNotes = trim($_POST['follow_up_notes'] ?? '');
        if ($followUpDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $followUpDate)) {
            $error = 'Invalid follow-up date.';
        } else {
            try {
                $conn->prepare("
                    UPDATE referrals
                    SET follow_up_date = NULLIF(?, ''),
                        follow_up_notes = NULLIF(?, ''),
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$followUpDate, $followUpNotes, $referralId]);
                header('Location: manage-referrals.php?message=' . urlencode('Follow-up date saved'));
                exit;
            } catch (PDOException $e) {
                $error = 'Could not save follow-up date.';
            }
        }
    }
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

$filter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT r.* FROM referrals r WHERE 1=1";
$params = [];

if (in_array($filter, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
    $sql .= " AND r.status = ?";
    $params[] = $filter;
}
if ($filter === 'follow_up') {
    $sql .= " AND r.follow_up_date IS NOT NULL AND r.follow_up_date >= CURDATE()";
}

if ($search !== '') {
    $sql .= " AND (r.patient_name LIKE ? OR r.contact LIKE ? OR r.location LIKE ? OR COALESCE(r.preferred_clinic, '') LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($filter === 'follow_up') {
    $sql .= " ORDER BY r.follow_up_date ASC";
} else {
    $sql .= " ORDER BY r.created_at DESC";
}

$referrals = [];
$counts = ['all' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0, 'follow_up' => 0];

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
    try {
        $counts['follow_up'] = (int)$conn->query("SELECT COUNT(*) FROM referrals WHERE follow_up_date IS NOT NULL AND follow_up_date >= CURDATE()")->fetchColumn();
    } catch (Exception $e) {
        $counts['follow_up'] = 0;
    }
} catch (PDOException $e) {
    $error = 'Could not load referrals.';
}

$active = 'referrals';
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
    .status-form { display:flex; flex-direction:column; gap:8px; min-width:140px; }
    .status-form select, .fu-form input {
      padding:8px 10px; border:1px solid #E5E7EB; border-radius:8px; background:#fff; color:#1F2937; font:inherit;
    }
    .status-form button, .fu-form button {
      border:none; background:#0F1C3A; color:#fff; border-radius:8px; padding:8px 10px; font-weight:600; cursor:pointer;
    }
    .fu-form { display:flex; flex-direction:column; gap:6px; min-width:150px; }
    .patient-name { font-weight:700; color:#0F1C3A !important; margin-bottom:4px; }
    .condition-text { max-width:220px; color:#334155 !important; line-height:1.45; }
    .fu-date { font-weight:700; color:#3730A3 !important; font-size:0.88rem; }
    .fu-date.today { color:#B91C1C !important; }
    .fu-date.soon { color:#B45309 !important; }
    [data-theme="dark"] .status-form select,
    [data-theme="dark"] .fu-form input { background:#1e293b; border-color:#334155; color:#E2E8F0; }
    [data-theme="dark"] .patient-name { color:#F8FAFC !important; }
    [data-theme="dark"] .condition-text { color:#E2E8F0 !important; }
  </style>
</head>
<body class="admin-body">
<div class="admin-wrapper">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button">☰</button>
        <span class="page-title">Manage Referrals</span>
      </div>
      <div class="admin-topbar-right">
        <button onclick="toggleDarkMode()" class="dark-toggle" type="button">🌓</button>
        <span class="welcome">Welcome, <strong><?= htmlspecialchars($adminName) ?></strong></span>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($message): ?><div class="alert success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="filters">
        <a href="?status=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All (<?= $counts['all'] ?>)</a>
        <a href="?status=pending" class="<?= $filter === 'pending' ? 'active' : '' ?>">Pending (<?= $counts['pending'] ?>)</a>
        <a href="?status=in_progress" class="<?= $filter === 'in_progress' ? 'active' : '' ?>">In Progress (<?= $counts['in_progress'] ?>)</a>
        <a href="?status=completed" class="<?= $filter === 'completed' ? 'active' : '' ?>">Completed (<?= $counts['completed'] ?>)</a>
        <a href="?status=follow_up" class="<?= $filter === 'follow_up' ? 'active' : '' ?>">📅 Follow-ups (<?= $counts['follow_up'] ?>)</a>

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
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Patient</th>
                  <th>Condition</th>
                  <th>Status</th>
                  <th>Follow-up</th>
                  <th>Update status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($referrals as $ref): ?>
                  <?php
                    $patient = $ref['patient_name'] ?? 'Unknown';
                    $condition = $ref['condition'] ?? ($ref['medical_condition'] ?? 'Not provided');
                    $status = $ref['status'] ?? 'pending';
                    $fu = $ref['follow_up_date'] ?? '';
                    $fuClass = '';
                    if ($fu) {
                      $days = (int)round((strtotime($fu) - strtotime(date('Y-m-d'))) / 86400);
                      if ($days === 0) $fuClass = 'today';
                      elseif ($days > 0 && $days <= 3) $fuClass = 'soon';
                    }
                  ?>
                  <tr>
                    <td>#<?= (int)$ref['id'] ?></td>
                    <td>
                      <div class="patient-name"><?= htmlspecialchars($patient) ?></div>
                      <div class="muted">
                        <?= htmlspecialchars($ref['contact'] ?? 'No contact') ?>
                        <?php if (!empty($ref['age'])): ?> · Age <?= (int)$ref['age'] ?><?php endif; ?>
                      </div>
                      <div class="muted"><?= htmlspecialchars($ref['location'] ?? '-') ?></div>
                    </td>
                    <td>
                      <div class="condition-text"><?= htmlspecialchars($condition) ?></div>
                    </td>
                    <td><span class="badge <?= htmlspecialchars($status) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?></span></td>
                    <td>
                      <?php if ($fu): ?>
                        <div class="fu-date <?= $fuClass ?>"><?= date('M d, Y', strtotime($fu)) ?></div>
                        <?php if (!empty($ref['follow_up_notes'])): ?>
                          <div class="muted"><?= htmlspecialchars($ref['follow_up_notes']) ?></div>
                        <?php endif; ?>
                      <?php else: ?>
                        <div class="muted">Not set</div>
                      <?php endif; ?>
                      <form method="POST" class="fu-form" style="margin-top:8px;">
                        <input type="hidden" name="referral_id" value="<?= (int)$ref['id'] ?>">
                        <input type="date" name="follow_up_date" value="<?= htmlspecialchars($fu) ?>">
                        <input type="text" name="follow_up_notes" value="<?= htmlspecialchars($ref['follow_up_notes'] ?? '') ?>" placeholder="Follow-up note">
                        <button type="submit" name="save_follow_up" value="1">Save</button>
                      </form>
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
if (sidebarToggle && sidebar) sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
</script>
</body>
</html>
