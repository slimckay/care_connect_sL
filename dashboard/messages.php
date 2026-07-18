<?php
/**
 * In-app chat — Care Connect SL
 * Patients and doctors/hospitals can message each other.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../db.php';

$userId = (int)$_SESSION['user_id'];
$role = strtolower($_SESSION['role'] ?? '');
$userName = $_SESSION['user_name'] ?? 'User';
$message = '';
$error = '';

if (!in_array($role, ['patient', 'doctor', 'hospital'], true)) {
    header('Location: ../login.php');
    exit;
}

// Ensure chat tables exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            provider_id INT NOT NULL,
            referral_id INT NULL,
            last_message_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_pair (patient_id, provider_id),
            INDEX idx_patient (patient_id),
            INDEX idx_provider (provider_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            sender_id INT NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conversation (conversation_id),
            INDEX idx_sender (sender_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    error_log('Chat schema: ' . $e->getMessage());
}

// Start new conversation (patient chooses provider)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_chat') {
    $providerId = (int)($_POST['provider_id'] ?? 0);
    if ($role !== 'patient') {
        $error = 'Only patients can start a new chat from this list.';
    } elseif ($providerId <= 0) {
        $error = 'Please choose a provider.';
    } else {
        try {
            // Verify provider exists
            $p = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('doctor','hospital') LIMIT 1");
            $p->execute([$providerId]);
            if (!$p->fetch()) {
                $error = 'Provider not found.';
            } else {
                $stmt = $conn->prepare("SELECT id FROM conversations WHERE patient_id = ? AND provider_id = ? LIMIT 1");
                $stmt->execute([$userId, $providerId]);
                $existing = $stmt->fetch();
                if ($existing) {
                    header('Location: messages.php?c=' . (int)$existing['id']);
                    exit;
                }
                $conn->prepare("INSERT INTO conversations (patient_id, provider_id, created_at, last_message_at) VALUES (?, ?, NOW(), NOW())")
                     ->execute([$userId, $providerId]);
                $newId = (int)$conn->lastInsertId();
                header('Location: messages.php?c=' . $newId);
                exit;
            }
        } catch (Exception $e) {
            $error = 'Could not start chat.';
        }
    }
}

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $convId = (int)($_POST['conversation_id'] ?? 0);
    $text = trim($_POST['message'] ?? '');

    if ($convId <= 0 || $text === '') {
        $error = 'Type a message before sending.';
    } elseif (strlen($text) > 2000) {
        $error = 'Message is too long (max 2000 characters).';
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ? LIMIT 1");
            $stmt->execute([$convId]);
            $conv = $stmt->fetch();

            if (!$conv || ((int)$conv['patient_id'] !== $userId && (int)$conv['provider_id'] !== $userId)) {
                $error = 'You do not have access to this chat.';
            } else {
                $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())")
                     ->execute([$convId, $userId, $text]);
                $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?")
                     ->execute([$convId]);

                // Notify the other person
                try {
                    $otherId = ((int)$conv['patient_id'] === $userId) ? (int)$conv['provider_id'] : (int)$conv['patient_id'];
                    $conn->prepare("
                        INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                        VALUES (?, 'chat', 'New message', ?, ?, 0, NOW())
                    ")->execute([
                        $otherId,
                        $userName . ' sent you a message.',
                        'dashboard/messages.php?c=' . $convId
                    ]);
                } catch (Exception $e) {}

                header('Location: messages.php?c=' . $convId);
                exit;
            }
        } catch (Exception $e) {
            $error = 'Could not send message.';
        }
    }
}

$activeConvId = (int)($_GET['c'] ?? 0);

// Load conversations for this user
$conversations = [];
try {
    if ($role === 'patient') {
        $stmt = $conn->prepare("
            SELECT c.*,
                   u.name AS other_name,
                   u.role AS other_role,
                   (SELECT message FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
                   (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread
            FROM conversations c
            JOIN users u ON u.id = c.provider_id
            WHERE c.patient_id = ?
            ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        ");
        $stmt->execute([$userId, $userId]);
    } else {
        $stmt = $conn->prepare("
            SELECT c.*,
                   u.name AS other_name,
                   u.role AS other_role,
                   (SELECT message FROM chat_messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
                   (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.is_read = 0) AS unread
            FROM conversations c
            JOIN users u ON u.id = c.patient_id
            WHERE c.provider_id = ?
            ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        ");
        $stmt->execute([$userId, $userId]);
    }
    $conversations = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $error ?: 'Could not load chats.';
}

// Active conversation + messages
$activeConv = null;
$chatMessages = [];
if ($activeConvId > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = ? LIMIT 1");
        $stmt->execute([$activeConvId]);
        $activeConv = $stmt->fetch();

        if ($activeConv && ((int)$activeConv['patient_id'] === $userId || (int)$activeConv['provider_id'] === $userId)) {
            // Mark as read
            $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?")
                 ->execute([$activeConvId, $userId]);

            $m = $conn->prepare("
                SELECT m.*, u.name AS sender_name
                FROM chat_messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.conversation_id = ?
                ORDER BY m.created_at ASC
                LIMIT 300
            ");
            $m->execute([$activeConvId]);
            $chatMessages = $m->fetchAll();

            // Other party name
            $otherId = ((int)$activeConv['patient_id'] === $userId) ? (int)$activeConv['provider_id'] : (int)$activeConv['patient_id'];
            $on = $conn->prepare("SELECT name, role FROM users WHERE id = ? LIMIT 1");
            $on->execute([$otherId]);
            $other = $on->fetch() ?: ['name' => 'User', 'role' => ''];
            $activeConv['other_name'] = $other['name'];
            $activeConv['other_role'] = $other['role'];
        } else {
            $activeConv = null;
            $activeConvId = 0;
        }
    } catch (Exception $e) {
        $activeConv = null;
    }
}

// Providers list for patients to start chat
$providers = [];
if ($role === 'patient') {
    try {
        $providers = $conn->query("
            SELECT u.id, u.name, u.role, p.specialty, p.clinic_name
            FROM users u
            LEFT JOIN provider_profiles p ON p.user_id = u.id
            WHERE u.role IN ('doctor','hospital') AND u.status = 'active'
            ORDER BY u.name ASC
            LIMIT 50
        ")->fetchAll();
    } catch (Exception $e) {
        try {
            $providers = $conn->query("
                SELECT id, name, role, NULL AS specialty, NULL AS clinic_name
                FROM users WHERE role IN ('doctor','hospital')
                ORDER BY name ASC LIMIT 50
            ")->fetchAll();
        } catch (Exception $e2) {}
    }
}

$backLink = $role === 'patient' ? 'patient-dashboard.php' : 'provider-dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    body { background:#F4F7FB; }
    .chat-wrap { max-width:1100px; margin:24px auto 48px; padding:0 14px; }
    .chat-top { display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
    .chat-top h1 { margin:0; font-size:1.5rem; color:#0F1C3A !important; }
    .back { color:#1EB53A !important; font-weight:600; text-decoration:none; }
    .alert { padding:12px 14px; border-radius:12px; margin-bottom:14px; font-weight:600; }
    .alert.success { background:#ECFDF5; color:#065F46; }
    .alert.error { background:#FEF2F2; color:#991B1B; }

    .chat-layout {
      display:grid;
      grid-template-columns: 320px 1fr;
      gap:14px;
      min-height:70vh;
    }
    .panel {
      background:#fff; border:1px solid #E5E7EB; border-radius:16px;
      box-shadow:0 6px 18px rgba(15,23,42,0.04); overflow:hidden;
      display:flex; flex-direction:column;
    }
    .panel-head {
      padding:14px 16px; border-bottom:1px solid #E5E7EB;
      font-weight:700; color:#0F1C3A !important;
    }
    .conv-list { overflow:auto; flex:1; }
    .conv-item {
      display:block; padding:14px 16px; border-bottom:1px solid #F1F5F9;
      text-decoration:none; color:inherit;
    }
    .conv-item:hover, .conv-item.active { background:#F0FDF4; }
    .conv-item .name { font-weight:700; color:#0F1C3A !important; margin-bottom:4px; }
    .conv-item .preview { color:#64748B !important; font-size:0.88rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .badge-unread {
      display:inline-block; min-width:20px; padding:2px 7px; border-radius:999px;
      background:#1EB53A; color:#fff !important; font-size:0.75rem; font-weight:700;
      margin-left:6px;
    }

    .thread { display:flex; flex-direction:column; min-height:70vh; }
    .thread-head {
      padding:14px 16px; border-bottom:1px solid #E5E7EB;
      font-weight:700; color:#0F1C3A !important;
    }
    .thread-body {
      flex:1; overflow:auto; padding:16px; background:#F8FAFC;
      display:flex; flex-direction:column; gap:10px;
    }
    .bubble {
      max-width:78%; padding:10px 14px; border-radius:14px; line-height:1.45;
      font-size:0.95rem;
    }
    .bubble.me {
      align-self:flex-end; background:linear-gradient(135deg,#1EB53A,#15803D); color:#fff !important;
      border-bottom-right-radius:4px;
    }
    .bubble.them {
      align-self:flex-start; background:#fff; border:1px solid #E5E7EB; color:#0F172A !important;
      border-bottom-left-radius:4px;
    }
    .bubble .meta { font-size:0.72rem; opacity:0.8; margin-top:4px; }

    .thread-form {
      display:flex; gap:8px; padding:12px; border-top:1px solid #E5E7EB; background:#fff;
    }
    .thread-form input[type="text"] {
      flex:1; border:1.5px solid #E5E7EB; border-radius:999px; padding:12px 16px; font:inherit;
    }
    .thread-form button {
      border:none; border-radius:999px; padding:12px 18px; font-weight:700; cursor:pointer;
      background:linear-gradient(135deg,#1EB53A,#15803D); color:#fff;
    }
    .empty { padding:28px; text-align:center; color:#64748B !important; }

    .start-box { padding:14px 16px; border-top:1px solid #E5E7EB; }
    .start-box select, .start-box button {
      width:100%; margin-top:8px; padding:10px 12px; border-radius:10px; font:inherit;
    }
    .start-box select { border:1.5px solid #E5E7EB; background:#fff; }
    .start-box button {
      border:none; background:#0F1C3A; color:#fff; font-weight:600; cursor:pointer;
    }

    [data-theme="dark"] body { background:#0f172a; }
    [data-theme="dark"] .panel { background:#1e293b; border-color:#334155; }
    [data-theme="dark"] .panel-head, [data-theme="dark"] .thread-head, [data-theme="dark"] .conv-item .name, [data-theme="dark"] .chat-top h1 { color:#F8FAFC !important; }
    [data-theme="dark"] .thread-body { background:#0f172a; }
    [data-theme="dark"] .bubble.them { background:#1e293b; border-color:#334155; color:#E2E8F0 !important; }
    [data-theme="dark"] .thread-form { background:#1e293b; border-color:#334155; }
    [data-theme="dark"] .thread-form input[type="text"], [data-theme="dark"] .start-box select {
      background:#0f172a; border-color:#334155; color:#E2E8F0;
    }

    @media (max-width: 800px) {
      .chat-layout { grid-template-columns: 1fr; }
      .panel.list-panel { max-height: 280px; }
      .thread { min-height: 55vh; }
    }
  </style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="../index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <div class="nav-actions">
      <button onclick="toggleDarkMode()" class="dark-toggle" type="button">🌓</button>
      <a href="<?= htmlspecialchars($backLink) ?>" class="btn-ghost">Dashboard</a>
      <a href="../logout.php" class="btn-ghost">Logout</a>
    </div>
  </div>
</header>

<main class="chat-wrap">
  <div class="chat-top">
    <div>
      <a class="back" href="<?= htmlspecialchars($backLink) ?>">← Back</a>
      <h1>💬 Messages</h1>
    </div>
  </div>

  <?php if ($message): ?><div class="alert success">✅ <?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="chat-layout">
    <div class="panel list-panel">
      <div class="panel-head">Conversations</div>
      <div class="conv-list">
        <?php if (empty($conversations)): ?>
          <div class="empty">No chats yet.</div>
        <?php else: ?>
          <?php foreach ($conversations as $c): ?>
            <a class="conv-item <?= $activeConvId === (int)$c['id'] ? 'active' : '' ?>" href="messages.php?c=<?= (int)$c['id'] ?>">
              <div class="name">
                <?= htmlspecialchars($c['other_name'] ?? 'User') ?>
                <?php if (!empty($c['unread'])): ?>
                  <span class="badge-unread"><?= (int)$c['unread'] ?></span>
                <?php endif; ?>
              </div>
              <div class="preview"><?= htmlspecialchars($c['last_message'] ?? 'No messages yet') ?></div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php if ($role === 'patient' && !empty($providers)): ?>
        <div class="start-box">
          <strong style="color:#0F1C3A;">Start chat with a provider</strong>
          <form method="POST">
            <input type="hidden" name="action" value="start_chat">
            <select name="provider_id" required>
              <option value="">Choose doctor / clinic...</option>
              <?php foreach ($providers as $p): ?>
                <option value="<?= (int)$p['id'] ?>">
                  <?= htmlspecialchars($p['name']) ?>
                  <?php if (!empty($p['specialty'])): ?> — <?= htmlspecialchars($p['specialty']) ?><?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit">Start Chat</button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel thread">
      <?php if (!$activeConv): ?>
        <div class="empty" style="margin:auto;">
          Select a conversation<?= $role === 'patient' ? ' or start a new chat with a provider' : '' ?>.
        </div>
      <?php else: ?>
        <div class="thread-head">
          <?= htmlspecialchars($activeConv['other_name'] ?? 'Chat') ?>
          <span style="font-weight:500; color:#64748B; font-size:0.9rem;">
            · <?= htmlspecialchars(ucfirst($activeConv['other_role'] ?? '')) ?>
          </span>
        </div>
        <div class="thread-body" id="threadBody">
          <?php if (empty($chatMessages)): ?>
            <div class="empty">No messages yet. Say hello 👋</div>
          <?php else: ?>
            <?php foreach ($chatMessages as $m): ?>
              <?php $mine = (int)$m['sender_id'] === $userId; ?>
              <div class="bubble <?= $mine ? 'me' : 'them' ?>">
                <?= nl2br(htmlspecialchars($m['message'])) ?>
                <div class="meta">
                  <?= $mine ? 'You' : htmlspecialchars($m['sender_name'] ?? '') ?>
                  · <?= !empty($m['created_at']) ? date('M d, H:i', strtotime($m['created_at'])) : '' ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <form method="POST" class="thread-form">
          <input type="hidden" name="action" value="send">
          <input type="hidden" name="conversation_id" value="<?= (int)$activeConvId ?>">
          <input type="text" name="message" placeholder="Type your message..." required maxlength="2000" autocomplete="off">
          <button type="submit">Send</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</main>

<script src="../js/dark-mode.js"></script>
<script>
  // Keep chat scrolled to latest message
  const body = document.getElementById('threadBody');
  if (body) body.scrollTop = body.scrollHeight;

  // Light auto-refresh while a chat is open
  <?php if ($activeConvId > 0): ?>
  setTimeout(function () {
    if (!document.hidden) {
      window.location.reload();
    }
  }, 15000);
  <?php endif; ?>
</script>
</body>
</html>
