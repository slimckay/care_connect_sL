<?php
/**
 * Provider Referrals — Care Connect SL
 * Doctors/hospitals can view cases, start care, and mark patients as done.
 * When marked done, admins receive a notification.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// Confirm provider role
try {
    $u = $conn->prepare("SELECT name, email, role FROM users WHERE id = ? LIMIT 1");
    $u->execute([$user_id]);
    $user = $u->fetch() ?: [];
} catch (Exception $e) {
    $user = [];
}

$role = strtolower($user['role'] ?? ($_SESSION['role'] ?? ''));
if (!in_array($role, ['doctor', 'hospital'], true)) {
    header('Location: ../login.php');
    exit;
}

$providerName = $user['name'] ?? ($_SESSION['user_name'] ?? 'Provider');

/**
 * Notify all admin users that a provider finished a patient
 */
function notifyAdminsDone(PDO $conn, int $referralId, string $patientName, string $providerName): void
{
    try {
        $admins = $conn->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
        if (empty($admins)) {
            // fallback: any admin role user
            $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        }

        $title = 'Provider finished a patient';
        $msg = $providerName . ' marked referral #' . $referralId . ' (' . $patientName . ') as done. Please review on Manage Referrals.';
        $link = 'admin/manage-referrals.php?status=completed';

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
            VALUES (?, 'referral_completed', ?, ?, ?, 0, NOW())
        ");

        foreach ($admins as $admin) {
            $stmt->execute([(int)$admin['id'], $title, $msg, $link]);
        }

        // Also log activity
        try {
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'referral_completed_by_provider', ?, NOW())");
            $log->execute([null, $msg]);
        } catch (Exception $e) {}
    } catch (Exception $e) {
        error_log('notifyAdminsDone: ' . $e->getMessage());
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $referralId = (int)($_POST['referral_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($referralId > 0 && in_array($action, ['start', 'done'], true)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM referrals WHERE id = ? LIMIT 1");
            $stmt->execute([$referralId]);
            $ref = $stmt->fetch();

            if (!$ref) {
                $error = 'Referral not found.';
            } else {
                $patientName = $ref['patient_name'] ?? 'Patient';

                if ($action === 'start') {
                    // Mark in progress and assign to this provider if column exists
                    try {
                        $conn->prepare("UPDATE referrals SET status = 'in_progress', assigned_to = ?, updated_at = NOW() WHERE id = ?")
                             ->execute([$user_id, $referralId]);
                    } catch (Exception $e) {
                        $conn->prepare("UPDATE referrals SET status = 'in_progress', updated_at = NOW() WHERE id = ?")
                             ->execute([$referralId]);
                    }
                    $message = 'You started care for ' . $patientName . '.';
                }

                if ($action === 'done') {
                    // Optional note stored in notes column if present
                    try {
                        if ($note !== '') {
                            $conn->prepare("UPDATE referrals SET status = 'completed', notes = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?")
                                 ->execute([$note, $user_id, $referralId]);
                        } else {
                            $conn->prepare("UPDATE referrals SET status = 'completed', assigned_to = ?, updated_at = NOW() WHERE id = ?")
                                 ->execute([$user_id, $referralId]);
                        }
                    } catch (Exception $e) {
                        $conn->prepare("UPDATE referrals SET status = 'completed', updated_at = NOW() WHERE id = ?")
                             ->execute([$referralId]);
                    }

                    notifyAdminsDone($conn, $referralId, $patientName, $providerName);
                    $message = 'Marked as done. Admin has been notified.';
                }
            }
        } catch (Exception $e) {
            error_log('provider-referrals action: ' . $e->getMessage());
            $error = 'Could not update referral. Please try again.';
        }
    }
}

// Load referrals for provider view
// Show: pending (available), in_progress (active), and recently completed by this provider
$activeCases = [];
$availableCases = [];
$doneCases = [];

