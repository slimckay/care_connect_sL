<?php
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
    $check = $conn->prepare("SELECT id FROM provider_profiles WHERE user_id = ? LIMIT 1");
    $check->execute([$user_id]);
    if (!$check->fetch()) {
        $conn->prepare("INSERT INTO provider_profiles (user_id, verification_status, created_at) VALUES (?, 'pending', NOW())")
             ->execute([$user_id]);
    }
} catch (Exception $e) {}

$unreadMessages = 0;
try {
    $um = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM chat_messages m
        JOIN conversations c ON c.id = m.conversation_id
        WHERE c.provider_id = ? AND m.sender_id != ? AND m.is_read = 0
    ");
    $um->execute([$user_id, $user_id]);
    $unreadMessages = (int)($um->fetch()['c'] ?? 0);
} catch (Exception $e) {
    $unreadMessages = 0;
}

$pendingReferrals = 0;
try {
    $pr = $conn->prepare("SELECT COUNT(*) AS c FROM referrals WHERE assigned_to = ? AND status = 'pending'");
    $pr->execute([$user_id]);
    $pendingReferrals = (int)($pr->fetch()['c'] ?? 0);
} catch (Exception $e) {
    $pendingReferrals = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/profile/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowed)) {
            $error = 'Photo must be JPG, PNG, WEBP, or GIF.';
        } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
            $error = 'Photo is too large. Max size is 5MB.';
        } else {
            $filename = 'provider_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $filename)) {
                try {
                    $conn->prepare("UPDATE provider_profiles SET profile_photo = ?, updated_at = NOW() WHERE user_id = ?")
                         ->execute(['uploads/profile/' . $filename, $user_id]);
                    $message = 'Profile photo updated.';
                } catch (Exception $e) {
                    $error = 'Photo saved but database update failed.';
                }
            } else {
                $error = 'Could not upload photo.';
            }
        }
    } else {
        $error = 'Please choose a photo to upload.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $specialty = trim($_POST['specialty'] ?? '');
    $qualifications = trim($_POST['qualifications'] ?? '');
    $experience = (int)($_POST['experience_years'] ?? 0);
    $clinic_name = trim($_POST['clinic_name'] ?? '');
    $clinic_address = trim($_POST['clinic_address'] ?? '');
    $clinic_phone = trim($_POST['clinic_phone'] ?? '');
    $consultation_fee = $_POST['consultation_fee'] !== '' ? (float)$_POST['consultation_fee'] : null;
    $is_accepting = isset($_POST['is_accepting_patients']) ? 1 : 0;

    if ($specialty === '') {
        $error = 'Specialty is required.';
    } else {
        try {
            $conn->prepare("
                UPDATE provider_profiles SET
                    specialty = ?, qualifications = ?, experience_years = ?,
                    clinic_name = ?, clinic_address = ?, clinic_phone = ?,
                    consultation_fee = ?, is_accepting_patients = ?, updated_at = NOW()
                WHERE user_id = ?
            ")->execute([
                $specialty, $qualifications, $experience, $clinic_name,
                $clinic_address, $clinic_phone, $consultation_fee, $is_accepting, $user_id
            ]);
            $message = 'Profile saved.';
        } catch (Exception $e) {
            $error = 'Could not save profile.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_docs') {
    $uploadDir = __DIR__ . '/../uploads/verification/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $documentPaths = [];
    if (!empty($_FILES['documents']['name'][0])) {
        foreach ($_FILES['documents']['name'] as $key => $filename) {
            if ($_FILES['documents']['error'][$key] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','pdf'])) continue;
            if ($_FILES['documents']['size'][$key] > 8 * 1024 * 1024) continue;
            $newName = 'doc_' . $user_id . '_' . time() . '_' . $key . '.' . $ext;
            if (move_uploaded_file($_FILES['documents']['tmp_name'][$key], $uploadDir . $newName)) {
                $documentPaths[] = 'uploads/verification/' . $newName;
            }
        }
    }
    if (empty($documentPaths)) {
        $error = 'Upload at least one valid document (JPG/PNG/PDF, max 8MB).';
    } else {
        try {
            $prev = $conn->prepare("SELECT verification_documents FROM provider_profiles WHERE user_id = ?");
            $prev->execute([$user_id]);
            $row = $prev->fetch();
            $existing = array_filter(array_map('trim', explode(',', $row['verification_documents'] ?? '')));
            $all = array_values(array_unique(array_merge($existing, $documentPaths)));
            $conn->prepare("UPDATE provider_profiles SET verification_documents = ?, verification_status = 'pending', updated_at = NOW() WHERE user_id = ?")
                 ->execute([implode(',', $all), $user_id]);
            $message = 'Documents uploaded. Verification is pending.';
        } catch (Exception $e) {
            $error = 'Could not save documents.';
        }
    }
}

$profile = null;
try {
    $stmt = $conn->prepare("SELECT p.*, u.name, u.email, u.role FROM provider_profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
} catch (Exception $e) {
    $profile = null;
}

if (!$profile) {
    try {
        $u = $conn->prepare("SELECT name, email, role FROM users WHERE id = ? LIMIT 1");
        $u->execute([$user_id]);
        $user = $u->fetch() ?: [];
    } catch (Exception $e) { $user = []; }
    $name = $user['name'] ?? ($_SESSION['user_name'] ?? 'Provider');
    $email = $user['email'] ?? '';
    $role = $user['role'] ?? 'doctor';
    $photo = '';
    $verificationStatus = 'pending';
    $specialty = '';
    $experience = 0;
    $qualifications = '';
    $clinic_name = '';
    $clinic_address = '';
    $clinic_phone = '';
    $consultation_fee = '';
    $is_accepting = 1;
    $docs = [];
} else {
    $name = $profile['name'] ?? 'Provider';
    $email = $profile['email'] ?? '';
    $role = $profile['role'] ?? 'doctor';
    $photo = $profile['profile_photo'] ?? '';
    $verificationStatus = $profile['verification_status'] ?? 'pending';
    $specialty = $profile['specialty'] ?? '';
    $experience = (int)($profile['experience_years'] ?? 0);
    $qualifications = $profile['qualifications'] ?? '';
    $clinic_name = $profile['clinic_name'] ?? '';
    $clinic_address = $profile['clinic_address'] ?? '';
    $clinic_phone = $profile['clinic_phone'] ?? '';
    $consultation_fee = $profile['consultation_fee'] ?? '';
    $is_accepting = (int)($profile['is_accepting_patients'] ?? 1);
    $docs = array_filter(array_map('trim', explode(',', $profile['verification_documents'] ?? '')));
}

$statusLabel = ucfirst($verificationStatus);
$statusIcon = match ($verificationStatus) { 'verified' => '✅', 'rejected' => '❌', default => '⏳' };
$statusClass = match ($verificationStatus) { 'verified' => 'ok', 'rejected' => 'bad', default => 'wait' };
$photoUrl = ($photo && file_exists(__DIR__ . '/../' . $photo)) ? '../' . $photo : '';
$initials = strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#0F1C3A">
  <title>Provider Dashboard — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    :root {
      --bg: #F4F7FB;
      --card: #fff;
      --text: #0F1C3A;
      --muted: #64748B;
      --line: #E5E7EB;
      --green: #1EB53A;
      --green-dark: #15803D;
      --navy: #0F1C3A;
      --safe-bottom: env(safe-area-inset-bottom, 0px);
    }
    * { box-sizing: border-box; }
    body { background: var(--bg); margin: 0; font-family: Inter, system-ui, sans-serif; color: var(--text); }

    /* Top bar */
    .topbar {
      position: sticky; top: 0; z-index: 50;
      background: var(--navy);
      color: #fff;
      padding: 10px 14px;
      display: flex; align-items: center; gap: 10px;
      min-height: 56px;
    }
    .topbar .brand { flex: 1; min-width: 0; text-decoration: none; }
    .topbar .brand strong { color: #fff; font-size: 1rem; display: block; line-height: 1.2; }
    .topbar .brand span { color: #86EFAC; font-size: 0.75rem; }
    .topbar .icon {
      width: 40px; height: 40px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      background: rgba(255,255,255,0.12); color: #fff; text-decoration: none;
      font-size: 1.05rem; border: none; cursor: pointer; position: relative;
    }
    .topbar .badge {
      position: absolute; top: -2px; right: -2px;
      min-width: 18px; height: 18px; padding: 0 5px;
      border-radius: 999px; background: #EF4444; color: #fff;
      font-size: 0.68rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
    }

    .wrap { max-width: 920px; margin: 0 auto; padding: 14px 14px calc(90px + var(--safe-bottom)); }

    /* Profile hero */
    .hero {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 18px;
      padding: 16px;
      display: flex; gap: 14px; align-items: center;
      margin-bottom: 14px;
      box-shadow: 0 4px 14px rgba(15,23,42,0.04);
    }
    .hero-photo {
      width: 64px; height: 64px; border-radius: 50%; object-fit: cover;
      border: 2px solid var(--green); background: #E2E8F0; flex-shrink: 0;
    }
    .hero-fallback {
      width: 64px; height: 64px; border-radius: 50%;
      background: linear-gradient(135deg, #0F1C3A, #1EB53A);
      color: #fff; display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 1.1rem; flex-shrink: 0;
    }
    .hero h1 { margin: 0 0 4px; font-size: 1.15rem; color: var(--text); }
    .hero .meta { margin: 0; color: var(--muted); font-size: 0.88rem; }
    .chip {
      display: inline-flex; align-items: center; gap: 4px;
      margin-top: 8px; padding: 4px 10px; border-radius: 999px;
      font-size: 0.78rem; font-weight: 700;
    }
    .chip.ok { background: #DCFCE7; color: #166534; }
    .chip.wait { background: #FEF3C7; color: #92400E; }
    .chip.bad { background: #FEE2E2; color: #991B1B; }

    /* Quick actions */
    .quick {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 16px;
    }
    .quick a {
      display: flex; flex-direction: column; align-items: flex-start; gap: 6px;
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 14px;
      text-decoration: none;
      min-height: 88px;
      box-shadow: 0 2px 10px rgba(15,23,42,0.03);
      position: relative;
    }
    .quick a.primary {
      background: linear-gradient(135deg, #0F1C3A, #14532D);
      border-color: transparent;
    }
    .quick a.primary .q-title,
    .quick a.primary .q-sub { color: #fff !important; }
    .quick .q-ico { font-size: 1.35rem; line-height: 1; }
    .quick .q-title { font-weight: 700; font-size: 0.95rem; color: var(--text); }
    .quick .q-sub { font-size: 0.78rem; color: var(--muted); }
    .quick .q-count {
      position: absolute; top: 10px; right: 10px;
      background: #EF4444; color: #fff; font-size: 0.72rem; font-weight: 700;
      min-width: 20px; height: 20px; border-radius: 999px;
      display: flex; align-items: center; justify-content: center; padding: 0 6px;
    }

    .alert {
      padding: 12px 14px; border-radius: 12px; margin-bottom: 12px;
      font-size: 0.92rem; border: 1px solid transparent;
    }
    .alert.ok { background: #ECFDF5; border-color: #A7F3D0; color: #065F46; }
    .alert.err { background: #FEF2F2; border-color: #FECACA; color: #991B1B; }

    /* Accordion sections */
    .section {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 16px;
      margin-bottom: 12px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(15,23,42,0.03);
    }
    .section summary {
      list-style: none;
      cursor: pointer;
      padding: 14px 16px;
      font-weight: 700;
      font-size: 0.98rem;
      color: var(--text);
      display: flex; align-items: center; justify-content: space-between;
      gap: 10px;
      user-select: none;
    }
    .section summary::-webkit-details-marker { display: none; }
    .section summary::after {
      content: '▾';
      color: var(--muted);
      font-size: 0.9rem;
      transition: transform .15s ease;
    }
    .section[open] summary::after { transform: rotate(180deg); }
    .section .body { padding: 0 16px 16px; border-top: 1px solid var(--line); }
    .section .body > :first-child { margin-top: 14px; }

    .form-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
    @media (min-width: 640px) { .form-grid { grid-template-columns: 1fr 1fr; } }
    .form-group label {
      display: block; font-weight: 600; font-size: 0.84rem;
      margin-bottom: 6px; color: #334155;
    }
    .form-group input, .form-group textarea, .form-group select {
      width: 100%; padding: 12px 13px; border: 1.5px solid var(--line);
      border-radius: 12px; font: inherit; background: #fff; color: var(--text);
    }
    .form-group input:focus { outline: none; border-color: var(--green); }
    .form-group.full { grid-column: 1 / -1; }
    .check-row { display: flex; align-items: center; gap: 8px; min-height: 44px; }
    .check-row input { width: 18px; height: 18px; }

    .photo-block { text-align: center; padding-top: 8px; }
    .photo-block .big {
      width: 110px; height: 110px; border-radius: 50%; object-fit: cover;
      border: 3px solid var(--green); margin: 0 auto 12px; display: block; background: #E2E8F0;
    }
    .photo-block .big.fallback {
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; color: #64748B; font-size: 1.2rem;
    }
    .hint { font-size: 0.8rem; color: var(--muted); margin: 6px 0 12px; }

    .docs-list { list-style: none; padding: 0; margin: 0 0 12px; }
    .docs-list li {
      display: flex; justify-content: space-between; align-items: center; gap: 10px;
      padding: 12px; border: 1px solid var(--line); border-radius: 12px; margin-bottom: 8px;
      font-size: 0.9rem;
    }
    .docs-list a { color: var(--green); font-weight: 700; text-decoration: none; }

    .btn {
      display: inline-flex; align-items: center; justify-content: center;
      min-height: 46px; padding: 11px 18px; border-radius: 999px;
      border: none; font-weight: 700; font-size: 0.92rem; cursor: pointer;
      text-decoration: none; width: 100%;
    }
    .btn-green { background: linear-gradient(135deg, var(--green), var(--green-dark)); color: #fff !important; }
    .btn-ghost {
      background: transparent; border: 1.5px solid var(--line); color: var(--text) !important;
    }

    /* Bottom nav mobile */
    .bottom-nav {
      position: fixed; left: 0; right: 0; bottom: 0; z-index: 60;
      background: rgba(255,255,255,0.97);
      backdrop-filter: blur(10px);
      border-top: 1px solid var(--line);
      display: grid; grid-template-columns: repeat(4, 1fr);
      padding: 6px 4px calc(6px + var(--safe-bottom));
      box-shadow: 0 -4px 16px rgba(15,23,42,0.06);
    }
    .bottom-nav a {
      text-decoration: none; color: var(--muted);
      display: flex; flex-direction: column; align-items: center; gap: 2px;
      padding: 6px 2px; font-size: 0.68rem; font-weight: 600;
      position: relative;
    }
    .bottom-nav a .ico { font-size: 1.2rem; line-height: 1; }
    .bottom-nav a.active { color: var(--green); }
    .bottom-nav .dot {
      position: absolute; top: 2px; right: 22%;
      width: 8px; height: 8px; border-radius: 50%; background: #EF4444;
    }

    [data-theme="dark"] {
      --bg: #0f172a; --card: #1e293b; --text: #F8FAFC; --muted: #94A3B8; --line: #334155;
    }
    [data-theme="dark"] .bottom-nav { background: rgba(15,23,42,0.96); }
    [data-theme="dark"] .form-group input,
    [data-theme="dark"] .form-group textarea,
    [data-theme="dark"] .form-group select {
      background: #0f172a; color: #e2e8f0; border-color: #334155;
    }

    @media (min-width: 768px) {
      .bottom-nav { display: none; }
      .wrap { padding-bottom: 40px; }
      .quick { grid-template-columns: repeat(4, 1fr); }
    }
  </style>
</head>
<body>

<header class="topbar">
  <a class="brand" href="../index.html">
    <strong>Care Connect</strong>
    <span>Provider</span>
  </a>
  <button class="icon" type="button" onclick="toggleDarkMode()" aria-label="Theme">🌓</button>
  <a class="icon" href="messages.php" aria-label="Messages">
    💬
    <?php if ($unreadMessages > 0): ?><span class="badge"><?= $unreadMessages > 9 ? '9+' : $unreadMessages ?></span><?php endif; ?>
  </a>
  <a class="icon" href="../logout.php" aria-label="Log out">🚪</a>
</header>

<main class="wrap">
  <div class="hero">
    <?php if ($photoUrl): ?>
      <img class="hero-photo" src="<?= htmlspecialchars($photoUrl) ?>" alt="">
    <?php else: ?>
      <div class="hero-fallback"><?= htmlspecialchars($initials) ?></div>
    <?php endif; ?>
    <div>
      <h1><?= htmlspecialchars($name) ?></h1>
      <p class="meta"><?= htmlspecialchars(ucfirst($role)) ?><?= $specialty ? ' · ' . htmlspecialchars($specialty) : '' ?></p>
      <span class="chip <?= $statusClass ?>"><?= $statusIcon ?> <?= htmlspecialchars($statusLabel) ?></span>
    </div>
  </div>

  <div class="quick">
    <a class="primary" href="messages.php">
      <span class="q-ico">💬</span>
      <span class="q-title">Messages</span>
      <span class="q-sub"><?= $unreadMessages > 0 ? $unreadMessages . ' unread' : 'Patient chats' ?></span>
      <?php if ($unreadMessages > 0): ?><span class="q-count"><?= $unreadMessages ?></span><?php endif; ?>
    </a>
    <a href="provider-referrals.php">
      <span class="q-ico">📋</span>
      <span class="q-title">Referrals</span>
      <span class="q-sub"><?= $pendingReferrals > 0 ? $pendingReferrals . ' pending' : 'Your cases' ?></span>
      <?php if ($pendingReferrals > 0): ?><span class="q-count"><?= $pendingReferrals ?></span><?php endif; ?>
    </a>
    <a href="../provider-payment-settings.php">
      <span class="q-ico">💳</span>
      <span class="q-title">Payments</span>
      <span class="q-sub">Fee settings</span>
    </a>
    <a href="../pages/doctors.php">
      <span class="q-ico">🏥</span>
      <span class="q-title">Listing</span>
      <span class="q-sub">Find Care page</span>
    </a>
  </div>

  <?php if ($message): ?><div class="alert ok">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <details class="section" open>
    <summary>Profile & practice</summary>
    <div class="body">
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-grid">
          <div class="form-group">
            <label for="specialty">Specialty *</label>
            <input id="specialty" name="specialty" required value="<?= htmlspecialchars($specialty) ?>" placeholder="e.g. General Medicine">
          </div>
          <div class="form-group">
            <label for="experience_years">Years experience</label>
            <input type="number" id="experience_years" name="experience_years" min="0" max="60" value="<?= (int)$experience ?>">
          </div>
          <div class="form-group full">
            <label for="qualifications">Qualifications</label>
            <input id="qualifications" name="qualifications" value="<?= htmlspecialchars($qualifications) ?>" placeholder="e.g. MBBS, MPH">
          </div>
          <div class="form-group">
            <label for="clinic_name">Clinic / hospital</label>
            <input id="clinic_name" name="clinic_name" value="<?= htmlspecialchars($clinic_name) ?>" placeholder="Clinic name">
          </div>
          <div class="form-group">
            <label for="clinic_phone">Clinic phone</label>
            <input id="clinic_phone" name="clinic_phone" value="<?= htmlspecialchars($clinic_phone) ?>" placeholder="+232 ...">
          </div>
          <div class="form-group full">
            <label for="clinic_address">Address</label>
            <input id="clinic_address" name="clinic_address" value="<?= htmlspecialchars($clinic_address) ?>" placeholder="Street, area">
          </div>
          <div class="form-group">
            <label for="consultation_fee">Fee (SLL)</label>
            <input type="number" id="consultation_fee" name="consultation_fee" min="0" step="1000" value="<?= htmlspecialchars((string)$consultation_fee) ?>">
          </div>
          <div class="form-group">
            <label>Accepting patients</label>
            <div class="check-row">
              <input type="checkbox" id="is_accepting_patients" name="is_accepting_patients" <?= $is_accepting ? 'checked' : '' ?>>
              <label for="is_accepting_patients" style="margin:0;font-weight:500;">Yes, open for new patients</label>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-green" style="margin-top:14px;">Save profile</button>
      </form>
    </div>
  </details>

  <details class="section">
    <summary>Profile photo</summary>
    <div class="body photo-block">
      <?php if ($photoUrl): ?>
        <img class="big" src="<?= htmlspecialchars($photoUrl) ?>" alt="" id="photoPreview">
      <?php else: ?>
        <div class="big fallback" id="photoFallback">No photo</div>
        <img class="big" src="" alt="" id="photoPreview" style="display:none;">
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data" id="photoForm">
        <input type="hidden" name="action" value="upload_photo">
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required>
        <p class="hint">JPG, PNG or WEBP · max 5MB</p>
        <button type="submit" class="btn btn-green" id="photoBtn">Upload photo</button>
      </form>
    </div>
  </details>

  <details class="section">
    <summary>Verification documents</summary>
    <div class="body">
      <p class="hint" style="margin-top:0;">License, ID, certificates, or clinic registration.</p>
      <?php if (!empty($docs)): ?>
        <ul class="docs-list">
          <?php foreach ($docs as $i => $doc): ?>
            <li>
              <span>📄 Document <?= $i + 1 ?></span>
              <a href="../<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener">View</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="hint">No documents yet.</p>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_docs">
        <div class="form-group">
          <label for="documents">Add files</label>
          <input type="file" id="documents" name="documents[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" required>
          <p class="hint">PDF or images · max 8MB each</p>
        </div>
        <button type="submit" class="btn btn-green">Upload for review</button>
      </form>
    </div>
  </details>
</main>

<nav class="bottom-nav" aria-label="Provider menu">
  <a class="active" href="provider-dashboard.php"><span class="ico">🏠</span>Home</a>
  <a href="messages.php">
    <span class="ico">💬</span>Chats
    <?php if ($unreadMessages > 0): ?><span class="dot"></span><?php endif; ?>
  </a>
  <a href="provider-referrals.php">
    <span class="ico">📋</span>Referrals
    <?php if ($pendingReferrals > 0): ?><span class="dot"></span><?php endif; ?>
  </a>
  <a href="../logout.php"><span class="ico">🚪</span>Logout</a>
</nav>

<script src="../js/dark-mode.js"></script>
<script>
  const input = document.getElementById('profile_photo');
  const preview = document.getElementById('photoPreview');
  const fallback = document.getElementById('photoFallback');
  const photoBtn = document.getElementById('photoBtn');
  if (input) {
    input.addEventListener('change', function () {
      const file = this.files && this.files[0];
      if (!file || !preview) return;
      preview.src = URL.createObjectURL(file);
      preview.style.display = 'block';
      if (fallback) fallback.style.display = 'none';
    });
  }
  const photoForm = document.getElementById('photoForm');
  if (photoForm) {
    photoForm.addEventListener('submit', function () {
      if (photoBtn) { photoBtn.disabled = true; photoBtn.textContent = 'Uploading...'; }
    });
  }
</script>
</body>
</html>
