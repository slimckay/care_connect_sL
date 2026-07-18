<?php
/**
 * Registration - Care Connect SL
 * Professional & Clean Version
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

    if (empty($name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    } elseif (!preg_match("/^[A-Za-z\s\-']+$/", $name)) {
        $errors[] = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';
    }

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $errors[] = 'Password must contain uppercase, lowercase, and a number.';
    }

    $allowedRoles = ['patient', 'doctor', 'hospital'];
    if (empty($role) || !in_array(strtolower($role), $allowedRoles)) {
        $errors[] = 'Please select a valid role.';
    }

    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        $_SESSION['registration_data'] = ['name' => $name, 'email' => $email, 'role' => $role];
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
                $_SESSION['registration_errors'] = ['This email is already registered. Please login instead.'];
                $_SESSION['registration_data'] = ['name' => $name, 'email' => $email, 'role' => $role];
                header('Location: register.php?error=exists');
                exit;
            }
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
        $verificationToken = bin2hex(random_bytes(32));

        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password, role, status, email_verified, verification_token, ip_address, created_at)
            VALUES (?, ?, ?, ?, 'active', FALSE, ?, ?, NOW())
        ");
        
        $stmt->execute([$name, $email, $hashedPassword, $role, $verificationToken, $ip]);
        $userId = $conn->lastInsertId();

        unset($_SESSION['registration_errors']);
        unset($_SESSION['registration_data']);

        if (strtolower($role) === 'doctor' || strtolower($role) === 'hospital') {
            header('Location: provider-registration.php');
        } else {
            header('Location: login.php?registered=1');
        }
        exit;

    } catch (Exception $e) {
        $_SESSION['registration_errors'] = ['A server error occurred. Please try again later.'];
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
  <title>Register — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;family=Playfair+Display:wght@600&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .auth-container {
      max-width: 520px;
      margin: 50px auto;
      padding: 0 20px;
    }
    .auth-card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(30, 181, 58, 0.12);
      padding: 48px 44px;
      border: 1px solid rgba(30,181,58,0.1);
    }
    .auth-header {
      text-align: center;
      margin-bottom: 36px;
    }
    .auth-header h1 {
      font-family: 'Playfair Display', serif;
      font-size: 2.1rem;
      color: #0F1C3A;
      margin-bottom: 8px;
    }
    .form-group {
      margin-bottom: 22px;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: #1F2937;
    }
    .form-group input, .form-group select {
      width: 100%;
      padding: 15px 18px;
      border: 2px solid #E5E7EB;
      border-radius: 12px;
      font-size: 1rem;
      transition: all 0.3s;
    }
    .form-group input:focus, .form-group select:focus {
      border-color: #1EB53A;
      box-shadow: 0 0 0 4px rgba(30,181,58,0.1);
      outline: none;
    }
    .btn-primary {
      width: 100%;
      padding: 17px;
      font-size: 1.1rem;
      font-weight: 700;
      border-radius: 12px;
      margin-top: 10px;
    }
    .error-box {
      background: #FEE2E2;
      color: #991B1B;
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-size: 0.95rem;
    }
    .success-box {
      background: #D1FAE5;
      color: #065F46;
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
    }
    .auth-footer {
      text-align: center;
      margin-top: 28px;
      color: #64748B;
    }
  </style>
</head>
<body>

<!-- Clean Professional Header -->
<header>
  <div class="nav-inner">
    <a href="index.html" class="logo">
      Care<span class="accent">Connect</span> SL
    </a>
    <div class="nav-actions">
      <a href="login.php" class="btn-ghost">Already have an account?</a>
    </div>
  </div>
</header>

<main class="auth-container">
  <div class="auth-card">
    <div class="auth-header">
      <h1>Create Your Account</h1>
      <p style="color:#64748B;">Join Sierra Leone’s home-based care network</p>
    </div>

    <!-- Error Messages -->
    <?php if (isset($_GET['error']) && isset($_SESSION['registration_errors'])): ?>
      <div class="error-box">
        <?php foreach ($_SESSION['registration_errors'] as $error): ?>
          <p style="margin:4px 0;">• <?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" placeholder="Your full name" 
               value="<?= htmlspecialchars($_SESSION['registration_data']['name'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="you@example.com" 
               value="<?= htmlspecialchars($_SESSION['registration_data']['email'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Create a strong password" required>
        <small style="color:#64748B; font-size:0.85rem;">Minimum 8 characters with uppercase, lowercase & number</small>
      </div>

      <div class="form-group">
        <label>I am registering as</label>
        <select name="role" required>
          <option value="">Select your role</option>
          <option value="patient" <?= (($_SESSION['registration_data']['role'] ?? '') === 'patient') ? 'selected' : '' ?>>Patient / Family Member</option>
          <option value="doctor" <?= (($_SESSION['registration_data']['role'] ?? '') === 'doctor') ? 'selected' : '' ?>>Doctor</option>
          <option value="hospital" <?= (($_SESSION['registration_data']['role'] ?? '') === 'hospital') ? 'selected' : '' ?>>Clinic / Hospital</option>
        </select>
      </div>

      <button type="submit" class="btn-primary">Create Account</button>
    </form>

    <div class="auth-footer">
      <p>Already have an account? <a href="login.php" style="color:#1EB53A; font-weight:600;">Sign in here</a></p>
    </div>
  </div>
</main>

</body>
</html>