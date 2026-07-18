<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Unified flow now lives on the provider dashboard
header('Location: provider-dashboard.php#documents');
exit;
