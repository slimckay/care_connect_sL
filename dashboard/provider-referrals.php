<?php
/**
 * Provider Referrals — Care Connect SL
 * Start care, mark done, set follow-up dates.
 * Patients are notified on status changes.
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

try {
    $cols = $conn->query("SHOW COLUMNS FROM referrals LIKE 'follow_up_date'")->fetch();
    if (!$cols) {
        $conn->exec("ALTER TABLE referrals ADD COLUMN follow_up_date DATE NULL");
    }
    $cols2 = $conn->query("SHOW COLUMNS FROM referrals LIKE 'follow_up_notes'")->fetch();
    if (!$cols2) {
        $conn->exec("ALTER TABLE referrals ADD COLUMN follow_up_notes TEXT NULL");
    }
} catch (Exception $e) {}

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

function notifyAdminsDone(PDO $conn, int $referralId, string $patientName, string $providerName, ?string $followUp = null): void
{
    try {
        $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        $title = 'Provider finished a patient';
        $msg = $providerName . ' marked referral #' . $referralId . ' (' . $patientName . ') as done.';
        if ($followUp) {
            $msg .= ' Follow-up scheduled for ' . $followUp . '.';
        }
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
            VALUES (?, 'referral_completed', ?, ?, 'admin/manage-referrals.php?status=completed', 0, NOW())
        ");
        foreach ($admins as $admin) {
            $stmt->execute([(int)$admin['id'], $title, $msg]);
        }
    } catch (Exception $e) {
        error_log('notifyAdminsDone: ' . $e->getMessage());
    }
}

function notifyPatientStatus(PDO $conn, $ref, string $title, string $msg): void
{
    $patientUserId = (int)($ref['user_id'] ?? 0);
    if ($patientUserId <= 0) return;
    try {
        $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
            VALUES (?, 'referral_status', ?, ?, 'dashboard/track-referrals.php', 0, NOW())
        ")->execute([$patientUserId, $title, $msg]);
    } catch (Exception $e) {
        error_log('notifyPatientStatus: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $referralId = (int)($_POST['referral_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $followUpDate = trim($_POST['follow_up_date'] ?? '');
    $followUpNotes = trim($_POST['follow_up_notes'] ?? '');

    if ($followUpDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $followUpDate)) {
        $followUpDate = '';
    }

    if ($referralId > 0 && in_array($action, ['start', 'done', 'set_follow_up'], true)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM referrals WHERE id = ? LIMIT 1");
            $stmt->execute([$referralId]);
            $ref = $stmt->fetch();

            if (!$ref) {
                $error = 'Referral not found.';
            } else {
                $patientName = $ref['patient_name'] ?? 'Patient';

                if ($action === 'start') {
                    try {
                        $conn->prepare("UPDATE referrals SET status = 'in_progress', assigned_to = ?, updated_at = NOW() WHERE id = ?")
                             ->execute([$user_id, $referralId]);
                    } catch (Exception $e) {
                        $conn->prepare("UPDATE referrals SET status = 'in_progress', updated_at = NOW() WHERE id = ?")
                             ->execute([$referralId]);
                    }
                    notifyPatientStatus(
                        $conn,
                        $ref,
                        'Care has started',
                        $providerName . ' has started care for your referral #' . $referralId . '. Track progress in Track Referrals.'
                    );
                    $message = 'You started care for ' . $patientName . '. Patient notified.';
                }

                if ($action === 'done') {
                    try {
                        $conn->prepare("
                            UPDATE referrals
                            SET status = 'completed',
                                notes = COALESCE(NULLIF(?, ''), notes),
                                follow_up_date = NULLIF(?, ''),
                                follow_up_notes = NULLIF(?, ''),
                                assigned_to = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ")->execute([$note, $followUpDate, $followUpNotes, $user_id, $referralId]);
                    } catch (Exception $e) {
                        try {
                            $conn->prepare("UPDATE referrals SET status = 'completed', notes = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?")
                                 ->execute([$note !== '' ? $note : ($ref['notes'] ?? null), $user_id, $referralId]);
                        } catch (Exception $e2) {
                            $conn->prepare("UPDATE referrals SET status = 'completed', updated_at = NOW() WHERE id = ?")
                                 ->execute([$referralId]);
                        }
                    }

                    notifyAdminsDone($conn, $referralId, $patientName, $providerName, $followUpDate ?: null);
                    $doneMsg = $providerName . ' marked your referral #' . $referralId . ' as completed.';
                    if ($followUpDate) {
                        $doneMsg .= ' Follow-up scheduled for ' . date('M d, Y', strtotime($followUpDate)) . '.';
                    }
                    notifyPatientStatus($conn, $ref, 'Referral completed', $doneMsg);
                    $message = 'Marked as done. Patient and admin notified.';
                    if ($followUpDate) {
                        $message .= ' Follow-up set for ' . date('M d, Y', strtotime($followUpDate)) . '.';
                    }
                }

                if ($action === 'set_follow_up') {
                    if ($followUpDate === '') {
                        $error = 'Please choose a follow-up date.';
                    } else {
                        try {
                            $conn->prepare("
                                UPDATE referrals
                                SET follow_up_date = ?,
                                    follow_up_notes = NULLIF(?, ''),
                                    assigned_to = COALESCE(assigned_to, ?),
                                    updated_at = NOW()
                                WHERE id = ?
                            ")->execute([$followUpDate, $followUpNotes, $user_id, $referralId]);
                            notifyPatientStatus(
                                $conn,
                                $ref,
                                'Follow-up scheduled',
                                'A follow-up was set for ' . date('M d, Y', strtotime($followUpDate)) . ' on referral #' . $referralId . '.'
                            );
                            $message = 'Follow-up date saved for ' . $patientName . ' (' . date('M d, Y', strtotime($followUpDate)) . ').';
                        } catch (Exception $e) {
                            $error = 'Could not save follow-up date.';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('provider-referrals action: ' . $e->getMessage());
            $error = 'Could not update referral. Please try again.';
        }
    }
}

$activeCases = [];
$availableCases = [];
$doneCases = [];
$upcomingFollowUps = [];

try {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM referrals
            WHERE status = 'pending' AND (assigned_to = ? OR assigned_to IS NULL)
            ORDER BY CASE WHEN assigned_to = ? THEN 0 ELSE 1 END, created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id, $user_id]);
        $availableCases = $stmt->fetchAll();
    } catch (Exception $e) {
        $availableCases = $conn->query("SELECT * FROM referrals WHERE status = 'pending' ORDER BY created_at DESC LIMIT 50")->fetchAll();
    }

    try {
        $stmt = $conn->prepare("
            SELECT * FROM referrals
            WHERE status = 'in_progress' AND (assigned_to = ? OR assigned_to IS NULL)
            ORDER BY updated_at DESC, created_at DESC LIMIT 50
        ");
        $stmt->execute([$user_id]);
        $activeCases = $stmt->fetchAll();
    } catch (Exception $e) {
        $activeCases = $conn->query("SELECT * FROM referrals WHERE status = 'in_progress' ORDER BY created_at DESC LIMIT 50")->fetchAll();
    }

    try {
        $stmt = $conn->prepare("
            SELECT * FROM referrals
            WHERE status = 'completed' AND (assigned_to = ? OR assigned_to IS NULL)
            ORDER BY updated_at DESC, created_at DESC LIMIT 20
        ");
        $stmt->execute([$user_id]);
        $doneCases = $stmt->fetchAll();
    } catch (Exception $e) {
        $doneCases = $conn->query("SELECT * FROM referrals WHERE status = 'completed' ORDER BY created_at DESC LIMIT 20")->fetchAll();
    }

    try {
        $stmt = $conn->prepare("
            SELECT * FROM referrals
            WHERE follow_up_date IS NOT NULL
              AND follow_up_date >= CURDATE()
              AND (assigned_to = ? OR assigned_to IS NULL OR status IN ('completed','in_progress'))
            ORDER BY follow_up_date ASC
            LIMIT 30
        ");
        $stmt->execute([$user_id]);
        $upcomingFollowUps = $stmt->fetchAll();
    } catch (Exception $e) {
        $upcomingFollowUps = [];
    }
} catch (Exception $e) {
    $error = $error ?: 'Could not load referrals.';
}

function conditionText(array $ref): string
{
    return $ref['condition'] ?? ($ref['medical_condition'] ?? 'Not provided');
}

function followUpLabel(?string $date): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    $today = strtotime(date('Y-m-d'));
    $diff = (int)round(($ts - $today) / 86400);
    $label = date('M d, Y', $ts);
    if ($diff === 0) return $label . ' · Today';
    if ($diff === 1) return $label . ' · Tomorrow';
    if ($diff > 1 && $diff <= 7) return $label . ' · in ' . $diff . ' days';
    return $label;
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
    .top { margin-bottom:18px; }
    .top h1 { margin:0 0 6px; font-size:1.7rem; color:#0F1C3A !important; }
    .top p { margin:0; color:#64748B !important; }
    .back { color:#1EB53A !important; font-weight:600; text-decoration:none; }
    .alert { padding:13px 16px; border-radius:12px; margin-bottom:16px; border:1px solid transparent; font-weight:600; }
    .alert.success { background:#ECFDF5; border-color:#A7F3D0; color:#065F46; }
    .alert.error { background:#FEF2F2; border-color:#FECACA; color:#991B1B; }
    .section-title { font-size:1.05rem; font-weight:700; color:#0F1C3A !important; margin:22px 0 12px; }
    .case { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:18px; margin-bottom:12px; box-shadow:0 6px 16px rgba(15,23,42,0.04); }
    .case-head { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
    .case-head h3 { margin:0; font-size:1.05rem; color:#0F1C3A !important; }
    .meta { color:#64748B !important; font-size:0.9rem; line-height:1.5; }
    .cond { margin:10px 0 14px; color:#334155 !important; line-height:1.5; }
    .follow-chip { display:inline-block; margin-top:6px; padding:5px 10px; border-radius:999px; background:#EEF2FF; color:#3730A3 !important; font-size:0.8rem; font-weight:700; }
    .follow-chip.due-soon { background:#FEF3C7; color:#B45309 !important; }
    .follow-chip.due-today { background:#FEE2E2; color:#B91C1C !important; }
    .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:0.75rem; font-weight:700; }
    .badge.pending { background:#FEF3C7; color:#B45309 !important; }
    .badge.in_progress { background:#DBEAFE; color:#1D4ED8 !important; }
    .badge.completed { background:#DCFCE7; color:#15803D !important; }
    .actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .btn { border:none; border-radius:999px; padding:10px 16px; font-weight:600; cursor:pointer; font-size:0.9rem; }
    .btn-main { background:linear-gradient(135deg,#1EB53A,#15803D); color:#fff !important; }
    .btn-done { background:#0F1C3A; color:#fff !important; }
    .btn-soft { background:#EEF2FF; color:#3730A3 !important; }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:10px; }
    .field-row label { display:block; font-size:0.82rem; font-weight:600; color:#475569 !important; margin-bottom:5px; }
    .field-row input, .note-box textarea { width:100%; border:1.5px solid #E5E7EB; border-radius:10px; padding:10px 12px; font:inherit; }
    .note-box { width:100%; margin-top:10px; }
    .note-box textarea { min-height:70px; resize:vertical; }
    .empty { background:#fff; border:1px dashed #CBD5E1; border-radius:14px; padding:22px; color:#64748B !important; text-align:center; }
    @media (max-width:700px) { .field-row { grid-template-columns:1fr; } }
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
    <a class="back" href="provider-dashboard.php">← Back to dashboard</a>
    <h1>My Patient Referrals</h1>
    <p>Start care, mark patients done, and schedule follow-up dates. Patients see the same status on Track Referrals.</p>
  </div>

  <?php if ($message): ?><div class="alert success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="section-title">📅 Upcoming follow-ups</div>
  <?php if (empty($upcomingFollowUps)): ?>
    <div class="empty">No upcoming follow-up dates scheduled.</div>
  <?php else: ?>
    <?php foreach ($upcomingFollowUps as $ref):
      $fu = $ref['follow_up_date'] ?? null;
      $days = $fu ? (int)round((strtotime($fu) - strtotime(date('Y-m-d'))) / 86400) : 99;
      $chipClass = $days === 0 ? 'due-today' : ($days <= 3 ? 'due-soon' : '');
    ?>
      <div class="case">
        <div class="case-head">
          <h3><?= htmlspecialchars($ref['patient_name'] ?? 'Patient') ?></h3>
          <span class="follow-chip <?= $chipClass ?>"><?= htmlspecialchars(followUpLabel($fu)) ?></span>
        </div>
        <div class="meta">
          <?= htmlspecialchars($ref['contact'] ?? '') ?> · <?= htmlspecialchars($ref['location'] ?? '-') ?>
          · Status: <?= htmlspecialchars(ucfirst(str_replace('_',' ', $ref['status'] ?? ''))) ?>
        </div>
        <?php if (!empty($ref['follow_up_notes'])): ?>
          <div class="meta" style="margin-top:8px;"><strong>Follow-up note:</strong> <?= htmlspecialchars($ref['follow_up_notes']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="section-title">In progress</div>
  <?php if (empty($activeCases)): ?>
    <div class="empty">No active cases right now.</div>
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
        <?php if (!empty($ref['follow_up_date'])): ?>
          <div class="follow-chip"><?= htmlspecialchars(followUpLabel($ref['follow_up_date'])) ?></div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="referral_id" value="<?= (int)$ref['id'] ?>">
          <div class="field-row">
            <div>
              <label for="fu_<?= (int)$ref['id'] ?>">Follow-up date</label>
              <input type="date" id="fu_<?= (int)$ref['id'] ?>" name="follow_up_date" value="<?= htmlspecialchars($ref['follow_up_date'] ?? '') ?>">
            </div>
            <div>
              <label for="fn_<?= (int)$ref['id'] ?>">Follow-up note</label>
              <input type="text" id="fn_<?= (int)$ref['id'] ?>" name="follow_up_notes" value="<?= htmlspecialchars($ref['follow_up_notes'] ?? '') ?>" placeholder="e.g. BP check, lab review">
            </div>
          </div>
          <div class="note-box"><textarea name="note" placeholder="Optional care summary..."></textarea></div>
          <div class="actions" style="margin-top:10px;">
            <button type="submit" name="action" value="set_follow_up" class="btn btn-soft">📅 Save Follow-up</button>
            <button type="submit" name="action" value="done" class="btn btn-done" onclick="return confirm('Mark this patient as done? Patient will be notified.')">✅ Mark Patient as Done</button>
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
          <?php if (!empty($ref['assigned_to']) && (int)$ref['assigned_to'] === $user_id): ?> · <strong>Assigned to you</strong><?php endif; ?>
        </div>
        <div class="cond"><strong>Condition:</strong> <?= htmlspecialchars(conditionText($ref)) ?></div>
        <div class="actions">
          <form method="POST">
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
          <?php if (!empty($ref['follow_up_date'])): ?>
            · <span class="follow-chip"><?= htmlspecialchars(followUpLabel($ref['follow_up_date'])) ?></span>
          <?php endif; ?>
        </div>
        <div class="cond"><strong>Condition:</strong> <?= htmlspecialchars(conditionText($ref)) ?></div>
        <?php if (!empty($ref['notes'])): ?>
          <div class="meta"><strong>Note:</strong> <?= htmlspecialchars($ref['notes']) ?></div>
        <?php endif; ?>
        <form method="POST" style="margin-top:10px;">
          <input type="hidden" name="referral_id" value="<?= (int)$ref['id'] ?>">
          <input type="hidden" name="action" value="set_follow_up">
          <div class="field-row">
            <div>
              <label>Update follow-up date</label>
              <input type="date" name="follow_up_date" value="<?= htmlspecialchars($ref['follow_up_date'] ?? '') ?>">
            </div>
            <div>
              <label>Follow-up note</label>
              <input type="text" name="follow_up_notes" value="<?= htmlspecialchars($ref['follow_up_notes'] ?? '') ?>" placeholder="Optional note">
            </div>
          </div>
          <div class="actions" style="margin-top:10px;">
            <button type="submit" class="btn btn-soft">📅 Save Follow-up</button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
<script src="../js/dark-mode.js"></script>
</body>
</html>
