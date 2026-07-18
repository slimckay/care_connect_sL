<?php
/**
 * Live chat UI — history + read receipts
 * Doctors see chats patients start with them (provider_id = doctor).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $redirect = '../login.php';
    if (!empty($_SERVER['REQUEST_URI'])) {
        $redirect .= '?redirect=' . urlencode($_SERVER['REQUEST_URI']);
    }
    header('Location: ' . $redirect);
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
$userName = $_SESSION['user_name'] ?? 'User';
$userId = (int)$_SESSION['user_id'];

if (!in_array($role, ['patient', 'doctor', 'hospital'], true)) {
    header('Location: ../login.php');
    exit;
}

$backLink = $role === 'patient' ? 'patient-dashboard.php' : 'provider-dashboard.php';
$initialConv = (int)($_GET['c'] ?? 0);
$startProvider = (int)($_GET['start'] ?? 0);
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
    .live-pill {
      display:inline-flex; align-items:center; gap:6px;
      background:#ECFDF5; color:#065F46; font-size:0.8rem; font-weight:700;
      padding:6px 10px; border-radius:999px;
    }
    .live-pill .dot {
      width:8px; height:8px; border-radius:50%; background:#16A34A;
      animation: pulseDot 1.4s infinite;
    }
    @keyframes pulseDot {
      0% { box-shadow:0 0 0 0 rgba(22,163,74,0.45); }
      70% { box-shadow:0 0 0 8px rgba(22,163,74,0); }
      100% { box-shadow:0 0 0 0 rgba(22,163,74,0); }
    }
    .back { color:#1EB53A !important; font-weight:600; text-decoration:none; }
    .role-note { font-size:0.88rem; color:#64748B; margin-top:4px; }

    .chat-layout { display:grid; grid-template-columns:320px 1fr; gap:14px; min-height:70vh; }
    .panel {
      background:#fff; border:1px solid #E5E7EB; border-radius:16px;
      box-shadow:0 6px 18px rgba(15,23,42,0.04); overflow:hidden;
      display:flex; flex-direction:column;
    }
    .panel-head { padding:14px 16px; border-bottom:1px solid #E5E7EB; font-weight:700; color:#0F1C3A !important; }
    .conv-list { overflow:auto; flex:1; }
    .conv-item {
      display:block; width:100%; text-align:left; padding:14px 16px; border:0; border-bottom:1px solid #F1F5F9;
      background:transparent; cursor:pointer; font:inherit;
    }
    .conv-item:hover, .conv-item.active { background:#F0FDF4; }
    .conv-item .name { font-weight:700; color:#0F1C3A !important; margin-bottom:4px; }
    .conv-item .preview { color:#64748B !important; font-size:0.88rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .conv-item .time { font-size:0.75rem; color:#94A3B8; margin-top:4px; }
    .badge-unread {
      display:inline-block; min-width:20px; padding:2px 7px; border-radius:999px;
      background:#1EB53A; color:#fff !important; font-size:0.75rem; font-weight:700; margin-left:6px;
    }

    .thread { display:flex; flex-direction:column; min-height:70vh; }
    .thread-head {
      padding:14px 16px; border-bottom:1px solid #E5E7EB; font-weight:700; color:#0F1C3A !important;
      display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;
    }
    .history-meta { font-weight:500; font-size:0.82rem; color:#64748B; }
    .thread-body {
      flex:1; overflow:auto; padding:16px; background:#F8FAFC;
      display:flex; flex-direction:column; gap:10px;
    }
    .day-sep {
      align-self:center; font-size:0.75rem; font-weight:600; color:#64748B;
      background:#E2E8F0; padding:4px 12px; border-radius:999px; margin:6px 0;
    }
    .bubble { max-width:78%; padding:10px 14px; border-radius:14px; line-height:1.45; font-size:0.95rem; }
    .bubble.me {
      align-self:flex-end; background:linear-gradient(135deg,#1EB53A,#15803D); color:#fff !important;
      border-bottom-right-radius:4px;
    }
    .bubble.them {
      align-self:flex-start; background:#fff; border:1px solid #E5E7EB; color:#0F172A !important;
      border-bottom-left-radius:4px;
    }
    .bubble .meta { font-size:0.72rem; opacity:0.9; margin-top:4px; display:flex; gap:8px; align-items:center; justify-content:flex-end; }
    .bubble.them .meta { justify-content:flex-start; }
    .receipt { font-size:0.72rem; letter-spacing:0.02em; }
    .receipt.sent { opacity:0.85; }
    .receipt.read { color:#BBF7D0; font-weight:700; }
    .bubble.them .receipt.read { color:#15803D; }
    .bubble.new-in { animation: popIn 0.2s ease; }
    @keyframes popIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }

    .thread-form { display:flex; gap:8px; padding:12px; border-top:1px solid #E5E7EB; background:#fff; }
    .thread-form input[type="text"] {
      flex:1; border:1.5px solid #E5E7EB; border-radius:999px; padding:12px 16px; font:inherit;
    }
    .thread-form button {
      border:none; border-radius:999px; padding:12px 18px; font-weight:700; cursor:pointer;
      background:linear-gradient(135deg,#1EB53A,#15803D); color:#fff;
    }
    .thread-form button:disabled { opacity:0.6; cursor:not-allowed; }
    .empty { padding:28px; text-align:center; color:#64748B !important; }

    .start-box { padding:14px 16px; border-top:1px solid #E5E7EB; }
    .start-box select, .start-box button { width:100%; margin-top:8px; padding:10px 12px; border-radius:10px; font:inherit; }
    .start-box select { border:1.5px solid #E5E7EB; background:#fff; }
    .start-box button { border:none; background:#0F1C3A; color:#fff; font-weight:600; cursor:pointer; }

    [data-theme="dark"] body { background:#0f172a; }
    [data-theme="dark"] .panel { background:#1e293b; border-color:#334155; }
    [data-theme="dark"] .panel-head, [data-theme="dark"] .thread-head, [data-theme="dark"] .conv-item .name, [data-theme="dark"] .chat-top h1 { color:#F8FAFC !important; }
    [data-theme="dark"] .thread-body { background:#0f172a; }
    [data-theme="dark"] .bubble.them { background:#1e293b; border-color:#334155; color:#E2E8F0 !important; }
    [data-theme="dark"] .thread-form { background:#1e293b; border-color:#334155; }
    [data-theme="dark"] .thread-form input[type="text"], [data-theme="dark"] .start-box select { background:#0f172a; border-color:#334155; color:#E2E8F0; }
    [data-theme="dark"] .day-sep { background:#334155; color:#E2E8F0; }

    @media (max-width:800px) {
      .chat-layout { grid-template-columns:1fr; }
      .panel.list-panel { max-height:260px; }
      .thread { min-height:55vh; }
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
      <a href="../logout.php" class="btn-ghost btn-logout">Log out</a>
    </div>
  </div>
</header>

<main class="chat-wrap">
  <div class="chat-top">
    <div>
      <a class="back" href="<?= htmlspecialchars($backLink) ?>">← Back</a>
      <h1>💬 Messages</h1>
      <div class="role-note">
        <?php if ($role === 'patient'): ?>
          Chat with doctors. Full history and read receipts included.
        <?php else: ?>
          Patient chats appear here when they message you. Unread counts show new messages.
        <?php endif; ?>
      </div>
    </div>
    <div class="live-pill"><span class="dot"></span> Live</div>
  </div>

  <div class="chat-layout">
    <div class="panel list-panel">
      <div class="panel-head">Message history</div>
      <div class="conv-list" id="convList"><div class="empty">Loading...</div></div>
      <?php if ($role === 'patient'): ?>
      <div class="start-box" id="startBox">
        <strong style="color:#0F1C3A;">Start chat with a provider</strong>
        <select id="providerSelect"><option value="">Loading providers...</option></select>
        <button type="button" id="startChatBtn">Start Chat</button>
      </div>
      <?php endif; ?>
    </div>

    <div class="panel thread">
      <div class="thread-head">
        <span id="threadHead">Select a conversation</span>
        <span class="history-meta" id="historyMeta"></span>
      </div>
      <div class="thread-body" id="threadBody"><div class="empty">Choose a chat to view full message history.</div></div>
      <form class="thread-form" id="sendForm" style="display:none;">
        <input type="text" id="messageInput" placeholder="Type your message..." maxlength="2000" autocomplete="off" required>
        <button type="submit" id="sendBtn">Send</button>
      </form>
    </div>
  </div>
</main>

<script src="../js/dark-mode.js"></script>
<script src="../js/mobile-logout.js"></script>
<script>
(function () {
  const ME = <?= (int)$userId ?>;
  const ROLE = <?= json_encode($role) ?>;
  const API = '../api/chat-api.php';
  const initialConv = <?= (int)$initialConv ?>;
  const startProvider = <?= (int)$startProvider ?>;

  let activeId = 0;
  let lastMsgId = 0;
  let pollTimer = null;
  let knownIds = new Set();
  let lastDayKey = '';

  const convList = document.getElementById('convList');
  const threadBody = document.getElementById('threadBody');
  const threadHead = document.getElementById('threadHead');
  const historyMeta = document.getElementById('historyMeta');
  const sendForm = document.getElementById('sendForm');
  const messageInput = document.getElementById('messageInput');
  const sendBtn = document.getElementById('sendBtn');

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[c]);
  }

  function parseDate(ts) {
    if (!ts) return null;
    const d = new Date(String(ts).replace(' ', 'T'));
    return isNaN(d.getTime()) ? null : d;
  }

  function fmtTime(ts) {
    const d = parseDate(ts);
    if (!d) return ts || '';
    return d.toLocaleString(undefined, { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
  }

  function dayKey(ts) {
    const d = parseDate(ts);
    if (!d) return '';
    return d.toDateString();
  }

  function dayLabel(ts) {
    const d = parseDate(ts);
    if (!d) return '';
    const today = new Date();
    const yday = new Date();
    yday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === yday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString(undefined, { weekday:'short', month:'short', day:'numeric', year:'numeric' });
  }

  function receiptHtml(m, mine) {
    if (!mine) return '';
    const read = parseInt(m.is_read, 10) === 1;
    if (read) {
      return '<span class="receipt read" title="Read' + (m.read_at ? ' · ' + fmtTime(m.read_at) : '') + '">✓✓ Seen</span>';
    }
    return '<span class="receipt sent" title="Delivered">✓ Sent</span>';
  }

  async function api(action, opts = {}) {
    const method = opts.method || 'GET';
    let url = API + '?action=' + encodeURIComponent(action);
    if (method === 'GET' && opts.params) {
      Object.keys(opts.params).forEach(k => {
        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(opts.params[k]);
      });
    }
    const res = await fetch(url, {
      method,
      headers: method === 'POST' ? { 'Content-Type': 'application/json' } : undefined,
      body: method === 'POST' ? JSON.stringify(Object.assign({ action }, opts.body || {})) : undefined,
      credentials: 'same-origin'
    });
    return res.json();
  }

  function renderConversations(list) {
    if (!list || !list.length) {
      convList.innerHTML = ROLE === 'doctor' || ROLE === 'hospital'
        ? '<div class="empty">No patient chats yet. When a patient messages you, it will show here.</div>'
        : '<div class="empty">No chats yet. Start one below or from a doctor profile.</div>';
      return;
    }
    convList.innerHTML = list.map(c => {
      const unread = parseInt(c.unread || 0, 10);
      const t = c.last_message_time || c.last_message_at || '';
      return `<button type="button" class="conv-item ${activeId === parseInt(c.id,10) ? 'active' : ''}" data-id="${c.id}">
        <div class="name">${esc(c.other_name || 'User')}${unread ? `<span class="badge-unread">${unread}</span>` : ''}</div>
        <div class="preview">${esc(c.last_message || 'No messages yet')}</div>
        <div class="time">${esc(fmtTime(t))}</div>
      </button>`;
    }).join('');

    convList.querySelectorAll('.conv-item').forEach(btn => {
      btn.addEventListener('click', () => openConversation(parseInt(btn.dataset.id, 10)));
    });
  }

  function maybeDaySep(ts) {
    const key = dayKey(ts);
    if (!key || key === lastDayKey) return;
    lastDayKey = key;
    const sep = document.createElement('div');
    sep.className = 'day-sep';
    sep.textContent = dayLabel(ts);
    threadBody.appendChild(sep);
  }

  function appendBubble(m, animate) {
    const id = parseInt(m.id, 10);
    if (knownIds.has(id)) {
      // Update receipt only
      const existing = threadBody.querySelector('.bubble[data-id="' + id + '"] .receipt');
      if (existing && parseInt(m.sender_id, 10) === ME) {
        const wrap = document.createElement('div');
        wrap.innerHTML = receiptHtml(m, true);
        if (wrap.firstChild) existing.replaceWith(wrap.firstChild);
      }
      return;
    }
    knownIds.add(id);
    lastMsgId = Math.max(lastMsgId, id);

    maybeDaySep(m.created_at);

    const mine = parseInt(m.sender_id, 10) === ME;
    const div = document.createElement('div');
    div.className = 'bubble ' + (mine ? 'me' : 'them') + (animate ? ' new-in' : '');
    div.dataset.id = id;
    div.innerHTML =
      esc(m.message).replace(/\n/g, '<br>') +
      '<div class="meta">' +
        '<span>' + (mine ? 'You' : esc(m.sender_name || '')) + ' · ' + fmtTime(m.created_at) + '</span>' +
        receiptHtml(m, mine) +
      '</div>';

    const empty = threadBody.querySelector('.empty');
    if (empty) empty.remove();
    threadBody.appendChild(div);
    threadBody.scrollTop = threadBody.scrollHeight;
  }

  function applyReceipts(receipts) {
    (receipts || []).forEach(r => {
      const el = threadBody.querySelector('.bubble[data-id="' + r.id + '"] .receipt');
      if (!el) return;
      if (parseInt(r.is_read, 10) === 1) {
        el.className = 'receipt read';
        el.textContent = '✓✓ Seen';
        el.title = 'Read' + (r.read_at ? ' · ' + fmtTime(r.read_at) : '');
      }
    });
  }

  async function loadConversations() {
    const data = await api('conversations');
    if (data.ok) renderConversations(data.conversations || []);
  }

  async function openConversation(id) {
    activeId = id;
    lastMsgId = 0;
    knownIds = new Set();
    lastDayKey = '';
    threadBody.innerHTML = '<div class="empty">Loading history...</div>';
    sendForm.style.display = 'flex';
    historyMeta.textContent = '';

    convList.querySelectorAll('.conv-item').forEach(el => {
      el.classList.toggle('active', parseInt(el.dataset.id, 10) === id);
    });

    const data = await api('messages', { params: { conversation_id: id } });
    if (!data.ok) {
      threadBody.innerHTML = '<div class="empty">Could not load chat history.</div>';
      return;
    }

    threadHead.textContent = (data.other_name || 'Chat') + ' · ' + String(data.other_role || '').replace(/^./, c => c.toUpperCase());
    threadBody.innerHTML = '';
    lastDayKey = '';
    const msgs = data.messages || [];
    historyMeta.textContent = msgs.length ? (msgs.length + ' message' + (msgs.length === 1 ? '' : 's') + ' in history') : '';
    if (!msgs.length) {
      threadBody.innerHTML = '<div class="empty">No messages yet. Say hello 👋</div>';
    } else {
      msgs.forEach(m => appendBubble(m, false));
    }

    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(pollNew, 2000);
    loadConversations();
    messageInput.focus();

    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, '', 'messages.php?c=' + id);
    }
  }

  async function startWithProvider(pid) {
    if (!pid) return;
    if (ROLE !== 'patient') {
      alert('Only patients start chats from a doctor profile.');
      return;
    }
    threadBody.innerHTML = '<div class="empty">Opening chat with provider...</div>';
    const data = await api('start', { method: 'POST', body: { provider_id: pid } });
    if (data.ok) {
      await loadConversations();
      openConversation(parseInt(data.conversation_id, 10));
    } else {
      threadBody.innerHTML = '<div class="empty">' + esc(data.error || 'Could not start chat') + '</div>';
    }
  }

  async function pollNew() {
    if (!activeId || document.hidden) return;
    try {
      const data = await api('messages', {
        params: { conversation_id: activeId, after_id: lastMsgId }
      });
      if (!data.ok) return;
      const msgs = data.messages || [];
      if (msgs.length) {
        msgs.forEach(m => appendBubble(m, true));
        historyMeta.textContent = knownIds.size + ' message' + (knownIds.size === 1 ? '' : 's') + ' in history';
        loadConversations();
      }
      // Update Seen receipts on your own older messages
      applyReceipts(data.receipts || []);
    } catch (e) {}
  }

  sendForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!activeId) return;
    const text = messageInput.value.trim();
    if (!text) return;

    sendBtn.disabled = true;
    messageInput.value = '';
    try {
      const data = await api('send', {
        method: 'POST',
        body: { conversation_id: activeId, message: text }
      });
      if (data.ok && data.message) {
        appendBubble(data.message, true);
        historyMeta.textContent = knownIds.size + ' message' + (knownIds.size === 1 ? '' : 's') + ' in history';
        loadConversations();
      } else {
        messageInput.value = text;
        alert(data.error || 'Could not send');
      }
    } catch (err) {
      messageInput.value = text;
      alert('Network error');
    } finally {
      sendBtn.disabled = false;
      messageInput.focus();
    }
  });

  const startBtn = document.getElementById('startChatBtn');
  const providerSelect = document.getElementById('providerSelect');
  if (startBtn && providerSelect) {
    api('providers').then(data => {
      if (!data.ok) return;
      const list = data.providers || [];
      providerSelect.innerHTML = '<option value="">Choose doctor / clinic...</option>' +
        list.map(p => `<option value="${p.id}">${esc(p.name)}${p.specialty ? ' — ' + esc(p.specialty) : ''}</option>`).join('');
      if (startProvider > 0) providerSelect.value = String(startProvider);
    });

    startBtn.addEventListener('click', async () => {
      const pid = parseInt(providerSelect.value, 10);
      if (!pid) return alert('Choose a provider first');
      startBtn.disabled = true;
      try { await startWithProvider(pid); }
      finally { startBtn.disabled = false; }
    });
  }

  loadConversations().then(async () => {
    if (initialConv > 0) openConversation(initialConv);
    else if (startProvider > 0) await startWithProvider(startProvider);
  });

  setInterval(() => { if (!document.hidden) loadConversations(); }, 8000);
})();
</script>
</body>
</html>
