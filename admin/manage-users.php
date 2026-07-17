<?php
/**
 * Manage Users - Care Connect SL Admin
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once '../db.php';

// Get all users
$stmt = $conn->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Handle user status toggle
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'activate' || $action === 'deactivate' || $action === 'ban') {
        $status = ($action === 'activate') ? 'active' : (($action === 'ban') ? 'banned' : 'inactive');
        try {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            
            // Log activity
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $logStmt->execute([$_SESSION['admin_id'], 'user_' . $action, "User ID $user_id: $status"]);
            
            header("Location: manage-users.php?message=User updated successfully!");
            exit;
        } catch (PDOException $e) {
            error_log("User update error: " . $e->getMessage());
            $error = "Failed to update user.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Manage Users — Care Connect SL Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar" id="sidebar">
        <div class="sb-logo">
            <h1>❤️ Care<em>Connect</em> SL</h1>
            <div class="sub">Administration Panel</div>
            <span class="badge">ADMIN</span>
        </div>
        <nav class="sb-nav">
            <div class="sb-section">Overview</div>
            <a href="admin-dashboard.php" class="sb-item">📊 Dashboard</a>
            <a href="verify-providers.php" class="sb-item">📋 Verify Providers</a>
            <div class="sb-section">Manage</div>
            <a href="manage-users.php" class="sb-item active">👥 Users</a>
            <a href="manage-referrals.php" class="sb-item">📋 Referrals</a>
            <div class="sb-section">System</div>
            <a href="admin-settings.php" class="sb-item">⚙️ Settings</a>
            <a href="logout.php" class="sb-item" style="color: var(--danger);">🚪 Logout</a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" id="sidebarToggle">☰</button>
                <span class="page-title">Manage Users</span>
            </div>
            <div class="admin-user">
                <span class="name"><?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></span>
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?></div>
            </div>
        </div>

        <div class="admin-content">
            <?php if (isset($_GET['message'])): ?>
                <div class="form-message success">✅ <?php echo htmlspecialchars($_GET['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="form-message error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="admin-card">
                <div class="card-header">
                    <h2>👥 All Users</h2>
                    <span style="color: var(--muted); font-size: 0.9rem;">Total: <?php echo count($users); ?></span>
                </div>
                <div class="card-body">
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
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge"><?php echo ucfirst($user['role']); ?></span></td>
                                    <td><span class="badge <?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <a href="manage-users.php?action=deactivate&id=<?php echo $user['id']; ?>" class="btn-small" style="background: var(--warning);" onclick="return confirm('Deactivate this user?')">Deactivate</a>
                                            <a href="manage-users.php?action=ban&id=<?php echo $user['id']; ?>" class="btn-small danger" onclick="return confirm('Ban this user?')">Ban</a>
                                        <?php elseif ($user['status'] === 'inactive'): ?>
                                            <a href="manage-users.php?action=activate&id=<?php echo $user['id']; ?>" class="btn-small success" onclick="return confirm('Activate this user?')">Activate</a>
                                        <?php elseif ($user['status'] === 'banned'): ?>
                                            <a href="manage-users.php?action=activate&id=<?php echo $user['id']; ?>" class="btn-small success" onclick="return confirm('Unban this user?')">Unban</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('open');
});
</script>
</body>
</html>