<?php
/**
 * Change Password - Care Connect SL
 * Allows logged-in users to change their password
 */

// Start session
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
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['role'] ?? 'patient';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Protection
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        
        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!checkRateLimit('password_change_' . $ip, 5, 3600)) {
            $error = 'Too many attempts. Please try again later.';
        } else {
            
            // Get inputs
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validation
            $errors = [];
            
            // Check current password
            if (empty($current_password)) {
                $errors[] = 'Please enter your current password.';
            }
            
            // New password validation
            if (empty($new_password)) {
                $errors[] = 'Please enter a new password.';
            } elseif (strlen($new_password) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif (strlen($new_password) > 72) {
                $errors[] = 'New password cannot exceed 72 characters.';
            } elseif (!preg_match("/[A-Z]/", $new_password)) {
                $errors[] = 'New password must contain at least one uppercase letter.';
            } elseif (!preg_match("/[a-z]/", $new_password)) {
                $errors[] = 'New password must contain at least one lowercase letter.';
            } elseif (!preg_match("/[0-9]/", $new_password)) {
                $errors[] = 'New password must contain at least one number.';
            }
            
            // Confirm password
            if (empty($confirm_password)) {
                $errors[] = 'Please confirm your new password.';
            } elseif ($new_password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            }
            
            // If no validation errors, proceed
            if (empty($errors)) {
                try {
                    // Get current user's password hash
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        $errors[] = 'User not found.';
                    } else {
                        // ✅ FIX: Verify current password
                        if (password_verify($current_password, $user['password'])) {
                            // Hash new password
                            $new_hash = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);
                            
                            // Update password
                            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $updateStmt->execute([$new_hash, $user_id]);
                            
                            // Log password change
                            error_log("Password changed for user ID: " . $user_id);
                            
                            // ✅ FIX: Set success in session and redirect
                            $_SESSION['password_change_success'] = 'Password changed successfully!';
                            header('Location: profile.php?password_changed=1');
                            exit;
                        } else {
                            // ✅ Better error message
                            $errors[] = 'Current password is incorrect. Please try again.';
                            // Log failed attempt for security
                            error_log("Failed password change attempt for user ID: " . $user_id . " from IP: " . $ip);
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Password change error: " . $e->getMessage());
                    $errors[] = 'A server error occurred. Please try again.';
                }
            }
            
            // If there are errors, store them
            if (!empty($errors)) {
                $error = implode('<br>', $errors);
            }
        }
    }
}

