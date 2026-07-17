<?php
/**
 * Create Admin User - Run this once then delete
 */
require_once '../db.php';

$name = 'System Admin';
$email = 'admin@careconnect.sl';
$password = 'Admin123!';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    
    if ($check->fetch()) {
        echo "❌ Admin user already exists!<br>";
        echo "Try logging in with:<br>";
        echo "Email: admin@careconnect.sl<br>";
        echo "Password: Admin123!<br>";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, password, role, status, email_verified) 
            VALUES (?, ?, ?, 'admin', 'active', 1)
        ");
        $stmt->execute([$name, $email, $hashedPassword]);
        
        echo "✅ Admin user created successfully!<br>";
        echo "Email: admin@careconnect.sl<br>";
        echo "Password: Admin123!<br>";
        echo "<br><strong>Delete this file after use for security.</strong>";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>