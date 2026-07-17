<?php
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

<!-- ... rest of your HTML ... -->
