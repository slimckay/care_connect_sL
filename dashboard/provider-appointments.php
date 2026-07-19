<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || !in_array($role, ['doctor','hospital'], true)) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../db.php';

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Provider';

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        provider_id INT NOT NULL,
        referral_id INT NULL,
        visit_type VARCHAR(20) NOT NULL DEFAULT 'home_visit',
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        patient_name VARCHAR(150) NOT NULL,
        patient_phone VARCHAR(40) NULL,
        address TEXT NULL,
        area VARCHAR(120) NULL,
        reason TEXT NULL,
        urgency VARCHAR(20) NOT NULL DEFAULT 'normal',
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        provider_notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $map = ['confirm' => 'confirmed', 'complete' => 'completed', 'cancel' => 'cancelled'];
    if ($id > 0 && isset($map[$action])) {
        try {
            $conn->prepare("UPDATE appointments SET status=?, updated_at=NOW() WHERE id=? AND provider_id=?")
                 ->execute([$map[$action], $id, $userId]);
            // notify patient
            try {
                $p = $conn->prepare('SELECT patient_id, appointment_date FROM appointments WHERE id=?');
                $p->execute([$id]);
                $row = $p->fetch();
                if ($row) {
                    $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                                    VALUES (?, 'appointment', ?, ?, 'dashboard/my-appointments.php', 0, NOW())")
                         ->execute([
                             (int)$row['patient_id'],
                             'Appointment ' . $map[$action],
                             'Your appointment on ' . $row['appointment_date'] . ' is now ' . $map[$action] . '.'
                         ]);
                }
            } catch (Exception $e) {}
        } catch (Exception $e) {}
    }
    header('Location: provider-appointments.php');
    exit;
}

rows = [];
try {
    $stmt = $conn->prepare("
        SELECT a.*
        FROM appointments a
        WHERE a.provider_id = ?
        ORDER BY FIELD(a.status,'pending','confirmed','completed','cancelled'),
                 a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointments — Provider — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    body{background:#F4F7FB}.wrap{max-width:960px;margin:28px auto 48px;padding:0 16px}
    h1{margin:0 0 8px;color:#0F1C3A!important}.sub{color:#64748B;margin:0 0 20px}
    .card{background:#fff;border:1px solid #E5E7EB;border-radius:16px;padding:18px;margin-bottom:12px}
    .row{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.78rem;font-weight:700}
    .badge.pending{background:#FEF3C7;color:#92400E}.badge.confirmed{background:#DCFCE7;color:#166534}
    .badge.completed{background:#E0E7FF;color:#3730A3}.badge.cancelled{background:#F1F5F9;color:#64748B}
    .badge.urgent{background:#FEE2E2;color:#991B1B;margin-left:6px}
    .meta{color:#64748B;font-size:.9rem;margin-top:6px;line-height:1.5}
    .actions{display:flex;gap:8px;flex-wrap:wrap}
    .btn-sm{border:none;border-radius:999px;padding:8px 14px;font-weight:600;font-size:.85rem;cursor:pointer}
    .btn-ok{background:#1EB53A;color:#fff}.btn-done{background:#0F1C3A;color:#fff}.btn-no{background:#F1F5F9;color:#334155}
    .empty{text-align:center;padding:40px;background:#fff;border-radius:16px;border:1px solid #E5E7EB;color:#64748B}
  </style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="../index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <a href="provider-dashboard.php" class="btn-ghost">Dashboard</a>
      <a href="messages.php" class="btn-ghost">Messages</a>
      <a href="../logout.php" class="btn-ghost btn-logout">Log out</a>
    </div>
  </div>
</header>
<main class="wrap">
  <h1>📅 Appointments</h1>
  <p class="sub">Home visits and clinic bookings assigned to you, <?= htmlspecialchars($userName) ?></p>

  <?php if (empty($rows)): ?>
    <div class="empty">No appointment requests yet. Patients book from the appointment page.</div>
  <?php else: ?>
    <?php foreach ($rows as $a): ?>
      <div class="card">
        <div class="row">
          <div>
            <strong style="color:#0F1C3A"><?= htmlspecialchars($a['patient_name']) ?></strong>
            <span class="badge <?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars(ucfirst($a['status'])) ?></span>
            <?php if ($a['urgency'] === 'urgent'): ?><span class="badge urgent">Urgent</span><?php endif; ?>
            <div class="meta">
              <?= $a['visit_type'] === 'home_visit' ? '🏠 Home visit' : '🏥 Clinic' ?>
              · <?= date('D, M j, Y', strtotime($a['appointment_date'])) ?>
              · <?= date('g:i A', strtotime($a['appointment_time'])) ?><br>
              <?php if ($a['patient_phone']): ?>📞 <?= htmlspecialchars($a['patient_phone']) ?><br><?php endif; ?>
              <?php if ($a['address']): ?>📍 <?= htmlspecialchars($a['address']) ?><?php if ($a['area']): ?>, <?= htmlspecialchars($a['area']) ?><?php endif; ?><br><?php endif; ?>
              <?php if ($a['reason']): ?>💬 <?= htmlspecialchars($a['reason']) ?><?php endif; ?>
            </div>
          </div>
          <div class="actions">
            <?php if ($a['status'] === 'pending'): ?>
              <form method="POST"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="action" value="confirm"><button class="btn-sm btn-ok" type="submit">Confirm</button></form>
              <form method="POST"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="action" value="cancel"><button class="btn-sm btn-no" type="submit">Decline</button></form>
            <?php elseif ($a['status'] === 'confirmed'): ?>
              <form method="POST"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="action" value="complete"><button class="btn-sm btn-done" type="submit">Mark done</button></form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
<script src="../js/dark-mode.js"></script>
<script src="../js/mobile-logout.js"></script>
</body>
</html>
