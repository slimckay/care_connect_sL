<?php
/**
 * Provider Payment Settings - Care Connect SL
 * Platform commission is FIXED:
 *   Doctors   → 15%
 *   Hospitals → 20%
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = strtolower($_SESSION['role'] ?? '');

require_once 'db.php';
require_once 'payment_helper.php';

try {
    $roleStmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ? LIMIT 1");
    $roleStmt->execute([$user_id]);
    $userRow = $roleStmt->fetch() ?: [];
    if ($user_role === '') {
        $user_role = strtolower($userRow['role'] ?? '');
    }
} catch (Exception $e) {
    $userRow = [];
}

if ($user_role !== 'doctor' && $user_role !== 'hospital') {
    header('Location: index.html');
    exit;
}

// Fixed platform rates — not editable by provider
$fixedRate = platformCommissionRate($user_role); // 15 doctor / 20 hospital
$roleLabel = $user_role === 'hospital' ? 'Hospital / Clinic' : 'Doctor';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $mobile_money_provider = sanitizeInput($_POST['mobile_money_provider'] ?? '');
    $mobile_money_number = sanitizeInput($_POST['mobile_money_number'] ?? '');
    $bank_name = sanitizeInput($_POST['bank_name'] ?? '');
    $bank_account = sanitizeInput($_POST['bank_account'] ?? '');
    $account_name = sanitizeInput($_POST['account_name'] ?? '');
    $payout_method = sanitizeInput($_POST['payout_method'] ?? 'mobile_money');

    // Always force fixed commission — ignore any posted rate
    $commission_rate = $fixedRate;

    if ($consultation_fee < 0) {
        $error = 'Consultation fee cannot be negative.';
    } else {
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

            $bankCombined = trim($bank_name . ($bank_account !== '' ? ' - ' . $bank_account : '') . ($account_name !== '' ? ' (' . $account_name . ')' : ''));
            if ($mobile_money_provider !== '' && $mobile_money_number !== '') {
                $mmDisplay = $mobile_money_provider . ': ' . $mobile_money_number;
            } else {
                $mmDisplay = $mobile_money_number;
            }

            $stmt->execute([
                $user_id,
                $consultation_fee,
                $commission_rate,
                $mmDisplay,
                $bankCombined !== '' ? $bankCombined : null
            ]);

            try {
                $conn->prepare("UPDATE provider_profiles SET consultation_fee = ?, updated_at = NOW() WHERE user_id = ?")
                     ->execute([$consultation_fee, $user_id]);
            } catch (Exception $e) {}

            $message = 'Payment settings saved. Platform commission remains fixed at ' . (int)$commission_rate . '%.';
        } catch (PDOException $e) {
            error_log('Payment settings error: ' . $e->getMessage());
            $error = 'Failed to save settings. Please try again.';
        }
    }
}

try {
    $stmt = $conn->prepare("SELECT * FROM provider_payment_settings WHERE provider_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch() ?: [];
} catch (PDOException $e) {
    $settings = [];
}

$fee = (float)($settings['consultation_fee'] ?? 0);
$rate = $fixedRate; // always display fixed rate
$mmRaw = $settings['mobile_money_number'] ?? '';
$bankRaw = $settings['bank_account'] ?? '';

// Keep DB in sync with fixed rate if an old custom rate was stored
if (!empty($settings) && (float)($settings['commission_rate'] ?? 0) !== $fixedRate) {
    try {
        $conn->prepare("UPDATE provider_payment_settings SET commission_rate = ?, updated_at = NOW() WHERE provider_id = ?")
             ->execute([$fixedRate, $user_id]);
    } catch (Exception $e) {}
}

$mmProvider = 'Orange Money';
$mmNumber = $mmRaw;
if (strpos($mmRaw, ':') !== false) {
    [$mmProvider, $mmNumber] = array_map('trim', explode(':', $mmRaw, 2));
}

$bankName = '';
$bankAccount = $bankRaw;
$accountName = '';
if (preg_match('/^(.*?)\s*-\s*(.*?)(?:\s*\((.*)\))?$/', $bankRaw, $m)) {
    $bankName = trim($m[1] ?? '');
    $bankAccount = trim($m[2] ?? '');
    $accountName = trim($m[3] ?? '');
}

$userName = $userRow['name'] ?? ($_SESSION['user_name'] ?? 'Provider');
$split = platformCommissionAmount($fee, $user_role);
$platformCut = $split['platform_fee'];
$youReceive = $split['provider_earnings'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Settings — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    body { background: #F4F7FB; }
    .pay-wrap { max-width: 980px; margin: 28px auto 48px; padding: 0 16px; }
    .pay-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; }
    .pay-header h1 { margin:0 0 6px; font-size:1.75rem; color:#0F1C3A !important; }
    .pay-header p { margin:0; color:#64748B !important; max-width:520px; line-height:1.5; }
    .back-link { display:inline-flex; align-items:center; gap:6px; color:#1EB53A !important; font-weight:600; text-decoration:none; font-size:0.92rem; margin-bottom:10px; }
    .alert { display:flex; gap:10px; align-items:flex-start; padding:14px 16px; border-radius:12px; margin-bottom:18px; border:1px solid transparent; font-size:0.95rem; }
    .alert.success { background:#ECFDF5; border-color:#A7F3D0; color:#065F46; }
    .alert.error { background:#FEF2F2; border-color:#FECACA; color:#991B1B; }
    .pay-grid { display:grid; grid-template-columns:1.35fr 0.85fr; gap:18px; align-items:start; }
    .card { background:#fff; border:1px solid #E5E7EB; border-radius:18px; box-shadow:0 8px 24px rgba(15,23,42,0.05); overflow:hidden; }
    .card-head { padding:18px 22px; border-bottom:1px solid #F1F5F9; background:linear-gradient(180deg,#FAFCFF 0%,#FFF 100%); }
    .card-head h2 { margin:0 0 4px; font-size:1.05rem; color:#0F1C3A !important; }
    .card-head p { margin:0; color:#64748B !important; font-size:0.9rem; }
    .card-body { padding:22px; }
    .section-label { font-size:0.78rem; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:#94A3B8 !important; margin:4px 0 12px; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-weight:600; font-size:0.9rem; margin-bottom:7px; color:#1E293B !important; }
    .form-group input, .form-group select { width:100%; padding:12px 14px; border:1.5px solid #E2E8F0; border-radius:12px; font:inherit; color:#0F172A; background:#fff; }
    .form-group input:focus, .form-group select:focus { outline:none; border-color:#1EB53A; box-shadow:0 0 0 3px rgba(30,181,58,0.12); }
    .form-group .hint { margin-top:6px; font-size:0.82rem; color:#64748B !important; }
    .divider { height:1px; background:#F1F5F9; margin:8px 0 18px; }
    .method-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:16px; }
    .method-option { position:relative; }
    .method-option input { position:absolute; opacity:0; pointer-events:none; }
    .method-option label { display:flex; flex-direction:column; gap:4px; padding:14px; border:1.5px solid #E2E8F0; border-radius:14px; cursor:pointer; background:#fff; }
    .method-option input:checked + label { border-color:#1EB53A; background:#F0FDF4; box-shadow:0 0 0 3px rgba(30,181,58,0.1); }
    .method-option .m-title { font-weight:700; color:#0F172A !important; font-size:0.95rem; }
    .method-option .m-sub { font-size:0.8rem; color:#64748B !important; }
    .btn-save { width:100%; border:none; border-radius:12px; padding:14px 18px; font-weight:700; font-size:1rem; color:#fff; cursor:pointer; background:linear-gradient(135deg,#1EB53A,#15803D); }
    .fixed-rate-box {
      display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
      padding:14px 16px; border-radius:14px; border:1.5px solid #BBF7D0; background:#F0FDF4; margin-bottom:16px;
    }
    .fixed-rate-box strong { color:#14532D !important; font-size:1.05rem; }
    .fixed-rate-box span { color:#166534 !important; font-size:0.9rem; }
    .badge-fixed {
      background:#14532D; color:#fff !important; font-size:0.75rem; font-weight:700;
      padding:4px 10px; border-radius:999px; letter-spacing:0.03em;
    }
    .summary-hero { padding:22px; background:linear-gradient(145deg,#0A1628 0%,#12253f 55%,#14532D 100%); color:#fff; }
    .summary-hero .label { font-size:0.82rem; opacity:0.8; margin-bottom:6px; }
    .summary-hero .amount { font-size:1.9rem; font-weight:700; }
    .summary-hero .amount span { font-size:0.95rem; font-weight:500; opacity:0.85; margin-left:4px; }
    .summary-row { display:flex; justify-content:space-between; gap:12px; padding:14px 22px; border-bottom:1px solid #F1F5F9; font-size:0.92rem; }
    .summary-row:last-child { border-bottom:none; }
    .summary-row .k { color:#64748B !important; }
    .summary-row .v { color:#0F172A !important; font-weight:600; }
    .summary-row.highlight .v { color:#15803D !important; }
    .summary-note { padding:14px 22px 20px; font-size:0.82rem; color:#64748B !important; line-height:1.45; }
    .info-list { margin:0; padding:0; list-style:none; }
    .info-list li { display:flex; gap:10px; padding:10px 0; border-bottom:1px solid #F1F5F9; font-size:0.9rem; color:#334155 !important; line-height:1.45; }
    .info-list li:last-child { border-bottom:none; }
    @media (max-width:900px){ .pay-grid,.form-row,.method-grid{grid-template-columns:1fr;} .pay-header{flex-direction:column;} }
  </style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <button onclick="toggleDarkMode()" class="dark-toggle" type="button">🌓</button>
      <a href="dashboard/provider-dashboard.php" class="btn-ghost">Dashboard</a>
      <a href="logout.php" class="btn-ghost btn-logout">Log out</a>
    </div>
  </div>
</header>

<main class="pay-wrap">
  <a class="back-link" href="dashboard/provider-dashboard.php">← Back to Provider Dashboard</a>
  <div class="pay-header">
    <div>
      <h1>Payment Settings</h1>
      <p>Set your consultation fee and payout details. Platform commission is fixed by account type.</p>
    </div>
  </div>

  <?php if ($message): ?><div class="alert success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="pay-grid">
    <div class="card">
      <div class="card-head">
        <h2>Fee & Payout Details</h2>
        <p>Logged in as <strong><?= htmlspecialchars($roleLabel) ?></strong></p>
      </div>
      <div class="card-body">
        <div class="fixed-rate-box">
          <div>
            <strong>Platform commission: <?= (int)$fixedRate ?>%</strong><br>
            <span>
              <?= $user_role === 'hospital'
                ? 'Hospitals & clinics — fixed 20% platform fee on each paid consultation.'
                : 'Doctors — fixed 15% platform fee on each paid consultation.' ?>
            </span>
          </div>
          <span class="badge-fixed">FIXED</span>
        </div>

        <form method="POST" id="paymentForm">
          <div class="section-label">Consultation pricing</div>
          <div class="form-group">
            <label for="consultation_fee">Consultation fee (SLL)</label>
            <input type="number" id="consultation_fee" name="consultation_fee"
                   value="<?= htmlspecialchars((string)$fee) ?>" min="0" step="1000" required>
            <div class="hint">Patient pays this amount. Care Connect keeps <?= (int)$fixedRate ?>%, you receive the rest.</div>
          </div>

          <div class="divider"></div>
          <div class="section-label">Preferred payout method</div>
          <div class="method-grid">
            <div class="method-option">
              <input type="radio" name="payout_method" id="method_mm" value="mobile_money" checked>
              <label for="method_mm"><span class="m-title">📱 Mobile Money</span><span class="m-sub">Orange / Africell</span></label>
            </div>
            <div class="method-option">
              <input type="radio" name="payout_method" id="method_bank" value="bank">
              <label for="method_bank"><span class="m-title">🏦 Bank Transfer</span><span class="m-sub">Optional backup</span></label>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="mobile_money_provider">Mobile money provider</label>
              <select id="mobile_money_provider" name="mobile_money_provider">
                <option value="Orange Money" <?= $mmProvider === 'Orange Money' ? 'selected' : '' ?>>Orange Money</option>
                <option value="Africell Money" <?= $mmProvider === 'Africell Money' ? 'selected' : '' ?>>Africell Money</option>
                <option value="QMoney" <?= $mmProvider === 'QMoney' ? 'selected' : '' ?>>QMoney</option>
              </select>
            </div>
            <div class="form-group">
              <label for="mobile_money_number">Mobile money number</label>
              <input type="text" id="mobile_money_number" name="mobile_money_number" value="<?= htmlspecialchars($mmNumber) ?>" placeholder="+232 76 000 000">
            </div>
          </div>

          <div class="divider"></div>
          <div class="section-label">Bank details (optional)</div>
          <div class="form-row">
            <div class="form-group">
              <label for="bank_name">Bank name</label>
              <input type="text" id="bank_name" name="bank_name" value="<?= htmlspecialchars($bankName) ?>" placeholder="e.g. Rokel Commercial Bank">
            </div>
            <div class="form-group">
              <label for="account_name">Account name</label>
              <input type="text" id="account_name" name="account_name" value="<?= htmlspecialchars($accountName) ?>" placeholder="Name on account">
            </div>
          </div>
          <div class="form-group">
            <label for="bank_account">Account number</label>
            <input type="text" id="bank_account" name="bank_account" value="<?= htmlspecialchars($bankAccount) ?>" placeholder="Account number">
          </div>

          <button type="submit" class="btn-save" id="saveBtn">Save Payment Settings</button>
        </form>
      </div>
    </div>

    <div>
      <div class="card" style="margin-bottom:18px;">
        <div class="summary-hero">
          <div class="label">You receive per consultation</div>
          <div class="amount" id="receivePreview"><?= number_format($youReceive, 0) ?><span>SLL</span></div>
        </div>
        <div class="summary-row"><span class="k">Consultation fee</span><span class="v" id="feePreview"><?= number_format($fee, 0) ?> SLL</span></div>
        <div class="summary-row"><span class="k">Platform (<?= (int)$fixedRate ?>% fixed)</span><span class="v" id="cutPreview">− <?= number_format($platformCut, 0) ?> SLL</span></div>
        <div class="summary-row highlight"><span class="k">Net to you</span><span class="v" id="netPreview"><?= number_format($youReceive, 0) ?> SLL</span></div>
        <div class="summary-note">Commission is fixed by Care Connect and cannot be changed by providers.</div>
      </div>

      <div class="card">
        <div class="card-head"><h2>Commission policy</h2><p>Same rates for every payout</p></div>
        <div class="card-body" style="padding-top:8px;">
          <ul class="info-list">
            <li>👨‍⚕️ <strong>Doctors:</strong> 15% platform commission</li>
            <li>🏥 <strong>Hospitals / clinics:</strong> 20% platform commission</li>
            <li>💚 Applied only on completed paid consultations</li>
            <li>🔐 Your payout details stay private</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="js/dark-mode.js"></script>
<script>
  const FIXED_RATE = <?= json_encode((float)$fixedRate) ?>;
  function formatSLL(n) { return Math.max(0, Math.round(n)).toLocaleString('en-US'); }
  function updatePreview() {
    const fee = parseFloat(document.getElementById('consultation_fee').value) || 0;
    const cut = fee * (FIXED_RATE / 100);
    const net = Math.max(0, fee - cut);
    document.getElementById('feePreview').textContent = formatSLL(fee) + ' SLL';
    document.getElementById('cutPreview').textContent = '− ' + formatSLL(cut) + ' SLL';
    document.getElementById('netPreview').textContent = formatSLL(net) + ' SLL';
    document.getElementById('receivePreview').innerHTML = formatSLL(net) + '<span>SLL</span>';
  }
  document.getElementById('consultation_fee').addEventListener('input', updatePreview);
  document.getElementById('paymentForm').addEventListener('submit', function () {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
  });
</script>
</body>
</html>
