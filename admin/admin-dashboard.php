<?php
/**
 * Admin Dashboard - Care Connect SL
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Include database
require_once '../db.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Get statistics
try {
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $stmt->fetch()['count'] ?? 0;

    // Total patients
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient'");
    $totalPatients = $stmt->fetch()['count'] ?? 0;

    // Total providers (doctors + hospitals)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('doctor', 'hospital')");
    $totalProviders = $stmt->fetch()['count'] ?? 0;

    // Pending verifications
    $stmt = $conn->query("SELECT COUNT(*) as count FROM provider_profiles WHERE verification_status = 'pending'");
    $pendingVerifications = $stmt->fetch()['count'] ?? 0;

    // Total referrals
    $stmt = $conn->query("SELECT COUNT(*) as count FROM referrals");
    $totalReferrals = $stmt->fetch()['count'] ?? 0;

    // Referrals by status
    $stmt = $conn->query("
        SELECT status, COUNT(*) as count 
        FROM referrals 
        GROUP BY status
    ");
    $referralStats = $stmt->fetchAll();

    // Recent activities (last 5)
    $stmt = $conn->query("
        SELECT * FROM activity_logs 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentActivities = $stmt->fetchAll();

    // Recent referrals
    $stmt = $conn->query("
        SELECT r.*, u.name as patient_name 
        FROM referrals r
        LEFT JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC 
        LIMIT 5
    ");
    $recentReferrals = $stmt->fetchAll();

    // Pending verifications list
    $stmt = $conn->query("
        SELECT p.*, u.name, u.email 
        FROM provider_profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.verification_status = 'pending'
        ORDER BY p.created_at ASC
        LIMIT 5
    ");
    $pendingList = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $error = "Unable to load dashboard data.";
}

// Get unread notifications count
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = FALSE");
    $unreadNotifications = $stmt->fetch()['count'] ?? 0;
} catch (PDOException $e) {
    $unreadNotifications = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Dashboard — Care Connect SL</title>
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
            <a href="admin-dashboard.php" class="sb-item active">📊 Dashboard</a>
            <a href="verify-providers.php" class="sb-item">📋 Verify Providers <?php if ($pendingVerifications > 0): ?><span class="notification-dot"><?php echo $pendingVerifications; ?></span><?php endif; ?></a>
            <div class="sb-section">Manage</div>
            <a href="manage-users.php" class="sb-item">👥 Users</a>
            <a href="manage-referrals.php" class="sb-item">📋 Referrals</a>
            <div class="sb-section">System</div>
            <a href="admin-settings.php" class="sb-item">⚙️ Settings</a>
            <a href="../ai-chat.php" class="sb-item">💬 AI Assistant</a>
            <a href="../index.html" class="sb-item">🌐 View Website</a>
            <a href="../logout.php" class="sb-item" style="color: var(--danger);">🚪 Logout</a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">☰</button>
                <span class="page-title">Dashboard</span>
            </div>
            <div class="admin-user">
                <span class="name"><?php echo htmlspecialchars($admin_name); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
            </div>
        </div>

        <div class="admin-content">
            <?php if (isset($error)): ?>
                <div class="form-message error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px;">
                <div class="stat-card" style="background: var(--white); padding: 22px; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                    <span style="font-size: 1.8rem; font-weight: 700; color: var(--primary); display: block;"><?php echo $totalUsers; ?></span>
                    <span style="color: var(--muted);">Total Users</span>
                </div>
                <div class="stat-card" style="background: var(--white); padding: 22px; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                    <span style="font-size: 1.8rem; font-weight: 700; color: var(--primary); display: block;"><?php echo $totalPatients; ?></span>
                    <span style="color: var(--muted);">Patients</span>
                </div>
                <div class="stat-card" style="background: var(--white); padding: 22px; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                    <span style="font-size: 1.8rem; font-weight: 700; color: var(--primary); display: block;"><?php echo $totalProviders; ?></span>
                    <span style="color: var(--muted);">Providers</span>
                </div>
                <div class="stat-card" style="background: var(--white); padding: 22px; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                    <span style="font-size: 1.8rem; font-weight: 700; color: var(--primary); display: block;"><?php echo $totalReferrals; ?></span>
                    <span style="color: var(--muted);">Total Referrals</span>
                </div>
                <div class="stat-card" style="background: var(--white); padding: 22px; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                    <span style="font-size: 1.8rem; font-weight: 700; color: var(--warning); display: block;"><?php echo $pendingVerifications; ?></span>
                    <span style="color: var(--muted);">Pending Verifications</span>
                </div>
            </div>

            <!-- Recent Referrals -->
            <div class="admin-card">
                <div class="card-header">
                    <h2>📋 Recent Referrals</h2>
                    <a href="manage-referrals.php" class="view-all">View All →</a>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Condition</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentReferrals)): ?>
                                    <?php foreach ($recentReferrals as $ref): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ref['patient_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars(substr($ref['medical_condition'], 0, 40)) . '...'; ?></td>
                                            <td><span class="badge <?php echo $ref['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $ref['status'])); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($ref['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align: center; color: var(--muted);">No referrals yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pending Verifications -->
            <div class="admin-card">
                <div class="card-header">
                    <h2>⏳ Pending Provider Verifications</h2>
                    <a href="verify-providers.php" class="view-all">View All →</a>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Specialty</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pendingList)): ?>
                                    <?php foreach ($pendingList as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                                            <td><?php echo htmlspecialchars($p['specialty'] ?? 'N/A'); ?></td>
                                            <td>
                                                <a href="verify-providers.php?action=verify&id=<?php echo $p['user_id']; ?>" class="btn-small" style="background: var(--success);">Verify</a>
                                                <a href="verify-providers.php?action=reject&id=<?php echo $p['user_id']; ?>" class="btn-small danger">Reject</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align: center; color: var(--muted);">No pending verifications.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="admin-card">
                <div class="card-header">
                    <h2>🕐 Recent Activity</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div style="padding: 8px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between;">
                                <span><?php echo htmlspecialchars($activity['details']); ?></span>
                                <span style="color: var(--muted); font-size: 0.85rem;"><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--muted); text-align: center;">No recent activity.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('open');
});

document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    if (window.innerWidth <= 768 &&
        !sidebar.contains(e.target) &&
        !toggle.contains(e.target) &&
        sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
    }
});
</script>
</body>
</html>