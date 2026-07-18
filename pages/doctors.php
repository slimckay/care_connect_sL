<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$search = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
$specialty = trim($_GET['specialty'] ?? '');
$loggedIn = isset($_SESSION['user_id']);
$role = strtolower($_SESSION['role'] ?? '');
$isPatient = $loggedIn && $role === 'patient';

$providers = [];
$specialties = [];

try {
    $sql = "
        SELECT p.*, u.name, u.email, u.id AS user_id
        FROM provider_profiles p
        JOIN users u ON p.user_id = u.id
        WHERE (p.verification_status = 'verified' OR p.verification_status IS NULL)
          AND u.role IN ('doctor', 'hospital')
    ";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (u.name LIKE ? OR p.specialty LIKE ? OR p.clinic_name LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($specialty !== '') {
        $sql .= " AND p.specialty LIKE ?";
        $params[] = '%' . $specialty . '%';
    }

    // Prefer accepting patients first when column exists
    try {
        $sqlOrder = $sql . " ORDER BY COALESCE(p.is_accepting_patients, 1) DESC, u.name ASC";
        $stmt = $conn->prepare($sqlOrder);
        $stmt->execute($params);
        $providers = $stmt->fetchAll();
    } catch (Exception $e) {
        $sqlOrder = $sql . " ORDER BY u.name ASC";
        $stmt = $conn->prepare($sqlOrder);
        $stmt->execute($params);
        $providers = $stmt->fetchAll();
    }

    try {
        $specialties = $conn->query("
            SELECT DISTINCT specialty FROM provider_profiles
            WHERE specialty IS NOT NULL AND specialty != ''
            ORDER BY specialty
        ")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $specialties = [];
    }
} catch (Exception $e) {
    // Fallback: show doctors even without full profile rows
    try {
        $providers = $conn->query("
            SELECT u.id AS user_id, u.name, u.email, u.role,
                   NULL AS specialty, NULL AS clinic_name, NULL AS clinic_address,
                   NULL AS experience_years, 1 AS is_accepting_patients,
                   'verified' AS verification_status, NULL AS profile_photo
            FROM users u
            WHERE u.role IN ('doctor', 'hospital')
            ORDER BY u.name ASC
            LIMIT 50
        ")->fetchAll();
    } catch (Exception $e2) {
        $providers = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find Verified Doctors — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/style.css">
  <base href="/">
  <style>
    .search-bar {
      display: flex;
      gap: 12px;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }
    .search-bar input {
      flex: 1;
      min-width: 260px;
      padding: 14px 18px;
      border: 2px solid #E5E7EB;
      border-radius: 12px;
      font-size: 1rem;
    }
    .search-bar select {
      padding: 14px 18px;
      border: 2px solid #E5E7EB;
      border-radius: 12px;
      min-width: 200px;
    }
    .provider-card {
      background: #fff;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.08);
      margin-bottom: 20px;
      border: 1px solid #e5e7eb;
    }
    .provider-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }
    .provider-photo {
      width: 64px; height: 64px; border-radius: 50%;
      object-fit: cover; border: 2px solid #1EB53A;
      background: #E2E8F0;
    }
    .provider-photo.fallback {
      display:flex; align-items:center; justify-content:center;
      font-weight:700; color:#64748B; font-size:1.2rem;
    }
    .availability {
      padding: 6px 14px;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .availability.available { background: #dcfce7; color: #166534; }
    .availability.busy { background: #fee2e2; color: #991b1b; }
    .provider-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 20px;
    }
    .provider-actions a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 44px;
      padding: 10px 18px;
      border-radius: 999px;
      font-weight: 600;
      text-decoration: none;
      font-size: 0.92rem;
    }
    .btn-msg {
      background: #0F1C3A;
      color: #fff !important;
    }
    .btn-ref {
      background: linear-gradient(135deg, #1EB53A, #15803D);
      color: #fff !important;
    }
    .btn-outline-doc {
      border: 2px solid #1EB53A;
      color: #1EB53A !important;
      background: transparent;
    }

    [data-theme="dark"] .provider-card {
      background: #1e293b;
      border-color: #334155;
    }
    [data-theme="dark"] .search-bar input,
    [data-theme="dark"] .search-bar select {
      background: #0f172a;
      color: #e2e8f0;
      border-color: #334155;
    }
  </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="/" class="logo">Care<span class="accent">Connect</span> SL</a>
    <nav>
      <ul class="nav-links">
        <li><a href="/">Home</a></li>
        <li><a href="/pages/doctors.php" class="active">Find Care</a></li>
        <li><a href="/pages/hospitals.html">Clinics</a></li>
        <li><a href="/pages/referral.html">Referrals</a></li>
      </ul>
    </nav>
    <div class="nav-actions">
      <button onclick="toggleDarkMode()" class="dark-toggle">🌓</button>
      <?php if ($loggedIn): ?>
        <a href="/dashboard/messages.php" class="btn-ghost">Messages</a>
        <a href="/logout.php" class="btn-ghost btn-logout">Log out</a>
      <?php else: ?>
        <a href="/login.php" class="btn-ghost">Sign In</a>
        <a href="/register.php" class="btn-primary">Get Started</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main style="max-width: 1100px; margin: 40px auto; padding: 0 20px;">
  <div style="text-align: center; margin-bottom: 40px;">
    <h1>Find Verified Doctors</h1>
    <p style="color: #64748B; max-width: 600px; margin: 12px auto 0;">
      Search providers, request a referral, or message them directly on Care Connect.
    </p>
  </div>

  <form method="GET" class="search-bar">
    <input type="text" name="search" placeholder="Search by name, clinic or specialty..." value="<?= htmlspecialchars($search) ?>">
    <select name="specialty">
      <option value="">All Specialties</option>
      <?php foreach ($specialties as $spec): ?>
        <option value="<?= htmlspecialchars($spec) ?>" <?= $specialty === $spec ? 'selected' : '' ?>>
          <?= htmlspecialchars($spec) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-primary" style="padding: 14px 28px;">Search</button>
  </form>

  <?php if (empty($providers)): ?>
    <div style="text-align: center; padding: 60px 20px; background: #f8fafc; border-radius: 16px;">
      <h3>No providers found</h3>
      <p style="color: #64748B; margin-top: 10px;">
        Try another search, or submit an open referral.
      </p>
      <a href="/pages/referral.html" class="btn-primary" style="margin-top: 20px; display: inline-block;">Submit Referral</a>
    </div>
  <?php else: ?>
    <?php foreach ($providers as $provider): ?>
      <?php
        $uid = (int)($provider['user_id'] ?? 0);
        $name = $provider['name'] ?? 'Provider';
        $spec = $provider['specialty'] ?? 'General Practice';
        $clinic = $provider['clinic_name'] ?? '';
        $address = $provider['clinic_address'] ?? ($provider['location'] ?? 'Sierra Leone');
        $years = (int)($provider['experience_years'] ?? 0);
        $accepting = array_key_exists('is_accepting_patients', $provider)
            ? (int)$provider['is_accepting_patients'] === 1
            : true;
        $photo = $provider['profile_photo'] ?? '';
        $photoUrl = ($photo && file_exists(__DIR__ . '/../' . $photo)) ? '/' . ltrim($photo, '/') : '';
        $initials = strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 2));
      ?>
      <div class="provider-card">
        <div class="provider-header">
          <div style="display:flex; gap:14px; align-items:center;">
            <?php if ($photoUrl): ?>
              <img class="provider-photo" src="<?= htmlspecialchars($photoUrl) ?>" alt="<?= htmlspecialchars($name) ?>">
            <?php else: ?>
              <div class="provider-photo fallback"><?= htmlspecialchars($initials) ?></div>
            <?php endif; ?>
            <div>
              <h3 style="margin: 0 0 6px;"><?= htmlspecialchars($name) ?></h3>
              <p style="margin: 0; color: #64748B;"><?= htmlspecialchars($spec) ?></p>
              <?php if ($clinic): ?>
                <p style="margin: 4px 0 0; color: #64748B; font-size: 0.92rem;">🏥 <?= htmlspecialchars($clinic) ?></p>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <?php if ($accepting): ?>
              <span class="availability available">✅ Available</span>
            <?php else: ?>
              <span class="availability busy">⏳ Not accepting</span>
            <?php endif; ?>
          </div>
        </div>

        <div style="display: flex; gap: 30px; flex-wrap: wrap; margin: 12px 0;">
          <div>
            <strong>Experience:</strong><br>
            <?= $years ?> years
          </div>
          <div>
            <strong>Location:</strong><br>
            <?= htmlspecialchars($address) ?>
          </div>
        </div>

        <div class="provider-actions">
          <a class="btn-ref" href="/pages/referral.html?doctor=<?= $uid ?>">📋 Request Referral</a>

          <?php if ($isPatient): ?>
            <a class="btn-msg" href="/dashboard/messages.php?start=<?= $uid ?>">💬 Message</a>
          <?php elseif ($loggedIn): ?>
            <a class="btn-outline-doc" href="/dashboard/messages.php">💬 Messages</a>
          <?php else: ?>
            <a class="btn-msg" href="/login.php?redirect=<?= urlencode('/dashboard/messages.php?start=' . $uid) ?>">💬 Message</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<script src="/js/dark-mode.js"></script>
<script src="/js/mobile-logout.js"></script>
</body>
</html>
