<?php
/**
 * Admin — platform payment history
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../payment_helper.php';

$adminName = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$payment = new PaymentSystem($conn); // ensures tables

$filter = $_GET['type'] ?? 'all'; // all | consultations | wallets
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 40;
$offset = ($page - 1) * $limit;

$totals = [
    'gross' => 0.0,
    'platform_fees' => 0.0,
    'provider_earnings' => 0.0,
    'count' => 0,
];
$payments = [];
$walletTx = [];
$error = '';

try {
    // Summary from referral_payments
    $sum = $conn->query("
        SELECT
            COUNT(*) AS c,
            COALESCE(SUM(amount),0) AS gross,
            COALESCE(SUM(platform_fee),0) AS fees,
            COALESCE(SUM(provider_earnings),0) AS earnings
        FROM referral_payments
        WHERE status IN ('completed','paid','success') OR status IS NULL OR status = 'pending'
    ")->fetch(PDO::FETCH_ASSOC);
    if ($sum) {
        $totals['count'] = (int)($sum['c'] ?? 0);
        $totals['gross'] = (float)($sum['gross'] ?? 0);
        $totals['platform_fees'] = (float)($sum['fees'] ?? 0);
        $totals['provider_earnings'] = (float)($sum['earnings'] ?? 0);
    }
} catch (Exception $e) {
    $error = 'Could not load payment summary.';
}

try {
    if ($filter === 'all' || $filter === 'consultations') {
        $stmt = $conn->prepare("
            SELECT rp.*,
                   pu.name AS patient_name,
                   pr.name AS provider_name,
                   pr.role AS provider_role
            FROM referral_payments rp
            LEFT JOIN users pu ON pu.id = rp.patient_id
            LEFT JOIN users pr ON pr.id = rp.provider_id
            ORDER BY COALESCE(rp.payment_date, rp.created_at) DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = $error ?: 'Could not load consultation payments.';
}

try {
    if ($filter === 'all' || $filter === 'wallets') {
        $stmt = $conn->prepare("
            SELECT t.*, u.name AS user_name, u.role AS user_role, u.email AS user_email
            FROM transactions t
            LEFT JOIN users u ON u.id = t.user_id
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $walletTx = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = $error ?: 'Could not load wallet transactions.';
}

$active = 'payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment history — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="admin-styles.css">
  <style>
    .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:16px; }
    .stat { background:#fff; border:1px solid #E5E7EB; border-radius:14px; padding:14px 16px; }
    .stat .n { font-size:1.35rem; font-weight:800; color:#0F1C3A; }
    .stat .l { font-size:.8rem; color:#64748B; margin-top:2px; }
    .filters a { display:inline-block; padding:8px 14px; border-radius:999px; margin:0 6px 10px 0; border:1px solid #E5E7EB; text-decoration:none; color:#334155; font-weight:600; font-size:.85rem; }
    .filters a.active { background:#1EB53A; color:#fff; border-color:#1EB53A; }
    table { width:100%; border-collapse:collapse; font-size:.88rem; }
    th, td { text-align:left; padding:10px 12px; border-bottom:1px solid #E5E7EB; vertical-align:top; }
    th { color:#64748B; font-weight:600; font-size:.78rem; text-transform:uppercase; letter-spacing:.02em; }
    .badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:.75rem; font-weight:700; }
    .badge.completed, .badge.paid, .badge.success { background:#DCFCE7; color:#166534; }
    .badge.pending { background:#FEF3C7; color:#92400E; }
    .badge.failed { background:#FEE2E2; color:#991B1B; }
    .money { font-weight:700; white-space:nowrap; }
    .money.pos { color:#15803D; }
    .money.neg { color:#B91C1C; }
  </style>
</head>
<body class="admin-body">
<div class="admin-wrapper">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <main class="admin-main">
    <div class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button">☰</button>
        <span class="page-title">Payment history</span>
      </div>
      <div class="admin-topbar-right">
        <span class="welcome"><?= htmlspecialchars($adminName) ?></span>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($error): ?><div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="stats">
        <div class="stat">
          <div class="n"><?= (int)$totals['count'] ?></div>
          <div class="l">Consultation payments</div>
        </div>
        <div class="stat">
          <div class="n">SLL <?= number_format($totals['gross'], 0) ?></div>
          <div class="l">Gross paid by patients</div>
        </div>
        <div class="stat">
          <div class="n" style="color:#1D4ED8">SLL <?= number_format($totals['platform_fees'], 0) ?></div>
          <div class="l">Platform fees (15–20%)</div>
        </div>
        <div class="stat">
          <div class="n" style="color:#15803D">SLL <?= number_format($totals['provider_earnings'], 0) ?></div>
          <div class="l">Paid to providers</div>
        </div>
      </div>

      <div class="filters">
        <a href="?type=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
        <a href="?type=consultations" class="<?= $filter === 'consultations' ? 'active' : '' ?>">Consultations</a>
        <a href="?type=wallets" class="<?= $filter === 'wallets' ? 'active' : '' ?>">Wallet ledger</a>
      </div>

      <?php if ($filter !== 'wallets'): ?>
      <div class="admin-card" style="margin-bottom:16px">
        <div class="card-header">
          <h2>Consultation payments</h2>
          <span>Patient → provider (with commission split)</span>
        </div>
        <?php if (empty($payments)): ?>
          <div class="empty">No consultation payments yet.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>When</th>
                  <th>Patient</th>
                  <th>Provider</th>
                  <th>Gross</th>
                  <th>Platform</th>
                  <th>Provider net</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $p): ?>
                  <tr>
                    <td>#<?= (int)$p['id'] ?><?php if (!empty($p['referral_id'])): ?> <span style="color:#94A3B8">R<?= (int)$p['referral_id'] ?></span><?php endif; ?></td>
                    <td><?= !empty($p['payment_date']) ? date('M j, Y H:i', strtotime($p['payment_date'])) : (!empty($p['created_at']) ? date('M j, Y H:i', strtotime($p['created_at'])) : '—') ?></td>
                    <td><?= htmlspecialchars($p['patient_name'] ?? ('User #' . (int)$p['patient_id'])) ?></td>
                    <td>
                      <?= htmlspecialchars($p['provider_name'] ?? ('User #' . (int)$p['provider_id'])) ?>
                      <div style="color:#94A3B8;font-size:.75rem"><?= htmlspecialchars(ucfirst($p['provider_role'] ?? 'doctor')) ?><?= isset($p['commission_rate']) ? ' · ' . (float)$p['commission_rate'] . '%' : '' ?></div>
                    </td>
                    <td class="money">SLL <?= number_format((float)$p['amount'], 0) ?></td>
                    <td class="money" style="color:#1D4ED8">SLL <?= number_format((float)$p['platform_fee'], 0) ?></td>
                    <td class="money pos">SLL <?= number_format((float)$p['provider_earnings'], 0) ?></td>
                    <td><span class="badge <?= htmlspecialchars($p['status'] ?? 'completed') ?>"><?= htmlspecialchars(ucfirst($p['status'] ?? 'completed')) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($filter !== 'consultations'): ?>
      <div class="admin-card">
        <div class="card-header">
          <h2>Wallet ledger</h2>
          <span>All deposits, payments, commissions</span>
        </div>
        <?php if (empty($walletTx)): ?>
          <div class="empty">No wallet transactions yet.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>When</th>
                  <th>User</th>
                  <th>Type</th>
                  <th>Description</th>
                  <th>Amount</th>
                  <th>Balance after</th>
                  <th>Ref</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($walletTx as $t):
                  $type = strtolower($t['type'] ?? '');
                  $in = in_array($type, ['deposit', 'refund', 'credit'], true);
                ?>
                  <tr>
                    <td>#<?= (int)$t['id'] ?></td>
                    <td><?= !empty($t['created_at']) ? date('M j, Y H:i', strtotime($t['created_at'])) : '—' ?></td>
                    <td>
                      <?= htmlspecialchars($t['user_name'] ?? ('#' . (int)$t['user_id'])) ?>
                      <div style="color:#94A3B8;font-size:.75rem"><?= htmlspecialchars(ucfirst($t['user_role'] ?? '')) ?></div>
                    </td>
                    <td><span class="badge <?= htmlspecialchars($type) ?>"><?= htmlspecialchars(ucfirst($type)) ?></span></td>
                    <td><?= htmlspecialchars($t['description'] ?? '') ?></td>
                    <td class="money <?= $in ? 'pos' : 'neg' ?>">
                      <?= $in ? '+' : '−' ?>SLL <?= number_format((float)$t['amount'], 0) ?>
                    </td>
                    <td><?= $t['balance_after'] !== null ? 'SLL ' . number_format((float)$t['balance_after'], 0) : '—' ?></td>
                    <td style="font-size:.75rem;color:#64748B"><?= htmlspecialchars($t['reference'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div style="margin-top:14px;text-align:center">
        <?php if ($page > 1): ?>
          <a class="btn-admin" href="?type=<?= urlencode($filter) ?>&page=<?= $page - 1 ?>" style="text-decoration:none;padding:8px 14px;border-radius:999px">← Prev</a>
        <?php endif; ?>
        <a class="btn-admin" href="?type=<?= urlencode($filter) ?>&page=<?= $page + 1 ?>" style="text-decoration:none;padding:8px 14px;border-radius:999px;margin-left:8px">Next →</a>
      </div>
    </div>
  </main>
</div>
<script src="../js/dark-mode.js"></script>
<script>
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
if (sidebarToggle && sidebar) sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
</script>
</body>
</html>
