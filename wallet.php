<?php
/**
 * Wallet Dashboard - Care Connect SL
 * Users can view balance, transaction history, and add funds
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

// Include database and payment system
require_once 'db.php';
require_once 'payment_helper.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['role'] ?? 'patient';

// Initialize payment system
$payment = new PaymentSystem($conn);

// Get wallet data
$balance = $payment->getBalance($user_id);
$transactions = $payment->getTransactionHistory($user_id, 20);

// Handle mobile money deposit request
$deposit_message = '';
$deposit_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit'])) {
    $amount = (float)$_POST['amount'] ?? 0;
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $provider = sanitizeInput($_POST['provider'] ?? 'Orange');
    
    if ($amount < 1000) {
        $deposit_error = 'Minimum deposit is 1,000 SLL.';
    } elseif (empty($phone) || !preg_match("/^[0-9+\-\s]{8,15}$/", $phone)) {
        $deposit_error = 'Please enter a valid phone number.';
    } else {
        $result = $payment->initiateMobileMoney($user_id, $amount, $phone, $provider);
        if ($result['success']) {
            $deposit_message = '✅ ' . $result['message'];
        } else {
            $deposit_error = $result['error'] ?? 'Deposit failed. Please try again.';
        }
    }
}

// Get provider payment settings (if provider)
$provider_settings = null;
if ($user_role === 'doctor' || $user_role === 'hospital') {
    try {
        $stmt = $conn->prepare("SELECT * FROM provider_payment_settings WHERE provider_id = ?");
        $stmt->execute([$user_id]);
        $provider_settings = $stmt->fetch();
    } catch (PDOException $e) {
        // No settings yet
    }
}

// Get referral payment stats
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(amount) as total_amount,
            SUM(platform_fee) as total_fees,
            SUM(provider_earnings) as total_earnings
        FROM referral_payments 
        WHERE patient_id = ? OR provider_id = ?
    ");
    $stmt->execute([$user_id, $user_id]);
    $payment_stats = $stmt->fetch();
} catch (PDOException $e) {
    $payment_stats = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💰 Wallet — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard/dashboard.css">
    <style>
        .wallet-container { max-width: 1000px; margin: 0 auto; }
        
        .wallet-balance-card {
            background: linear-gradient(135deg, #0A1628, #1a2a4a);
            border-radius: var(--radius-xl);
            padding: 36px 40px;
            color: white;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .wallet-balance-card .balance-label {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .wallet-balance-card .balance-amount {
            font-size: 3rem;
            font-weight: 700;
            color: #fff;
            font-family: 'Playfair Display', serif;
        }
        .wallet-balance-card .balance-amount .currency {
            font-size: 1.2rem;
            color: rgba(255,255,255,0.5);
        }
        .wallet-balance-card .balance-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .wallet-balance-card .balance-actions .btn {
            padding: 10px 24px;
            border-radius: var(--radius-full);
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        .wallet-balance-card .balance-actions .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        .wallet-balance-card .balance-actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30,181,58,0.3);
        }
        .wallet-balance-card .balance-actions .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255,255,255,0.2);
        }
        .wallet-balance-card .balance-actions .btn-outline:hover {
            background: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.4);
        }
        
        .wallet-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .wallet-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .wallet-card h3 {
            font-size: 1.1rem;
            margin-bottom: 16px;
            color: var(--dark);
        }
        
        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .transaction-item:last-child { border-bottom: none; }
        .transaction-item .tx-info { flex: 1; }
        .transaction-item .tx-type {
            display: inline-block;
            padding: 2px 12px;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
        }
        .transaction-item .tx-type.deposit { background: rgba(0,200,150,0.12); color: var(--primary-dark); }
        .transaction-item .tx-type.payment { background: rgba(239,68,68,0.12); color: #B91C1C; }
        .transaction-item .tx-type.commission { background: rgba(245,158,11,0.12); color: #B45309; }
        .transaction-item .tx-type.refund { background: rgba(59,130,246,0.12); color: #1D4ED8; }
        .transaction-item .tx-amount { font-weight: 600; }
        .transaction-item .tx-amount.positive { color: var(--primary-dark); }
        .transaction-item .tx-amount.negative { color: var(--danger); }
        .transaction-item .tx-date { font-size: 0.8rem; color: var(--muted); }
        
        .deposit-form .form-group { margin-bottom: 16px; }
        .deposit-form label { display: block; font-weight: 500; font-size: 0.9rem; color: var(--dark); margin-bottom: 4px; }
        .deposit-form input, .deposit-form select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #E2E8F0;
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .deposit-form input:focus, .deposit-form select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(30,181,58,0.1);
        }
        
        @media (max-width: 768px) {
            .wallet-grid { grid-template-columns: 1fr; }
            .wallet-balance-card { padding: 24px; flex-direction: column; text-align: center; }
            .wallet-balance-card .balance-actions { width: 100%; justify-content: center; }
            .wallet-balance-card .balance-amount { font-size: 2.4rem; }
        }
        @media (max-width: 480px) {
            .wallet-balance-card .balance-actions { flex-direction: column; }
            .wallet-balance-card .balance-actions .btn { width: 100%; justify-content: center; }
            .wallet-card { padding: 16px 18px; }
            .transaction-item { flex-wrap: wrap; gap: 8px; }
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
        <div class="wallet-container">

            <!-- Balance Card -->
            <div class="wallet-balance-card">
                <div>
                    <div class="balance-label">Available Balance</div>
                    <div class="balance-amount">
                        <span class="currency">SLL</span> <?php echo number_format($balance, 0); ?>
                    </div>
                    <?php if ($user_role === 'doctor' || $user_role === 'hospital'): ?>
                        <div style="margin-top: 8px; color: rgba(255,255,255,0.5); font-size: 0.85rem;">
                            💰 Provider earnings from referrals
                        </div>
                    <?php endif; ?>
                </div>
                <div class="balance-actions">
                    <button class="btn btn-primary" onclick="document.getElementById('depositForm').scrollIntoView({behavior:'smooth'})">
                        💳 Add Funds
                    </button>
                    <a href="transaction-history.php" class="btn btn-outline">📊 History</a>
                    <?php if ($user_role === 'doctor' || $user_role === 'hospital'): ?>
                        <a href="provider-payment-settings.php" class="btn btn-outline">⚙️ Settings</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($deposit_message)): ?>
                <div class="form-message success"><?php echo $deposit_message; ?></div>
            <?php endif; ?>
            <?php if (!empty($deposit_error)): ?>
                <div class="form-message error">❌ <?php echo $deposit_error; ?></div>
            <?php endif; ?>

            <!-- Wallet Grid -->
            <div class="wallet-grid">

                <!-- Deposit Form -->
                <div class="wallet-card" id="depositForm">
                    <h3>💰 Add Funds</h3>
                    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 16px;">
                        Deposit money using Mobile Money (Orange Money, MoMo)
                    </p>
                    <form method="POST" class="deposit-form">
                        <div class="form-group">
                            <label for="amount">Amount (SLL)</label>
                            <input type="number" id="amount" name="amount" placeholder="e.g., 50000" min="1000" step="1000" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Mobile Money Number</label>
                            <input type="text" id="phone" name="phone" placeholder="+232 76 123 456" required>
                        </div>
                        <div class="form-group">
                            <label for="provider">Mobile Money Provider</label>
                            <select id="provider" name="provider">
                                <option value="Orange">Orange Money</option>
                                <option value="MoMo">MoMo (Africell)</option>
                                <option value="QCell">QCell</option>
                            </select>
                        </div>
                        <button type="submit" name="deposit" class="btn-primary" style="width: 100%;">💳 Request Deposit</button>
                    </form>
                    <p style="color: var(--muted); font-size: 0.75rem; margin-top: 12px;">
                        ⚡ Funds will be added to your wallet after confirmation. Processing time: 2-5 minutes.
                    </p>
                </div>

                <!-- Quick Stats / Payment Summary -->
                <div class="wallet-card">
                    <h3>📊 Payment Summary</h3>
                    <?php if ($payment_stats && $payment_stats['total_payments'] > 0): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div style="background: var(--light); padding: 12px; border-radius: var(--radius-md); text-align: center;">
                                <span style="font-size: 1.2rem; font-weight: 700; color: var(--primary); display: block;">
                                    <?php echo $payment_stats['total_payments']; ?>
                                </span>
                                <span style="color: var(--muted); font-size: 0.8rem;">Total Payments</span>
                            </div>
                            <div style="background: var(--light); padding: 12px; border-radius: var(--radius-md); text-align: center;">
                                <span style="font-size: 1.2rem; font-weight: 700; color: var(--primary); display: block;">
                                    SLL <?php echo number_format($payment_stats['total_amount'] ?? 0, 0); ?>
                                </span>
                                <span style="color: var(--muted); font-size: 0.8rem;">Total Value</span>
                            </div>
                        </div>
                        <?php if ($user_role === 'doctor' || $user_role === 'hospital'): ?>
                            <div style="background: rgba(0,200,150,0.05); padding: 12px; border-radius: var(--radius-md); border-left: 3px solid var(--primary);">
                                <span style="color: var(--muted); font-size: 0.8rem;">Your Earnings</span>
                                <span style="font-weight: 700; color: var(--primary-dark); display: block; font-size: 1.1rem;">
                                    SLL <?php echo number_format($payment_stats['total_earnings'] ?? 0, 0); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 20px 0;">
                            <p style="color: var(--muted);">No payment activity yet.</p>
                            <?php if ($user_role === 'patient'): ?>
                                <p style="color: var(--gray-light); font-size: 0.9rem;">Complete a referral to start payments.</p>
                            <?php else: ?>
                                <p style="color: var(--gray-light); font-size: 0.9rem;">Complete a referral to start earning.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Quick action -->
                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border);">
                        <a href="pages/referral.html" class="btn-primary" style="width: 100%; text-align: center; display: block;">
                            📝 Make a Referral
                        </a>
                    </div>
                </div>

            </div>

            <!-- Recent Transactions -->
            <div class="wallet-card" style="margin-top: 24px;">
                <h3>🔄 Recent Transactions</h3>
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $tx): ?>
                        <div class="transaction-item">
                            <div class="tx-info">
                                <div>
                                    <span class="tx-type <?php echo $tx['type']; ?>">
                                        <?php echo ucfirst($tx['type']); ?>
                                    </span>
                                    <span style="font-size: 0.85rem; color: var(--dark); margin-left: 8px;">
                                        <?php echo htmlspecialchars($tx['description']); ?>
                                    </span>
                                </div>
                                <div class="tx-date"><?php echo date('M d, Y h:i A', strtotime($tx['created_at'])); ?></div>
                            </div>
                            <div class="tx-amount <?php echo $tx['type'] === 'deposit' || $tx['type'] === 'refund' ? 'positive' : 'negative'; ?>">
                                <?php echo $tx['type'] === 'deposit' || $tx['type'] === 'refund' ? '+' : '-'; ?>
                                SLL <?php echo number_format($tx['amount'], 0); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px 0;">
                        <p style="color: var(--muted);">No transactions yet.</p>
                    </div>
                <?php endif; ?>
                <div style="margin-top: 12px; text-align: center;">
                    <a href="transaction-history.php" class="view-all">View All Transactions →</a>
                </div>
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
                <li><a href="pages/referral.html">New Referral</a></li>
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
// Auto-hide success message after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successMsg = document.querySelector('.form-message.success');
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