<?php
/**
 * Registration - Care Connect SL
 * All-in-one registration form with processing
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
require_once __DIR__ . '/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkRateLimit('register_' . $ip, 3, 3600)) {
        $_SESSION['registration_errors'] = ['Too many registration attempts. Please try again later.'];
        header('Location: register.php?error=too_many_attempts');
        exit;
    }

    // CSRF Protection
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrf_token)) {
        $_SESSION['registration_errors'] = ['Security validation failed. Please refresh the page and try again.'];
        header('Location: register.php?error=csrf');
        exit;
    }

    // Get and sanitize inputs
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitizeInput($_POST['role'] ?? '');

    // Validation
    $errors = [];

    // Name validation
    if (empty($name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Full name cannot exceed 100 characters.';
    } elseif (!preg_match("/^[A-Za-z\s\-']+$/", $name)) {
        $errors[] = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';
    }

    // Email validation
    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email address cannot exceed 100 characters.';
    }

    // Password validation
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors[] = 'Password cannot exceed 72 characters.';
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match("/[a-z]/", $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match("/[0-9]/", $password)) {
        $errors[] = 'Password must contain at least one number.';
    }

    // Role validation
    $allowedRoles = ['patient', 'doctor', 'hospital'];
    if (empty($role)) {
        $errors[] = 'Please select a role.';
    } elseif (!in_array(strtolower($role), $allowedRoles)) {
        $errors[] = 'Invalid role selected.';
    }

    // Check for validation errors
    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        $_SESSION['registration_data'] = [
            'name' => $name,
            'email' => $email,
            'role' => $role
        ];
        header('Location: register.php?error=validation');
        exit;
    }

    // Process registration
    try {
        // Check if email already exists
        $check = $conn->prepare("SELECT id, status FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        $existingUser = $check->fetch();

        if ($existingUser) {
            if ($existingUser['status'] === 'inactive') {
                $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $deleteStmt->execute([$existingUser['id']]);
            } else {
                $_SESSION['registration_errors'] = ['This email is already registered. Please login or use a different email.'];
                $_SESSION['registration_data'] = [
                    'name' => $name,
                    'email' => $email,
                    'role' => $role
                ];
                header('Location: register.php?error=exists');
                exit;
            }
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));

        // Insert user
        $stmt = $conn->prepare("
            INSERT INTO users (
                name, email, password, role, status, 
                email_verified, verification_token, ip_address, created_at
            ) VALUES (
                ?, ?, ?, ?, 'active', FALSE, ?, ?, NOW()
            )
        ");
        
        $result = $stmt->execute([
            $name, $email, $hashedPassword, $role,
            $verificationToken, $ip
        ]);

        if (!$result) {
            throw new Exception("Failed to insert user into database.");
        }

        $userId = $conn->lastInsertId();

        // Log successful registration
        error_log("New user registered: " . $email . " (ID: " . $userId . ") from IP: " . $ip);

        // Clear session data
        unset($_SESSION['registration_errors']);
        unset($_SESSION['registration_data']);

        // ROLE-BASED REDIRECTION AFTER REGISTRATION
        $roleLower = strtolower($role);

        // Redirect based on role
        if ($roleLower === 'doctor' || $roleLower === 'hospital') {
            // Redirect to provider registration form to complete profile
            header('Location: provider-registration.php');
            exit;
        } else {
            // Redirect to login for patients
            header('Location: login.php?registered=1');
            exit;
        }

    } catch (PDOException $e) {
        error_log("Registration PDO Error: " . $e->getMessage());
        $_SESSION['registration_errors'] = ['A server error occurred. Please try again later.'];
        $_SESSION['registration_data'] = [
            'name' => $name,
            'email' => $email,
            'role' => $role
        ];
        header('Location: register.php?error=server');
        exit;
    } catch (Exception $e) {
        error_log("Registration Error: " . $e->getMessage());
        $_SESSION['registration_errors'] = ['A server error occurred. Please try again later.'];
        $_SESSION['registration_data'] = [
            'name' => $name,
            'email' => $email,
            'role' => $role
        ];
        header('Location: register.php?error=server');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Register for Care Connect SL - Sierra Leone's healthcare referral platform.">
  <title>Register - Care Connect SL</title>
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
    </div>
  </div>
</header>

<main class="page-content page-content--narrow" role="main">
  <section class="page-hero" aria-labelledby="register-title">
    <h1 id="register-title">Join Care Connect SL</h1>
    <p>Register as a patient, care provider, or clinic and start managing referrals faster.</p>
  </section>

  <?php
  // Display error messages from session
  if (isset($_SESSION['registration_errors']) && !empty($_SESSION['registration_errors'])) {
      echo '<div class="form-message error">';
      echo '<strong>Please fix the following errors:</strong><ul>';
      foreach ($_SESSION['registration_errors'] as $error) {
          echo '<li>' . htmlspecialchars($error) . '</li>';
      }
      echo '</ul></div>';
      unset($_SESSION['registration_errors']);
  }

  // Display error from URL parameter
  if (isset($_GET['error'])) {
      $error = $_GET['error'];
      $message = '';
      switch($error) {
          case 'missing':
              $message = 'Please fill in all required fields.';
              break;
          case 'invalid_email':
              $message = 'Please enter a valid email address.';
              break;
          case 'exists':
              $message = 'This email is already registered. Please <a href="login.php">login</a> or use a different email.';
              break;
          case 'server':
              $message = 'A server error occurred. Please try again later.';
              break;
          case 'too_many_attempts':
              $message = 'Too many registration attempts. Please try again later.';
              break;
          case 'csrf':
              $message = 'Security validation failed. Please refresh the page and try again.';
              break;
          case 'validation':
              $message = 'Please check your input and try again.';
              break;
          default:
              $message = 'An error occurred. Please try again.';
      }
      echo '<div class="form-message error">❌ ' . $message . '</div>';
  }
  ?>

  <div class="form-card page-grid">
    <form action="register.php" method="POST" class="page-grid" novalidate>
      <!-- CSRF Token -->
      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
      
      <!-- Name -->
      <div>
        <label for="registerName">Full Name</label>
        <input type="text" 
               id="registerName" 
               name="name" 
               placeholder="Enter your full name" 
               required 
               maxlength="100" 
               pattern="[A-Za-z\s\-']+" 
               autocomplete="name" 
               aria-required="true"
               value="<?php echo isset($_SESSION['registration_data']['name']) ? htmlspecialchars($_SESSION['registration_data']['name']) : ''; ?>">
        <small style="color: var(--muted); font-size: 0.85rem;">Letters, spaces, hyphens, and apostrophes only</small>
      </div>
      
      <!-- Email -->
      <div>
        <label for="registerEmail">Email Address</label>
        <input type="email" 
               id="registerEmail" 
               name="email" 
               placeholder="Enter your email address" 
               required 
               maxlength="100" 
               autocomplete="email" 
               aria-required="true"
               value="<?php echo isset($_SESSION['registration_data']['email']) ? htmlspecialchars($_SESSION['registration_data']['email']) : ''; ?>">
      </div>
      
      <!-- Password -->
      <div>
        <label for="registerPassword">Password</label>
        <div style="position: relative;">
          <input type="password" 
                 id="registerPassword" 
                 name="password" 
                 placeholder="Minimum 8 characters" 
                 required 
                 minlength="8" 
                 autocomplete="new-password" 
                 aria-required="true">
          <button type="button" 
                  class="password-toggle" 
                  style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1.2rem; cursor: pointer;"
                  aria-label="Show password">
            👁️
          </button>
        </div>
        <small style="color: var(--muted); font-size: 0.85rem;">
          Must be at least 8 characters with uppercase, lowercase, and a number
        </small>
      </div>
      
      <!-- Role -->
      <div>
        <label for="registerRole">I am a...</label>
        <select id="registerRole" name="role" required aria-required="true">
          <option value="">Select your role</option>
          <option value="patient" <?php echo (isset($_SESSION['registration_data']['role']) && $_SESSION['registration_data']['role'] === 'patient') ? 'selected' : ''; ?>>Patient / Family Member</option>
          <option value="doctor" <?php echo (isset($_SESSION['registration_data']['role']) && $_SESSION['registration_data']['role'] === 'doctor') ? 'selected' : ''; ?>>Community Health Worker / Doctor</option>
          <option value="hospital" <?php echo (isset($_SESSION['registration_data']['role']) && $_SESSION['registration_data']['role'] === 'hospital') ? 'selected' : ''; ?>>Clinic / Hospital</option>
        </select>
      </div>
      
      <button type="submit" class="btn-primary">Register Now</button>
    </form>
    
    <p class="page-note">Already have an account? <a href="login.php">Sign in</a></p>
    <p class="page-note" style="font-size: 0.85rem; margin-top: 8px;">
      By registering, you agree to our <a href="terms.html">Terms of Service</a> and <a href="privacy.html">Privacy Policy</a>.
    </p>
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
        <li><a href="pages/referral.html">Referrals</a></li>
        <li><a href="login.php">Login</a></li>
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

<?php
// Clear session data after displaying
if (isset($_SESSION['registration_data'])) {
    unset($_SESSION['registration_data']);
}
?>

<script>
// Password toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtns = document.querySelectorAll('.password-toggle');
    toggleBtns.forEach(function(btn) {
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
});
</script>

</body>
</html>