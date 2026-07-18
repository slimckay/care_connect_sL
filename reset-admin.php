<?php
// TEMPORARY ADMIN RESET SCRIPT
// Delete this file after use for security!

require_once 'db.php';

$adminEmail = 'admin2@careconnect.sl';
$adminPassword = 'Admin123!';
$hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

try {
    // Check if admin already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$adminEmail]);
    
    if ($check->rowCount() > 0) {
        // Update existing admin
        $stmt = $conn->prepare("UPDATE users SET password = ?, role = 'admin', status = 'active' WHERE email = ?");
        $stmt->execute([$hashedPassword, $adminEmail]);
        echo "✅ Admin password has been reset successfully!<br>";
    } else {
        // Create new admin
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, email_verified, created_at) VALUES (?, ?, ?, 'admin', 'active', TRUE, NOW())");
        $stmt->execute(['System Admin', $adminEmail, $hashedPassword]);
        echo "✅ New admin account created successfully!<br>";
    }
    
    echo "<br><strong>Login Details:</strong><br>";
    echo "Email: admin2@careconnect.sl<br>";
    echo "Password: Admin123!<br><br>";
    echo "<strong style='color:red;'>IMPORTANT: Delete this file (reset-admin.php) immediately after use!</strong>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>