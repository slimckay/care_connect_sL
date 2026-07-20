<?php
/**
 * Patient payment path — Care Connect SL
 * Doctor 15% platform fee · Hospital 20% platform fee
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: ../login.php?redirect=' . urlencode('/pages/pay.php'));
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../payment_helper.php';

$userId = (int)$_SESSION['user_id'];
$payment = new PaymentSystem($conn);
$balance = $payment->getBalance($userId);

$providerId = (int)($_GET['provider'] ?? $_POST['provider_id'] ?? 0);
$referralId = (int)($_GET['referral'] ?? $_POST['referral_id'] ?? 0);

$message = '';
$error = '';
$result = null;

$providers = [];
try {
    $providers = $conn->query("
        SELECT u.id, u.name, u.role, p.specialty, p.consultation_fee
        FROM users u
        LEFT JOIN provider_profiles p ON p.user_id = u.id
        WHERE u.role IN ('doctor','hospital')
        ORDER BY u.name ASC LIMIT 80
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $providers = [];
}

$myReferrals = [];
try {
    $st = $conn->prepare("
        SELECT r.id, r.status, r.assigned_to, u.name AS provider_name
        FROM referrals r
        LEFT JOIN users u ON u.id = r.assigned_to
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC LIMIT 30
    ");
    $st->execute([$userId]);
    $myReferrals = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $providerId = (int)($_POST['provider_id'] ?? 0);
    $referralId = (int)($_POST['referral_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);

    if ($providerId <= 0) {
        $error = 'Choose a doctor or clinic to pay.';
    } elseif ($amount < 1000) {
        $error = 'Minimum payment is 1,000 SLL.';
    } else {
        $result = $payment->processReferralPayment(
            $referralId > 0 ? $referralId : null,
            $userId,
            $providerId,
            $amount
        );
        if (!empty($result['success'])) {
            try {
                $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                                VALUES (?, 'payment', 'Payment received', ?, 'wallet.php', 0, NOW())")
                     ->execute([
                         $providerId,
                         'You received SLL ' . number_format($result['provider_earnings'], 0) .
                         ' (platform fee ' . (int)$result['commission_rate'] . '% = SLL ' .
                         number_format($result['platform_fee'], 0) . ').'
                     ]);
            } catch (Exception $e) {}
            $message = 'Payment successful. Provider got their share; Care Connect kept the platform fee.';
            $balance = $payment->getBalance($userId);
        } else {
            $error = $result['error'] ?? 'Payment failed.';
        }
    }
}

$previewFee = 50000.0;
$previewSplit = platformCommissionAmount($previewFee, 'doctor');
foreach ($providers as $p) {
    if ((int)$p['id'] === $providerId) {
        $previewFee = ($p['consultation_fee'] !== null && $p['consultation_fee'] !== '')
            ? (float)$p['consultation_fee'] : 50000.0;
        $previewSplit = platformCommissionAmount($previewFee, $p['role'] ?? 'doctor');
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pay for care — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/style.css">
  <style>
    body{background:#F4F7FB}
    .wrap{max-width:720px;margin:28px auto 48px;padding:0 16px}
    .card{background:#fff;border:1px solid #E5E7EB;border-radius:18px;padding:24px;box-shadow:0 8px 24px rgba(15,23,42,.05)}
    h1{margin:0 0 6px;font-size:1.5rem;color:#0F1C3A!important}
    .sub{color:#64748B;margin:0 0 18px;line-height:1.5}
    .bal{background:#0F1C3A;color:#fff;border-radius:14px;padding:14px 16px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .bal strong{font-size:1.25rem}
    .form-group{margin-bottom:14px}
    .form-group label{display:block;font-weight:600;font-size:.9rem;margin-bottom:6px;color:#1E293B}
    .form-group select,.form-group input{width:100%;padding:12px 14px;border:1.5px solid #E2E8F0;border-radius:12px;font:inherit}
    .split{background:#F8FAFC;border:1px solid #E5E7EB;border-radius:14px;padding:14px;margin:14px 0}
    .split-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F1F5F9;font-size:.92rem}
    .split-row:last-child{border-bottom:none;font-weight:700;color:#15803D}
    .btn-pay{width:100%;border:none;border-radius:12px;padding:14px;font-weight:700;font-size:1rem;background:linear-gradient(135deg,#1EB53A,#15803D);color:#fff;cursor:pointer}
    .alert{padding:12px 14px;border-radius:12px;margin-bottom:14px;font-size:.95rem}
    .alert.ok{background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0}
    .alert.err{background:#FEF2F2;color:#991B1B;border:1px solid #FECACA}
    .hint{font-size:.82rem;color:#64748B;margin-top:6px}
    .links{margin-top:14px;display:flex;gap:12px;flex-wrap:wrap}
    .links a{color:#1EB53A;font-weight:600;text-decoration:none}
  </style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="/" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <a href="/dashboard/track-referrals.php" class="btn-ghost">Track</a>
      <a href="/wallet.php" class="btn-ghost">Wallet</a>
      <a href="/logout.php" class="btn-ghost btn-logout">Log out</a>
    </div>
  </div>
</header>
<main class="wrap">
  <div class="card">
    <h1>Pay for care</h1>
    <p class="sub">Pay your doctor or clinic. Care Connect keeps a fixed platform fee; the rest goes to the provider. Medical details stay private.</p>

    <div class="bal">
      <div>
        <div style="opacity:.8;font-size:.85rem">Your wallet balance</div>
        <strong>SLL <?= number_format($balance, 0) ?></strong>
      </div>
      <a href="/wallet.php" style="color:#86EFAC;font-weight:700;text-decoration:none">+ Add funds</a>
    </div>

    <?php if ($message): ?><div class="alert ok">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($result && !empty($result['success'])): ?>
      <div class="split">
        <div class="split-row"><span>You paid</span><span>SLL <?= number_format($result['platform_fee'] + $result['provider_earnings'], 0) ?></span></div>
        <div class="split-row"><span>Provider received</span><span>SLL <?= number_format($result['provider_earnings'], 0) ?></span></div>
        <div class="split-row"><span>Care Connect fee (<?= (int)$result['commission_rate'] ?>%)</span><span>SLL <?= number_format($result['platform_fee'], 0) ?></span></div>
      </div>
      <div class="links">
        <a href="/dashboard/track-referrals.php">Track referrals</a>
        <a href="/wallet.php">View wallet</a>
        <a href="/pages/pay.php">Pay again</a>
      </div>
    <?php else: ?>
    <form method="POST">
      <div class="form-group">
        <label for="provider_id">Doctor / clinic *</label>
        <select name="provider_id" id="provider_id" required>
          <option value="">Select provider...</option>
          <?php foreach ($providers as $p):
            $fee = ($p['consultation_fee'] !== null && $p['consultation_fee'] !== '') ? (float)$p['consultation_fee'] : 50000;
            $rate = platformCommissionRate($p['role'] ?? 'doctor');
          ?>
            <option value="<?= (int)$p['id'] ?>"
              data-fee="<?= (int)$fee ?>"
              data-rate="<?= (int)$rate ?>"
              <?= $providerId === (int)$p['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['name']) ?><?= !empty($p['specialty']) ? ' — ' . htmlspecialchars($p['specialty']) : '' ?>
              (<?= (int)$rate ?>% platform)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="referral_id">Link to referral (optional)</label>
        <select name="referral_id" id="referral_id">
          <option value="0">No linked referral</option>
          <?php foreach ($myReferrals as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= $referralId === (int)$r['id'] ? 'selected' : '' ?>>
              #<?= (int)$r['id'] ?> · <?= htmlspecialchars($r['provider_name'] ?: 'Unassigned') ?> · <?= htmlspecialchars($r['status']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="hint">This screen only shows money amounts — not medical conditions. Case details stay with doctors.</div>
      </div>

      <div class="form-group">
        <label for="amount">Amount (SLL) *</label>
        <input type="number" name="amount" id="amount" min="1000" step="1000" value="<?= (int)$previewFee ?>" required>
      </div>

      <div class="split">
        <div class="split-row"><span>Consultation fee</span><span id="rowFee">SLL <?= number_format($previewFee, 0) ?></span></div>
        <div class="split-row"><span>Platform fee (<span id="rowRate"><?= (int)$previewSplit['rate'] ?></span>%)</span><span id="rowPlat">SLL <?= number_format($previewSplit['platform_fee'], 0) ?></span></div>
        <div class="split-row"><span>Provider receives</span><span id="rowNet">SLL <?= number_format($previewSplit['provider_earnings'], 0) ?></span></div>
      </div>

      <button type="submit" class="btn-pay">Pay now</button>
      <p class="hint" style="margin-top:12px">Doctors: 15% platform · Hospitals: 20% platform. Paid from your Care Connect wallet.</p>
    </form>
    <?php endif; ?>
  </div>
</main>
<script>
function fmt(n){return Math.max(0,Math.round(n)).toLocaleString('en-US')}
function updateSplit(){
  var sel=document.getElementById('provider_id'); if(!sel) return;
  var opt=sel.options[sel.selectedIndex];
  var amount=parseFloat(document.getElementById('amount').value)||0;
  var rate=parseFloat(opt&&opt.dataset?opt.dataset.rate:15)||15;
  var plat=amount*(rate/100);
  document.getElementById('rowFee').textContent='SLL '+fmt(amount);
  document.getElementById('rowRate').textContent=String(rate);
  document.getElementById('rowPlat').textContent='SLL '+fmt(plat);
  document.getElementById('rowNet').textContent='SLL '+fmt(Math.max(0,amount-plat));
}
var p=document.getElementById('provider_id');
if(p)p.addEventListener('change',function(){
  var o=this.options[this.selectedIndex];
  if(o&&o.dataset&&o.dataset.fee) document.getElementById('amount').value=o.dataset.fee;
  updateSplit();
});
var a=document.getElementById('amount'); if(a)a.addEventListener('input',updateSplit);
updateSplit();
</script>
<script src="/js/dark-mode.js"></script>
</body>
</html>
