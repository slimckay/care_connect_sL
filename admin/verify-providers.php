<?php
/**
 * Verify Providers - Care Connect SL Admin
 * Admin panel to verify doctor/hospital profiles
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database
require_once '../db.php';

$admin_id = $_SESSION['admin_id'] ?? null;

// 🔥 FIX: Use a valid admin_id (fallback to 1 if null)
if ($admin_id === null) {
    error_log("⚠️ Admin ID is NULL in session! Using fallback ID 1.");
    $admin_id = 1; // Fallback to admin user ID 1
}

// Handle verification actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $provider_id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'verify' || $action === 'reject') {
        try {
            $status = ($action === 'verify') ? 'verified' : 'rejected';
            
            // Start transaction
            $conn->beginTransaction();
            
            // Update provider profile
            $stmt = $conn->prepare("
                UPDATE provider_profiles 
                SET verification_status = ?, verified_at = NOW(), verified_by = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$status, $admin_id, $provider_id]);

            // Update user status if verified
            if ($status === 'verified') {
                $roleStmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                $roleStmt->execute([$provider_id]);
            }

            // Log activity - with table check
            try {
                $checkTable = $conn->query("SHOW TABLES LIKE 'activity_logs'");
                if ($checkTable->rowCount() > 0) {
                    $logStmt = $conn->prepare("
                        INSERT INTO activity_logs (user_id, action, details, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $logStmt->execute([
                        $admin_id,
                        'provider_verification',
                        "Provider ID $provider_id: $status"
                    ]);
                }
            } catch (PDOException $e) {
                // Log table doesn't exist - skip logging
                error_log("Activity logs table not found: " . $e->getMessage());
            }

            // Commit transaction
            $conn->commit();

            $message = "Provider " . ($action === 'verify' ? 'verified' : 'rejected') . " successfully!";
            header("Location: verify-providers.php?message=" . urlencode($message));
            exit;

        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Verification error: " . $e->getMessage());
            $error = "Failed to update verification status. Error: " . $e->getMessage();
        }
    }
}

// Get all provider profiles
try {
    $stmt = $conn->prepare("
        SELECT p.*, u.name, u.email, u.created_at as user_created
        FROM provider_profiles p
        JOIN users u ON p.user_id = u.id
        ORDER BY FIELD(p.verification_status, 'pending', 'rejected', 'verified'), p.created_at DESC
    ");
    $stmt->execute();
    $providers = $stmt->fetchAll();

    // Get counts by status
    $statusCounts = [];
    foreach ($providers as $p) {
        $status = $p['verification_status'] ?? 'pending';
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }

} catch (PDOException $e) {
    error_log("Verify providers error: " . $e->getMessage());
    $error = "Unable to load provider data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Verify Providers — Care Connect SL Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>

<div class="admin-wrapper">

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <div class="sb-logo">
            <h1>❤️ Care<em>Connect</em> SL</h1>
            <div class="sub">Administration Panel</div>
            <span class="badge">ADMIN</span>
        </div>
        <nav class="sb-nav">
            <div class="sb-section">Overview</div>
            <a href="admin-dashboard.php" class="sb-item">📊 Dashboard</a>
            <a href="verify-providers.php" class="sb-item active">📋 Verify Providers</a>
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

    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">☰</button>
                <span class="page-title">Verify Providers</span>
            </div>
            <div class="admin-user">
                <span class="name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
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

            <!-- Status Counts -->
            <div style="display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;">
                <div style="padding: 12px 20px; background: var(--white); border-radius: var(--radius-lg); border-left: 4px solid var(--warning);">
                    <span style="font-weight: 700; font-size: 1.2rem;"><?php echo $statusCounts['pending'] ?? 0; ?></span>
                    <span style="color: var(--muted); margin-left: 8px;">⏳ Pending</span>
                </div>
                <div style="padding: 12px 20px; background: var(--white); border-radius: var(--radius-lg); border-left: 4px solid var(--success);">
                    <span style="font-weight: 700; font-size: 1.2rem;"><?php echo $statusCounts['verified'] ?? 0; ?></span>
                    <span style="color: var(--muted); margin-left: 8px;">✅ Verified</span>
                </div>
                <div style="padding: 12px 20px; background: var(--white); border-radius: var(--radius-lg); border-left: 4px solid var(--danger);">
                    <span style="font-weight: 700; font-size: 1.2rem;"><?php echo $statusCounts['rejected'] ?? 0; ?></span>
                    <span style="color: var(--muted); margin-left: 8px;">❌ Rejected</span>
                </div>
                <div style="padding: 12px 20px; background: var(--white); border-radius: var(--radius-lg); border-left: 4px solid var(--primary);">
                    <span style="font-weight: 700; font-size: 1.2rem;"><?php echo count($providers); ?></span>
                    <span style="color: var(--muted); margin-left: 8px;">📊 Total</span>
                </div>
            </div>

            <!-- Provider List -->
            <div class="admin-card">
                <div class="card-header">
                    <h2>👨‍⚕️ All Providers</h2>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Specialty</th>
                                    <th>Clinic</th>
                                    <th>Experience</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($providers)): ?>
                                    <?php foreach ($providers as $provider): ?>
                                        <tr>
                                            <td><?php echo $provider['user_id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($provider['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($provider['email']); ?></td>
                                            <td><?php echo htmlspecialchars($provider['specialty'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($provider['clinic_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $provider['experience_years'] ?? 0; ?> yrs</td>
                                            <td>
                                                <span class="badge <?php echo $provider['verification_status']; ?>">
                                                    <?php echo ucfirst($provider['verification_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($provider['verification_status'] === 'pending'): ?>
                                                    <a href="verify-providers.php?action=verify&id=<?php echo $provider['user_id']; ?>" 
                                                       class="btn-small" 
                                                       style="background: var(--success); color: white; padding: 4px 14px;"
                                                       onclick="return confirm('Verify this provider? They will be able to accept referrals.')">
                                                        ✅ Verify
                                                    </a>
                                                    <a href="verify-providers.php?action=reject&id=<?php echo $provider['user_id']; ?>" 
                                                       class="btn-small" 
                                                       style="background: var(--danger); color: white; padding: 4px 14px;"
                                                       onclick="return confirm('Reject this provider? They will need to re-submit.')">
                                                        ❌ Reject
                                                    </a>
                                                <?php elseif ($provider['verification_status'] === 'verified'): ?>
                                                    <span style="color: var(--success); font-size: 0.85rem;">✅ Verified</span>
                                                    <a href="verify-providers.php?action=reject&id=<?php echo $provider['user_id']; ?>" 
                                                       class="btn-small" 
                                                       style="background: var(--danger); color: white; padding: 4px 14px;"
                                                       onclick="return confirm('Revoke verification for this provider?')">
                                                        Revoke
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: var(--danger); font-size: 0.85rem;">❌ Rejected</span>
                                                    <a href="verify-providers.php?action=verify&id=<?php echo $provider['user_id']; ?>" 
                                                       class="btn-small" 
                                                       style="background: var(--success); color: white; padding: 4px 14px;"
                                                       onclick="return confirm('Re-verify this provider?')">
                                                        ✅ Re-verify
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: var(--muted); padding: 32px;">
                                            No providers registered yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Info -->
            <div style="background: var(--light); padding: 16px 20px; border-radius: var(--radius-lg); margin-top: 16px;">
                <p style="color: var(--muted); font-size: 0.9rem; margin: 0;">
                    💡 <strong>Tip:</strong> Providers must be <strong>verified</strong> before they can accept referrals. 
                    Review their credentials and documents before verifying.
                </p>
            </div>

        </div>
    </main>
</div>

<script>
// Sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('open');
});

// Close sidebar when clicking outside on mobile
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