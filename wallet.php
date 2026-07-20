<?php
/**
 * Wallet Dashboard - Care Connect SL
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
require_once 'payment_helper.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['role'] ?? 'patient';

$payment = new PaymentSystem($conn);
$balance = $payment->getBalance($user_id);
$transactions = $payment->getTransactionHistory($user_id, 20);

$deposit_message = '';
$deposit_error = '';

// Return from Orange Money
if (isset($_GET['orange'])) {
    if ($_GET['orange'] === 'success') {
        $deposit_message = '✅ Orange Money payment submitted. Wallet updates when Orange confirms (usually under a minute). Refresh shortly.';
        $balance = $payment->getBalance($user_id);
    } elseif ($_GET['orange'] === 'demo') {
        $deposit_message = '✅ Demo mode: funds added to your wallet. Add Orange API keys on Render for live transfers.';
        $balance = $payment->getBalance($user_id);
    } elseif ($_GET['orange'] === 'cancel') {
        $deposit_error = 'Orange Money payment was cancelled.';
    }
}

// Legacy POST path (non-Orange providers / fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit'])) {
    $amount = (float)($_POST['amount'] ?? 0);
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $provider = sanitizeInput($_POST['provider'] ?? 'Orange');

    if ($amount < 1000) {
        $deposit_error = 'Minimum deposit is 1,000 SLL.';
    } elseif (empty($phone) || !preg_match("/^[0-9+\-\s]{8,15}$/", $phone)) {
        $deposit_error = 'Please enter a valid phone number.';
    } elseif (strcasecmp($provider, 'Orange') === 0) {
        // Handled by JS → /api/orange-pay.php
        $deposit_error = 'Use the Pay with Orange Money button.';
    } else {
        $result = $payment->initiateMobileMoney($user_id, $amount, $phone, $provider);
        if (!empty($result['success'])) {
            $deposit_message = '✅ ' . $result['message'];
            $balance = $payment->getBalance($user_id);
            $transactions = $payment->getTransactionHistory($user_id, 20);
        } else {
            $deposit_error = $result['error'] ?? 'Deposit failed. Please try again.';
        }
    }
}

$provider_settings = null;
if ($user_role === 'doctor' || $user_role === 'hospital') {
    try {
        $stmt = $conn->prepare("SELECT * FROM provider_payment_settings WHERE provider_id = ?");
        $stmt->execute([$user_id]);
        $provider_settings = $stmt->fetch();
    } catch (PDOException $e) {}
}

try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_payments, SUM(amount) as total_amount,
               SUM(platform_fee) as total_fees, SUM(provider_earnings) as total_earnings
        FROM referral_payments WHERE patient_id = ? OR provider_id = ?
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
    <title>Wallet — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard/dashboard.css">
    <style>
        .wallet-container { max-width: 1000px; margin: 0 auto; }
        .wallet-balance-card {
            background: linear-gradient(135deg, #0A1628, #1a2a4a);
            border-radius: var(--radius-xl);
            padding: 36px 40px; color: white; margin-bottom: 32px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 20px; border: 1px solid rgba(255,255,255,0.06);
        }
        .wallet-balance-card .balance-label { font-size: 0.9rem; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.05em; }
        .wallet-balance-card .balance-amount { font-size: 3rem; font-weight: 700; color: #fff; }
        .wallet-balance-card .balance-amount .currency { font-size: 1.2rem; color: rgba(255,255,255,0.5); }
        .wallet-balance-card .balance-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .wallet-balance-card .balance-actions .btn {
            padding: 10px 24px; border-radius: var(--radius-full); font-weight: 600;
            border: none; cursor: pointer; text-decoration: none;
        }
        .wallet-balance-card .balance-actions .btn-primary { background: var(--primary-gradient); color: white; }
        .wallet-balance-card .balance-actions .btn-outline { background: transparent; color: white; border: 2px solid rgba(255,255,255,0.2); }
        .wallet-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .wallet-card { background: var(--white); border-radius: var(--radius-lg); padding: 24px 28px; box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
        .wallet-card h3 { font-size: 1.1rem; margin-bottom: 16px; color: var(--dark); }
        .transaction-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .transaction-item:last-child { border-bottom: none; }
        .tx-type { display: inline-block; padding: 2px 12px; border-radius: var(--radius-full); font-size: 0.7rem; font-weight: 600; }
        .tx-type.deposit { background: rgba(0,200,150,0.12); color: var(--primary-dark); }
        .tx-type.payment { background: rgba(239,68,68,0.12); color: #B91C1C; }
        .tx-type.commission { background: rgba(245,158,11,0.12); color: #B45309; }
        .tx-type.refund { background: rgba(59,130,246,0.12); color: #1D4ED8; }
        .tx-amount.positive { color: var(--primary-dark); font-weight: 600; }
        .tx-amount.negative { color: var(--danger); font-weight: 600; }
        .tx-date { font-size: 0.8rem; color: var(--muted); }
        .deposit-form .form-group { margin-bottom: 16px; }
        .deposit-form label { display: block; font-weight: 500; font-size: 0.9rem; color: var(--dark); margin-bottom: 4px; }
        .deposit-form input, .deposit-form select {
            width: 100%; padding: 10px 14px; border: 2px solid #E2E8F0; border-radius: var(--radius-md); font-size: 1rem;
        }
        .form-message { padding: 12px 14px; border-radius: 12px; margin-bottom: 16px; }
        .form-message.success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .form-message.error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        @media (max-width: 768px) {
            .wallet-grid { grid-template-columns: 1fr; }
            .wallet-balance-card { padding: 24px; flex-direction: column; text-align: center; }
            .wallet-balance-card .balance-amount { font-size: 2.4rem; }
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
        <a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
        <div class="nav-actions">
            <span style="color: var(--muted); font-size: 0.9rem;">👋 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="logout.php" class="btn-ghost btn-logout">Log out</a>
        </div>
    </div>
</header>

<main class="dashboard-main" role="main">
    <div class="dashboard-container">
        <div class="wallet-container">

            <div class="wallet-balance-card">
                <div>
                    <div class="balance-label">Available Balance</div>
                    <div class="balance-amount">
                        <span class="currency">SLL</span> <?php echo number_format($balance, 0); ?>
                    </div>
                </div>
                <div class="balance-actions">
                    <button class="btn btn-primary" type="button" onclick="document.getElementById('depositForm').scrollIntoView({behavior:'smooth'})">Add Funds</button>
                    <a href="transaction-history.php" class="btn btn-outline">History</a>
                </div>
            </div>

            <?php if (!empty($deposit_message)): ?>
                <div class="form-message success"><?php echo htmlspecialchars($deposit_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($deposit_error)): ?>
                <div class="form-message error"><?php echo htmlspecialchars($deposit_error); ?></div>
            <?php endif; ?>
            <div id="jsMsg" class="form-message" style="display:none"></div>

            <div class="wallet-grid">
                <div class="wallet-card" id="depositForm">
                    <h3>Add Funds — Orange Money</h3>
                    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 16px;">
                        Pay with Orange Money. Live mode needs API keys on the server; without keys it runs in demo mode for testing.
                    </p>
                    <form id="orangeForm" class="deposit-form">
                        <div class="form-group">
                            <label for="amount">Amount (SLL)</label>
                            <input type="number" id="amount" name="amount" placeholder="e.g., 50000" min="1000" step="1000" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Orange Money number</label>
                            <input type="text" id="phone" name="phone" placeholder="+232 76 123 456" required>
                        </div>
                        <button type="submit" id="orangeBtn" class="btn-primary" style="width: 100%;">Pay with Orange Money</button>
                    </form>
                    <p style="color: var(--muted); font-size: 0.75rem; margin-top: 12px;">
                        Live: you are sent to Orange → pay → we credit your wallet when Orange confirms.<br>
                        Demo (no keys): wallet is credited immediately so you can test Pay for care.
                    </p>
                </div>

                <div class="wallet-card">
                    <h3>Payment Summary</h3>
                    <?php if ($payment_stats && ($payment_stats['total_payments'] ?? 0) > 0): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div style="background: var(--light); padding: 12px; border-radius: var(--radius-md); text-align: center;">
                                <span style="font-size: 1.2rem; font-weight: 700; color: var(--primary); display: block;"><?php echo (int)$payment_stats['total_payments']; ?></span>
                                <span style="color: var(--muted); font-size: 0.8rem;">Payments</span>
                            </div>
                            <div style="background: var(--light); padding: 12px; border-radius: var(--radius-md); text-align: center;">
                                <span style="font-size: 1.2rem; font-weight: 700; color: var(--primary); display: block;">SLL <?php echo number_format($payment_stats['total_amount'] ?? 0, 0); ?></span>
                                <span style="color: var(--muted); font-size: 0.8rem;">Total value</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--muted);">No consultation payments yet.</p>
                    <?php endif; ?>
                    <div style="margin-top: 16px;">
                        <a href="pages/pay.php" class="btn-primary" style="width: 100%; text-align: center; display: block;">Pay for care</a>
                    </div>
                </div>
            </div>

            <div class="wallet-card" style="margin-top: 24px;">
                <h3>Recent Transactions</h3>
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $tx): ?>
                        <div class="transaction-item">
                            <div class="tx-info">
                                <div>
                                    <span class="tx-type <?php echo htmlspecialchars($tx['type']); ?>"><?php echo ucfirst($tx['type']); ?></span>
                                    <span style="font-size: 0.85rem; margin-left: 8px;"><?php echo htmlspecialchars($tx['description']); ?></span>
                                </div>
                                <div class="tx-date"><?php echo date('M d, Y h:i A', strtotime($tx['created_at'])); ?></div>
                            </div>
                            <div class="tx-amount <?php echo in_array($tx['type'], ['deposit','refund'], true) ? 'positive' : 'negative'; ?>">
                                <?php echo in_array($tx['type'], ['deposit','refund'], true) ? '+' : '-'; ?>
                                SLL <?php echo number_format($tx['amount'], 0); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--muted);">No transactions yet.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</main>

<script src="js/main.js"></script>
<script>
(function(){
  var form = document.getElementById('orangeForm');
  var btn = document.getElementById('orangeBtn');
  var msg = document.getElementById('jsMsg');
  if (!form) return;
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    var amount = parseFloat(document.getElementById('amount').value || '0');
    var phone = (document.getElementById('phone').value || '').trim();
    if (amount < 1000) { alert('Minimum is 1,000 SLL'); return; }
    if (phone.length < 8) { alert('Enter a valid Orange Money number'); return; }
    btn.disabled = true;
    btn.textContent = 'Connecting to Orange…';
    try {
      var res = await fetch('/api/orange-pay.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ amount: amount, phone: phone })
      });
      var data = await res.json();
      if (!data.ok) {
        msg.style.display = 'block';
        msg.className = 'form-message error';
        msg.textContent = data.error || 'Payment failed';
        btn.disabled = false;
        btn.textContent = 'Pay with Orange Money';
        return;
      }
      if (data.payment_url) {
        window.location.href = data.payment_url;
        return;
      }
      // Demo mode
      window.location.href = data.redirect || ('/wallet.php?orange=demo&ref=' + encodeURIComponent(data.reference || ''));
    } catch (err) {
      msg.style.display = 'block';
      msg.className = 'form-message error';
      msg.textContent = 'Network error. Try again.';
      btn.disabled = false;
      btn.textContent = 'Pay with Orange Money';
    }
  });
})();
</script>
</body>
</html>
