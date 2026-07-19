<?php
/**
 * Patient Dashboard - Care Connect SL
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'patient') {
    $role = $_SESSION['role'];
    switch($role) {
        case 'admin':
            header('Location: ../admin/admin-dashboard.php');
            break;
        case 'doctor':
        case 'hospital':
            header('Location: provider-dashboard.php');
            break;
        default:
            header('Location: ../index.html');
    }
    exit;
}

require_once '../db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$totalReferrals = 0;
$pendingReferrals = 0;
$completedReferrals = 0;
$inProgressReferrals = 0;
$recentReferrals = [];
$upcomingAppts = 0;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM referrals WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalReferrals = $stmt->fetch()['total'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as pending FROM referrals WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pendingReferrals = $stmt->fetch()['pending'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as completed FROM referrals WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completedReferrals = $stmt->fetch()['completed'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as in_progress FROM referrals WHERE user_id = ? AND status = 'in_progress'");
    $stmt->execute([$user_id]);
    $inProgressReferrals = $stmt->fetch()['in_progress'] ?? 0;

    try {
        $stmt = $conn->prepare("
            SELECT id, patient_name, `condition`, status, created_at
            FROM referrals WHERE user_id = ? ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recentReferrals = $stmt->fetchAll();
    } catch (Exception $e) {
        $stmt = $conn->prepare("
            SELECT id, patient_name, medical_condition, status, created_at
            FROM referrals WHERE user_id = ? ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recentReferrals = $stmt->fetchAll();
    }

    try {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM appointments WHERE patient_id = ? AND status IN ('pending','confirmed') AND appointment_date >= CURDATE()");
        $stmt->execute([$user_id]);
        $upcomingAppts = (int)($stmt->fetch()['c'] ?? 0);
    } catch (Exception $e) {
        $upcomingAppts = 0;
    }
} catch (PDOException $e) {
    error_log("Patient dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>

<div id="preloader" role="status" aria-label="Loading">
    <div class="pulse-ring"></div>
    <svg class="heartbeat-svg" viewBox="0 0 300 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <polyline points="0,40 60,40 80,10 100,70 120,5 140,75 160,40 300,40" fill="none" stroke="#00C896" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <p class="preload-text">Care Connect SL</p>
</div>

<header role="banner">
    <div class="nav-inner">
        <a href="../index.html" class="logo" aria-label="Care Connect SL Home">
            Care<span class="accent">Connect</span> SL
        </a>
        <nav aria-label="Main navigation">
            <ul class="nav-links" role="menubar">
                <li><a href="../index.html" role="menuitem">Home</a></li>
                <li><a href="../pages/doctors.php" role="menuitem">Find Care</a></li>
                <li><a href="../pages/appointment.php" role="menuitem">Book Visit</a></li>
                <li><a href="../pages/referral.html" role="menuitem">New Referral</a></li>
                <li><a href="messages.php" role="menuitem">Messages</a></li>
            </ul>
        </nav>
        <div class="nav-actions">
            <span style="color: var(--muted); font-size: 0.9rem;">👋 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../logout.php" class="btn-ghost btn-logout">Log out</a>
        </div>
    </div>
</header>

<main class="dashboard-main" role="main">
    <div class="dashboard-container">
        <section class="dashboard-welcome">
            <div class="welcome-content">
                <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
                <p>Here's an overview of your healthcare journey on Care Connect SL.</p>
            </div>
            <div class="welcome-actions">
                <a href="../pages/appointment.php" class="btn-primary">📅 Book Visit</a>
                <a href="../pages/referral.html" class="btn-ghost">➕ Referral</a>
                <a href="messages.php" class="btn-ghost">💬 Messages</a>
            </div>
        </section>

        <section class="stats-grid">
            <div class="stat-card stat-total">
                <div class="stat-icon">📋</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $totalReferrals; ?></span>
                    <span class="stat-label">Total Referrals</span>
                </div>
            </div>
            <div class="stat-card stat-pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $pendingReferrals; ?></span>
                    <span class="stat-label">Pending</span>
                </div>
            </div>
            <div class="stat-card stat-progress">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $upcomingAppts; ?></span>
                    <span class="stat-label">Upcoming Visits</span>
                </div>
            </div>
            <div class="stat-card stat-completed">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $completedReferrals; ?></span>
                    <span class="stat-label">Completed</span>
                </div>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="card-header">
                <h2>📋 Recent Referrals</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($recentReferrals)): ?>
                    <div class="referral-list">
                        <?php foreach ($recentReferrals as $referral): ?>
                            <?php $cond = $referral['condition'] ?? ($referral['medical_condition'] ?? 'Not provided'); ?>
                            <div class="referral-item">
                                <div class="referral-info">
                                    <h4><?php echo htmlspecialchars($referral['patient_name']); ?></h4>
                                    <p class="referral-condition"><?php echo htmlspecialchars(mb_substr($cond, 0, 60)); ?><?php echo mb_strlen($cond) > 60 ? '...' : ''; ?></p>
                                    <span class="referral-date"><?php echo date('M d, Y', strtotime($referral['created_at'])); ?></span>
                                </div>
                                <div class="referral-status">
                                    <span class="badge <?php echo htmlspecialchars($referral['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $referral['status'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>You haven't submitted any referrals yet.</p>
                        <a href="../pages/referral.html" class="btn-primary">Submit Your First Referral</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="quick-actions">
            <h2>⚡ Quick Actions</h2>
            <div class="action-grid">
                <a href="../pages/appointment.php" class="action-card">
                    <div class="action-icon">📅</div>
                    <h3>Book Visit</h3>
                    <p>Home visit or clinic appointment</p>
                </a>
                <a href="my-appointments.php" class="action-card">
                    <div class="action-icon">🗓️</div>
                    <h3>My Appointments</h3>
                    <p>Track upcoming and past visits</p>
                </a>
                <a href="../pages/referral.html" class="action-card">
                    <div class="action-icon">📝</div>
                    <h3>New Referral</h3>
                    <p>Submit a healthcare referral</p>
                </a>
                <a href="messages.php" class="action-card">
                    <div class="action-icon">💬</div>
                    <h3>Messages</h3>
                    <p>Chat with your doctor or clinic</p>
                </a>
            </div>
        </section>
    </div>
</main>

<footer class="site-footer" role="contentinfo">
    <div class="footer-grid container">
        <div>
            <a href="../index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
            <p>Home-based care referrals and clinic coordination across Sierra Leone.</p>
        </div>
        <div>
            <h3>Quick links</h3>
            <ul class="footer-links">
                <li><a href="../pages/appointment.php">Book Visit</a></li>
                <li><a href="../pages/referral.html">New Referral</a></li>
                <li><a href="messages.php">Messages</a></li>
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
<script src="../js/mobile-logout.js"></script>
</body>
</html>
