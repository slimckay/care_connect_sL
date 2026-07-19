<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../db.php';

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Patient';
$booked = isset($_GET['booked']);

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

// Cancel own pending appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $conn->prepare("UPDATE appointments SET status='cancelled', updated_at=NOW() WHERE id=? AND patient_id=? AND status='pending'")
             ->execute([$id, $userId]);
    } catch (Exception $e) {}
    header('Location: my-appointments.php');
    exit;
}

$rows = [];
try {
    $stmt = $conn->prepare("
        SELECT a.*, u.name AS provider_name
        FROM appointments a
        JOIN users u ON u.id = a.provider_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
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
  <title>My Appointments — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    body{background:#F4F7FB}.wrap{max-width:900px;margin:28px auto 48px;padding:0 16px}
    h1{margin:0 0 8px;color:#0F1C3A!important}.sub{color:#64748B;margin:0 0 20px}
    .top{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:18px}
    .card{background:#fff;border:1px solid #E5E7EB;border-radius:16px;padding:18px;margin-bottom:12px;box-shadow:0 4px 14px rgba(15,23,42,.04)}
    .row{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.78rem;font-weight:700}
    .badge.pending{background:#FEF3C7;color:#92400E}.badge.confirmed{background:#DCFCE7;color:#166534}
    .badge.completed{background:#E0E7FF;color:#3730A3}.badge.cancelled{background:#F1F5F9;color:#64748B}
    .badge.urgent{background:#FEE2E2;color:#991B1B;margin-left:6px}
    .meta{color:#64748B;font-size:.9rem;margin-top:6px;line-height:1.45}
    .alert{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;padding:12px 14px;border-radius:12px;margin-bottom:16px}
    .btn-sm{display:inline-block;padding:8px 14px;border-radius:999px;font-weight:600;font-size:.88rem;text-decoration:none;border:none;cursor:pointer}
    .btn-primary-sm{background:#1EB53A;color:#fff!important}.btn-ghost-sm{background:#F1F5F9;color:#334155!important}
    .empty{text-align:center;padding:40px 16px;background:#fff;border-radius:16px;border:1px solid #E5E7EB}
  </style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="../index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <a href="patient-dashboard.php" class="btn-ghost">Dashboard</a>
      <a href="../pages/appointment.php" class="btn-primary">Book visit</a>
      <a href="../logout.php" class="btn-ghost btn-logout">Log out</a>
    </div>
  </div>
</header>
<main class="wrap">
  <div class="top">
    <div>
      <h1>📅 My appointments</h1>
      <p class="sub">Home visits and clinic bookings for <?= htmlspecialchars($userName) ?></p>
    </div>
    <a class="btn-sm btn-primary-sm" href="../pages/appointment.php">+ Book new</a>
  </div>

  <?php if ($booked): ?><div class="alert">✅ Appointment requested. The provider will confirm soon.</div><?php endif; ?>

  <?php if (empty($rows)): ?>
    <div class="empty">
      <p>No appointments yet.</p>
      <a class="btn-sm btn-primary-sm" href="../pages/appointment.php" style="margin-top:12px">Book a visit</a>
    </div>
  <?php else: ?>
    <?php foreach ($rows as $a): ?>
      <div class="card">
        <div class="row">
          <div>
            <strong style="color:#0F1C3A"><?= htmlspecialchars($a['provider_name']) ?></strong>
            <span class="badge <?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars(ucfirst($a['status'])) ?></span>
            <?php if ($a['urgency'] === 'urgent'): ?><span class="badge urgent">Urgent</span><?php endif; ?>
            <div class="meta">
              <?= $a['visit_type'] === 'home_visit' ? '🏠 Home visit' : '🏥 Clinic' ?>
              · <?= date('D, M j, Y', strtotime($a['appointment_date'])) ?>
              · <?= date('g:i A', strtotime($a['appointment_time'])) ?><br>
              <?php if ($a['address']): ?>📍 <?= htmlspecialchars($a['address']) ?><?php if ($a['area']): ?>, <?= htmlspecialchars($a['area']) ?><?php endif; ?><br><?php endif; ?>
              <?php if ($a['reason']): ?><?= htmlspecialchars(mb_substr($a['reason'], 0, 120)) ?><?php endif; ?>
            </div>
          </div>
          <div>
            <?php if ($a['status'] === 'pending'): ?>
              <form method="POST" onsubmit="return confirm('Cancel this appointment?')">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn-sm btn-ghost-sm" type="submit">Cancel</button>
              </form>
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
