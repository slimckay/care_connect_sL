<?php
/**
 * Support chat API — admin ↔ contact (linked user when registered)
 * Actions: thread, messages, send
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$isAdmin = (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true)
    || (strtolower($_SESSION['role'] ?? '') === 'admin');
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Support');

if (!$isAdmin && $userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

// Tables
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS support_threads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contact_message_id INT NULL,
        user_id INT NULL,
        contact_name VARCHAR(120) NULL,
        contact_email VARCHAR(160) NULL,
        contact_phone VARCHAR(40) NULL,
        subject VARCHAR(255) NULL,
        status VARCHAR(20) DEFAULT 'open',
        last_message_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_contact (contact_message_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        thread_id INT NOT NULL,
        sender_role VARCHAR(20) NOT NULL,
        sender_id INT NULL,
        sender_name VARCHAR(120) NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_thread (thread_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $_POST = array_merge($_POST, $json);
        if (isset($json['action'])) $action = $json['action'];
    }
}

function out(array $d, int $c = 200): void
{
    http_response_code($c);
    echo json_encode($d);
    exit;
}

function getOrCreateThread(PDO $conn, int $contactId): ?array
{
    $c = $conn->prepare('SELECT * FROM contact_messages WHERE id = ? LIMIT 1');
    $c->execute([$contactId]);
    $contact = $c->fetch(PDO::FETCH_ASSOC);
    if (!$contact) return null;

    $t = $conn->prepare('SELECT * FROM support_threads WHERE contact_message_id = ? LIMIT 1');
    $t->execute([$contactId]);
    $thread = $t->fetch(PDO::FETCH_ASSOC);
    if ($thread) return $thread;

    // Match registered user by email
    $uid = null;
    if (!empty($contact['email'])) {
        try {
            $u = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $u->execute([$contact['email']]);
            $row = $u->fetch(PDO::FETCH_ASSOC);
            if ($row) $uid = (int)$row['id'];
        } catch (Exception $e) {}
    }

    $conn->prepare("INSERT INTO support_threads
        (contact_message_id, user_id, contact_name, contact_email, contact_phone, subject, status, last_message_at, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())")
         ->execute([
             $contactId,
             $uid,
             $contact['name'] ?? null,
             $contact['email'] ?? null,
             $contact['phone'] ?? null,
             mb_substr((string)($contact['message'] ?? 'Contact support'), 0, 120),
         ]);
    $id = (int)$conn->lastInsertId();

    // Seed first message from original contact form
    if (!empty($contact['message'])) {
        $conn->prepare("INSERT INTO support_messages (thread_id, sender_role, sender_id, sender_name, message, created_at)
                        VALUES (?, 'contact', ?, ?, ?, ?)")
             ->execute([
                 $id,
                 $uid,
                 $contact['name'] ?? 'Contact',
                 $contact['message'],
                 $contact['created_at'] ?? date('Y-m-d H:i:s'),
             ]);
    }

    $t->execute([$contactId]);
    return $t->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    if ($action === 'thread') {
        $contactId = (int)($_GET['contact_id'] ?? $_POST['contact_id'] ?? 0);
        if ($contactId <= 0) out(['ok' => false, 'error' => 'Missing contact'], 400);
        if (!$isAdmin) out(['ok' => false, 'error' => 'Admin only'], 403);

        $thread = getOrCreateThread($conn, $contactId);
        if (!$thread) out(['ok' => false, 'error' => 'Contact not found'], 404);
        out(['ok' => true, 'thread' => $thread]);
    }

    if ($action === 'messages') {
        $threadId = (int)($_GET['thread_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);
        if ($threadId <= 0) out(['ok' => false, 'error' => 'Missing thread'], 400);

        $t = $conn->prepare('SELECT * FROM support_threads WHERE id = ? LIMIT 1');
        $t->execute([$threadId]);
        $thread = $t->fetch(PDO::FETCH_ASSOC);
        if (!$thread) out(['ok' => false, 'error' => 'Thread not found'], 404);

        // Patients only their thread
        if (!$isAdmin) {
            if ((int)($thread['user_id'] ?? 0) !== $userId) {
                out(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }

        if ($afterId > 0) {
            $m = $conn->prepare('SELECT * FROM support_messages WHERE thread_id = ? AND id > ? ORDER BY id ASC LIMIT 200');
            $m->execute([$threadId, $afterId]);
        } else {
            $m = $conn->prepare('SELECT * FROM support_messages WHERE thread_id = ? ORDER BY id ASC LIMIT 500');
            $m->execute([$threadId]);
        }
        out(['ok' => true, 'messages' => $m->fetchAll(PDO::FETCH_ASSOC), 'thread' => $thread]);
    }

    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $text = trim((string)($_POST['message'] ?? ''));
        if ($threadId <= 0 || $text === '') out(['ok' => false, 'error' => 'Empty'], 400);
        if (strlen($text) > 3000) out(['ok' => false, 'error' => 'Too long'], 400);

        $t = $conn->prepare('SELECT * FROM support_threads WHERE id = ? LIMIT 1');
        $t->execute([$threadId]);
        $thread = $t->fetch(PDO::FETCH_ASSOC);
        if (!$thread) out(['ok' => false, 'error' => 'Thread not found'], 404);

        if ($isAdmin) {
            $role = 'admin';
            $sid = $userId ?: null;
            $sname = $userName ?: 'Care Connect Support';
        } else {
            if ((int)($thread['user_id'] ?? 0) !== $userId) out(['ok' => false, 'error' => 'Forbidden'], 403);
            $role = 'patient';
            $sid = $userId;
            $sname = $userName;
        }

        $conn->prepare("INSERT INTO support_messages (thread_id, sender_role, sender_id, sender_name, message, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())")
             ->execute([$threadId, $role, $sid, $sname, $text]);
        $msgId = (int)$conn->lastInsertId();
        $conn->prepare('UPDATE support_threads SET last_message_at = NOW(), status = ? WHERE id = ?')
             ->execute([$isAdmin ? 'replied' : 'open', $threadId]);

        // Mark contact message replied when admin sends
        if ($isAdmin && !empty($thread['contact_message_id'])) {
            try {
                $conn->prepare("UPDATE contact_messages SET status = 'replied', updated_at = NOW() WHERE id = ?")
                     ->execute([(int)$thread['contact_message_id']]);
            } catch (Exception $e) {}
        }

        // Notify registered patient
        if ($isAdmin && !empty($thread['user_id'])) {
            try {
                $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                                VALUES (?, 'support', 'Support replied', ?, ?, 0, NOW())")
                     ->execute([
                         (int)$thread['user_id'],
                         mb_substr($text, 0, 100),
                         'dashboard/support-inbox.php?t=' . $threadId,
                     ]);
            } catch (Exception $e) {}
        }

        out([
            'ok' => true,
            'message' => [
                'id' => $msgId,
                'thread_id' => $threadId,
                'sender_role' => $role,
                'sender_id' => $sid,
                'sender_name' => $sname,
                'message' => $text,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    out(['ok' => false, 'error' => 'Unknown action'], 400);
} catch (Exception $e) {
    error_log('support-chat-api: ' . $e->getMessage());
    out(['ok' => false, 'error' => 'Server error'], 500);
}
