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
admin_mark_seen('providers'); // opening page clears badge

$admin_name = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$message = '';

if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    try {
        $stmt = $conn->prepare("SELECT u.email, u.name FROM provider_profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
        $stmt->execute([$id]);
        $provider = $stmt->fetch();

        if ($action === 'verify') {
            $conn->prepare("UPDATE provider_profiles SET verification_status = 'verified' WHERE user_id = ?")->execute([$id]);
            if ($provider) {
                @mail($provider['email'], 'Your Care Connect SL Application has been Approved',
                    "Hello {$provider['name']},\n\nCongratulations! Your application has been approved.\n\nBest regards,\nCare Connect SL Team");
            }
            header('Location: verify-providers.php?message=' . urlencode('Provider verified'));
            exit;
        }

        if ($action === 'reject') {
            $conn->prepare("UPDATE provider_profiles SET verification_status = 'rejected' WHERE user_id = ?")->execute([$id]);
            if ($provider) {
                @mail($provider['email'], 'Update on Your Care Connect SL Application',
                    "Hello {$provider['name']},\n\nYour application was not approved. You may reapply with updated documents.\n\nBest regards,\nCare Connect SL Team");
            }
            header('Location: verify-providers.php?message=' . urlencode('Provider rejected'));
            exit;
        }
    } catch (Exception $e) {
        error_log('verify-providers: ' . $e->getMessage());
    }
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

$pending = [];
try {
    $pending = $conn->query("
        SELECT p.*, u.name, u.email
        FROM provider_profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.verification_status = 'pending'
        ORDER BY p.created_at ASC
    ")->fetchAll();
} catch (Exception $e) {
    $pending = [];
}

$active = 'providers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Providers — Care Connect SL Admin</title>
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
        <span class="page-title">Verify Providers</span>
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
          <h2>Pending Applications</h2>
          <span><?= count($pending) ?> waiting</span>
        </div>
        <div class="card-body">
          <?php if (empty($pending)): ?>
            <div class="empty">No pending provider applications.</div>
          <?php else: ?>
            <?php foreach ($pending as $p): ?>
              <div class="provider-card">
                <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                  <div>
                    <h3><?= htmlspecialchars($p['name']) ?></h3>
                    <div class="meta"><?= htmlspecialchars($p['email']) ?></div>
                  </div>
                  <span class="badge pending">Applied <?= !empty($p['created_at']) ? date('M d, Y', strtotime($p['created_at'])) : '' ?></span>
                </div>

                <div class="detail-grid">
                  <div class="detail-item">
                    <label>Specialty</label>
                    <p><?= htmlspecialchars($p['specialty'] ?? 'Not specified') ?></p>
                  </div>
                  <div class="detail-item">
                    <label>Experience</label>
                    <p><?= (int)($p['experience_years'] ?? 0) ?> years</p>
                  </div>
                  <div class="detail-item">
                    <label>Clinic</label>
                    <p><?= htmlspecialchars($p['clinic_name'] ?? 'Not set') ?></p>
                  </div>
                </div>

                <div style="margin-top:8px;">
                  <label class="muted" style="display:block; margin-bottom:6px;">Documents</label>
                  <?php if (!empty($p['verification_documents'])): ?>
                    <?php foreach (explode(',', $p['verification_documents']) as $doc): ?>
                      <?php $doc = trim($doc); if ($doc === '') continue; ?>
                      <a href="../<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener" style="display:inline-block; margin-right:12px; color:#1EB53A; font-weight:600; text-decoration:none;">📄 View document</a>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span style="color:#DC2626;">No documents uploaded</span>
                  <?php endif; ?>
                </div>

                <div class="provider-actions">
                  <a class="btn-small success" href="?action=verify&id=<?= (int)$p['user_id'] ?>" onclick="return confirm('Approve this provider?')">✅ Verify & Approve</a>
                  <a class="btn-small danger" href="?action=reject&id=<?= (int)$p['user_id'] ?>" onclick="return confirm('Reject this provider?')">❌ Reject</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
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
