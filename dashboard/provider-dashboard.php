<?php
/**
 * Provider Dashboard - Care Connect SL
 * For Doctors and Clinics
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user is a provider (doctor or hospital)
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'hospital') {
    if ($_SESSION['role'] === 'patient') {
        header('Location: patient-dashboard.php');
    } elseif ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/admin-dashboard.php');
    } else {
        header('Location: ../index.html');
    }
    exit;
}

// Include database
require_once '../db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = ucfirst($_SESSION['role']);

// Display success message from provider registration
if (isset($_SESSION['provider_success'])) {
    $success_message = $_SESSION['provider_success'];
    unset($_SESSION['provider_success']);
}

// Get provider profile status
try {
    $stmt = $conn->prepare("SELECT verification_status FROM provider_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    $verification_status = $profile['verification_status'] ?? 'not_submitted';
} catch (PDOException $e) {
    $verification_status = 'not_submitted';
}

try {
    // Get assigned referrals
    $stmt = $conn->prepare("
        SELECT r.*, u.name as patient_name 
        FROM referrals r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.assigned_to = ? OR r.assigned_to IS NULL
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $assignedReferrals = $stmt->fetchAll();

    // Get counts
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM referrals 
        WHERE assigned_to = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Provider dashboard error: " . $e->getMessage());
    $error = "Unable to load dashboard data.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Dashboard — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>

<!-- Preloader -->
<div id="preloader" role="status" aria-label="Loading">
    <div class="pulse-ring"></div>
    <svg class="heartbeat-svg" viewBox="0 0 300 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <polyline points="0,40 60,40 80,10 100,70 120,5 140,75 160,40 300,40" fill="none" stroke="#00C896" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <p class="preload-text">Care Connect SL</p>
</div>

<!-- Header -->
<header role="banner">
    <div class="nav-inner">
        <a href="../index.html" class="logo" aria-label="Care Connect SL Home">
            <span class="logo-icon" aria-hidden="true">❤️</span> Care<span class="accent">Connect</span> SL
        </a>
        <nav aria-label="Main navigation">
            <ul class="nav-links" role="menubar">
                <li><a href="../index.html" role="menuitem">Home</a></li>
                <li><a href="../pages/doctors.php" role="menuitem">Find Care</a></li>
                <li><a href="../pages/hospitals.html" role="menuitem">Clinics</a></li>
                <li><a href="../pages/referral.html" role="menuitem">New Referral</a></li>
            </ul>
        </nav>
        <div class="nav-actions">
            <span style="color: var(--muted); font-size: 0.9rem;">👨‍⚕️ <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="btn-ghost">Logout</a>
        </div>
    </div>
</header>

<!-- Main Dashboard -->
<main class="dashboard-main" role="main">
    <div class="dashboard-container">
        
        <!-- Success Message -->
        <?php if (isset($success_message)): ?>
            <div class="form-message success" style="margin-bottom: 20px;">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- Verification Status Banner -->
        <?php if ($verification_status === 'pending'): ?>
            <div class="form-message" style="background: rgba(245, 158, 11, 0.1); color: #B45309; border: 1px solid rgba(245, 158, 11, 0.2); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px;">
                ⏳ Your profile is pending verification. You'll be able to accept referrals once verified.
            </div>
        <?php elseif ($verification_status === 'rejected'): ?>
            <div class="form-message error" style="margin-bottom: 20px;">
                ❌ Your profile was rejected. Please <a href="../provider-registration.php">update your information</a> and resubmit.
            </div>
        <?php elseif ($verification_status === 'not_submitted'): ?>
            <div class="form-message" style="background: rgba(59, 130, 246, 0.1); color: #1D4ED8; border: 1px solid rgba(59, 130, 246, 0.2); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 20px;">
                📋 Please <a href="../provider-registration.php">complete your provider profile</a> to start receiving referrals.
            </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <section class="dashboard-welcome">
            <div class="welcome-content">
                <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>! 👨‍⚕️</h1>
                <p>Manage your patient referrals and clinic activities.</p>
                <span class="status-badge accepting">✅ <?php echo $user_role; ?> Account</span>
            </div>
            <div class="welcome-actions">
                <a href="../pages/referral.html" class="btn-primary">➕ New Referral</a>
                <?php if ($verification_status === 'not_submitted' || $verification_status === 'rejected'): ?>
                    <a href="../provider-registration.php" class="btn-ghost">📋 Complete Profile</a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Stats Cards -->
        <section class="stats-grid">
            <div class="stat-card stat-total">
                <div class="stat-icon">📋</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $stats['total'] ?? 0; ?></span>
                    <span class="stat-label">Total Referrals</span>
                </div>
            </div>
            <div class="stat-card stat-pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $stats['pending'] ?? 0; ?></span>
                    <span class="stat-label">Pending</span>
                </div>
            </div>
            <div class="stat-card stat-progress">
                <div class="stat-icon">🔄</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $stats['in_progress'] ?? 0; ?></span>
                    <span class="stat-label">In Progress</span>
                </div>
            </div>
            <div class="stat-card stat-completed">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $stats['completed'] ?? 0; ?></span>
                    <span class="stat-label">Completed</span>
                </div>
            </div>
        </section>

        <!-- Assigned Referrals -->
        <section class="dashboard-card">
            <div class="card-header">
                <h2>📋 Assigned Referrals</h2>
                <a href="#" class="view-all">View All →</a>
            </div>
            <div class="card-body">
                <?php if (!empty($assignedReferrals)): ?>
                    <div class="referral-list">
                        <?php foreach ($assignedReferrals as $referral): ?>
                            <div class="referral-item">
                                <div class="referral-info">
                                    <h4><?php echo htmlspecialchars($referral['patient_name'] ?? 'Unknown Patient'); ?></h4>
                                    <p class="referral-condition"><?php echo htmlspecialchars(substr($referral['medical_condition'], 0, 60)) . '...'; ?></p>
                                    <span class="referral-date"><?php echo date('M d, Y', strtotime($referral['created_at'])); ?></span>
                                </div>
                                <div class="referral-status">
                                    <span class="badge <?php echo $referral['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $referral['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No referrals assigned to you yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="quick-actions">
            <h2>⚡ Quick Actions</h2>
            <div class="action-grid">
                <a href="../pages/referral.html" class="action-card">
                    <div class="action-icon">📝</div>
                    <h3>New Referral</h3>
                    <p>Submit a new healthcare referral</p>
                </a>
                <?php if ($verification_status === 'verified'): ?>
                    <a href="#" class="action-card">
                        <div class="action-icon">🔄</div>
                        <h3>Update Status</h3>
                        <p>Update referral statuses</p>
                    </a>
                <?php endif; ?>
                <a href="../profile.php" class="action-card">
                    <div class="action-icon">👤</div>
                    <h3>My Profile</h3>
                    <p>Manage your professional profile</p>
                </a>
                <?php if ($verification_status === 'not_submitted' || $verification_status === 'rejected'): ?>
                    <a href="../provider-registration.php" class="action-card" style="border-color: var(--warning);">
                        <div class="action-icon">📋</div>
                        <h3>Complete Profile</h3>
                        <p>Submit your credentials for verification</p>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<!-- Footer -->
<footer class="site-footer" role="contentinfo">
    <div class="footer-grid container">
        <div>
            <a href="../index.html" class="logo" aria-label="Care Connect SL Home">Care<span class="accent">Connect</span> SL</a>
            <p>Home-based care referrals and clinic coordination across Sierra Leone.</p>
        </div>
        <div>
            <h3>Quick links</h3>
            <ul class="footer-links">
                <li><a href="../index.html">Home</a></li>
                <li><a href="../pages/referral.html">New Referral</a></li>
                <li><a href="../privacy.html">Privacy Policy</a></li>
            </ul>
        </div>
        <div>
            <h3>Contact</h3>
            <p><a href="mailto:hello@careconnect.sl">hello@careconnect.sl</a></p>
            <p><a href="tel:+23276000000">+232 76 000 000</a></p>
        </div>
    </div>
    <p class="footer-note">&copy; 2026 Care Connect SL. All rights reserved.</p>
</footer>

<script src="../js/main.js"></script>
</body>
</html>