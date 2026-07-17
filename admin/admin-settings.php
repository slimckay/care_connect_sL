<?php
/**
 * Admin Settings - Care Connect SL
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Placeholder for future settings
    $message = "Settings saved successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Settings — Care Connect SL</title>
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
            <a href="manage-referrals.php" class="sb-item">📋 Referrals</a>
            <div class="sb-section">System</div>
            <a href="admin-settings.php" class="sb-item active">⚙️ Settings</a>
            <a href="logout.php" class="sb-item" style="color: var(--danger);">🚪 Logout</a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" id="sidebarToggle">☰</button>
                <span class="page-title">Admin Settings</span>
            </div>
            <div class="admin-user">
                <span class="name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?></div>
            </div>
        </div>

        <div class="admin-content">
            <?php if (isset($message)): ?>
                <div class="form-message success">✅ <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="admin-card">
                <div class="card-header">
                    <h2>⚙️ System Settings</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div style="display: grid; gap: 20px; max-width: 600px;">
                            <div>
                                <label for="site_name">Site Name</label>
                                <input type="text" id="site_name" name="site_name" value="Care Connect SL" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: var(--radius-md);">
                            </div>
                            <div>
                                <label for="site_email">Support Email</label>
                                <input type="email" id="site_email" name="site_email" value="hello@careconnect.sl" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: var(--radius-md);">
                            </div>
                            <div>
                                <label for="site_phone">Support Phone</label>
                                <input type="text" id="site_phone" name="site_phone" value="+232 76 000 000" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: var(--radius-md);">
                            </div>
                            <div>
                                <button type="submit" class="btn-primary" style="width: 100%;">Save Settings</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-header">
                    <h2>📊 System Information</h2>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div style="padding: 12px; background: var(--light); border-radius: var(--radius-sm);">
                            <span style="color: var(--muted); font-size: 0.85rem;">PHP Version</span>
                            <p style="font-weight: 600; margin-top: 4px;"><?php echo phpversion(); ?></p>
                        </div>
                        <div style="padding: 12px; background: var(--light); border-radius: var(--radius-sm);">
                            <span style="color: var(--muted); font-size: 0.85rem;">Database</span>
                            <p style="font-weight: 600; margin-top: 4px;">MySQL</p>
                        </div>
                        <div style="padding: 12px; background: var(--light); border-radius: var(--radius-sm);">
                            <span style="color: var(--muted); font-size: 0.85rem;">Server Time</span>
                            <p style="font-weight: 600; margin-top: 4px;"><?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                        <div style="padding: 12px; background: var(--light); border-radius: var(--radius-sm);">
                            <span style="color: var(--muted); font-size: 0.85rem;">Admin Logged In</span>
                            <p style="font-weight: 600; margin-top: 4px;"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Unknown'); ?></p>
                        </div>
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