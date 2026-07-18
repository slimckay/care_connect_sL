<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'patient';
    if ($role === 'admin') header('Location: admin/admin-dashboard.php');
    elseif (in_array($role, ['doctor', 'hospital'])) header('Location: dashboard/provider-dashboard.php');
    else header('Location: dashboard/patient-dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password.';
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                if ($user['role'] === 'admin') {
                    $_SESSION['admin_logged_in'] = true;
                    header('Location: admin/admin-dashboard.php');
                } elseif (in_array($user['role'], ['doctor', 'hospital'])) {
                    header('Location: dashboard/provider-dashboard.php');
                } else {
                    header('Location: dashboard/patient-dashboard.php');
                }
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-container { max-width: 420px; margin: 60px auto; padding: 0 20px; }
        .auth-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            padding: 40px 36px;
            color: #1F2937;
        }
        .auth-card h1 {
            text-align: center;
            margin-bottom: 8px;
            color: #0F1C3A !important;
        }
        .auth-subtitle {
            text-align: center;
            color: #64748B !important;
            margin-bottom: 30px;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1F2937 !important;
        }
        .form-group input {
            width: 100%;
            padding: 13px 16px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            color: #1F2937;
            background: #fff;
        }
        .btn-primary { width: 100%; padding: 14px; font-size: 1.05rem; margin-top: 10px; }
        .error-box { background: #FEE2E2; color: #991B1B !important; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; }
        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.95rem;
            color: #1F2937 !important;
        }
        .auth-footer a { color: #1EB53A !important; font-weight: 600; }

        [data-theme="dark"] .auth-card {
            background: #1e293b !important;
            color: #e2e8f0 !important;
        }
        [data-theme="dark"] .auth-card h1 { color: #f8fafc !important; }
        [data-theme="dark"] .auth-subtitle { color: #94A3B8 !important; }
        [data-theme="dark"] .form-group label { color: #e2e8f0 !important; }
        [data-theme="dark"] .form-group input {
            background: #0f172a !important;
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }
        [data-theme="dark"] .auth-footer { color: #e2e8f0 !important; }
    </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <button onclick="toggleDarkMode()" class="dark-toggle">🌓</button>
      <a href="register.php" class="btn-primary">Get Started</a>
    </div>
  </div>
</header>

<div class="auth-container">
    <div class="auth-card">
        <h1>Welcome Back</h1>
        <p class="auth-subtitle">Sign in to your account</p>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <p class="auth-footer">
            Don't have an account? <a href="register.php">Create one</a>
        </p>
    </div>
</div>

<script src="js/dark-mode.js"></script>
</body>
</html>