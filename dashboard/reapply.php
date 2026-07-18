<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . '/../uploads/verification/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $documentPaths = [];

    if (!empty($_FILES['documents']['name'][0])) {
        foreach ($_FILES['documents']['name'] as $key => $filename) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['documents']['tmp_name'][$key];
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $newName = 'reapply_' . time() . '_' . $key . '.' . $ext;
                $destination = $uploadDir . $newName;

                if (move_uploaded_file($tmpName, $destination)) {
                    $documentPaths[] = 'uploads/verification/' . $newName;
                }
            }
        }
    }

    $documentsString = implode(',', $documentPaths);

    try {
        // Reset status to pending and update documents
        $stmt = $conn->prepare("
            UPDATE provider_profiles 
            SET verification_status = 'pending', 
                verification_documents = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$documentsString, $user_id]);

        $message = "✅ Reapplication submitted successfully! You will be notified once reviewed again.";
    } catch (Exception $e) {
        $message = "Error submitting reapplication. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reapply — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="../index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
  </div>
</header>

<main style="max-width:700px; margin:50px auto; padding:0 20px;">
  <div class="form-card">
    <h1>Reapply for Verification</h1>
    <p style="color:#64748B;">Upload updated or additional documents for review.</p>

    <?php if ($message): ?>
      <div style="background:#D1FAE5; padding:16px; border-radius:10px; margin:20px 0;"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label>Upload New/Updated Documents</label>
        <input type="file" name="documents[]" multiple required>
        <small>You can upload multiple files (PDF, JPG, PNG)</small>
      </div>

      <button type="submit" class="btn-primary">Submit Reapplication</button>
    </form>
  </div>
</main>

</body>
</html>