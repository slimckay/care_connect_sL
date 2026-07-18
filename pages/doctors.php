<?php
require_once __DIR__ . '/../db.php';

$search = trim($_GET['search'] ?? '');
$specialty = trim($_GET['specialty'] ?? '');

$sql = "
    SELECT p.*, u.name, u.email 
    FROM provider_profiles p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.verification_status = 'verified'
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR p.specialty LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($specialty)) {
    $sql .= " AND p.specialty LIKE ?";
    $params[] = "%$specialty%";
}

$sql .= " ORDER BY p.is_available DESC, u.name ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $providers = $stmt->fetchAll();
} catch (Exception $e) {
    $providers = [];
}

// Get unique specialties for filter
$specialties = $conn->query("SELECT DISTINCT specialty FROM provider_profiles WHERE verification_status = 'verified' AND specialty IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
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
      margin-bottom: 16px;
    }
    .availability {
      padding: 6px 14px;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .availability.available { background: #dcfce7; color: #166534; }
    .availability.busy { background: #fee2e2; color: #991b1b; }

    [data-theme="dark"] .provider-card {
      background: #1e293b;
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
      <a href="/login.php" class="btn-ghost">Sign In</a>
      <a href="/register.php" class="btn-primary">Get Started</a>
    </div>
  </div>
</header>

<main style="max-width: 1100px; margin: 40px auto; padding: 0 20px;">
  <div style="text-align: center; margin-bottom: 40px;">
    <h1>Find Verified Doctors</h1>
    <p style="color: #64748B; max-width: 600px; margin: 12px auto 0;">
      Search for verified healthcare providers. Only verified professionals are shown.
    </p>
  </div>

  <!-- Search Bar -->
  <form method="GET" class="search-bar">
    <input type="text" name="search" placeholder="Search by name or specialty..." value="<?= htmlspecialchars($search) ?>">
    
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
      <h3>No verified providers found</h3>
      <p style="color: #64748B; margin-top: 10px;">
        We are currently verifying more healthcare providers. Try broadening your search.
      </p>
      <a href="/ai-chat.php" class="btn-primary" style="margin-top: 20px; display: inline-block;">💬 Ask AI for Help</a>
    </div>
  <?php else: ?>
    <?php foreach ($providers as $provider): ?>
      <div class="provider-card">
        <div class="provider-header">
          <div>
            <h3 style="margin: 0 0 6px;"><?= htmlspecialchars($provider['name']) ?></h3>
            <p style="margin: 0; color: #64748B;"><?= htmlspecialchars($provider['specialty'] ?? 'General Practice') ?></p>
          </div>
          
          <div>
            <?php if (!empty($provider['is_available']) && $provider['is_available']): ?>
              <span class="availability available">✅ Available</span>
            <?php else: ?>
              <span class="availability busy">⏳ Currently Busy</span>
            <?php endif; ?>
          </div>
        </div>

        <div style="display: flex; gap: 30px; flex-wrap: wrap; margin: 20px 0;">
          <div>
            <strong>Experience:</strong><br>
            <?= $provider['experience_years'] ?? 0 ?> years
          </div>
          <div>
            <strong>Location:</strong><br>
            <?= htmlspecialchars($provider['location'] ?? 'Sierra Leone') ?>
          </div>
        </div>

        <div style="margin-top: 20px;">
          <a href="/pages/referral.html?provider=<?= $provider['user_id'] ?>" 
             class="btn-primary" style="padding: 12px 24px; text-decoration: none; display: inline-block;">
            Request Referral
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<script src="/js/dark-mode.js"></script>
</body>
</html>