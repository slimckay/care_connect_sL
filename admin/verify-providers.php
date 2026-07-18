<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

// Handle approval/rejection + send email
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    // Get provider email first
    $stmt = $conn->prepare("SELECT u.email, u.name FROM provider_profiles p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
    $stmt->execute([$id]);
    $provider = $stmt->fetch();

    if ($action === 'verify') {
        $conn->prepare("UPDATE provider_profiles SET verification_status = 'verified' WHERE user_id = ?")
             ->execute([$id]);

        // Send approval email
        if ($provider) {
            $to = $provider['email'];
            $subject = "Your Care Connect SL Application has been Approved";
            $message = "Hello " . $provider['name'] . ",\n\n" .
                       "Congratulations! Your application to join Care Connect SL as a provider has been approved.\n\n" .
                       "You can now receive referrals and will appear in our public directory.\n\n" .
                       "Thank you for partnering with us to improve healthcare in Sierra Leone.\n\n" .
                       "Best regards,\nCare Connect SL Team";
            @mail($to, $subject, $message);
        }

    } elseif ($action === 'reject') {
        $conn->prepare("UPDATE provider_profiles SET verification_status = 'rejected' WHERE user_id = ?")
             ->execute([$id]);

        // Send rejection email
        if ($provider) {
            $to = $provider['email'];
            $subject = "Update on Your Care Connect SL Application";
            $message = "Hello " . $provider['name'] . ",\n\n" .
                       "We regret to inform you that your application to join Care Connect SL has been rejected at this time.\n\n" .
                       "If you would like to reapply or need more information, please contact us.\n\n" .
                       "Thank you for your interest in improving healthcare access in Sierra Leone.\n\n" .
                       "Best regards,\nCare Connect SL Team";
            @mail($to, $subject, $message);
        }
    }

    header('Location: verify-providers.php');
    exit;
}

// Get pending providers
$pending = $conn->query("
    SELECT p.*, u.name, u.email 
    FROM provider_profiles p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.verification_status = 'pending'
    ORDER BY p.created_at ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Providers — Admin</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="admin-dashboard.php" class="logo">Care<span class="accent">Connect</span> SL Admin</a>
  </div>
</header>

<main style="max-width:1100px; margin:40px auto; padding:0 20px;">
  <h1>Pending Provider Verifications</h1>

  <?php if (empty($pending)): ?>
    <p>No pending verifications.</p>
  <?php else: ?>
    <table class="data-table" style="width:100%; margin-top:20px;">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Specialty</th>
          <th>Documents</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= htmlspecialchars($p['email']) ?></td>
            <td><?= htmlspecialchars($p['specialty'] ?? 'N/A') ?></td>
            <td>
              <?php if (!empty($p['verification_documents'])): ?>
                <a href="../<?= $p['verification_documents'] ?>" target="_blank">View Documents</a>
              <?php else: ?>
                No documents
              <?php endif; ?>
            </td>
            <td>
              <a href="?action=verify&id=<?= $p['user_id'] ?>" class="btn-small" style="background:#16A34A;">Verify</a>
              <a href="?action=reject&id=<?= $p['user_id'] ?>" class="btn-small" style="background:#DC2626;">Reject</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>

</body>
</html>