<?php
/**
 * Chat JSON API — Care Connect SL
 * Actions: conversations, messages, send, start
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/../db.php';

$userId = (int)$_SESSION['user_id'];
$role = strtolower($_SESSION['role'] ?? '');
$userName = $_SESSION['user_name'] ?? 'User';

if (!in_array($role, ['patient', 'doctor', 'hospital'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// Ensure tables
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        provider_id INT NOT NULL,
        referral_id INT NULL,
        last_message_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_pair (patient_id, provider_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conversation (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $_POST = array_merge($_POST, $json);
        if (isset($json['action'])) $action = $json['action'];
    }
}

function jsonOut(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function userCanAccess(PDO $conn, int $convId, int $userId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM conversations WHERE id = ? LIMIT 1');
    $stmt->execute([$convId]);
    $conv = $stmt->fetch();
    if (!$conv) return null;
    if ((int)$conv['patient_id'] !== $userId && (int)$conv['provider_id'] !== $userId) return null;
    return $conv;
}

try {
    // List conversations
    if ($action === 'conversations') {
        if ($role === 'patient') {
            $stmt = $conn->prepare("
                SELECT c.id, c.patient_id, c.provider_id, c.last_message_at,
                       u.name AS other_name, u.role AS other_role,
                       (SELECT message FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                       (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread
                FROM conversations c
                JOIN users u ON u.id = c.provider_id
                WHERE c.patient_id = ?
                ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
            ");
            $stmt->execute([$userId, $userId]);
        } else {
            $stmt = $conn->prepare("
                SELECT c.id, c.patient_id, c.provider_id, c.last_message_at,
                       u.name AS other_name, u.role AS other_role,
                       (SELECT message FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                       (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread
                FROM conversations c
                JOIN users u ON u.id = c.patient_id
                WHERE c.provider_id = ?
                ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
            ");
            $stmt->execute([$userId, $userId]);
        }
        jsonOut(['ok' => true, 'conversations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // Get messages (optionally only newer than after_id)
    if ($action === 'messages') {
        $convId = (int)($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? $_POST['after_id'] ?? 0);
        $conv = userCanAccess($conn, $convId, $userId);
        if (!$conv) jsonOut(['ok' => false, 'error' => 'Chat not found'], 404);

        // Mark read
        $conn->prepare('UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?')
             ->execute([$convId, $userId]);

        if ($afterId > 0) {
            $m = $conn->prepare("
                SELECT m.id, m.conversation_id, m.sender_id, m.message, m.created_at, u.name AS sender_name
                FROM chat_messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.conversation_id = ? AND m.id > ?
                ORDER BY m.id ASC
                LIMIT 100
            ");
            $m->execute([$convId, $afterId]);
        } else {
            $m = $conn->prepare("
                SELECT m.id, m.conversation_id, m.sender_id, m.message, m.created_at, u.name AS sender_name
                FROM chat_messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.conversation_id = ?
                ORDER BY m.id ASC
                LIMIT 300
            ");
            $m->execute([$convId]);
        }

        $otherId = ((int)$conv['patient_id'] === $userId) ? (int)$conv['provider_id'] : (int)$conv['patient_id'];
        $on = $conn->prepare('SELECT name, role FROM users WHERE id = ? LIMIT 1');
        $on->execute([$otherId]);
        $other = $on->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'User', 'role' => ''];

        jsonOut([
            'ok' => true,
            'messages' => $m->fetchAll(PDO::FETCH_ASSOC),
            'other_name' => $other['name'],
            'other_role' => $other['role'],
            'conversation_id' => $convId,
            'me' => $userId,
        ]);
    }

    // Send message
    if ($action === 'send' && $method === 'POST') {
        $convId = (int)($_POST['conversation_id'] ?? 0);
        $text = trim((string)($_POST['message'] ?? ''));
        if ($convId <= 0 || $text === '') jsonOut(['ok' => false, 'error' => 'Empty message'], 400);
        if (strlen($text) > 2000) jsonOut(['ok' => false, 'error' => 'Message too long'], 400);

        $conv = userCanAccess($conn, $convId, $userId);
        if (!$conv) jsonOut(['ok' => false, 'error' => 'Chat not found'], 404);

        $conn->prepare('INSERT INTO chat_messages (conversation_id, sender_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())')
             ->execute([$convId, $userId, $text]);
        $msgId = (int)$conn->lastInsertId();
        $conn->prepare('UPDATE conversations SET last_message_at = NOW() WHERE id = ?')->execute([$convId]);

        try {
            $otherId = ((int)$conv['patient_id'] === $userId) ? (int)$conv['provider_id'] : (int)$conv['patient_id'];
            $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                            VALUES (?, 'chat', 'New message', ?, ?, 0, NOW())")
                 ->execute([$otherId, $userName . ' sent you a message.', 'dashboard/messages.php?c=' . $convId]);
        } catch (Exception $e) {}

        jsonOut([
            'ok' => true,
            'message' => [
                'id' => $msgId,
                'conversation_id' => $convId,
                'sender_id' => $userId,
                'message' => $text,
                'created_at' => date('Y-m-d H:i:s'),
                'sender_name' => $userName,
            ],
        ]);
    }

    // Start conversation (patient)
    if ($action === 'start' && $method === 'POST') {
        if ($role !== 'patient') jsonOut(['ok' => false, 'error' => 'Only patients can start chats'], 403);
        $providerId = (int)($_POST['provider_id'] ?? 0);
        if ($providerId <= 0) jsonOut(['ok' => false, 'error' => 'Choose a provider'], 400);

        $p = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('doctor','hospital') LIMIT 1");
        $p->execute([$providerId]);
        if (!$p->fetch()) jsonOut(['ok' => false, 'error' => 'Provider not found'], 404);

        $stmt = $conn->prepare('SELECT id FROM conversations WHERE patient_id = ? AND provider_id = ? LIMIT 1');
        $stmt->execute([$userId, $providerId]);
        $existing = $stmt->fetch();
        if ($existing) jsonOut(['ok' => true, 'conversation_id' => (int)$existing['id']]);

        $conn->prepare('INSERT INTO conversations (patient_id, provider_id, created_at, last_message_at) VALUES (?, ?, NOW(), NOW())')
             ->execute([$userId, $providerId]);
        jsonOut(['ok' => true, 'conversation_id' => (int)$conn->lastInsertId()]);
    }

    // Providers list for patients
    if ($action === 'providers') {
        try {
            $providers = $conn->query("
                SELECT u.id, u.name, u.role, p.specialty, p.clinic_name
                FROM users u
                LEFT JOIN provider_profiles p ON p.user_id = u.id
                WHERE u.role IN ('doctor','hospital') AND (u.status = 'active' OR u.status IS NULL)
                ORDER BY u.name ASC LIMIT 50
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $providers = $conn->query("SELECT id, name, role, NULL specialty, NULL clinic_name FROM users WHERE role IN ('doctor','hospital') ORDER BY name LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
        }
        jsonOut(['ok' => true, 'providers' => $providers]);
    }

    jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
} catch (Exception $e) {
    error_log('chat-api: ' . $e->getMessage());
    jsonOut(['ok' => false, 'error' => 'Server error'], 500);
}
