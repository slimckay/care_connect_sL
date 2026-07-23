<?php
/**
 * Doctor / hospital — payment & earnings history
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['doctor', 'hospital', 'admin'], true)) {
    header('Location: patient-dashboard.php');
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../payment_helper.php';

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Provider';
$payment = new PaymentSystem($conn);
$balance = $payment->getBalance($userId);

$earnings = [
    'count' => 0,
    'gross' => 0.0,
    'fees' => 0.0,
    'net' => 0.0,
];
$consults = [];
$walletTx = [];
$error = '';

try {
    $s = $conn->prepare("
        SELECT COUNT(*) AS c,
               COALESCE(SUM(amount),0) AS gross,
               COALESCE(SUM(platform_fee),0) AS fees,
               COALESCE(SUM(provider_earnings),0) AS net
        FROM referral_payments
        WHERE provider_id = ?
    ");
    $s->execute([$userId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $earnings['count'] = (int)$row['c'];
        $earnings['gross'] = (float)$row['gross'];
        $earnings['fees'] = (float)$row['fees'];
        $earnings['net'] = (float)$row['net'];
    }
} catch (Exception $e) {
    $error = 'Could not load earnings summary.';
}

try {
    $stmt = $conn->prepare("
        SELECT rp.*, u.name AS patient_name
        FROM referral_payments rp
        LEFT JOIN users u ON u.id = rp.patient_id
        WHERE rp.provider_id = ?
        ORDER BY COALESCE(rp.payment_date, rp.created_at) DESC
        LIMIT 80
    ");
    $stmt->execute([$userId]);
    $consults = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

try {
    $stmt = $conn->prepare("
        SELECT * FROM transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 80
    ");
    $stmt->execute([$userId]);
    $walletTx = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$unreadMessages = 0;
$pendingReferrals = 0;
try {
    $um = $conn->prepare("
        SELECT COUNT(*) AS c FROM chat_messages m
        JOIN conversations c ON c.id = m.conversation_id
        WHERE c.provider_id = ? AND m.sender_id != ? AND m.is_read = 0
    ");
    $um->execute([$userId, $userId]);
    $unreadMessages = (int)($um->fetch()['c'] ?? 0);
} catch (Exception $e) {}
try {
    $pr = $conn->prepare("SELECT COUNT(*) AS c FROM referrals WHERE assigned_to = ? AND status = 'pending'");
    $pr->execute([$userId]);
    $pendingReferrals = (int)($pr->fetch()['c'] ?? 0);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0F1C3A">
  <title>My payments — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    :root {
      --bg:#F4F7FB; --card:#fff; --text:#0F1C3A; --muted:#64748B; --line:#E5E7EB;
      --green:#1EB53A; --navy:#0F1C3A; --safe-bottom:env(safe-area-inset-bottom,0px);
    }
    * { box-sizing:border-box; }
    body { margin:0; font-family:Inter,system-ui,sans-serif; background:var(--bg); color:var(--text); }
    .topbar {
      position:sticky; top:0; z-index:50; background:var(--navy); color:#fff;
      padding:10px 14px; display:flex; align-items:center; gap:10px; min-height:56px;
    }
    .topbar a { color:#fff; text-decoration:none; }
    .topbar .brand { flex:1; font-weight:700; }
    .topbar .icon {
      width:40px; height:40px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;
      background:rgba(255,255,255,.12); font-size:1.05rem;
    }
    .wrap { max-width:920px; margin:0 auto; padding:14px 14px calc(90px + var(--safe-bottom)); }
    .stats { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px; }
    @media(min-width:640px){ .stats { grid-template-columns:repeat(4,1fr); } }
    .stat {
      background:var(--card); border:1px solid var(--line); border-radius:16px; padding:14px;
      box-shadow:0 2px 10px rgba(15,23,42,.03);
    }
    .stat .n { font-size:1.2rem; font-weight:800; }
    .stat .l { font-size:.75rem; color:var(--muted); margin-top:2px; }
    .card {
      background:var(--card); border:1px solid var(--line); border-radius:16px; padding:16px;
      margin-bottom:14px; box-shadow:0 2px 10px rgba(15,23,42,.03);
    }
    .card h2 { margin:0 0 12px; font-size:1.05rem; }
    table { width:100%; border-collapse:collapse; font-size:.85rem; }
    th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); vertical-align:top; }
    th { color:var(--muted); font-size:.72rem; text-transform:uppercase; }
    .badge { display:inline-block; padding:3px 8px; border-radius:999px; font-size:.72rem; font-weight:700; background:#E2E8F0; }
    .badge.completed { background:#DCFCE7; color:#166534; }
    .money { font-weight:700; white-space:nowrap; }
    .money.pos { color:#15803D; }
    .money.neg { color:#B91C1C; }
    .empty { color:var(--muted); padding:12px 0; text-align:center; }
    .bottom-nav {
      position:fixed; left:0; right:0; bottom:0; z-index:60;
      background:rgba(255,255,255,.97); border-top:1px solid var(--line);
      display:grid; grid-template-columns:repeat(4,1fr);
      padding:6px 4px calc(6px + var(--safe-bottom));
    }
    .bottom-nav a {
      text-decoration:none; color:var(--muted); display:flex; flex-direction:column; align-items:center;
      gap:2px; padding:6px 2px; font-size:.68rem; font-weight:600;
    }
    .bottom-nav a.active { color:var(--green); }
    .bottom-nav .ico { font-size:1.2rem; }
    @media(min-width:768px){ .bottom-nav { display:none; } .wrap { padding-bottom:40px; } }
    [data-theme="dark"] { --bg:#0f172a; --card:#1e293b; --text:#F8FAFC; --muted:#94A3B8; --line:#334155; }
  </style>
</head>
<body>
<header class="topbar">
  <a class="brand" href="provider-dashboard.php">← Payments</a>
  <a class="icon" href="messages.php">💬</a>
  <a class="icon" href="../logout.php">🚪</a>
</header>

<main class="wrap">
  <?php if ($error): ?><div style="background:#FEF2F2;color:#991B1B;padding:12px;border-radius:12px;margin-bottom:12px">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="stats">
    <div class="stat">
      <div class="n">SLL <?= number_format($balance, 0) ?></div>
      <div class="l">Wallet balance</div>
    </div>
    <div class="stat">
      <div class="n"><?= (int)$earnings['count'] ?></div>
      <div class="l">Paid consultations</div>
    </div>
    <div class="stat">
      <div class="n" style="color:#15803D">SLL <?= number_format($earnings['net'], 0) ?></div>
      <div class="l">Your net earnings</div>
    </div>
    <div class="stat">
      <div class="n" style="color:#1D4ED8">SLL <?= number_format($earnings['fees'], 0) ?></div>
      <div class="l">Platform fees taken</div>
    </div>
  </div>

  <div class="card">
    <h2>Consultation earnings</h2>
    <?php if (empty($consults)): ?>
      <div class="empty">No consultation payments yet. When patients pay you, they show here.</div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>Patient</th>
              <th>Gross</th>
              <th>Fee</th>
              <th>You received</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($consults as $p): ?>
              <tr>
                <td><?= !empty($p['payment_date']) ? date('M j, Y', strtotime($p['payment_date'])) : (!empty($p['created_at']) ? date('M j, Y', strtotime($p['created_at'])) : '—') ?></td>
                <td><?= htmlspecialchars($p['patient_name'] ?? ('Patient #' . (int)$p['patient_id'])) ?><?php if (!empty($p['referral_id'])): ?> <span style="color:#94A3B8">· R<?= (int)$p['referral_id'] ?></span><?php endif; ?></td>
                <td class="money">SLL <?= number_format((float)$p['amount'], 0) ?></td>
                <td class="money" style="color:#1D4ED8">SLL <?= number_format((float)$p['platform_fee'], 0) ?><?= isset($p['commission_rate']) ? ' (' . (float)$p['commission_rate'] . '%)' : '' ?></td>
                <td class="money pos">SLL <?= number_format((float)$p['provider_earnings'], 0) ?></td>
                <td><span class="badge <?= htmlspecialchars($p['status'] ?? 'completed') ?>"><?= htmlspecialchars(ucfirst($p['status'] ?? 'completed')) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Wallet activity</h2>
    <?php if (empty($walletTx)): ?>
      <div class="empty">No wallet transactions yet.</div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>When</th>
              <th>Type</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Balance</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($walletTx as $t):
              $type = strtolower($t['type'] ?? '');
              $in = in_array($type, ['deposit', 'refund', 'credit'], true);
            ?>
              <tr>
                <td><?= !empty($t['created_at']) ? date('M j, Y H:i', strtotime($t['created_at'])) : '—' ?></td>
                <td><span class="badge"><?= htmlspecialchars(ucfirst($type)) ?></span></td>
                <td><?= htmlspecialchars($t['description'] ?? '') ?></td>
                <td class="money <?= $in ? 'pos' : 'neg' ?>">
                  <?= $in ? '+' : '−' ?>SLL <?= number_format((float)$t['amount'], 0) ?>
                </td>
                <td><?= $t['balance_after'] !== null ? 'SLL ' . number_format((float)$t['balance_after'], 0) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <p style="text-align:center;margin:8px 0 0">
    <a href="../provider-payment-settings.php" style="color:var(--green);font-weight:700;text-decoration:none">Fee & payout settings →</a>
  </p>
</main>

<nav class="bottom-nav" aria-label="Provider menu">
  <a href="provider-dashboard.php"><span class="ico">🏠</span>Home</a>
  <a href="messages.php"><span class="ico">💬</span>Chats</a>
  <a href="provider-referrals.php"><span class="ico">📋</span>Referrals</a>
  <a class="active" href="provider-payments.php"><span class="ico">💰</span>Pay</a>
</nav>
<script src="../js/dark-mode.js"></script>
</body>
</html>
