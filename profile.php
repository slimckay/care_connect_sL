<?php
// Start session to get user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];
$user_role = $_SESSION['role'] ?? 'patient';

// Handle profile update (POST)
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // CSRF Protection
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        // Get and sanitize inputs
        $name = sanitizeInput($_POST['name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');

        // Validate
        $errors = [];
        if (empty($name)) {
            $errors[] = 'Full name is required.';
        } elseif (strlen($name) < 2) {
            $errors[] = 'Name must be at least 2 characters.';
        }

        if (!empty($phone) && !preg_match("/^[0-9+\-\s()]{8,20}$/", $phone)) {
            $errors[] = 'Invalid phone number format.';
        }

        if (empty($errors)) {
            try {
                // Update user profile
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET name = ?, phone = ?, location = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $phone, $location, $user_id]);

                // Update session name
                $_SESSION['user_name'] = $name;

                // Handle profile picture upload
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $file_type = $_FILES['profile_picture']['type'];
                    if (in_array($file_type, $allowed)) {
                        $upload_dir = __DIR__ . '/uploads/profile/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                        $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                        $dest = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $dest)) {
                            // Update database with new picture path
                            $pic_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                            $pic_stmt->execute(['uploads/profile/' . $filename, $user_id]);
                        }
                    } else {
                        $error = 'Only JPEG, PNG, GIF, and WEBP images are allowed.';
                    }
                }

                if (empty($error)) {
                    $message = '✅ Profile updated successfully!';
                    // Refresh user data for display
                }

            } catch (PDOException $e) {
                error_log("Profile update error: " . $e->getMessage());
                $error = 'A server error occurred. Please try again.';
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $user_name = $user['name'];
    $user_email = $user['email'];
    $user_phone = $user['phone'] ?? '';
    $user_location = $user['location'] ?? '';
    $user_picture = $user['profile_picture'] ?? '';
    $user_role = $user['role'] ?? 'patient';
    $user_created = $user['created_at'] ?? date('Y-m-d H:i:s');

    // Get stats
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM referrals WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalReferrals = $stmt->fetch()['total'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as completed FROM referrals WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completedReferrals = $stmt->fetch()['completed'] ?? 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as pending FROM referrals WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pendingReferrals = $stmt->fetch()['pending'] ?? 0;

    $stmt = $conn->prepare("
        SELECT patient_name, medical_condition, status, created_at
        FROM referrals
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $recentReferrals = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    // Fallback
    $user_name = $_SESSION['user_name'];
    $user_phone = '';
    $user_location = '';
    $user_picture = '';
}

$initials = strtoupper(substr($user_name, 0, 1));
$avatar_url = !empty($user_picture) ? $user_picture : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard/dashboard.css">
    <style>
        .profile-container { max-width: 1100px; margin: 0 auto; }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 28px;
            padding: 32px 36px;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .profile-avatar-container {
            position: relative;
            flex-shrink: 0;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            overflow: hidden;
            border: 4px solid #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-avatar .placeholder { font-size: 2.5rem; font-weight: 700; color: #fff; }
        .avatar-upload-btn {
            position: absolute;
            bottom: 4px;
            right: 4px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            border: 2px solid #fff;
        }
        .avatar-upload-btn:hover { background: var(--primary-dark); transform: scale(1.05); }
        .profile-info { flex: 1; }
        .profile-info h1 { font-size: 1.8rem; margin: 0 0 4px 0; }
        .profile-email { color: var(--muted); margin: 0 0 4px 0; font-size: 0.95rem; }
        .profile-role { margin: 0 0 4px 0; }
        .profile-since { font-size: 0.85rem; color: var(--gray-light); }
        .profile-actions-top { display: flex; gap: 12px; flex-shrink: 0; flex-wrap: wrap; }

        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .profile-card {
            background: var(--white);
            padding: 24px 28px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.3s;
        }
        .profile-card:hover { box-shadow: var(--shadow-md); }
        .profile-card h2 { font-size: 1.1rem; margin: 0 0 16px 0; color: var(--dark); display: flex; align-items: center; gap: 8px; }

        .profile-detail { padding: 10px 0; border-bottom: 1px solid var(--border); }
        .profile-detail:last-child { border-bottom: none; }
        .profile-detail label { font-size: 0.75rem; color: var(--gray-light); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 2px; }
        .profile-detail p { font-size: 1rem; color: var(--dark); margin: 0; }

        .edit-link { display: inline-block; margin-top: 12px; color: var(--primary); font-weight: 500; cursor: pointer; }

        .activity-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            text-align: center;
        }
        .activity-stat .stat-number { font-size: 1.8rem; font-weight: 700; color: var(--primary); display: block; }
        .activity-stat .stat-label { font-size: 0.8rem; color: var(--muted); }

        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge.patient { background: rgba(59, 130, 246, 0.12); color: #1D4ED8; }
        .badge.doctor { background: rgba(0, 200, 150, 0.12); color: var(--primary-dark); }
        .badge.hospital { background: rgba(139, 92, 246, 0.12); color: #6D28D9; }
        .badge.admin { background: rgba(239, 68, 68, 0.12); color: #B91C1C; }
        .badge.completed { background: rgba(0, 200, 150, 0.12); color: var(--primary-dark); }
        .badge.pending { background: rgba(245, 158, 11, 0.12); color: #B45309; }
        .badge.in_progress { background: rgba(59, 130, 246, 0.12); color: #1D4ED8; }
        .badge.cancelled { background: rgba(239, 68, 68, 0.12); color: #B91C1C; }

        .btn-small {
            display: inline-block;
            padding: 6px 18px;
            background: var(--primary);
            color: var(--white);
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-small:hover { opacity: 0.85; transform: translateY(-2px); }
        .btn-small.gray { background: var(--gray); }
        .btn-small.outline { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
        .btn-small.outline:hover { background: var(--primary); color: #fff; }

        .edit-form { display: none; }
        .edit-form.active { display: block; }
        .view-mode { display: block; }
        .view-mode.hidden { display: none; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 500; font-size: 0.9rem; color: var(--dark); margin-bottom: 4px; }
        .form-group input { width: 100%; padding: 10px 14px; border: 2px solid #E2E8F0; border-radius: var(--radius-md); font-size: 1rem; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(0,200,150,0.1); }
        .form-actions { display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap; }

        .message {
            padding: 12px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            font-weight: 500;
        }
        .message.success { background: rgba(0,200,150,0.08); color: #04664D; border: 1px solid rgba(0,200,150,0.15); }
        .message.error { background: rgba(239,68,68,0.08); color: #991B1B; border: 1px solid rgba(239,68,68,0.15); }

        @media (max-width: 768px) {
            .profile-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; padding: 24px; }
            .profile-actions-top { width: 100%; justify-content: center; }
            .activity-stats { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 480px) {
            .profile-avatar { width: 80px; height: 80px; font-size: 2rem; }
            .profile-card { padding: 16px 18px; }
            .activity-stats { grid-template-columns: 1fr; }
            .profile-actions-top { flex-direction: column; width: 100%; }
            .profile-actions-top .btn-primary, .profile-actions-top .btn-ghost { width: 100%; justify-content: center; }
        }
        #profile_picture_input { display: none; }
    </style>
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
        <a href="index.html" class="logo" aria-label="Care Connect SL Home">
            <span class="logo-icon" aria-hidden="true">❤️</span> Care<span class="accent">Connect</span> SL
        </a>
        <nav aria-label="Main navigation">
            <ul class="nav-links" role="menubar">
                <li><a href="index.html" role="menuitem">Home</a></li>
                <?php if ($user_role === 'patient'): ?>
                    <li><a href="dashboard/patient-dashboard.php" role="menuitem">Dashboard</a></li>
                <?php elseif ($user_role === 'doctor' || $user_role === 'hospital'): ?>
                    <li><a href="dashboard/provider-dashboard.php" role="menuitem">Dashboard</a></li>
                <?php elseif ($user_role === 'admin'): ?>
                    <li><a href="admin/admin-dashboard.php" role="menuitem">Dashboard</a></li>
                <?php endif; ?>
                <li><a href="pages/doctors.php" role="menuitem">Find Care</a></li>
                <li><a href="pages/hospitals.html" role="menuitem">Clinics</a></li>
                <li><a href="pages/referral.html" role="menuitem">New Referral</a></li>
            </ul>
        </nav>
        <div class="nav-actions">
            <a href="logout.php" class="btn-ghost">Logout</a>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="dashboard-main" role="main">
    <div class="dashboard-container">
        <div class="profile-container">

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="message success" id="successMsg"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Profile Header -->
            <section class="profile-header" id="profileHeader">
                <div class="profile-avatar-container">
                    <div class="profile-avatar" id="avatarDisplay">
                        <?php if (!empty($avatar_url) && file_exists($avatar_url)): ?>
                            <img src="<?php echo $avatar_url; ?>" alt="Profile picture" id="avatarImg">
                        <?php else: ?>
                            <span class="placeholder"><?php echo $initials; ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="avatar-upload-btn" id="avatarUploadBtn" aria-label="Upload profile photo">📷</button>
                    <form id="avatarUploadForm" style="display:none;">
                        <input type="file" id="profile_picture_input" accept="image/*">
                    </form>
                </div>

                <div class="profile-info">
                    <h1 id="displayName"><?php echo htmlspecialchars($user_name); ?></h1>
                    <p class="profile-email"><?php echo htmlspecialchars($user_email); ?></p>
                    <p class="profile-role">
                        <span class="badge <?php echo strtolower($user_role); ?>"><?php echo ucfirst($user_role); ?></span>
                    </p>
                    <p class="profile-since">Member since <?php echo date('F Y', strtotime($user_created)); ?></p>
                </div>

                <div class="profile-actions-top">
                    <button class="btn-primary" id="editProfileBtn">✏️ Edit Profile</button>
                    <a href="change-password.php" class="btn-ghost">🔐 Change Password</a>
                </div>
            </section>

            <!-- Profile Details Grid -->
            <div class="profile-grid">

                <!-- Personal Information Card -->
                <section class="profile-card" id="infoCard">
                    <h2>👤 Personal Information</h2>
                    <!-- View Mode -->
                    <div class="view-mode" id="infoView">
                        <div class="profile-detail">
                            <label>Full Name</label>
                            <p id="viewName"><?php echo htmlspecialchars($user_name); ?></p>
                        </div>
                        <div class="profile-detail">
                            <label>Email Address</label>
                            <p><?php echo htmlspecialchars($user_email); ?></p>
                        </div>
                        <div class="profile-detail">
                            <label>Phone Number</label>
                            <p id="viewPhone"><?php echo htmlspecialchars($user_phone ?: 'Not set'); ?></p>
                        </div>
                        <div class="profile-detail">
                            <label>Location</label>
                            <p id="viewLocation"><?php echo htmlspecialchars($user_location ?: 'Not set'); ?></p>
                        </div>
                        <div class="profile-detail">
                            <label>Role</label>
                            <p><span class="badge <?php echo strtolower($user_role); ?>"><?php echo ucfirst($user_role); ?></span></p>
                        </div>
                        <button class="edit-link" id="editInfoBtn">✏️ Edit Information</button>
                    </div>

                    <!-- Edit Mode -->
                    <form class="edit-form" id="infoEditForm" method="POST" action="profile.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-group">
                            <label for="editName">Full Name</label>
                            <input type="text" id="editName" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="editPhone">Phone Number</label>
                            <input type="text" id="editPhone" name="phone" value="<?php echo htmlspecialchars($user_phone); ?>" placeholder="+232 76 123 456">
                        </div>
                        <div class="form-group">
                            <label for="editLocation">Location</label>
                            <input type="text" id="editLocation" name="location" value="<?php echo htmlspecialchars($user_location); ?>" placeholder="Freetown, Sierra Leone">
                        </div>
                        <div class="form-group">
                            <label for="editPicture">Profile Picture</label>
                            <input type="file" id="editPicture" name="profile_picture" accept="image/*">
                            <small style="color: var(--muted);">JPEG, PNG, GIF, WEBP</small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-primary" id="saveProfileBtn">💾 Save Changes</button>
                            <button type="button" class="btn-ghost" id="cancelEditBtn">Cancel</button>
                        </div>
                    </form>
                </section>

                <!-- Account Settings -->
                <section class="profile-card">
                    <h2>🔐 Account Settings</h2>
                    <div class="profile-detail">
                        <label>Member Since</label>
                        <p><?php echo date('F Y', strtotime($user_created)); ?></p>
                    </div>
                    <div class="profile-detail">
                        <label>Account Status</label>
                        <p><span class="badge completed">Active</span></p>
                    </div>
                    <div class="profile-detail">
                        <label>Email Verified</label>
                        <p><span class="badge completed">✅ Verified</span></p>
                    </div>
                    <div class="profile-detail">
                        <label>Two-Factor Authentication</label>
                        <p><span class="badge pending">Not Enabled</span></p>
                    </div>
                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); display: flex; gap: 12px; flex-wrap: wrap;">
                        <a href="change-password.php" class="btn-small">Change Password</a>
                        <a href="#" class="btn-small gray">Settings</a>
                    </div>
                </section>

                <!-- Activity Summary -->
                <section class="profile-card">
                    <h2>📊 Activity Summary</h2>
                    <div class="activity-stats">
                        <div class="activity-stat">
                            <span class="stat-number"><?php echo $totalReferrals; ?></span>
                            <span class="stat-label">Total Referrals</span>
                        </div>
                        <div class="activity-stat">
                            <span class="stat-number"><?php echo $completedReferrals; ?></span>
                            <span class="stat-label">Completed</span>
                        </div>
                        <div class="activity-stat">
                            <span class="stat-number"><?php echo $pendingReferrals; ?></span>
                            <span class="stat-label">Pending</span>
                        </div>
                    </div>
                    <div class="profile-detail" style="margin-top: 16px;">
                        <label>Last Activity</label>
                        <p>Today at <?php echo date('h:i A'); ?></p>
                    </div>
                </section>

                <!-- Recent Referrals -->
                <section class="profile-card">
                    <h2>📋 Recent Referrals</h2>
                    <?php if (!empty($recentReferrals)): ?>
                        <div class="referral-list">
                            <?php foreach ($recentReferrals as $referral): ?>
                                <div class="referral-item" style="padding: 10px 0; border-bottom: 1px solid var(--border);">
                                    <div class="referral-info">
                                        <h4 style="font-size: 0.95rem; margin: 0;"><?php echo htmlspecialchars($referral['patient_name']); ?></h4>
                                        <p style="font-size: 0.85rem; color: var(--muted); margin: 4px 0 0 0;"><?php echo substr($referral['medical_condition'], 0, 50); ?>...</p>
                                    </div>
                                    <span class="badge <?php echo $referral['status']; ?>" style="margin-top: 4px;">
                                        <?php echo ucfirst(str_replace('_', ' ', $referral['status'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="referrals-history.php" class="view-all" style="display: block; text-align: center; margin-top: 12px;">View All Referrals →</a>
                    <?php else: ?>
                        <div class="empty-state">
                            <p style="color: var(--muted);">No referrals yet.</p>
                            <a href="pages/referral.html" class="btn-primary" style="margin-top: 12px; display: inline-block;">Submit Your First Referral</a>
                        </div>
                    <?php endif; ?>
                </section>

            </div>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="site-footer" role="contentinfo">
    <div class="footer-grid container">
        <div>
            <a href="index.html" class="logo" aria-label="Care Connect SL Home">Care<span class="accent">Connect</span> SL</a>
            <p>Home-based care referrals and clinic coordination across Sierra Leone.</p>
        </div>
        <div>
            <h3>Quick links</h3>
            <ul class="footer-links">
                <li><a href="index.html">Home</a></li>
                <li><a href="dashboard/patient-dashboard.php">Dashboard</a></li>
                <li><a href="privacy.html">Privacy Policy</a></li>
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

<script src="js/main.js"></script>

<script>
// ============================================
// PROFILE EDIT TOGGLE
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const editBtn = document.getElementById('editProfileBtn');
    const editForm = document.getElementById('infoEditForm');
    const viewMode = document.getElementById('infoView');
    const cancelBtn = document.getElementById('cancelEditBtn');
    const editInfoBtn = document.getElementById('editInfoBtn');

    function showEdit() {
        viewMode.classList.add('hidden');
        editForm.classList.add('active');
        editBtn.textContent = '✏️ Editing...';
        editBtn.style.opacity = '0.6';
        // Scroll to form
        editForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function showView() {
        viewMode.classList.remove('hidden');
        editForm.classList.remove('active');
        editBtn.textContent = '✏️ Edit Profile';
        editBtn.style.opacity = '1';
    }

    editBtn.addEventListener('click', function() {
        // Toggle: if form is already active, maybe do nothing? But we want to open it.
        // If already active, just keep it active.
        if (!editForm.classList.contains('active')) {
            showEdit();
        }
    });

    editInfoBtn.addEventListener('click', function() {
        showEdit();
    });

    cancelBtn.addEventListener('click', function() {
        // Reload page to reset any changes
        location.reload();
    });

    // ============================================
    // PROFILE PICTURE UPLOAD
    // ============================================
    const uploadBtn = document.getElementById('avatarUploadBtn');
    const fileInput = document.getElementById('profile_picture_input');

    uploadBtn.addEventListener('click', function() {
        fileInput.click();
    });

    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const avatarImg = document.getElementById('avatarImg');
                const placeholder = document.querySelector('.profile-avatar .placeholder');
                if (avatarImg) {
                    avatarImg.src = e.target.result;
                } else {
                    const avatarDiv = document.getElementById('avatarDisplay');
                    avatarDiv.innerHTML = `<img src="${e.target.result}" alt="Profile picture" id="avatarImg">`;
                }
                // Also set the file input in the edit form
                const editPic = document.getElementById('editPicture');
                if (editPic) {
                    // We can't set the file input value directly for security, but we can show a notification
                    document.querySelector('.form-group label[for="editPicture"]').innerHTML = 'Profile Picture <span style="color: var(--primary);">(New file selected)</span>';
                }
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // ============================================
    // LIVE PREVIEW
    // ============================================
    const editName = document.getElementById('editName');
    const displayName = document.getElementById('displayName');
    const viewName = document.getElementById('viewName');

    if (editName) {
        editName.addEventListener('input', function() {
            const val = this.value.trim() || 'User';
            displayName.textContent = val;
            viewName.textContent = val;
        });
    }

    const editPhone = document.getElementById('editPhone');
    const editLocation = document.getElementById('editLocation');
    const viewPhone = document.getElementById('viewPhone');
    const viewLocation = document.getElementById('viewLocation');

    if (editPhone) {
        editPhone.addEventListener('input', function() {
            viewPhone.textContent = this.value.trim() || 'Not set';
        });
    }
    if (editLocation) {
        editLocation.addEventListener('input', function() {
            viewLocation.textContent = this.value.trim() || 'Not set';
        });
    }

    // ============================================
    // AUTO-HIDE SUCCESS MESSAGE AFTER 5 SECONDS
    // ============================================
    const successMsg = document.getElementById('successMsg');
    if (successMsg) {
        setTimeout(function() {
            successMsg.style.transition = 'opacity 0.5s';
            successMsg.style.opacity = '0';
            setTimeout(function() { successMsg.style.display = 'none'; }, 500);
        }, 5000);
    }
});
</script>

</body>
</html>