// Check if there's a success message from session
$success_message = $_SESSION['password_change_success'] ?? null;
if ($success_message) {
    unset($_SESSION['password_change_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard/dashboard.css">
    <style>
        .password-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .password-container h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            color: var(--dark);
        }
        .password-container .subtitle {
            color: var(--muted);
            margin-bottom: 24px;
        }
        .password-strength {
            height: 4px;
            border-radius: 4px;
            margin-top: 8px;
            background: #E2E8F0;
            transition: all 0.3s ease;
        }
        .password-strength .bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease, background 0.3s ease;
            width: 0%;
        }
        .password-strength .bar.weak { width: 25%; background: #EF4444; }
        .password-strength .bar.medium { width: 50%; background: #F59E0B; }
        .password-strength .bar.strong { width: 75%; background: #3B82F6; }
        .password-strength .bar.very-strong { width: 100%; background: #00C896; }
        .strength-text {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 4px;
        }
        .strength-text .weak { color: #EF4444; }
        .strength-text .medium { color: #F59E0B; }
        .strength-text .strong { color: #3B82F6; }
        .strength-text .very-strong { color: #00C896; }
        .password-requirements {
            font-size: 0.85rem;
            color: var(--muted);
            margin-top: 8px;
            padding-left: 0;
            list-style: none;
        }
        .password-requirements li {
            margin-bottom: 4px;
        }
        .password-requirements li .check {
            margin-right: 8px;
        }
        .password-requirements li.valid .check {
            color: #00C896;
        }
        .password-requirements li.invalid .check {
            color: #EF4444;
        }
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }
        .form-card {
            padding: 36px 40px;
        }
        /* Password toggle button */
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 4px;
            color: var(--muted);
        }
        .password-toggle:hover {
            color: var(--dark);
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper input {
            padding-right: 44px;
        }
        @media (max-width: 480px) {
            .form-actions {
                flex-direction: column;
            }
            .form-actions .btn-primary,
            .form-actions .btn-ghost {
                width: 100%;
                justify-content: center;
            }
            .form-card {
                padding: 24px 20px;
            }
        }
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
                <?php else: ?>
                    <li><a href="dashboard/patient-dashboard.php" role="menuitem">Dashboard</a></li>
                <?php endif; ?>
                <li><a href="pages/doctors.php" role="menuitem">Find Care</a></li>
                <li><a href="pages/hospitals.html" role="menuitem">Clinics</a></li>
                <li><a href="pages/referral.html" role="menuitem">New Referral</a></li>
            </ul>
        </nav>
        <div class="nav-actions">
            <span style="color: var(--muted); font-size: 0.9rem;">👋 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="logout.php" class="btn-ghost">Logout</a>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="dashboard-main" role="main">
    <div class="dashboard-container">
        <div class="password-container">
            
            <h1>🔐 Change Password</h1>
            <p class="subtitle">Update your password to keep your account secure.</p>

            <?php if (isset($success_message)): ?>
                <div class="form-message success">✅ <?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="form-message error">❌ <?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-card">
                <form action="change-password.php" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Current Password -->
                    <div style="margin-bottom: 20px;">
                        <label for="currentPassword">Current Password</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   id="currentPassword" 
                                   name="current_password" 
                                   placeholder="Enter your current password" 
                                   required 
                                   minlength="8"
                                   autocomplete="current-password"
                                   aria-required="true">
                            <button type="button" 
                                    class="password-toggle" 
                                    aria-label="Show password">👁️</button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div style="margin-bottom: 20px;">
                        <label for="newPassword">New Password</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   id="newPassword" 
                                   name="new_password" 
                                   placeholder="Minimum 8 characters" 
                                   required 
                                   minlength="8"
                                   autocomplete="new-password"
                                   aria-required="true">
                            <button type="button" 
                                    class="password-toggle" 
                                    aria-label="Show password">👁️</button>
                        </div>
                        
                        <!-- Password Strength Meter -->
                        <div class="password-strength" id="strengthMeter">
                            <div class="bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText">Enter a strong password</div>
                        
                        <!-- Password Requirements -->
                        <ul class="password-requirements" id="requirements">
                            <li id="req-length" class="invalid"><span class="check">✗</span> At least 8 characters</li>
                            <li id="req-upper" class="invalid"><span class="check">✗</span> At least 1 uppercase letter</li>
                            <li id="req-lower" class="invalid"><span class="check">✗</span> At least 1 lowercase letter</li>
                            <li id="req-number" class="invalid"><span class="check">✗</span> At least 1 number</li>
                        </ul>
                    </div>

                    <!-- Confirm Password -->
                    <div style="margin-bottom: 24px;">
                        <label for="confirmPassword">Confirm New Password</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   id="confirmPassword" 
                                   name="confirm_password" 
                                   placeholder="Re-enter your new password" 
                                   required 
                                   minlength="8"
                                   autocomplete="new-password"
                                   aria-required="true">
                            <button type="button" 
                                    class="password-toggle" 
                                    aria-label="Show password">👁️</button>
                        </div>
                        <div id="matchMessage" style="font-size: 0.85rem; margin-top: 4px;"></div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary" style="flex: 1;">Update Password</button>
                        <a href="profile.php" class="btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Password Tips -->
            <div style="margin-top: 24px; padding: 20px 24px; background: #F8FAFC; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                <h3 style="font-size: 1rem; margin-bottom: 8px;">💡 Password Tips</h3>
                <ul style="color: var(--muted); font-size: 0.9rem; padding-left: 20px;">
                    <li>Use a mix of uppercase, lowercase, numbers, and symbols</li>
                    <li>Make it at least 8 characters long</li>
                    <li>Don't use common words or personal information</li>
                    <li>Use a unique password for each account</li>
                </ul>
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

<!-- Password Strength & Validation Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Password toggle
    document.querySelectorAll('.password-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            if (input) {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                this.textContent = isPassword ? '🙈' : '👁️';
                this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            }
        });
    });

    // Password strength meter
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const matchMessage = document.getElementById('matchMessage');

    // Requirements
    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqLower = document.getElementById('req-lower');
    const reqNumber = document.getElementById('req-number');

    function checkPasswordStrength(password) {
        let score = 0;
        
        // Length check
        if (password.length >= 8) {
            score++;
            reqLength.className = 'valid';
            reqLength.querySelector('.check').textContent = '✓';
        } else {
            reqLength.className = 'invalid';
            reqLength.querySelector('.check').textContent = '✗';
        }
        
        // Uppercase check
        if (/[A-Z]/.test(password)) {
            score++;
            reqUpper.className = 'valid';
            reqUpper.querySelector('.check').textContent = '✓';
        } else {
            reqUpper.className = 'invalid';
            reqUpper.querySelector('.check').textContent = '✗';
        }
        
        // Lowercase check
        if (/[a-z]/.test(password)) {
            score++;
            reqLower.className = 'valid';
            reqLower.querySelector('.check').textContent = '✓';
        } else {
            reqLower.className = 'invalid';
            reqLower.querySelector('.check').textContent = '✗';
        }
        
        // Number check
        if (/[0-9]/.test(password)) {
            score++;
            reqNumber.className = 'valid';
            reqNumber.querySelector('.check').textContent = '✓';
        } else {
            reqNumber.className = 'invalid';
            reqNumber.querySelector('.check').textContent = '✗';
        }
        
        // Update strength bar
        const strengthLevels = ['', 'weak', 'medium', 'strong', 'very-strong'];
        const strengthLabels = ['', 'Weak', 'Medium', 'Strong', 'Very Strong'];
        
        let level = 0;
        if (password.length === 0) {
            level = 0;
            strengthText.textContent = 'Enter a strong password';
        } else if (score <= 1) {
            level = 1;
        } else if (score === 2) {
            level = 2;
        } else if (score === 3) {
            level = 3;
        } else {
            level = 4;
        }
        
        strengthBar.className = 'bar ' + strengthLevels[level];
        strengthBar.style.width = (level * 25) + '%';
        if (level > 0) {
            strengthText.innerHTML = '<span class="' + strengthLevels[level] + '">' + strengthLabels[level] + '</span>';
        } else {
            strengthText.textContent = 'Enter a strong password';
        }
    }

    function checkPasswordMatch() {
        const pass = newPassword.value;
        const confirm = confirmPassword.value;
        
        if (confirm.length === 0) {
            matchMessage.textContent = '';
            matchMessage.style.color = '';
        } else if (pass === confirm) {
            matchMessage.textContent = '✓ Passwords match';
            matchMessage.style.color = '#00C896';
        } else {
            matchMessage.textContent = '✗ Passwords do not match';
            matchMessage.style.color = '#EF4444';
        }
    }

    newPassword.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        checkPasswordMatch();
    });

    confirmPassword.addEventListener('input', function() {
        checkPasswordMatch();
    });

    // Auto-focus on current password field
    document.getElementById('currentPassword').focus();
});
</script>

</body>
</html>