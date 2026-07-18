<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

// Handle approval/rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    $stmt = $conn->prepare("SELECT u.email, u.name FROM provider_profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
    $stmt->execute([$id]);
    $provider = $stmt->fetch();

    if ($action === 'verify') {
        $conn->prepare("UPDATE provider_profiles SET verification_status = 'verified' WHERE user_id = ?")
             ->execute([$id]);

        if ($provider) {
            @mail($provider['email'], "Your Care Connect SL Application has been Approved", 
                "Hello " . $provider['name'] . ",\n\nCongratulations! Your application has been approved. You can now receive referrals.\n\nBest regards,\nCare Connect SL Team");
        }
    } elseif ($action === 'reject') {
        $conn->prepare("UPDATE provider_profiles SET verification_status = 'rejected' WHERE user_id = ?")
             ->execute([$id]);

        if ($provider) {
            @mail($provider['email'], "Update on Your Care Connect SL Application", 
                "Hello " . $provider['name'] . ",\n\nWe regret to inform you that your application has been rejected.\n\nYou may reapply with updated documents.\n\nBest regards,\nCare Connect SL Team");
        }
    }

    header('Location: verify-providers.php');
    exit;
}

$pending = $conn->query("
    SELECT p.*, u.name, u.email 
    FROM provider_profiles p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.verification_status = 'pending'
    ORDER BY p.created_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pending Verifications — Admin</title>
  <link rel="stylesheet" href="../style.css">
  <style>
    .provider-card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.06);
      border: 1px solid #e5e7eb;
    }
    .back-btn {
      display: inline-block;
      margin-bottom: 20px;
      color: #1EB53A;
      text-decoration: none;
      font-weight: 500;
    }
  </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="admin-dashboard.php" class="logo">Care<span class="accent">Connect</span> SL • Admin</a>
  </div>
</header>

<main style="max-width:1100px; margin:40px auto; padding:0 20px;">
  <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>

  <h1 style="margin-bottom:30px;">Pending Provider Verifications</h1>

  <?php if (empty($pending)): ?>
    <p style="color:#64748B;">No pending applications at the moment.</p>
  <?php else: ?>
    <?php foreach ($pending as $p): ?>
      <div class="provider-card">
        <div class="provider-header">
          <div class="provider-info">
            <h3><?= htmlspecialchars($p['name']) ?></h3>
            <p><?= htmlspecialchars($p['email']) ?></p>
          </div>
          <div style="text-align:right;">
            <span style="background:#fefce8; color:#854d0e; padding:4px 12px; border-radius:20px; font-size:0.85rem;">
              Applied: <?= date('M d, Y', strtotime($p['created_at'])) ?>
            </span>
          </div>
        </div>

        <div class="detail-row">
          <div class="detail-item">
            <label>Specialty / Services</label>
            <p><strong><?= htmlspecialchars($p['specialty'] ?? 'Not specified') ?></strong></p>
          </div>
          <div class="detail-item">
            <label>Experience</label>
            <p><strong><?= $p['experience_years'] ?? 0 ?> years</strong></p>
          </div>
        </div>

        <div style="margin:16px 0;">
          <label style="font-size:0.8rem; color:#94A3B8; display:block; margin-bottom:6px;">Verification Documents</label>
          <?php if (!empty($p['verification_documents'])): ?>
            <?php $docs = explode(',', $p['verification_documents']); ?>
            <?php foreach ($docs as $doc): ?>
              <a href="../<?= trim($doc) ?>" target="_blank" style="display:inline-block; margin-right:12px; color:#1EB53A; text-decoration:underline;">
                📄 View Document
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <span style="color:#DC2626;">No documents uploaded</span>
          <?php endif; ?>
        </div>

        <div class="action-buttons">
          <a href="?action=verify&id=<?= $p['user_id'] ?>" 
             onclick="return confirm('Approve this provider?')"
             class="btn-primary" style="background:#16A34A; padding:10px 24px; text-decoration:none; border-radius:8px;">
            ✅ Verify & Approve
          </a>

          <a href="?action=reject&id=<?= $p['user_id'] ?>" 
             onclick="return confirm('Reject this provider?')"
             class="btn-primary" style="background:#DC2626; padding:10px 24px; text-decoration:none; border-radius:8px;">
            ❌ Reject Application
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

</body>
</html>