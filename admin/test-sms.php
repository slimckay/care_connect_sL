<?php
/**
 * Admin — test live SMS + view recent sms_log
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../sms_helper.php';

$adminName = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$sms = new SmsHelper($conn);
$msg = '';
$err = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $text = trim($_POST['message'] ?? 'Care Connect SL test message. Reply STOP to opt out.');
    if ($phone === '') {
        $err = 'Enter a phone number.';
    } else {
        $result = $sms->send($phone, $text);
        if (!empty($result['ok'])) {
            $msg = !empty($result['demo'])
                ? 'Demo mode: SMS logged only. Add AT keys on Render for real delivery.'
                : 'Live SMS accepted by provider' . (isset($result['to']) ? ' → ' . $result['to'] : '') . '.';
        } else {
            $err = $result['error'] ?? 'Send failed';
        }
    }
}

$logs = [];
try {
    $logs = $conn->query('SELECT * FROM sms_log ORDER BY id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$active = 'settings';
$mode = $sms->modeLabel();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SMS test — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="admin-styles.css">
  <style>
    .mode { display:inline-block; padding:4px 12px; border-radius:999px; font-weight:700; font-size:.8rem; }
    .mode.live { background:#DCFCE7; color:#166534; }
    .mode.demo { background:#FEF3C7; color:#92400E; }
    .mode.disabled { background:#FEE2E2; color:#991B1B; }
    .form-card label { display:block; font-weight:600; margin:10px 0 4px; font-size:.9rem; }
    .form-card input, .form-card textarea { width:100%; padding:12px; border:1.5px solid #E2E8F0; border-radius:10px; font:inherit; }
    table { width:100%; border-collapse:collapse; font-size:.85rem; }
    th, td { text-align:left; padding:8px 10px; border-bottom:1px solid #E5E7EB; vertical-align:top; }
    th { color:#64748B; font-weight:600; }
  </style>
</head>
<body class="admin-body">
<div class="admin-wrapper">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <main class="admin-main">
    <div class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button">☰</button>
        <span class="page-title">SMS (live test)</span>
      </div>
      <div class="admin-topbar-right">
        <span class="mode <?= htmlspecialchars($mode) ?>"><?= strtoupper($mode) ?></span>
        <span class="welcome"><?= htmlspecialchars($adminName) ?></span>
      </div>
    </div>
    <div class="admin-content">
      <?php if ($msg): ?><div class="alert success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert error">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

      <div class="admin-card form-card">
        <div class="card-header">
          <h2>Send test SMS</h2>
          <span>Mode: <strong><?= htmlspecialchars($mode) ?></strong></span>
        </div>
        <p style="color:#64748B;font-size:.9rem;line-height:1.5">
          Live needs Africa's Talking keys on Render:<br>
          <code>SMS_ENABLED=1</code> · <code>SMS_PROVIDER=africastalking</code> ·
          <code>AT_USERNAME</code> · <code>AT_API_KEY</code> · <code>AT_SANDBOX=0</code>
        </p>
        <form method="POST">
          <label>Phone (Sierra Leone)</label>
          <input name="phone" placeholder="076123456 or +23276…" required>
          <label>Message</label>
          <textarea name="message" rows="3">Care Connect SL test: your SMS channel is working.</textarea>
          <button type="submit" class="btn-primary" style="margin-top:12px;border:none;padding:12px 20px;border-radius:10px;font-weight:700;cursor:pointer">Send SMS</button>
        </form>
        <?php if (is_array($result)): ?>
          <pre style="margin-top:14px;background:#F8FAFC;padding:12px;border-radius:10px;overflow:auto;font-size:.78rem"><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
        <?php endif; ?>
      </div>

      <div class="admin-card" style="margin-top:16px">
        <div class="card-header"><h2>Recent SMS log</h2></div>
        <?php if (empty($logs)): ?>
          <div class="empty">No SMS logged yet.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>ID</th><th>Phone</th><th>Status</th><th>Provider</th><th>Message</th><th>When</th></tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $l): ?>
                  <tr>
                    <td><?= (int)$l['id'] ?></td>
                    <td><?= htmlspecialchars($l['phone']) ?></td>
                    <td><?= htmlspecialchars($l['status']) ?></td>
                    <td><?= htmlspecialchars($l['provider'] ?? '') ?></td>
                    <td><?= htmlspecialchars(mb_substr($l['message'], 0, 80)) ?></td>
                    <td><?= htmlspecialchars($l['created_at'] ?? '') ?></td>
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
