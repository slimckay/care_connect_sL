<?php
/**
 * Provider Payment Settings - Care Connect SL
 * Doctors and hospitals can set their consultation fees
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

if ($user_role !== 'doctor' && $user_role !== 'hospital') {
    header('Location: index.html');
    exit;
}

require_once 'db.php';
require_once 'payment_helper.php';

$payment = new PaymentSystem($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $commission_rate = (float)($_POST['commission_rate'] ?? 5);
    $mobile_money_number = sanitizeInput($_POST['mobile_money_number'] ?? '');
    $bank_account = sanitizeInput($_POST['bank_account'] ?? '');
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO provider_payment_settings 
            (provider_id, consultation_fee, commission_rate, mobile_money_number, bank_account, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            consultation_fee = VALUES(consultation_fee),
            commission_rate = VALUES(commission_rate),
            mobile_money_number = VALUES(mobile_money_number),
            bank_account = VALUES(bank_account),
            updated_at = NOW()
        ");
        $stmt->execute([$user_id, $consultation_fee, $commission_rate, $mobile_money_number, $bank_account]);
        $message = '✅ Payment settings saved successfully!';
    } catch (PDOException $e) {
        error_log("Payment settings error: " . $e->getMessage());
        $error = 'Failed to save settings. Please try again.';
    }
}

// Get current settings
try {
    $stmt = $conn->prepare("SELECT * FROM provider_payment_settings WHERE provider_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    $settings = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Settings — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header role="banner">
    <div class="nav-inner">
        <a href="index.html" class="logo">❤️ Care<span class="accent">Connect</span> SL</a>
        <nav>
            <ul class="nav-links">
                <li><a href="index.html">Home</a></li>
                <li><a href="wallet.php">💰 Wallet</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="page-content page-content--narrow">
    <section class="page-hero">
        <h1>⚙️ Payment Settings</h1>
        <p>Set your consultation fees and payment preferences.</p>
    </section>

    <?php if (isset($message)): ?>
        <div class="form-message success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="form-message error">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST">
            <div style="margin-bottom: 16px;">
                <label for="consultation_fee">Consultation Fee (SLL)</label>
                <input type="number" id="consultation_fee" name="consultation_fee" 
                       value="<?php echo $settings['consultation_fee'] ?? 0; ?>" 
                       min="0" step="1000" required>
                <small style="color: var(--muted);">Minimum fee for your services</small>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label for="commission_rate">Platform Commission Rate (%)</label>
                <input type="number" id="commission_rate" name="commission_rate" 
                       value="<?php echo $settings['commission_rate'] ?? 5; ?>" 
                       min="0" max="20" step="0.5" required>
                <small style="color: var(--muted);">Percentage taken by platform for each referral</small>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label for="mobile_money_number">Mobile Money Number</label>
                <input type="text" id="mobile_money_number" name="mobile_money_number" 
                       value="<?php echo $settings['mobile_money_number'] ?? ''; ?>" 
                       placeholder="+232 76 123 456">
                <small style="color: var(--muted);">For receiving payments</small>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label for="bank_account">Bank Account (Optional)</label>
                <input type="text" id="bank_account" name="bank_account" 
                       value="<?php echo $settings['bank_account'] ?? ''; ?>" 
                       placeholder="Bank Name - Account Number">
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%;">💾 Save Settings</button>
        </form>
    </div>
</main>

<footer class="site-footer">
    <p class="footer-note">&copy; 2026 Care Connect SL. All rights reserved.</p>
</footer>

<script src="js/main.js"></script>
</body>
</html>