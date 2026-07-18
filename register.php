<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$errors = [];
$formData = ['name' => '', 'email' => '', 'role' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['role'] = $_POST['role'] ?? '';

    if (empty($formData['name'])) $errors[] = 'Full name is required.';
    if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($formData['role'])) $errors[] = 'Please select a role.';

    if (empty($errors)) {
        try {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$formData['email']]);

            if ($check->rowCount() > 0) {
                $errors[] = 'This email is already registered.';
            } else {
                $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$formData['name'], $formData['email'], $hashed, $formData['role']]);

                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-container { max-width: 480px; margin: 50px auto; padding: 0 20px; }
        .auth-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            padding: 40px 36px;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; }
        .form-group input, .form-group select {
            width: 100%; padding: 13px 16px; border: 2px solid #E5E7EB; border-radius: 10px;
        }
        .btn-primary { width: 100%; padding: 14px; font-size: 1.05rem; }
        .error-box { background: #FEE2E2; color: #991B1B; padding: 14px 18px; border-radius: 10px; margin-bottom: 16px; }

        [data-theme="dark"] .auth-card {
            background: #1e293b;
            color: #e2e8f0;
        }
    </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <button onclick="toggleDarkMode()" class="dark-toggle">🌓</button>
    </div>
  </div>
</header>

<div class="auth-container">
    <div class="auth-card">
        <h1 style="text-align:center; margin-bottom:8px;">Create Account</h1>
        <p style="text-align:center; color:#64748B; margin-bottom:28px;">Join Care Connect SL</p>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <?php foreach ($errors as $err): ?>
                    <p>• <?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($formData['name']) ?>" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>I am registering as</label>
                <select name="role" required>
                    <option value="">Select role</option>
                    <option value="patient" <?= $formData['role']==='patient' ? 'selected' : '' ?>>Patient / Family</option>
                    <option value="doctor" <?= $formData['role']==='doctor' ? 'selected' : '' ?>>Doctor</option>
                    <option value="hospital" <?= $formData['role']==='hospital' ? 'selected' : '' ?>>Clinic / Hospital</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Create Account</button>
        </form>

        <p style="text-align:center; margin-top:20px;">
            Already have an account? <a href="login.php" style="color:#1EB53A; font-weight:600;">Sign in</a>
        </p>
    </div>
</div>

<script src="js/dark-mode.js"></script>
</body>
</html>