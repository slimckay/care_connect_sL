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

$adminName = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$adminId = (int)($_SESSION['user_id'] ?? 0);
$message = '';
$error = '';

// Opening this page clears the Notifications badge
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_clear_notifications($conn, $adminId);
}

// Mark notifications read (manual actions still work)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'mark_all_read' && $adminId > 0) {
            $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$adminId]);
            $message = 'All notifications marked as read.';
            admin_mark_seen('notifications');
        } elseif ($action === 'mark_read') {
            $id = (int)($_POST['notification_id'] ?? 0);
            if ($id > 0) {
                $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $adminId]);
                $message = 'Notification marked as read.';
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['notification_id'] ?? 0);
            if ($id > 0) {
                $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$id, $adminId]);
                $message = 'Notification deleted.';
            }
        }
    } catch (Exception $e) {
        $error = 'Could not update notifications.';
    }
}

$notifications = [];
$unreadCount = 0;

try {
    if ($adminId > 0) {
        $stmt = $conn->prepare("
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$adminId]);
        $notifications = $stmt->fetchAll();

        $c = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $c->execute([$adminId]);
        $unreadCount = (int)$c->fetchColumn();
    }
} catch (Exception $e) {
    $error = $error ?: 'Could not load notifications.';
}

$active = 'notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications — Care Connect SL Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="admin-styles.css">
  <style>
    .n-card {
      border: 1px solid #E5E7EB;
      border-radius: 14px;
      padding: 16px 18px;
      margin-bottom: 12px;
      background: #fff;
    }
    .n-card.unread {
      border-left: 4px solid #1EB53A;
      background: #F0FDF4;
    }
    .n-top { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .n-top h3 { margin:0 0 6px; font-size:1rem; color:#0F1C3A !important; }
    .n-msg { color:#334155 !important; line-height:1.55; margin:8px 0 12px; }
    .n-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .n-actions form { display:inline; }
    .n-actions button {
      border:none; border-radius:999px; padding:8px 14px; font-weight:600; cursor:pointer; font-size:0.85rem;
    }
    .btn-soft { background:#E2E8F0; color:#0F172A; }
    .btn-del { background:#FEE2E2; color:#B91C1C; }
    [data-theme="dark"] .n-card { background:#1e293b; border-color:#334155; }
    [data-theme="dark"] .n-card.unread { background:#052e16; border-color:#16A34A; }
    [data-theme="dark"] .n-top h3 { color:#F8FAFC !important; }
    [data-theme="dark"] .n-msg { color:#E2E8F0 !important; }
  </style>
</head>
<body class="admin-body">
<div class="admin-wrapper">
  <?php include __DIR__ . '/_sidebar.php'; ?>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button">☰</button>
        <span class="page-title">Notifications</span>
      </div>
      <div class="admin-topbar-right">
        <button onclick="toggleDarkMode()" class="dark-toggle" type="button">🌓</button>
        <span class="welcome">Welcome, <strong><?= htmlspecialchars($adminName) ?></strong></span>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($message): ?><div class="alert success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="admin-card">
        <div class="card-header">
          <h2>System Notifications <?= $unreadCount > 0 ? '(' . $unreadCount . ' unread)' : '' ?></h2>
          <?php if ($unreadCount > 0): ?>
            <form method="POST">
              <input type="hidden" name="action" value="mark_all_read">
              <button type="submit" class="btn-admin" style="padding:8px 14px;">Mark all read</button>
            </form>
          <?php endif; ?>
        </div>

        <p class="muted" style="margin:0 0 14px;">
          These appear when a doctor marks a patient as done, and for other system events tied to your admin account.
        </p>

        <?php if (empty($notifications)): ?>
          <div class="empty">No notifications yet. When a provider finishes a patient, it will show here.</div>
        <?php else: ?>
          <?php foreach ($notifications as $n): ?>
            <?php $unread = empty($n['is_read']); ?>
            <div class="n-card <?= $unread ? 'unread' : '' ?>">
              <div class="n-top">
                <div>
                  <h3><?= htmlspecialchars($n['title'] ?? 'Notification') ?></h3>
                  <div class="muted">
                    <?= htmlspecialchars($n['type'] ?? 'system') ?>
                    · <?= !empty($n['created_at']) ? date('M d, Y H:i', strtotime($n['created_at'])) : '' ?>
                  </div>
                </div>
                <?php if ($unread): ?>
                  <span class="badge pending">Unread</span>
                <?php endif; ?>
              </div>
              <div class="n-msg"><?= htmlspecialchars($n['message'] ?? '') ?></div>
              <div class="n-actions">
                <?php if (!empty($n['link'])): ?>
                  <a href="../<?= htmlspecialchars(ltrim($n['link'], '/')) ?>" class="btn-admin" style="padding:8px 14px; border-radius:999px; font-size:0.85rem; text-decoration:none;">Open</a>
                <?php endif; ?>
                <?php if ($unread): ?>
                  <form method="POST">
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
                    <button type="submit" class="btn-soft">Mark read</button>
                  </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this notification?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
                  <button type="submit" class="btn-del">Delete</button>
                </form>
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
