﻿<?php
/**
 * Find Care Providers - Care Connect SL
 * Search functionality for doctors and providers
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
require_once '../db.php';

// Get search query
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$results = [];
$hasSearched = false;

// Perform search if query exists
if (!empty($search)) {
    $hasSearched = true;
    try {
        // Search for providers in users table
        $stmt = $conn->prepare("
            SELECT 
                u.id, 
                u.name, 
                u.email, 
                u.role,
                p.specialty,
                p.qualifications,
                p.experience_years,
                p.clinic_name,
                p.is_accepting_patients,
                p.created_at
            FROM users u
            LEFT JOIN provider_profiles p ON u.id = p.user_id
            WHERE u.role IN ('doctor', 'hospital')
            AND u.status = 'active'
            AND (
                u.name LIKE ? 
                OR p.specialty LIKE ? 
                OR p.clinic_name LIKE ? 
                OR u.email LIKE ?
            )
            ORDER BY u.name ASC
            LIMIT 20
        ");
        
        $searchTerm = '%' . $search . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $results = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        $error = "Search failed. Please try again.";
    }
}

// Get featured providers (no search)
try {
    $featuredStmt = $conn->prepare("
        SELECT 
            u.id, 
            u.name, 
            u.role,
            p.specialty,
            p.clinic_name,
            p.is_accepting_patients
        FROM users u
        LEFT JOIN provider_profiles p ON u.id = p.user_id
        WHERE u.role IN ('doctor', 'hospital')
        AND u.status = 'active'
        AND p.is_accepting_patients = 1
        LIMIT 5
    ");
    $featuredStmt->execute();
    $featuredProviders = $featuredStmt->fetchAll();
} catch (PDOException $e) {
    $featuredProviders = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Find healthcare providers, doctors, and community health workers across Sierra Leone with Care Connect SL.">
  <meta name="keywords" content="find doctors, healthcare providers, Sierra Leone, community health workers">
  <meta property="og:title" content="Find Care — Care Connect SL">
  <meta property="og:description" content="Search and connect with community health workers and doctors in your area.">
  <title>Find Care Providers — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div id="preloader" role="status" aria-label="Loading">
  <div class="pulse-ring"></div>
  <svg class="heartbeat-svg" viewBox="0 0 300 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <polyline points="0,40 60,40 80,10 100,70 120,5 140,75 160,40 300,40" fill="none" stroke="#00C896" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <p class="preload-text">Care Connect SL</p>
</div>

<header role="banner">
  <div class="nav-inner">
    <a href="../index.html" class="logo" aria-label="Care Connect SL Home">
      <span class="logo-icon" aria-hidden="true">❤️</span> Care<span class="accent">Connect</span> SL
    </a>
    <nav aria-label="Main navigation">
      <ul class="nav-links" role="menubar">
        <li><a href="../index.html" role="menuitem">Home</a></li>
        <li><a href="hospitals.html" role="menuitem">Clinics</a></li>
        <li><a href="referral.html" role="menuitem">Referrals</a></li>
        <li><a href="about.html" role="menuitem">About</a></li>
        <li><a href="contact.html" role="menuitem">Contact</a></li>
        <li><a href="../ai-chat.php" role="menuitem" style="color: var(--primary); font-weight: 600;">💬 AI Assistant</a></li>
      </ul>
    </nav>
    <div class="nav-actions">
      <a href="../login.php" class="btn-ghost">Sign In</a>
      <a href="../register.php" class="btn-primary">Register</a>
    </div>
  </div>
</header>

<main class="page-content" role="main">
  <section class="page-hero" aria-labelledby="find-care-title">
    <h1 id="find-care-title">Find Care Providers</h1>
    <p>Search community health workers and doctors in your area, and request a referral to a nearby clinic.</p>
  </section>

  <!-- Search Section -->
  <section class="card" aria-labelledby="search-title">
    <div class="page-grid">
      <form action="doctors.php" method="GET" class="page-grid" style="display: grid; gap: 12px;">
        <label for="providerSearch" id="search-title" style="font-weight: 600; color: var(--dark);">Search providers</label>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <input type="text" 
                 id="providerSearch" 
                 name="search" 
                 placeholder="Search by name, specialty, or clinic..." 
                 aria-label="Search for healthcare providers"
                 value="<?php echo htmlspecialchars($search); ?>"
                 style="flex: 1; min-width: 200px;">
          <button type="submit" class="btn-primary" style="min-width: 120px;">🔍 Search</button>
        </div>
      </form>
      
      <!-- AI Assistant Quick Link -->
      <div style="text-align: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
        <span style="color: var(--muted); font-size: 0.9rem;">🤖 Need help finding the right provider? </span>
        <a href="../ai-chat.php" style="font-weight: 600; color: var(--primary);">Ask our AI Assistant →</a>
      </div>
    </div>
  </section>

  <!-- Search Results -->
  <?php if ($hasSearched): ?>
    <section class="section-panel" style="grid-template-columns: 1fr;">
      <div class="card">
        <h2 style="margin-bottom: 16px;">
          Search Results 
          <?php if (!empty($search)): ?>
            <span style="font-size: 0.9rem; color: var(--muted); font-weight: 400;">
              for "<?php echo htmlspecialchars($search); ?>"
            </span>
          <?php endif; ?>
        </h2>
        
        <?php if (isset($error)): ?>
          <div class="form-message error"><?php echo $error; ?></div>
        <?php elseif (!empty($results)): ?>
          <div class="provider-results">
            <?php foreach ($results as $provider): ?>
              <div class="provider-card" style="
                padding: 16px 20px;
                margin-bottom: 12px;
                background: var(--light);
                border-radius: var(--radius-md);
                border-left: 4px solid var(--primary);
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 12px;
              ">
                <div>
                  <h4 style="margin: 0 0 4px 0; font-size: 1.05rem;">
                    <?php echo htmlspecialchars($provider['name']); ?>
                    <?php if ($provider['is_accepting_patients']): ?>
                      <span class="badge green" style="font-size: 0.65rem; margin-left: 8px;">Available</span>
                    <?php else: ?>
                      <span class="badge amber" style="font-size: 0.65rem; margin-left: 8px;">Not Available</span>
                    <?php endif; ?>
                  </h4>
                  <p style="margin: 0; font-size: 0.9rem; color: var(--muted);">
                    <?php 
                      $role = ucfirst($provider['role']);
                      $specialty = !empty($provider['specialty']) ? ' - ' . $provider['specialty'] : '';
                      $clinic = !empty($provider['clinic_name']) ? ' at ' . $provider['clinic_name'] : '';
                      echo $role . $specialty . $clinic;
                    ?>
                  </p>
                  <?php if (!empty($provider['qualifications'])): ?>
                    <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--gray-light);">
                      📜 <?php echo htmlspecialchars($provider['qualifications']); ?>
                    </p>
                  <?php endif; ?>
                </div>
                <div>
                  <a href="../pages/referral.html?provider=<?php echo $provider['id']; ?>" class="btn-primary" style="padding: 8px 20px; font-size: 0.85rem; min-height: auto;">
                    Request Referral
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <p style="margin-top: 12px; color: var(--muted); font-size: 0.9rem;">
            Found <?php echo count($results); ?> provider(s)
          </p>
        <?php else: ?>
          <div class="empty-state" style="padding: 32px 16px; text-align: center;">
            <p style="font-size: 1.1rem; color: var(--muted);">
              😕 No providers found matching "<?php echo htmlspecialchars($search); ?>"
            </p>
            <p style="color: var(--gray-light); margin-top: 8px;">
              Try searching by name, specialty, or clinic name.
            </p>
            <a href="../ai-chat.php" class="btn-primary" style="margin-top: 16px; display: inline-block;">💬 Ask AI for Help</a>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- Featured Providers -->
  <section class="section-panel" style="margin-top: <?php echo $hasSearched ? '0' : '32px'; ?>;">
    <div class="card" aria-labelledby="featured-title">
      <h2 id="featured-title">⭐ Featured Providers</h2>
      <?php if (!empty($featuredProviders)): ?>
        <ul style="list-style: none; padding: 0; margin: 12px 0 0 0;">
          <?php foreach ($featuredProviders as $provider): ?>
            <li style="
              padding: 12px 0;
              border-bottom: 1px solid var(--border);
              display: flex;
              justify-content: space-between;
              align-items: center;
              flex-wrap: wrap;
              gap: 8px;
            ">
              <div>
                <strong><?php echo htmlspecialchars($provider['name']); ?></strong>
                <?php if (!empty($provider['specialty'])): ?>
                  <span style="color: var(--muted); font-size: 0.9rem;">— <?php echo htmlspecialchars($provider['specialty']); ?></span>
                <?php endif; ?>
                <?php if ($provider['is_accepting_patients']): ?>
                  <span class="badge green" style="font-size: 0.65rem;">Available</span>
                <?php endif; ?>
              </div>
              <a href="../pages/referral.html?provider=<?php echo $provider['id']; ?>" class="btn-small" style="padding: 4px 16px;">
                Refer
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p style="color: var(--muted);">No featured providers available at the moment.</p>
      <?php endif; ?>
    </div>

    <div class="card" aria-labelledby="help-title">
      <h2 id="help-title">Need immediate help?</h2>
      <p>Submit a referral and our team will connect you with the best available provider for your condition.</p>
      <a href="referral.html" class="btn-primary" style="margin-top: 12px; display: inline-block;">Make a Referral</a>
      <p style="margin-top: 12px; font-size: 0.9rem; color: var(--muted);">
        Or 🤖 <a href="../ai-chat.php" style="font-weight: 600;">ask our AI Assistant</a> for guidance
      </p>
    </div>
  </section>
</main>

<footer class="site-footer" role="contentinfo">
  <div class="footer-grid container">
    <div>
      <a href="../index.html" class="logo" aria-label="Care Connect SL Home">Care<span class="accent">Connect</span> SL</a>
      <p>Home-based care referrals and clinic coordination across Sierra Leone.</p>
    </div>
    <div>
      <h3>Browse</h3>
      <ul class="footer-links">
        <li><a href="../index.html">Home</a></li>
        <li><a href="referral.html">Referrals</a></li>
        <li><a href="hospitals.html">Clinics</a></li>
        <li><a href="../ai-chat.php">💬 AI Assistant</a></li>
      </ul>
    </div>
    <div>
      <h3>Contact</h3>
      <p><a href="mailto:hello@careconnect.sl">hello@careconnect.sl</a></p>
      <p><a href="tel:+23276000000">+232 76 000 000</a></p>
    </div>
  </div>
  <p class="footer-note">&copy; 2026 Care Connect SL. All rights reserved.</p>
</footer>

<script src="../js/main.js"></script>
</body>
</html>