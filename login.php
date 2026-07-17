<?php
/**
 * Login - Care Connect SL
 * All-in-one login form with processing
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
require_once __DIR__ . '/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'patient';
    switch($role) {
        case 'admin':
            header('Location: admin/admin-dashboard.php');
            break;
        case 'doctor':
        case 'hospital':
            header('Location: dashboard/provider-dashboard.php');
            break;
        default:
            header('Location: dashboard/patient-dashboard.php');
    }
    exit;
}

// Handle login form submission (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit('login_' . $ip, 5, 300)) {
        header('Location: login.php?error=rate_limit');
        exit;
    }

    // CSRF Protection
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        header('Location: login.php?error=csrf');
        exit;
    }

    // Get and sanitize inputs
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate inputs
    if (empty($email) || empty($password)) {
        header('Location: login.php?error=missing');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: login.php?error=invalid_email');
        exit;
    }

    try {
        // Get user
        $stmt = $conn->prepare("
            SELECT id, name, email, password, role, status 
            FROM users 
            WHERE email = ? 
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Check if user exists
        if (!$user) {
            header('Location: login.php?error=invalid_credentials');
            exit;
        }

        // Check account status
        if ($user['status'] === 'inactive') {
            header('Location: login.php?error=account_inactive');
            exit;
        }

        if ($user['status'] === 'banned') {
            header('Location: login.php?error=account_banned');
            exit;
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            error_log("Failed login attempt for email: " . $email . " from IP: " . $ip);
            header('Location: login.php?error=invalid_credentials');
            exit;
        }

        // Regenerate session ID
        session_regenerate_id(true);

        // Set session variables
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = strtolower($user['role']);
        $_SESSION['login_time'] = time();
        $_SESSION['user_ip'] = $ip;

        // Log successful login
        error_log("User logged in: " . $user['email'] . " from IP: " . $ip);

        // Update last login
        try {
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
        } catch (PDOException $e) {
            error_log("Failed to update last_login: " . $e->getMessage());
        }

        // ROLE-BASED REDIRECTION
        $role = strtolower($user['role']);
        
        switch($role) {
            case 'admin':
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_name'] = $user['name'];
                header('Location: admin/admin-dashboard.php');
                break;
            case 'doctor':
            case 'hospital':
                header('Location: dashboard/provider-dashboard.php');
                break;
            case 'patient':
            default:
                header('Location: dashboard/patient-dashboard.php');
                break;
        }
        exit;

    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        header('Location: login.php?error=server_error');
        exit;
    }
}

// If NOT POST, display the login form
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in to your Care Connect SL account.">
  <title>Login - Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .login-container {
        max-width: 480px;
        margin: 0 auto;
    }
    .login-container .form-card {
        padding: 36px 40px;
    }
    .login-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }
    .login-options a {
        font-size: 0.9rem;
        color: var(--muted);
        transition: color var(--transition);
    }
    .login-options a:hover {
        color: var(--primary);
        text-decoration: none;
    }
    @media (max-width: 480px) {
        .login-container .form-card {
            padding: 24px 20px;
        }
        .login-options {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
    }
  </style>
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
    <a href="index.html" class="logo" aria-label="Care Connect SL Home">
      <span class="logo-icon" aria-hidden="true">❤️</span> Care<span class="accent">Connect</span> SL
    </a>
    <nav aria-label="Main navigation">
      <ul class="nav-links" role="menubar">
        <li><a href="index.html" role="menuitem">Home</a></li>
        <li><a href="pages/doctors.php" role="menuitem">Find Care</a></li>
        <li><a href="pages/about.html" role="menuitem">About</a></li>
        <li><a href="pages/contact.html" role="menuitem">Contact</a></li>
      </ul>
    </nav>
    <div class="nav-actions">
      <a href="register.php" class="btn-primary">Register</a>
    </div>
  </div>
</header>

<main class="page-content page-content--narrow" role="main">
  <div class="login-container">
    <section class="page-hero" aria-labelledby="login-title">
      <h1 id="login-title">Welcome Back</h1>
      <p>Sign in to access Care Connect referrals, provider connections, and clinic support.</p>
    </section>

    <?php
    // Display error messages
    if (isset($_GET['error'])) {
        $error = $_GET['error'];
        $message = '';
        switch($error) {
            case 'invalid_credentials':
                $message = 'Invalid email or password. Please try again.';
                break;
            case 'account_inactive':
                $message = 'Your account is inactive. Please contact support.';
                break;
            case 'account_banned':
                $message = 'Your account has been banned. Please contact support.';
                break;
            case 'rate_limit':
                $message = 'Too many login attempts. Please wait 5 minutes.';
                break;
            case 'csrf':
                $message = 'Security validation failed. Please refresh and try again.';
                break;
            case 'server_error':
                $message = 'A server error occurred. Please try again later.';
                break;
            case 'missing':
                $message = 'Please fill in all fields.';
                break;
            case 'invalid_email':
                $message = 'Please enter a valid email address.';
                break;
            default:
                $message = 'An error occurred. Please try again.';
        }
        echo '<div class="form-message error">❌ ' . $message . '</div>';
    }

    // Display success message after registration
    if (isset($_GET['registered']) && $_GET['registered'] == 1) {
        echo '<div class="form-message success">✅ Registration successful! Please login below.</div>';
    }

    // Display logout success message
    if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
        echo '<div class="form-message success">✅ You have been logged out successfully.</div>';
    }
    ?>

    <div class="form-card">
      <form action="login.php" method="POST" novalidate>
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div style="margin-bottom: 16px;">
          <label for="loginEmail">Email Address</label>
          <input type="email" 
                 id="loginEmail" 
                 name="email" 
                 placeholder="Enter your email" 
                 required 
                 maxlength="100" 
                 autocomplete="email" 
                 aria-required="true">
        </div>
        
        <div style="margin-bottom: 16px;">
          <label for="loginPassword">Password</label>
          <div style="position: relative;">
            <input type="password" 
                   id="loginPassword" 
                   name="password" 
                   placeholder="Enter your password" 
                   required 
                   minlength="8" 
                   autocomplete="current-password" 
                   aria-required="true">
            <button type="button" 
                    class="password-toggle" 
                    style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1.2rem; cursor: pointer; padding: 4px;"
                    aria-label="Show password">👁️</button>
          </div>
        </div>
        
        <button type="submit" class="btn-primary" style="width: 100%;">Login</button>
      </form>
      
      <div class="login-options">
        <p style="margin: 0; font-size: 0.9rem; color: var(--muted);">
          Don't have an account? <a href="register.php" style="font-weight: 600;">Register</a>
        </p>
        <a href="forgot-password.php">Forgot your password?</a>
      </div>
    </div>

    <!-- Quick AI Help - FIXED LINK -->
    <div style="margin-top: 24px; padding: 20px; text-align: center; background: linear-gradient(135deg, #F0FDF9, #F7FFFB); border-radius: var(--radius-lg); border: 1px solid var(--border);">
      <p style="margin: 0; color: var(--muted); font-size: 0.95rem;">
        🤖 Need help? <a href="ai-chat.php" style="font-weight: 600;">Chat with our AI Assistant</a>
      </p>
    </div>
  </div>
</main>

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
        <li><a href="register.php">Register</a></li>
        <li><a href="pages/referral.html">Make Referral</a></li>
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
</body>
</html>