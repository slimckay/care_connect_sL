<?php
/**
 * Patient Referral Tracking — Care Connect SL
 * Timeline: Pending → In progress → Completed
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Patient';
$filter = $_GET['status'] ?? 'all';

$rows = [];
try {
    $sql = "
        SELECT r.*,
               u.name AS provider_name
        FROM referrals r
        LEFT JOIN users u ON u.id = r.assigned_to
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
        LIMIT 100
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $stmt = $conn->prepare("SELECT * FROM referrals WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $rows = [];
    }
}

function conditionOf(array $r): string {
    return $r['condition'] ?? ($r['medical_condition'] ?? ($r['reason'] ?? 'Not provided'));
}

function normalizeStatus(string $s): string {
    $s = strtolower(str_replace(' ', '_', $s));
    if (in_array($s, ['accepted', 'active', 'assigned'], true)) return 'in_progress';
    if (in_array($s, ['done', 'closed', 'resolved'], true)) return 'completed';
    if (in_array($s, ['pending', 'in_progress', 'completed', 'cancelled'], true)) return $s;
    return 'pending';
}

function stepIndex(string $status): int {
    return match (normalizeStatus($status)) {
        'in_progress' => 1,
        'completed' => 2,
        'cancelled' => -1,
        default => 0,
    };
}

$counts = ['all' => count($rows), 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
foreach ($rows as $r) {
    $st = normalizeStatus($r['status'] ?? 'pending');
    if (isset($counts[$st])) $counts[$st]++;
}

if ($filter !== 'all') {
    $rows = array_values(array_filter($rows, function ($r) use ($filter) {
        return normalizeStatus($r['status'] ?? 'pending') === $filter;
    }));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track Referrals — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    body { background:#F4F7FB; }
    .wrap { max-width:860px; margin:28px auto 56px; padding:0 16px; }
    h1 { margin:0 0 6px; font-size:1.6rem; color:#0F1C3A !important; }
    .sub { margin:0 0 18px; color:#64748B; }
    .filters { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px; }
    .filters a {
      text-decoration:none; padding:8px 14px; border-radius:999px; font-size:.88rem; font-weight:600;
      background:#fff; border:1px solid #E5E7EB; color:#334155 !important;
    }
    .filters a.active { background:#0F1C3A; color:#fff !important; border-color:#0F1C3A; }
    .filters a span { opacity:.75; margin-left:4px; }

    .card {
      background:#fff; border:1px solid #E5E7EB; border-radius:18px; padding:18px 18px 16px;
      margin-bottom:14px; box-shadow:0 6px 18px rgba(15,23,42,.04);
    }
    .card-top { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:flex-start; }
    .card-top h3 { margin:0; font-size:1.05rem; color:#0F1C3A !important; }
    .meta { color:#64748B; font-size:.9rem; margin-top:4px; line-height:1.45; }
    .cond { margin:12px 0 16px; color:#334155; line-height:1.5; font-size:.95rem; }

    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:.75rem; font-weight:700; }
    .badge.pending { background:#FEF3C7; color:#92400E; }
    .badge.in_progress { background:#DBEAFE; color:#1D4ED8; }
    .badge.completed { background:#DCFCE7; color:#166534; }
    .badge.cancelled { background:#F1F5F9; color:#64748B; }

    /* Timeline */
    .timeline {
      display:grid; grid-template-columns:1fr 1fr 1fr; gap:0; position:relative; margin:8px 0 4px;
    }
    .timeline::before {
      content:''; position:absolute; left:16%; right:16%; top:14px; height:3px; background:#E5E7EB; z-index:0;
    }
    .step { text-align:center; position:relative; z-index:1; }
    .dot {
      width:28px; height:28px; border-radius:50%; margin:0 auto 8px;
      display:flex; align-items:center; justify-content:center;
      background:#E5E7EB; color:#94A3B8; font-size:.75rem; font-weight:700;
      border:3px solid #fff; box-shadow:0 0 0 1px #E5E7EB;
    }
    .step.on .dot, .step.done .dot {
      background:#1EB53A; color:#fff; box-shadow:0 0 0 1px #1EB53A;
    }
    .step.done .dot { background:#15803D; }
    .step label { display:block; font-size:.72rem; font-weight:600; color:#94A3B8; }
    .step.on label, .step.done label { color:#166534; }
    .step.cancelled-note { grid-column:1 / -1; text-align:center; color:#991B1B; font-size:.85rem; font-weight:600; }

    .footer-row { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-top:12px; align-items:center; }
    .provider { font-size:.88rem; color:#475569; }
    .links a { color:#1EB53A !important; font-weight:600; font-size:.88rem; text-decoration:none; margin-left:10px; }

    .empty {
      text-align:center; padding:40px 16px; background:#fff; border-radius:16px; border:1px solid #E5E7EB; color:#64748B;
    }
    .btn-new {
      display:inline-block; margin-top:12px; background:#1EB53A; color:#fff !important; padding:10px 18px;
      border-radius:999px; font-weight:700; text-decoration:none;
    }

    @media (max-width:520px) {
      .timeline::before { left:18%; right:18%; }
      .step label { font-size:.68rem; }
    }
  </style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="../index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <a href="patient-dashboard.php" class="btn-ghost">Dashboard</a>
      <a href="../pages/referral.html" class="btn-primary">New referral</a>
      <a href="../logout.php" class="btn-ghost btn-logout">Log out</a>
    </div>
  </div>
</header>

<main class="wrap">
  <h1>📍 Track your referrals</h1>
  <p class="sub">See where each request is — from submitted to care completed.</p>

  <div class="filters">
    <a href="?status=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All <span><?= (int)$counts['all'] ?></span></a>
    <a href="?status=pending" class="<?= $filter === 'pending' ? 'active' : '' ?>">Pending <span><?= (int)$counts['pending'] ?></span></a>
    <a href="?status=in_progress" class="<?= $filter === 'in_progress' ? 'active' : '' ?>">In progress <span><?= (int)$counts['in_progress'] ?></span></a>
    <a href="?status=completed" class="<?= $filter === 'completed' ? 'active' : '' ?>">Completed <span><?= (int)$counts['completed'] ?></span></a>
  </div>

  <?php if (empty($rows)): ?>
    <div class="empty">
      <p>No referrals<?= $filter !== 'all' ? ' with this status' : '' ?> yet.</p>
      <a class="btn-new" href="../pages/referral.html">Submit a referral</a>
    </div>
  <?php else: ?>
    <?php foreach ($rows as $r):
      $st = normalizeStatus($r['status'] ?? 'pending');
      $idx = stepIndex($st);
      $cond = conditionOf($r);
      $provider = $r['provider_name'] ?? '';
      $created = !empty($r['created_at']) ? date('M j, Y · g:i A', strtotime($r['created_at'])) : '';
      $follow = $r['follow_up_date'] ?? null;
    ?>
      <article class="card">
        <div class="card-top">
          <div>
            <h3><?= htmlspecialchars($r['patient_name'] ?? 'Patient') ?></h3>
            <div class="meta">#<?= (int)$r['id'] ?> · <?= htmlspecialchars($created) ?><?php if (!empty($r['location'])): ?> · <?= htmlspecialchars($r['location']) ?><?php endif; ?></div>
          </div>
          <span class="badge <?= htmlspecialchars($st) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $st))) ?></span>
        </div>

        <div class="cond"><?= htmlspecialchars(mb_substr($cond, 0, 180)) ?><?= mb_strlen($cond) > 180 ? '…' : '' ?></div>

        <?php if ($st === 'cancelled'): ?>
          <div class="timeline"><div class="step cancelled-note">This referral was cancelled</div></div>
        <?php else: ?>
          <div class="timeline" aria-label="Referral progress">
            <div class="step <?= $idx >= 0 ? 'done' : '' ?> <?= $idx === 0 ? 'on' : '' ?>">
              <div class="dot"><?= $idx > 0 ? '✓' : '1' ?></div>
              <label>Pending</label>
            </div>
            <div class="step <?= $idx >= 1 ? 'done' : '' ?> <?= $idx === 1 ? 'on' : '' ?>">
              <div class="dot"><?= $idx > 1 ? '✓' : '2' ?></div>
              <label>In progress</label>
            </div>
            <div class="step <?= $idx >= 2 ? 'done' : '' ?> <?= $idx === 2 ? 'on' : '' ?>">
              <div class="dot"><?= $idx >= 2 ? '✓' : '3' ?></div>
              <label>Completed</label>
            </div>
          </div>
        <?php endif; ?>

        <div class="footer-row">
          <div class="provider">
            <?php if ($provider): ?>
              👨‍⚕️ <?= htmlspecialchars($provider) ?>
            <?php else: ?>
              Awaiting provider assignment
            <?php endif; ?>
            <?php if ($follow && $st !== 'cancelled'): ?>
              <div class="meta" style="margin-top:4px">📅 Follow-up: <?= date('M j, Y', strtotime($follow)) ?></div>
            <?php endif; ?>
          </div>
          <div class="links">
            <?php if ($provider && !empty($r['assigned_to'])): ?>
              <a href="messages.php?provider=<?= (int)$r['assigned_to'] ?>">Message</a>
            <?php endif; ?>
            <a href="../pages/appointment.php<?= !empty($r['assigned_to']) ? '?doctor='.(int)$r['assigned_to'] : '' ?>"">Book visit</a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
<script src="../js/dark-mode.js"></script>
<script src="../js/mobile-logout.js"></script>
</body>
</html>
