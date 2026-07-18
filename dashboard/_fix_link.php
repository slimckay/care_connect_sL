<?php
// Temporary helper: if old wrong admin link is bookmarked, send providers here
if (session_status() === PHP_SESSION_NONE) session_start();
header('Location: provider-referrals.php');
exit;
