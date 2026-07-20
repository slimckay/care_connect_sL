<?php
/**
 * Public referral status — works on any phone browser, no login.
 * /status.php?id=12
 * or POST phone + id for extra check
 */
require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$phoneHint = trim((string)($_GET['phone'] ?? $_POST['phone'] ?? ''));
$error = '';
$row = null;

if ($id > 0) {
    try {
        $stmt = $conn->prepare('SELECT * FROM referrals WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            $error = 'No referral found with that number.';
        } elseif ($phoneHint !== '') {
            // Soft verify last 4 digits against contact/phone if present
            $stored = preg_replace('/\D/', '', (string)($row['contact'] ?? $row['phone'] ?? ''));
            $hint = preg_replace('/\D/', '', $phoneHint);
            if ($stored !== '' && $hint !== '' && !str_ends_with($stored, substr($hint, -4))) {
                $error = 'Phone does not match this referral. Check the number and try again.';
                $row = null;
            }
        }
    } catch (Exception $e) {
        $error = 'Could not look up referral right now.';
    }
}

function statusLabel(?string $s): string
{
    $s = strtolower(str_replace(' ', '_', (string)$s));
    return match ($s) {
        'in_progress', 'accepted', 'assigned', 'active' => 'In progress',
        'completed', 'done', 'closed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => 'Pending',
    };
}

function statusStep(?string $s): int
{
    $s = strtolower(str_replace(' ', '_', (string)$s));
    return match ($s) {
        'in_progress', 'accepted', 'assigned', 'active' => 1,
        'completed', 'done', 'closed' => 2,
        'cancelled' => -1,
        default => 0,
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Check referral status — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    body { background:#F4F7FB; }
    .box { max-width:480px; margin:28px auto; padding:0 16px 40px; }
    .card { background:#fff; border:1px solid #E5E7EB; border-radius:18px; padding:22px; box-shadow:0 8px 24px rgba(15,23,42,.05); }
    h1 { margin:0 0 6px; font-size:1.35rem; color:#0F1C3A !important; }
    .sub { color:#64748B; margin:0 0 16px; line-height:1.45; font-size:.92rem; }
    label { display:block; font-weight:600; font-size:.88rem; margin:10px 0 4px; }
    input { width:100%; padding:12px; border:1.5px solid #E2E8F0; border-radius:12px; font:inherit; }
    button { width:100%; margin-top:14px; border:none; border-radius:12px; padding:14px; font-weight:700; background:#1EB53A; color:#fff; cursor:pointer; font-size:1rem; }
    .err { background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; padding:12px; border-radius:12px; margin-bottom:12px; }
    .timeline { display:grid; grid-template-columns:1fr 1fr 1fr; gap:0; position:relative; margin:18px 0 8px; }
    .timeline::before { content:''; position:absolute; left:16%; right:16%; top:14px; height:3px; background:#E5E7EB; }
    .step { text-align:center; position:relative; z-index:1; }
    .dot { width:28px; height:28px; border-radius:50%; margin:0 auto 8px; display:flex; align-items:center; justify-content:center;
           background:#E5E7EB; color:#94A3B8; font-size:.75rem; font-weight:700; border:3px solid #fff; }
    .step.on .dot, .step.done .dot { background:#1EB53A; color:#fff; }
    .step label { font-size:.72rem; color:#94A3B8; font-weight:600; }
    .step.on label, .step.done label { color:#166534; }
    .badge { display:inline-block; padding:4px 12px; border-radius:999px; font-size:.8rem; font-weight:700; background:#FEF3C7; color:#92400E; }
    .badge.progress { background:#DBEAFE; color:#1D4ED8; }
    .badge.done { background:#DCFCE7; color:#166534; }
    .meta { color:#64748B; font-size:.9rem; line-height:1.5; margin-top:10px; }
  </style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <a href="login.php" class="btn-ghost">Login</a>
    </div>
  </div>
</header>

<main class="box">
  <div class="card">
    <h1>Check referral status</h1>
    <p class="sub">No app needed. Enter your referral number from SMS or the clinic.</p>

    <?php if ($error): ?>
      <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($row):
      $st = statusLabel($row['status'] ?? 'pending');
      $step = statusStep($row['status'] ?? 'pending');
      $cond = $row['condition'] ?? ($row['medical_condition'] ?? '');
      $cls = $step === 2 ? 'done' : ($step === 1 ? 'progress' : '');
    ?>
      <div style="margin-bottom:8px">
        <strong style="font-size:1.1rem">Referral #<?= (int)$row['id'] ?></strong>
        <span class="badge <?= $cls ?>" style="margin-left:8px"><?= htmlspecialchars($st) ?></span>
      </div>
      <div class="meta">
        <div><strong><?= htmlspecialchars($row['patient_name'] ?? 'Patient') ?></strong></div>
        <?php if ($cond): ?><div><?= htmlspecialchars(mb_substr((string)$cond, 0, 120)) ?></div><?php endif; ?>
        <?php if (!empty($row['location'])): ?><div>📍 <?= htmlspecialchars($row['location']) ?></div><?php endif; ?>
        <?php if (!empty($row['created_at'])): ?>
          <div>Submitted <?= date('M j, Y', strtotime($row['created_at'])) ?></div>
        <?php endif; ?>
      </div>

      <?php if ($step >= 0): ?>
      <div class="timeline">
        <div class="step <?= $step >= 0 ? 'done' : '' ?> <?= $step === 0 ? 'on' : '' ?>">
          <div class="dot"><?= $step > 0 ? '✓' : '1' ?></div>
          <label>Pending</label>
        </div>
        <div class="step <?= $step >= 1 ? 'done' : '' ?> <?= $step === 1 ? 'on' : '' ?>">
          <div class="dot"><?= $step > 1 ? '✓' : '2' ?></div>
          <label>In progress</label>
        </div>
        <div class="step <?= $step >= 2 ? 'done' : '' ?> <?= $step === 2 ? 'on' : '' ?>">
          <div class="dot"><?= $step >= 2 ? '✓' : '3' ?></div>
          <label>Completed</label>
        </div>
      </div>
      <?php endif; ?>

      <p class="sub" style="margin-top:16px;margin-bottom:0">Need help? Use the contact form or login for chat.</p>
      <a href="pages/contact.html" class="btn-ghost" style="display:block;text-align:center;margin-top:12px">Contact support</a>
    <?php else: ?>
      <form method="GET" action="status.php">
        <label for="id">Referral number</label>
        <input type="number" id="id" name="id" min="1" placeholder="e.g. 12" value="<?= $id > 0 ? (int)$id : '' ?>" required>
        <label for="phone">Phone used on referral (optional)</label>
        <input type="text" id="phone" name="phone" placeholder="Last 4 digits or full number" value="<?= htmlspecialchars($phoneHint) ?>">
        <button type="submit">Check status</button>
      </form>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
