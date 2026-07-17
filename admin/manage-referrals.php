<?php
/**
 * Manage Referrals - Care Connect SL Admin
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once '../db.php';

// Get all referrals
$stmt = $conn->query("
    SELECT r.*, u.name as patient_name 
    FROM referrals r
    LEFT JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
");
$referrals = $stmt->fetchAll();

// Handle referral status update
if (isset($_POST['update_status']) && isset($_POST['referral_id']) && isset($_POST['status'])) {
    $referral_id = (int)$_POST['referral_id'];
    $status = $_POST['status'];
    $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
    
    if (in_array($status, $valid_statuses)) {
        try {
            $stmt = $conn->prepare("UPDATE referrals SET status = ? WHERE id = ?");
            $stmt->execute([$status, $referral_id]);
            
            // Log activity
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $logStmt->execute([$_SESSION['admin_id'], 'referral_update', "Referral ID $referral_id: status updated to $status"]);
            
            header("Location: manage-referrals.php?message=Referral updated successfully!");
            exit;
        } catch (PDOException $e) {
            error_log("Referral update error: " . $e->getMessage());
            $error = "Failed to update referral.";
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
    <title>Manage Referrals — Care Connect SL Admin</title>
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
            <a href="manage-users.php" class="sb-item">👥 Users</a>
            <a href="manage-referrals.php" class="sb-item active">📋 Referrals</a>
            <div class="sb-section">System</div>
            <a href="admin-settings.php" class="sb-item">⚙️ Settings</a>
            <a href="logout.php" class="sb-item" style="color: var(--danger);">🚪 Logout</a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" id="sidebarToggle">☰</button>
                <span class="page-title">Manage Referrals</span>
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
                    <h2>📋 All Referrals</h2>
                    <span style="color: var(--muted); font-size: 0.9rem;">Total: <?php echo count($referrals); ?></span>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($referrals as $ref): ?>
                                <tr>
                                    <td><?php echo $ref['id']; ?></td>
                                    <td><?php echo htmlspecialchars($ref['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($ref['contact']); ?></td>
                                    <td><?php echo htmlspecialchars($ref['location']); ?></td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                                            <input type="hidden" name="referral_id" value="<?php echo $ref['id']; ?>">
                                            <select name="status" class="badge" style="padding: 4px 8px; border-radius: 20px; border: 1px solid var(--border); font-size: 0.75rem; background: var(--white);">
                                                <option value="pending" <?php echo $ref['status'] === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                                                <option value="in_progress" <?php echo $ref['status'] === 'in_progress' ? 'selected' : ''; ?>>🔄 In Progress</option>
                                                <option value="completed" <?php echo $ref['status'] === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                                                <option value="cancelled" <?php echo $ref['status'] === 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn-small" style="padding: 2px 12px;">Update</button>
                                        </form>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($ref['created_at'])); ?></td>
                                    <td>
                                        <a href="../pages/referral.html?view=<?php echo $ref['id']; ?>" class="btn-small" style="padding: 2px 12px;">View</a>
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