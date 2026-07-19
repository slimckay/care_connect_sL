<?php
/**
 * Book appointment / home visit — Care Connect SL
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db.php';

$loggedIn = isset($_SESSION['user_id']);
$role = strtolower($_SESSION['role'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? '';

if ($loggedIn && $role !== 'patient') {
    header('Location: ../dashboard/' . ($role === 'admin' ? '../admin/admin-dashboard.php' : 'provider-dashboard.php'));
    exit;
}

// Ensure table exists
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
        updated_at DATETIME NULL,
        INDEX idx_patient (patient_id),
        INDEX idx_provider (provider_id),
        INDEX idx_date (appointment_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$preDoctor = (int)($_GET['doctor'] ?? 0);
$message = '';
$error = '';

// Load providers
$providers = [];
try {
    $providers = $conn->query("
        SELECT u.id, u.name, p.specialty, p.clinic_name, p.consultation_fee
        FROM users u
        LEFT JOIN provider_profiles p ON p.user_id = u.id
        WHERE u.role IN ('doctor','hospital')
          AND (p.verification_status = 'verified' OR p.verification_status IS NULL)
        ORDER BY u.name ASC
        LIMIT 80
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $providers = $conn->query("SELECT id, name, NULL specialty, NULL clinic_name, NULL consultation_fee FROM users WHERE role IN ('doctor','hospital') ORDER BY name LIMIT 80")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $providers = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$loggedIn || $role !== 'patient') {
        header('Location: ../login.php?redirect=' . urlencode('/pages/appointment.php'));
        exit;
    }

    $providerId = (int)($_POST['provider_id'] ?? 0);
    $visitType = $_POST['visit_type'] === 'clinic' ? 'clinic' : 'home_visit';
    $date = trim($_POST['appointment_date'] ?? '');
    $time = trim($_POST['appointment_time'] ?? '');
    $patientName = trim($_POST['patient_name'] ?? $userName);
    $phone = trim($_POST['patient_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $urgency = $_POST['urgency'] === 'urgent' ? 'urgent' : 'normal';

    if ($providerId <= 0) {
        $error = 'Please choose a doctor or clinic.';
    } elseif ($date === '' || $time === '') {
        $error = 'Please choose a date and time.';
    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
        $error = 'Appointment date cannot be in the past.';
    } elseif ($visitType === 'home_visit' && $address === '') {
        $error = 'Home visits need an address.';
    } elseif ($patientName === '') {
        $error = 'Patient name is required.';
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO appointments
                (patient_id, provider_id, visit_type, appointment_date, appointment_time,
                 patient_name, patient_phone, address, area, reason, urgency, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $userId, $providerId, $visitType, $date, $time,
                $patientName, $phone ?: null, $address ?: null, $area ?: null,
                $reason ?: null, $urgency
            ]);
            $apptId = (int)$conn->lastInsertId();

            try {
                $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                                VALUES (?, 'appointment', 'New appointment request', ?, ?, 0, NOW())")
                     ->execute([
                         $providerId,
                         $patientName . ' requested a ' . ($visitType === 'home_visit' ? 'home visit' : 'clinic visit') . ' on ' . $date,
                         'dashboard/provider-appointments.php'
                     ]);
            } catch (Exception $e) {}

            header('Location: ../dashboard/my-appointments.php?booked=1');
            exit;
        } catch (Exception $e) {
            error_log('appointment book: ' . $e->getMessage());
            $error = 'Could not book appointment. Please try again.';
        }
    }
}

$minDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book Appointment — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/style.css">
  <style>
    body { background:#F4F7FB; }
    .ap-wrap { max-width:720px; margin:28px auto 48px; padding:0 16px; }
    .ap-card { background:#fff; border:1px solid #E5E7EB; border-radius:18px; padding:28px 24px; box-shadow:0 8px 24px rgba(15,23,42,.05); }
    .ap-card h1 { margin:0 0 6px; font-size:1.55rem; color:#0F1C3A !important; }
    .ap-card .sub { color:#64748B; margin:0 0 22px; line-height:1.5; }
    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-weight:600; font-size:.9rem; margin-bottom:6px; color:#1E293B !important; }
    .form-group input, .form-group select, .form-group textarea {
      width:100%; padding:12px 14px; border:1.5px solid #E2E8F0; border-radius:12px; font:inherit; background:#fff;
    }
    .form-group textarea { min-height:90px; resize:vertical; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .visit-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .visit-grid input { position:absolute; opacity:0; pointer-events:none; }
    .visit-grid label {
      display:block; padding:14px; border:1.5px solid #E2E8F0; border-radius:14px; cursor:pointer; text-align:center; font-weight:600;
    }
    .visit-grid input:checked + label { border-color:#1EB53A; background:#F0FDF4; color:#14532D; }
    .alert { padding:12px 14px; border-radius:12px; margin-bottom:16px; font-size:.95rem; }
    .alert.error { background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; }
    .btn-book {
      width:100%; border:none; border-radius:12px; padding:14px; font-weight:700; font-size:1rem;
      background:linear-gradient(135deg,#1EB53A,#15803D); color:#fff; cursor:pointer; margin-top:8px;
    }
    .login-note { background:#FFF7ED; border:1px solid #FED7AA; border-radius:12px; padding:14px; margin-bottom:16px; color:#9A3412; }
    @media (max-width:640px){ .form-row, .visit-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="/" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <?php if ($loggedIn): ?>
        <a href="/dashboard/my-appointments.php" class="btn-ghost">My Appointments</a>
        <a href="/dashboard/patient-dashboard.php" class="btn-ghost">Dashboard</a>
      <?php else: ?>
        <a href="/login.php?redirect=<?= urlencode('/pages/appointment.php') ?>" class="btn-ghost">Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="ap-wrap">
  <div class="ap-card">
    <h1>📅 Book a visit</h1>
    <p class="sub">Request a home visit or clinic appointment with a Care Connect provider in Sierra Leone.</p>

    <?php if ($error): ?><div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (!$loggedIn): ?>
      <div class="login-note">
        You need a patient account to book.
        <a href="/login.php?redirect=<?= urlencode('/pages/appointment.php') ?>" style="color:#1EB53A;font-weight:700;">Sign in</a>
        or <a href="/register.php" style="color:#1EB53A;font-weight:700;">create an account</a>.
      </div>
    <?php endif; ?>

    <form method="POST" <?= !$loggedIn ? 'onsubmit="location.href=\'/login.php?redirect=/pages/appointment.php\';return false;"' : '' ?>>
      <div class="form-group">
        <label>Visit type</label>
        <div class="visit-grid">
          <div>
            <input type="radio" name="visit_type" id="vt_home" value="home_visit" checked>
            <label for="vt_home">🏠 Home visit</label>
          </div>
          <div>
            <input type="radio" name="visit_type" id="vt_clinic" value="clinic">
            <label for="vt_clinic">🏥 Clinic visit</label>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label for="provider_id">Doctor / clinic *</label>
        <select name="provider_id" id="provider_id" required>
          <option value="">Select provider...</option>
          <?php foreach ($providers as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $preDoctor === (int)$p['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['name']) ?><?= !empty($p['specialty']) ? ' — ' . htmlspecialchars($p['specialty']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="appointment_date">Date *</label>
          <input type="date" name="appointment_date" id="appointment_date" min="<?= $minDate ?>" required>
        </div>
        <div class="form-group">
          <label for="appointment_time">Time *</label>
          <input type="time" name="appointment_time" id="appointment_time" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="patient_name">Patient name *</label>
          <input type="text" name="patient_name" id="patient_name" value="<?= htmlspecialchars($userName) ?>" required>
        </div>
        <div class="form-group">
          <label for="patient_phone">Phone</label>
          <input type="tel" name="patient_phone" id="patient_phone" placeholder="+232 ...">
        </div>
      </div>

      <div class="form-group" id="addressGroup">
        <label for="address">Home address *</label>
        <input type="text" name="address" id="address" placeholder="Street, landmark, community">
      </div>

      <div class="form-group">
        <label for="area">Area / district</label>
        <input type="text" name="area" id="area" placeholder="e.g. Kissy, Waterloo, Bo">
      </div>

      <div class="form-group">
        <label for="urgency">Urgency</label>
        <select name="urgency" id="urgency">
          <option value="normal">Normal</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>

      <div class="form-group">
        <label for="reason">Reason for visit</label>
        <textarea name="reason" id="reason" placeholder="Briefly describe the problem or what you need"></textarea>
      </div>

      <button type="submit" class="btn-book">Request appointment</button>
    </form>
  </div>
</main>

<script src="/js/dark-mode.js"></script>
<script>
  function syncVisit() {
    const home = document.getElementById('vt_home').checked;
    const addr = document.getElementById('address');
    document.getElementById('addressGroup').style.display = home ? 'block' : 'none';
    addr.required = home;
  }
  document.getElementById('vt_home').addEventListener('change', syncVisit);
  document.getElementById('vt_clinic').addEventListener('change', syncVisit);
  syncVisit();
</script>
</body>
</html>
