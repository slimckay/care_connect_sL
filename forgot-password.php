<?php
/**
 * Forgot Password - Care Connect SL
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Reset your Care Connect SL password to regain access to your account.">
  <title>Forgot Password — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
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
      <a href="login.php" class="btn-ghost">Sign In</a>
      <a href="register.php" class="btn-primary">Register</a>
    </div>
  </div>
</header>

<main class="page-content page-content--narrow" role="main">
  <section class="page-hero" aria-labelledby="reset-title">
    <h1 id="reset-title">Reset Your Password</h1>
    <p>Enter your email address and we'll send you a link to reset your password.</p>
  </section>

  <?php
  // Display messages
  if (isset($_GET['sent']) && $_GET['sent'] == 1) {
      echo '<div class="form-message success">✅ Password reset link sent! Please check your email.</div>';
  }
  if (isset($_GET['error'])) {
      $error = $_GET['error'];
      $message = '';
      switch($error) {
          case 'invalid_email':
              $message = 'Please enter a valid email address.';
              break;
          case 'too_many_attempts':
              $message = 'Too many attempts. Please try again later.';
              break;
          case 'server_error':
              $message = 'A server error occurred. Please try again.';
              break;
          case 'csrf':
              $message = 'Security validation failed. Please refresh and try again.';
              break;
          default:
              $message = 'An error occurred. Please try again.';
      }
      echo '<div class="form-message error">❌ ' . $message . '</div>';
  }
  ?>

  <div class="form-card page-grid">
    <form action="reset-password.php" method="POST" class="page-grid" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
      
      <label for="resetEmail" class="sr-only">Email address</label>
      <input type="email" id="resetEmail" name="email" placeholder="Enter your email address" required maxlength="100" autocomplete="email" aria-required="true">
      
      <button type="submit" class="btn-primary">Send Reset Link</button>
    </form>

    <div class="page-note" style="margin-top:12px;">
      <p style="font-size:0.9rem;">Remember your password? <a href="login.php">Sign in</a></p>
    </div>

    <div style="margin-top:20px;padding:20px;background:#F8FAFC;border-radius:12px;border:1px solid #E2E8F0;">
      <p style="font-size:0.9rem;margin:0;color:#64748B;">
        <strong style="color:#0F1C3A;">💡 Password Reset Tips:</strong>
      </p>
      <ul style="font-size:0.85rem;color:#64748B;margin-top:8px;padding-left:20px;">
        <li>Check your spam folder if you don't see the email</li>
        <li>The reset link expires in 24 hours</li>
        <li>Choose a strong password with at least 8 characters</li>
        <li>Contact support if you need further assistance</li>
      </ul>
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
        <li><a href="login.php">Login</a></li>
        <li><a href="register.php">Register</a></li>
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