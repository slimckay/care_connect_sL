<?php
// Provider Registration with Profile Photo + Documents
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'doctor';
    $specialty = trim($_POST['specialty'] ?? '');
    $experience = intval($_POST['experience'] ?? 0);

    $uploadDir = __DIR__ . '/uploads/verification/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $profilePhotoPath = '';

    // Handle Profile Photo
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $photoName = 'profile_' . time() . '.' . $ext;
        $photoDest = $uploadDir . $photoName;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $photoDest)) {
            $profilePhotoPath = 'uploads/verification/' . $photoName;
        }
    }

    // Handle multiple document uploads
    $documentPaths = [];
    if (!empty($_FILES['documents']['name'][0])) {
        foreach ($_FILES['documents']['name'] as $key => $filename) {
            if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['documents']['tmp_name'][$key];
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $newName = 'doc_' . time() . '_' . $key . '.' . $ext;
                $destination = $uploadDir . $newName;
                if (move_uploaded_file($tmpName, $destination)) {
                    $documentPaths[] = 'uploads/verification/' . $newName;
                }
            }
        }
    }

    $documentsString = implode(',', $documentPaths);

    try {
        $hashed = password_hash('TempPass123!', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$name, $email, $hashed, $role]);
        $userId = $conn->lastInsertId();

        $stmt = $conn->prepare("
            INSERT INTO provider_profiles 
            (user_id, specialty, experience_years, verification_status, verification_documents, profile_photo, created_at)
            VALUES (?, ?, ?, 'pending', ?, ?, NOW())
        ");
        $stmt->execute([$userId, $specialty, $experience, $documentsString, $profilePhotoPath]);

        $message = "✅ Thank you! Your application has been submitted successfully. Our team will review your documents and contact you within 48 hours.";

    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Provider Registration — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="/" class="logo">Care<span class="accent">Connect</span> SL</a>
  </div>
</header>

<div class="form-container" style="max-width:720px; margin:40px auto; padding:0 20px;">
  <div class="form-card">
    <h1>Join as a Provider</h1>
    <p style="color:#64748B;">Upload your profile photo and verification documents.</p>

    <?php if ($message): ?>
      <div style="background:#D1FAE5; padding:16px; border-radius:10px; margin:20px 0;"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label>Full Name / Clinic Name</label>
        <input type="text" name="name" required>
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required>
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input type="tel" name="phone" required>
      </div>

      <div class="form-group">
        <label>Registering as</label>
        <select name="role" required>
          <option value="doctor">Doctor</option>
          <option value="hospital">Clinic / Hospital</option>
        </select>
      </div>

      <div class="form-group">
        <label>Specialty</label>
        <input type="text" name="specialty">
      </div>

      <div class="form-group">
        <label>Years of Experience</label>
        <input type="number" name="experience" min="0">
      </div>

      <div class="form-group">
        <label>Profile Photo (Optional but recommended)</label>
        <input type="file" name="profile_photo" accept="image/*">
      </div>

      <div class="form-group">
        <label>Verification Documents (License, ID, Certificate)</label>
        <input type="file" name="documents[]" multiple required>
        <small>You can upload multiple files</small>
      </div>

      <button type="submit" class="btn-primary">Submit for Verification</button>
    </form>
  </div>
</div>

</body>
</html>