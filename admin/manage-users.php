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
admin_mark_seen('users'); // opening Users clears the badge

$admin_name = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$message = '';
$error = '';

if (isset($_GET['action'], $_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $action = $_GET['action'];

    if (in_array($action, ['activate', 'deactivate', 'ban'], true)) {
        $status = $action === 'activate' ? 'active' : ($action === 'ban' ? 'banned' : 'inactive');
        try {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            header('Location: manage-users.php?message=' . urlencode('User updated successfully'));
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to update user.';
        }
    }
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

$users = [];
try {
    $users = $conn->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $error = 'Could not load users.';
}

$active = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users — Care Connect SL Admin</title>
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
        <span class="page-title">Manage Users</span>
      </div>
      <div class="admin-topbar-right">
        <button onclick="toggleDarkMode()" class="dark-toggle" type="button">🌓</button>
        <span class="welcome">Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong></span>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($message): ?><div class="alert success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="admin-card">
        <div class="card-header">
          <h2>All Users</h2>
          <span>Total: <?= count($users) ?></span>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
                <tr><td colspan="7" class="empty">No users found.</td></tr>
              <?php endif; ?>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td>#<?= (int)$user['id'] ?></td>
                  <td><?= htmlspecialchars($user['name']) ?></td>
                  <td><?= htmlspecialchars($user['email']) ?></td>
                  <td><span class="badge <?= htmlspecialchars($user['role']) ?>"><?= htmlspecialchars(ucfirst($user['role'])) ?></span></td>
                  <td><span class="badge <?= htmlspecialchars($user['status']) ?>"><?= htmlspecialchars(ucfirst($user['status'])) ?></span></td>
                  <td><?= !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '-' ?></td>
                  <td>
                    <?php if ($user['status'] === 'active'): ?>
                      <a class="btn-small warning" href="manage-users.php?action=deactivate&id=<?= (int)$user['id'] ?>" onclick="return confirm('Deactivate this user?')">Deactivate</a>
                      <a class="btn-small danger" href="manage-users.php?action=ban&id=<?= (int)$user['id'] ?>" onclick="return confirm('Ban this user?')">Ban</a>
                    <?php else: ?>
                      <a class="btn-small success" href="manage-users.php?action=activate&id=<?= (int)$user['id'] ?>" onclick="return confirm('Activate this user?')">Activate</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
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
