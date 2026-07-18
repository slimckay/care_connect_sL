<?php
/**
 * Contact form handler — Care Connect SL
 * Saves message to contact_messages and redirects with status.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$messageText = trim($_POST['message'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

if ($name === '' || $email === '' || $messageText === '') {
    header('Location: contact.html?error=' . urlencode('Please fill in your name, email, and message.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: contact.html?error=' . urlencode('Please enter a valid email address.'));
    exit;
}

if (strlen($messageText) < 5) {
    header('Location: contact.html?error=' . urlencode('Message is too short. Please write a bit more.'));
    exit;
}

try {
    // Ensure table exists (lightweight safety)
    $conn->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(50) NULL,
            message TEXT NOT NULL,
            status ENUM('new', 'read', 'replied') DEFAULT 'new',
            ip_address VARCHAR(45) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $conn->prepare("
        INSERT INTO contact_messages (name, email, phone, message, status, ip_address, created_at)
        VALUES (?, ?, ?, ?, 'new', ?, NOW())
    ");
    $stmt->execute([
        $name,
        $email,
        $phone !== '' ? $phone : null,
        $messageText,
        $ip
    ]);

    // Notify admin users
    try {
        $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        $title = 'New contact message';
        $msg = $name . ' sent a message via the contact form.';
        $link = 'admin/manage-messages.php';
        $n = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
            VALUES (?, 'contact_message', ?, ?, ?, 0, NOW())
        ");
        foreach ($admins as $admin) {
            $n->execute([(int)$admin['id'], $title, $msg, $link]);
        }
    } catch (Exception $e) {
        // notifications optional
    }

    // Activity log
    try {
        $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (NULL, 'contact_form', ?, ?, NOW())")
             ->execute(['Contact form from ' . $name . ' (' . $email . ')', $ip]);
    } catch (Exception $e) {}

    header('Location: contact.html?success=1');
    exit;
} catch (Exception $e) {
    error_log('Contact form error: ' . $e->getMessage());
    header('Location: contact.html?error=' . urlencode('Could not send your message. Please try again in a moment.'));
    exit;
}
