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

// Mark message status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['message_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['read', 'replied', 'new', 'delete'], true)) {
        try {
            if ($action === 'delete') {
                $conn->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
                $message = 'Message deleted.';
            } else {
                $conn->prepare("UPDATE contact_messages SET status = ?, updated_at = NOW() WHERE id = ?")
                     ->execute([$action, $id]);
                $message = 'Message marked as ' . $action . '.';
            }
        } catch (Exception $e) {
            $error = 'Could not update message.';
        }
    }
}

$filter = $_GET['status'] ?? 'all';
$messages = [];
$counts = ['all' => 0, 'new' => 0, 'read' => 0, 'replied' => 0];

try {
    $sql = "SELECT * FROM contact_messages WHERE 1=1";
    $params = [];
    if (in_array($filter, ['new', 'read', 'replied'], true)) {
        $sql .= " AND status = ?";
        $params[] = $filter;
    }
    $sql .= " ORDER BY created_at DESC LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    $rows = $conn->query("SELECT status, COUNT(*) AS total FROM contact_messages GROUP BY status")->fetchAll();
    foreach ($rows as $row) {
        $key = $row['status'] ?? 'new';
        $counts[$key] = (int)$row['total'];
        $counts['all'] += (int)$row['total'];
    }
} catch (Exception $e) {
    $error = $error ?: 'Could not load messages. Contact form table may be missing.';
}

$active = 'messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages — Care Connect SL Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="admin-styles.css">
  <style>
    .msg-card {
      border: 1px solid #E5E7EB;
      border-radius: 14px;
      padding: 16px 18px;
      margin-bottom: 12px;
      background: #fff;
    }
    .msg-card.new { border-left: 4px solid #DC2626; }
    .msg-card.read { border-left: 4px solid #2563EB; }
    .msg-card.replied { border-left: 4px solid #16A34A; }
    .msg-top { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
    .msg-top h3 { margin:0; font-size:1rem; color:#0F1C3A !important; }
    .msg-body { color:#334155 !important; line-height:1.55; margin:10px 0 14px; white-space:pre-wrap; }
    .msg-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .msg-actions form { display:inline; }
    .msg-actions button {
      border:none; border-radius:999px; padding:8px 14px; font-weight:600; cursor:pointer; font-size:0.85rem;
    }
    .btn-read { background:#DBEAFE; color:#1D4ED8; }
    .btn-replied { background:#DCFCE7; color:#15803D; }
    .btn-new { background:#FEF3C7; color:#B45309; }
    .btn-del { background:#FEE2E2; color:#B91C1C; }
    [data-theme="dark"] .msg-card { background:#1e293b; border-color:#334155; }
    [data-theme="dark"] .msg-top h3 { color:#F8FAFC !important; }
    [data-theme="dark"] .msg-body { color:#E2E8F0 !important; }
  </style>
</head>
<body class="admin-body">
<div class="admin-wrapper">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button">☰</button>
        <span class="page-title">Contact Messages</span>
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
        <a href="?status=new" class="<?= $filter === 'new' ? 'active' : '' ?>">New (<?= $counts['new'] ?>)</a>
        <a href="?status=read" class="<?= $filter === 'read' ? 'active' : '' ?>">Read (<?= $counts['read'] ?>)</a>
        <a href="?status=replied" class="<?= $filter === 'replied' ? 'active' : '' ?>">Replied (<?= $counts['replied'] ?>)</a>
      </div>

      <div class="admin-card">
        <div class="card-header">
          <h2>Inbox</h2>
          <span>Messages from the website contact form</span>
        </div>

        <?php if (empty($messages)): ?>
          <div class="empty">No messages yet.</div>
        <?php else: ?>
          <?php foreach ($messages as $m): ?>
            <?php $st = $m['status'] ?? 'new'; ?>
            <div class="msg-card <?= htmlspecialchars($st) ?>">
              <div class="msg-top">
                <div>
                  <h3><?= htmlspecialchars($m['name'] ?? 'Unknown') ?></h3>
                  <div class="muted">
                    <?= htmlspecialchars($m['email'] ?? '') ?>
                    <?php if (!empty($m['phone'])): ?> · <?= htmlspecialchars($m['phone']) ?><?php endif; ?>
                  </div>
                </div>
                <div style="text-align:right;">
                  <span class="badge <?= $st === 'new' ? 'pending' : ($st === 'replied' ? 'completed' : 'in_progress') ?>"><?= htmlspecialchars(ucfirst($st)) ?></span>
                  <div class="muted" style="margin-top:6px;">
                    <?= !empty($m['created_at']) ? date('M d, Y H:i', strtotime($m['created_at'])) : '' ?>
                  </div>
                </div>
              </div>
              <div class="msg-body"><?= htmlspecialchars($m['message'] ?? '') ?></div>
              <div class="msg-actions">
                <?php if ($st !== 'read'): ?>
                  <form method="POST">
                    <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="action" value="read">
                    <button type="submit" class="btn-read">Mark Read</button>
                  </form>
                <?php endif; ?>
                <?php if ($st !== 'replied'): ?>
                  <form method="POST">
                    <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="action" value="replied">
                    <button type="submit" class="btn-replied">Mark Replied</button>
                  </form>
                <?php endif; ?>
                <?php if ($st !== 'new'): ?>
                  <form method="POST">
                    <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="action" value="new">
                    <button type="submit" class="btn-new">Mark New</button>
                  </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this message?')">
                  <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn-del">Delete</button>
                </form>
                <?php if (!empty($m['email'])): ?>
                  <a href="mailto:<?= htmlspecialchars($m['email']) ?>" class="btn-admin" style="padding:8px 14px; border-radius:999px; font-size:0.85rem; text-decoration:none;">Reply by Email</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
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
