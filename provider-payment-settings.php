<?php
/**
 * Provider Payment Settings - Care Connect SL
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Allow doctor/hospital; also fall back to DB role if session role missing
require_once 'db.php';

try {
    $roleStmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ? LIMIT 1");
    $roleStmt->execute([$user_id]);
    $userRow = $roleStmt->fetch() ?: [];
    if ($user_role === '') {
        $user_role = $userRow['role'] ?? '';
    }
} catch (Exception $e) {
    $userRow = [];
}

if ($user_role !== 'doctor' && $user_role !== 'hospital') {
    header('Location: index.html');
    exit;
}

if (file_exists(__DIR__ . '/payment_helper.php')) {
    require_once 'payment_helper.php';
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $commission_rate = (float)($_POST['commission_rate'] ?? 5);
    $mobile_money_provider = sanitizeInput($_POST['mobile_money_provider'] ?? '');
    $mobile_money_number = sanitizeInput($_POST['mobile_money_number'] ?? '');
    $bank_name = sanitizeInput($_POST['bank_name'] ?? '');
    $bank_account = sanitizeInput($_POST['bank_account'] ?? '');
    $account_name = sanitizeInput($_POST['account_name'] ?? '');
    $payout_method = sanitizeInput($_POST['payout_method'] ?? 'mobile_money');

    if ($consultation_fee < 0) {
        $error = 'Consultation fee cannot be negative.';
    } elseif ($commission_rate < 0 || $commission_rate > 20) {
        $error = 'Commission rate must be between 0% and 20%.';
    } else {
        try {
            // Store provider preference fields; keep schema-compatible core columns
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

            // Also sync consultation fee onto provider profile if column exists
            try {
                $conn->prepare("UPDATE provider_profiles SET consultation_fee = ?, updated_at = NOW() WHERE user_id = ?")
                     ->execute([$consultation_fee, $user_id]);
            } catch (Exception $e) {
                // optional
            }

            $message = 'Payment settings saved successfully.';
        } catch (PDOException $e) {
            error_log('Payment settings error: ' . $e->getMessage());
            $error = 'Failed to save settings. Please try again.';
        }
    }
}

// Load current settings
try {
    $stmt = $conn->prepare("SELECT * FROM provider_payment_settings WHERE provider_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch() ?: [];
} catch (PDOException $e) {
    $settings = [];
}

$fee = (float)($settings['consultation_fee'] ?? 0);
$rate = (float)($settings['commission_rate'] ?? 5);
$mmRaw = $settings['mobile_money_number'] ?? '';
$bankRaw = $settings['bank_account'] ?? '';

// Parse stored "Provider: number" if present
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
$platformCut = $fee * ($rate / 100);
$youReceive = max(0, $fee - $platformCut);
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

    .pay-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 22px;
    }
    .pay-header h1 {
      margin: 0 0 6px;
      font-size: 1.75rem;
      color: #0F1C3A !important;
    }
    .pay-header p {
      margin: 0;
      color: #64748B !important;
      max-width: 520px;
      line-height: 1.5;
    }
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #1EB53A !important;
      font-weight: 600;
      text-decoration: none;
      font-size: 0.92rem;
      margin-bottom: 10px;
    }

    .alert {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      padding: 14px 16px;
      border-radius: 12px;
      margin-bottom: 18px;
      border: 1px solid transparent;
      font-size: 0.95rem;
    }
    .alert.success { background: #ECFDF5; border-color: #A7F3D0; color: #065F46; }
    .alert.error { background: #FEF2F2; border-color: #FECACA; color: #991B1B; }

    .pay-grid {
      display: grid;
      grid-template-columns: 1.35fr 0.85fr;
      gap: 18px;
      align-items: start;
    }

    .card {
      background: #fff;
      border: 1px solid #E5E7EB;
      border-radius: 18px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
      overflow: hidden;
    }
    .card-head {
      padding: 18px 22px;
      border-bottom: 1px solid #F1F5F9;
      background: linear-gradient(180deg, #FAFCFF 0%, #FFFFFF 100%);
    }
    .card-head h2 {
      margin: 0 0 4px;
      font-size: 1.05rem;
      color: #0F1C3A !important;
    }
    .card-head p {
      margin: 0;
      color: #64748B !important;
      font-size: 0.9rem;
    }
    .card-body { padding: 22px; }

    .section-label {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #94A3B8 !important;
      margin: 4px 0 12px;
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }
    .form-group { margin-bottom: 16px; }
    .form-group label {
      display: block;
      font-weight: 600;
      font-size: 0.9rem;
      margin-bottom: 7px;
      color: #1E293B !important;
    }
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid #E2E8F0;
      border-radius: 12px;
      font: inherit;
      color: #0F172A;
      background: #fff;
      transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #1EB53A;
      box-shadow: 0 0 0 3px rgba(30, 181, 58, 0.12);
    }
    .form-group .hint {
      margin-top: 6px;
      font-size: 0.82rem;
      color: #64748B !important;
    }

    .divider {
      height: 1px;
      background: #F1F5F9;
      margin: 8px 0 18px;
    }

    .method-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 16px;
    }
    .method-option {
      position: relative;
    }
    .method-option input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }
    .method-option label {
      display: flex;
      flex-direction: column;
      gap: 4px;
      padding: 14px;
      border: 1.5px solid #E2E8F0;
      border-radius: 14px;
      cursor: pointer;
      transition: all 0.15s;
      background: #fff;
    }
    .method-option input:checked + label {
      border-color: #1EB53A;
      background: #F0FDF4;
      box-shadow: 0 0 0 3px rgba(30, 181, 58, 0.1);
    }
    .method-option .m-title {
      font-weight: 700;
      color: #0F172A !important;
      font-size: 0.95rem;
    }
    .method-option .m-sub {
      font-size: 0.8rem;
      color: #64748B !important;
    }

    .btn-save {
      width: 100%;
      border: none;
      border-radius: 12px;
      padding: 14px 18px;
      font-weight: 700;
      font-size: 1rem;
      color: #fff;
      cursor: pointer;
      background: linear-gradient(135deg, #1EB53A, #15803D);
      box-shadow: 0 8px 18px rgba(21, 128, 61, 0.25);
    }
    .btn-save:hover { filter: brightness(1.05); }
    .btn-save:disabled { opacity: 0.7; cursor: not-allowed; }

    /* Summary card */
    .summary-card .card-body { padding: 0; }
    .summary-hero {
      padding: 22px;
      background: linear-gradient(145deg, #0A1628 0%, #12253f 55%, #14532D 100%);
      color: #fff;
    }
    .summary-hero .label {
      font-size: 0.82rem;
      opacity: 0.8;
      margin-bottom: 6px;
    }
    .summary-hero .amount {
      font-size: 1.9rem;
      font-weight: 700;
      letter-spacing: -0.02em;
    }
    .summary-hero .amount span {
      font-size: 0.95rem;
      font-weight: 500;
      opacity: 0.85;
      margin-left: 4px;
    }
    .summary-rows { padding: 8px 0; }
    .summary-row {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 22px;
      border-bottom: 1px solid #F1F5F9;
      font-size: 0.92rem;
    }
    .summary-row:last-child { border-bottom: none; }
    .summary-row .k { color: #64748B !important; }
    .summary-row .v { color: #0F172A !important; font-weight: 600; }
    .summary-row.highlight .v { color: #15803D !important; }
    .summary-note {
      padding: 14px 22px 20px;
      font-size: 0.82rem;
      color: #64748B !important;
      line-height: 1.45;
    }

    .info-list {
      margin: 0;
      padding: 0;
      list-style: none;
    }
    .info-list li {
      display: flex;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid #F1F5F9;
      font-size: 0.9rem;
      color: #334155 !important;
      line-height: 1.45;
    }
    .info-list li:last-child { border-bottom: none; }

    [data-theme="dark"] body { background: #0f172a; }
    [data-theme="dark"] .pay-header h1,
    [data-theme="dark"] .card-head h2 { color: #F8FAFC !important; }
    [data-theme="dark"] .pay-header p,
    [data-theme="dark"] .card-head p,
    [data-theme="dark"] .form-group .hint,
    [data-theme="dark"] .summary-row .k,
    [data-theme="dark"] .summary-note { color: #94A3B8 !important; }
    [data-theme="dark"] .card { background: #1e293b; border-color: #334155; }
    [data-theme="dark"] .card-head { background: #1e293b; border-color: #334155; }
    [data-theme="dark"] .form-group label,
    [data-theme="dark"] .summary-row .v,
    [data-theme="dark"] .method-option .m-title { color: #E2E8F0 !important; }
    [data-theme="dark"] .form-group input,
    [data-theme="dark"] .form-group select,
    [data-theme="dark"] .method-option label {
      background: #0f172a;
      border-color: #334155;
      color: #E2E8F0;
    }
    [data-theme="dark"] .method-option input:checked + label {
      background: #052e16;
      border-color: #16A34A;
    }
    [data-theme="dark"] .divider,
    [data-theme="dark"] .summary-row,
    [data-theme="dark"] .info-list li { border-color: #334155; }

    @media (max-width: 900px) {
      .pay-grid, .form-row, .method-grid { grid-template-columns: 1fr; }
      .pay-header { flex-direction: column; }
    }
  </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <button onclick="toggleDarkMode()" class="dark-toggle" title="Toggle dark mode">🌓</button>
      <a href="dashboard/provider-dashboard.php" class="btn-ghost">Dashboard</a>
      <a href="logout.php" class="btn-ghost">Logout</a>
    </div>
  </div>
</header>

<main class="pay-wrap">
  <a class="back-link" href="dashboard/provider-dashboard.php">← Back to Provider Dashboard</a>

  <div class="pay-header">
    <div>
      <h1>Payment Settings</h1>
      <p>Configure your consultation fee and how you receive payouts from Care Connect referrals.</p>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert success">✅ <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="pay-grid">
    <div class="card">
      <div class="card-head">
        <h2>Fee & Payout Details</h2>
        <p>These details are used when patients pay through Care Connect.</p>
      </div>
      <div class="card-body">
        <form method="POST" id="paymentForm">
          <div class="section-label">Consultation pricing</div>
          <div class="form-row">
            <div class="form-group">
              <label for="consultation_fee">Consultation fee (SLL)</label>
              <input type="number" id="consultation_fee" name="consultation_fee"
                     value="<?= htmlspecialchars((string)$fee) ?>"
                     min="0" step="1000" required>
              <div class="hint">Standard fee patients will see for your service</div>
            </div>
            <div class="form-group">
              <label for="commission_rate">Platform commission (%)</label>
              <input type="number" id="commission_rate" name="commission_rate"
                     value="<?= htmlspecialchars((string)$rate) ?>"
                     min="0" max="20" step="0.5" required>
              <div class="hint">Typical range: 3% – 10%</div>
            </div>
          </div>

          <div class="divider"></div>
          <div class="section-label">Preferred payout method</div>

          <div class="method-grid">
            <div class="method-option">
              <input type="radio" name="payout_method" id="method_mm" value="mobile_money" checked>
              <label for="method_mm">
                <span class="m-title">📱 Mobile Money</span>
                <span class="m-sub">Orange / Africell</span>
              </label>
            </div>
            <div class="method-option">
              <input type="radio" name="payout_method" id="method_bank" value="bank">
              <label for="method_bank">
                <span class="m-title">🏦 Bank Transfer</span>
                <span class="m-sub">Optional backup</span>
              </label>
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
              <input type="text" id="mobile_money_number" name="mobile_money_number"
                     value="<?= htmlspecialchars($mmNumber) ?>"
                     placeholder="+232 76 000 000">
            </div>
          </div>

          <div class="divider"></div>
          <div class="section-label">Bank details (optional)</div>

          <div class="form-row">
            <div class="form-group">
              <label for="bank_name">Bank name</label>
              <input type="text" id="bank_name" name="bank_name"
                     value="<?= htmlspecialchars($bankName) ?>"
                     placeholder="e.g. Rokel Commercial Bank">
            </div>
            <div class="form-group">
              <label for="account_name">Account name</label>
              <input type="text" id="account_name" name="account_name"
                     value="<?= htmlspecialchars($accountName) ?>"
                     placeholder="Name on account">
            </div>
          </div>
          <div class="form-group">
            <label for="bank_account">Account number</label>
            <input type="text" id="bank_account" name="bank_account"
                   value="<?= htmlspecialchars($bankAccount) ?>"
                   placeholder="Account number">
          </div>

          <button type="submit" class="btn-save" id="saveBtn">Save Payment Settings</button>
        </form>
      </div>
    </div>

    <div>
      <div class="card summary-card" style="margin-bottom:18px;">
        <div class="summary-hero">
          <div class="label">You receive per consultation</div>
          <div class="amount" id="receivePreview"><?= number_format($youReceive, 0) ?><span>SLL</span></div>
        </div>
        <div class="summary-rows">
          <div class="summary-row">
            <span class="k">Consultation fee</span>
            <span class="v" id="feePreview"><?= number_format($fee, 0) ?> SLL</span>
          </div>
          <div class="summary-row">
            <span class="k">Platform commission</span>
            <span class="v" id="cutPreview">− <?= number_format($platformCut, 0) ?> SLL</span>
          </div>
          <div class="summary-row highlight">
            <span class="k">Net to you</span>
            <span class="v" id="netPreview"><?= number_format($youReceive, 0) ?> SLL</span>
          </div>
        </div>
        <div class="summary-note">
          Estimates update as you type. Final payouts may vary based on payment method fees.
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Good to know</h2>
          <p>How payments work on Care Connect</p>
        </div>
        <div class="card-body" style="padding-top:8px;">
          <ul class="info-list">
            <li>💚 Patients can pay via mobile money when booking care.</li>
            <li>📊 Commission is only applied on completed paid consultations.</li>
            <li>🔐 Your payout details stay private and are only used for transfers.</li>
            <li>⏱️ Keep your mobile money number active to avoid payout delays.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="js/dark-mode.js"></script>
<script>
  function formatSLL(n) {
    return Math.max(0, Math.round(n)).toLocaleString('en-US');
  }

  function updatePreview() {
    const fee = parseFloat(document.getElementById('consultation_fee').value) || 0;
    const rate = parseFloat(document.getElementById('commission_rate').value) || 0;
    const cut = fee * (rate / 100);
    const net = Math.max(0, fee - cut);

    document.getElementById('feePreview').textContent = formatSLL(fee) + ' SLL';
    document.getElementById('cutPreview').textContent = '− ' + formatSLL(cut) + ' SLL';
    document.getElementById('netPreview').textContent = formatSLL(net) + ' SLL';
    document.getElementById('receivePreview').innerHTML = formatSLL(net) + '<span>SLL</span>';
  }

  document.getElementById('consultation_fee').addEventListener('input', updatePreview);
  document.getElementById('commission_rate').addEventListener('input', updatePreview);

  document.getElementById('paymentForm').addEventListener('submit', function () {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
  });
</script>
</body>
</html>
