<?php
// Provider Registration with Document Upload
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
  <div class="form-card" style="background:#fff; padding:40px; border-radius:20px; box-shadow:0 10px 40px rgba(0,0,0,0.08);">
    <h1 style="text-align:center; margin-bottom:8px;">Join as a Provider</h1>
    <p style="text-align:center; color:#64748B; margin-bottom:30px;">Register as Doctor or Clinic (Verification Required)</p>

    <form method="POST" action="provider-registration.php" enctype="multipart/form-data">
      <div class="form-group">
        <label>Full Name / Clinic Name</label>
        <input type="text" name="name" required>
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" required>
      </div>

      <div class="form-group">
        <label>Phone Number</label>
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
        <label>Specialty / Services</label>
        <input type="text" name="specialty" placeholder="e.g. General Medicine">
      </div>

      <div class="form-group">
        <label>Years of Experience</label>
        <input type="number" name="experience" min="0">
      </div>

      <div class="form-group">
        <label>Upload Verification Documents (License, ID, Certificate)</label>
        <input type="file" name="documents[]" multiple required>
        <small style="color:#64748B;">You can upload multiple files (PDF, JPG, PNG)</small>
      </div>

      <button type="submit" class="btn-primary">Submit for Verification</button>
    </form>

    <p style="text-align:center; margin-top:20px; color:#64748B; font-size:0.9rem;">
      Your application will be reviewed within 48 hours.
    </p>
  </div>
</div>

</body>
</html>