try {
    // Available pending
    $availableCases = $conn->query("
        SELECT * FROM referrals
        WHERE status = 'pending'
        ORDER BY created_at DESC
        LIMIT 50
    ")->fetchAll();

    // In progress (preferably assigned to this provider, else all in progress)
    try {
        $stmt = $conn->prepare("
            SELECT * FROM referrals
            WHERE status = 'in_progress'
              AND (assigned_to = ? OR assigned_to IS NULL)
            ORDER BY updated_at DESC, created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id]);
        $activeCases = $stmt->fetchAll();
    } catch (Exception $e) {
        $activeCases = $conn->query("
            SELECT * FROM referrals
            WHERE status = 'in_progress'
            ORDER BY created_at DESC
            LIMIT 50
        ")->fetchAll();
    }

    // Recently completed
    try {
        $stmt = $conn->prepare("
            SELECT * FROM referrals
            WHERE status = 'completed'
              AND (assigned_to = ? OR assigned_to IS NULL)
            ORDER BY updated_at DESC, created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
        $doneCases = $stmt->fetchAll();
    } catch (Exception $e) {
        $doneCases = $conn->query("
            SELECT * FROM referrals
            WHERE status = 'completed'
            ORDER BY created_at DESC
            LIMIT 20
        ")->fetchAll();
    }
} catch (Exception $e) {
    $error = $error ?: 'Could not load referrals.';
}

function conditionText(array $ref): string
{
    return $ref['condition'] ?? ($ref['medical_condition'] ?? 'Not provided');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Referrals — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    body { background:#F4F7FB; }
    .wrap { max-width:1000px; margin:28px auto 48px; padding:0 16px; }
    .top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:18px; flex-wrap:wrap; }
    .top h1 { margin:0 0 6px; font-size:1.7rem; color:#0F1C3A !important; }
    .top p { margin:0; color:#64748B !important; }
    .back { color:#1EB53A !important; font-weight:600; text-decoration:none; }

    .alert { padding:13px 16px; border-radius:12px; margin-bottom:16px; border:1px solid transparent; font-weight:600; }
    .alert.success { background:#ECFDF5; border-color:#A7F3D0; color:#065F46; }
    .alert.error { background:#FEF2F2; border-color:#FECACA; color:#991B1B; }

    .section-title {
      font-size:1.05rem; font-weight:700; color:#0F1C3A !important;
      margin:22px 0 12px;
    }

    .case {
      background:#fff; border:1px solid #E5E7EB; border-radius:16px;
      padding:18px; margin-bottom:12px; box-shadow:0 6px 16px rgba(15,23,42,0.04);
    }
    .case-head { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
    .case-head h3 { margin:0; font-size:1.05rem; color:#0F1C3A !important; }
    .meta { color:#64748B !important; font-size:0.9rem; line-height:1.5; }
    .cond { margin:10px 0 14px; color:#334155 !important; line-height:1.5; }

    .badge {
      display:inline-block; padding:4px 10px; border-radius:999px;
      font-size:0.75rem; font-weight:700;
    }
    .badge.pending { background:#FEF3C7; color:#B45309 !important; }
    .badge.in_progress { background:#DBEAFE; color:#1D4ED8 !important; }
    .badge.completed { background:#DCFCE7; color:#15803D !important; }

    .actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .btn {
      border:none; border-radius:999px; padding:10px 16px; font-weight:600;
      cursor:pointer; font-size:0.9rem; text-decoration:none; display:inline-flex;
    }
    .btn-main { background:linear-gradient(135deg,#1EB53A,#15803D); color:#fff !important; }
    .btn-done { background:#0F1C3A; color:#fff !important; }
    .btn-outline { background:transparent; border:2px solid #1EB53A; color:#1EB53A !important; }

    .note-box { width:100%; margin-top:10px; }
    .note-box textarea {
      width:100%; min-height:70px; border:1.5px solid #E5E7EB; border-radius:10px;
      padding:10px 12px; font:inherit; resize:vertical;
    }

    .empty {
      background:#fff; border:1px dashed #CBD5E1; border-radius:14px;
      padding:22px; color:#64748B !important; text-align:center;
    }

    [data-theme="dark"] body { background:#0f172a; }
    [data-theme="dark"] .top h1, [data-theme="dark"] .section-title, [data-theme="dark"] .case-head h3 { color:#F8FAFC !important; }
    [data-theme="dark"] .case { background:#1e293b; border-color:#334155; }
    [data-theme="dark"] .meta, [data-theme="dark"] .top p, [data-theme="dark"] .empty { color:#94A3B8 !important; }
    [data-theme="dark"] .cond { color:#E2E8F0 !important; }
    [data-theme="dark"] .note-box textarea { background:#0f172a; border-color:#334155; color:#E2E8F0; }
  </style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="../index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <button onclick="toggleDarkMode()" class="dark-toggle" type="button">🌓</button>
      <a href="provider-dashboard.php" class="btn-ghost">Dashboard</a>
      <a href="../logout.php" class="btn-ghost">Logout</a>
    </div>
  </div>
</header>

<main class="wrap">
  <div class="top">
    <div>
      <a class="back" href="provider-dashboard.php">← Back to dashboard</a>
      <h1>My Patient Referrals</h1>
      <p>Start care on a case, then mark the patient as done when finished. Admin will be notified.</p>
    </div>
  </div>

  <?php if ($message): ?><div class="alert success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="section-title">In progress</div>
  <?php if (empty($activeCases)): ?>
    <div class="empty">No active cases right now. Start one from the available list below.</div>
  <?php else: ?>
    <?php foreach ($activeCases as $ref): ?>
      <div class="case">
        <div class="case-head">
          <h3><?= htmlspecialchars($ref['patient_name'] ?? 'Patient') ?></h3>
          <span class="badge in_progress">In progress</span>
        </div>
        <div class="meta">
          <?= htmlspecialchars($ref['contact'] ?? 'No contact') ?>
          <?php if (!empty($ref['age'])): ?> · Age <?= (int)$ref['age'] ?><?php endif; ?>
          · <?= htmlspecialchars($ref['location'] ?? '-') ?>
        </div>
        <div class="cond"><strong>Condition:</strong> <?= htmlspecialchars(conditionText($ref)) ?></div>

        <form method="POST">
          <input type="hidden" name="referral_id" value="<?= (int)$ref['id'] ?>">
          <input type="hidden" name="action" value="done">
          <div class="note-box">
            <textarea name="note" placeholder="Optional note for admin (treatment summary, follow-up needed...)"></textarea>
          </div>
          <div class="actions" style="margin-top:10px;">
            <button type="submit" class="btn btn-done" onclick="return confirm('Mark this patient as done? Admin will be notified.')">✅ Mark Patient as Done</button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="section-title">Available referrals</div>
  <?php if (empty($availableCases)): ?>
    <div class="empty">No pending referrals at the moment.</div>
  <?php else: ?>
    <?php foreach ($availableCases as $ref): ?>
      <div class="case">
        <div class="case-head">
          <h3><?= htmlspecialchars($ref['patient_name'] ?? 'Patient') ?></h3>
          <span class="badge pending">Pending</span>
        </div>
        <div class="meta">
          <?= htmlspecialchars($ref['contact'] ?? 'No contact') ?>
          <?php if (!empty($ref['age'])): ?> · Age <?= (int)$ref['age'] ?><?php endif; ?>
          · <?= htmlspecialchars($ref['location'] ?? '-') ?>
          <?php if (!empty($ref['preferred_clinic'])): ?> · Preferred: <?= htmlspecialchars($ref['preferred_clinic']) ?><?php endif; ?>
        </div>
        <div class="cond"><strong>Condition:</strong> <?= htmlspecialchars(conditionText($ref)) ?></div>
        <div class="actions">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="referral_id" value="<?= (int)$ref['id'] ?>">
            <input type="hidden" name="action" value="start">
            <button type="submit" class="btn btn-main">Start Care</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="section-title">Recently completed</div>
  <?php if (empty($doneCases)): ?>
    <div class="empty">No completed cases yet.</div>
  <?php else: ?>
    <?php foreach ($doneCases as $ref): ?>
      <div class="case">
        <div class="case-head">
          <h3><?= htmlspecialchars($ref['patient_name'] ?? 'Patient') ?></h3>
          <span class="badge completed">Completed</span>
        </div>
        <div class="meta">
          <?= htmlspecialchars($ref['location'] ?? '-') ?>
          · <?= !empty($ref['updated_at']) ? date('M d, Y H:i', strtotime($ref['updated_at'])) : (!empty($ref['created_at']) ? date('M d, Y', strtotime($ref['created_at'])) : '') ?>
        </div>
        <div class="cond"><strong>Condition:</strong> <?= htmlspecialchars(conditionText($ref)) ?></div>
        <?php if (!empty($ref['notes'])): ?>
          <div class="meta"><strong>Note:</strong> <?= htmlspecialchars($ref['notes']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<script src="../js/dark-mode.js"></script>
</body>
</html>
