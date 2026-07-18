<?php
// Provider Registration Form
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Provider Registration — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <base href="/">
  <style>
    .form-container {
      max-width: 720px;
      margin: 40px auto;
      padding: 0 20px;
    }
    .form-card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.08);
      padding: 40px 36px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 13px 16px;
      border: 2px solid #E5E7EB;
      border-radius: 10px;
      font-size: 1rem;
    }
    .form-group textarea {
      min-height: 100px;
    }
    .btn-primary {
      width: 100%;
      padding: 15px;
      font-size: 1.1rem;
      margin-top: 10px;
    }
    @media (max-width: 600px) {
      .form-card {
        padding: 28px 20px;
      }
    }
  </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="/" class="logo">Care<span class="accent">Connect</span> SL</a>
  </div>
</header>

<div class="form-container">
  <div class="form-card">
    <h1 style="text-align:center; margin-bottom:8px;">Join as a Provider</h1>
    <p style="text-align:center; color:#64748B; margin-bottom:30px;">Register as a Doctor or Clinic</p>

    <form method="POST" action="provider-registration.php">
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
        <label>I am registering as</label>
        <select name="role" required>
          <option value="">Select</option>
          <option value="doctor">Doctor</option>
          <option value="hospital">Clinic / Hospital</option>
        </select>
      </div>

      <div class="form-group">
        <label>Specialty / Services Offered</label>
        <input type="text" name="specialty" placeholder="e.g. General Medicine, Maternal Health">
      </div>

      <div class="form-group">
        <label>Years of Experience</label>
        <input type="number" name="experience" min="0">
      </div>

      <div class="form-group">
        <label>Clinic / Hospital Name (if applicable)</label>
        <input type="text" name="clinic_name">
      </div>

      <div class="form-group">
        <label>Clinic Address</label>
        <textarea name="clinic_address"></textarea>
      </div>

      <button type="submit" class="btn-primary">Submit Application</button>
    </form>
  </div>
</div>

</body>
</html>