<?php
/**
 * Admin Dashboard - Care Connect SL
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    // Stats
    $totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalPatients = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
    $totalProviders = $conn->query("SELECT COUNT(*) FROM users WHERE role IN ('doctor', 'hospital')")->fetchColumn();
    $totalReferrals = $conn->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
    $pendingReferrals = $conn->query("SELECT COUNT(*) FROM referrals WHERE status = 'pending'")->fetchColumn();
    $newMessages = $conn->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();

    // Recent Referrals
    $recentReferrals = $conn->query("
        SELECT r.*, u.name as patient_name 
        FROM referrals r 
        LEFT JOIN users u ON r.user_id = u.id 
        ORDER BY r.created_at DESC LIMIT 6
    ")->fetchAll();

    // Recent Activity
    $recentActivities = $conn->query("
        SELECT * FROM activity_logs 
        ORDER BY created_at DESC LIMIT 8
    ")->fetchAll();

} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $error = "Unable to load dashboard data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin-styles.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1EB53A;
        }
        .admin-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar">
        <div class="sb-logo">
            <h1>Care<span style="color:#1EB53A">Connect</span> SL</h1>
            <div class="sub">Administration Panel</div>
        </div>
        <nav class="sb-nav">
            <a href="admin-dashboard.php" class="sb-item active">📊 Dashboard</a>
            <a href="manage-referrals.php" class="sb-item">📋 Manage Referrals</a>
            <a href="manage-users.php" class="sb-item">👥 Manage Users</a>
            <a href="verify-providers.php" class="sb-item">✅ Verify Providers</a>
            <a href="../logout.php" class="sb-item" style="color:#ef4444; margin-top:40px;">🚪 Logout</a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <span class="page-title">Dashboard</span>
            <div>Welcome, <strong><?= htmlspecialchars($admin_name) ?></strong></div>
        </div>

        <div class="admin-content">
            <?php if (isset($error)): ?>
                <div class="form-message error"><?= $error ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalUsers ?></div>
                    <div>Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $totalPatients ?></div>
                    <div>Patients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $totalProviders ?></div>
                    <div>Providers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $totalReferrals ?></div>
                    <div>Total Referrals</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color:#f59e0b;"><?= $pendingReferrals ?></div>
                    <div>Pending Referrals</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color:#ef4444;"><?= $newMessages ?></div>
                    <div>New Messages</div>
                </div>
            </div>

            <!-- Recent Referrals -->
            <div class="admin-card">
                <div class="card-header">
                    <h2>Recent Referrals</h2>
                    <a href="manage-referrals.php">View All →</a>
                </div>
                <table class="data-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentReferrals as $ref): ?>
                            <tr>
                                <td><?= htmlspecialchars($ref['patient_name'] ?? 'Guest') ?></td>
                                <td><?= htmlspecialchars(substr($ref['medical_condition'] ?? '', 0, 50)) ?>...</td>
                                <td><span class="badge <?= $ref['status'] ?>"><?= ucfirst($ref['status']) ?></span></td>
                                <td><?= date('M d, Y', strtotime($ref['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Activity -->
            <div class="admin-card">
                <div class="card-header">
                    <h2>Recent Activity</h2>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($recentActivities as $act): ?>
                        <div style="padding: 10px 0; border-bottom: 1px solid #eee; display:flex; justify-content:space-between;">
                            <span><?= htmlspecialchars($act['details']) ?></span>
                            <span style="color:#64748b; font-size:0.85rem;"><?= date('M d H:i', strtotime($act['created_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>