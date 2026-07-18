<?php
/**
 * WhatsApp-style messaging UI — Care Connect SL
 * Mobile: list OR chat (full screen). Desktop: split view.
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#075E54">
  <title>Messages — Care Connect SL</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --wa-teal: #075E54;
      --wa-teal-dark: #054C44;
      --wa-green: #25D366;
      --wa-light: #DCF8C6;
      --wa-bg: #E5DDD5;
      --wa-panel: #FFFFFF;
      --wa-chat-bg: #EFEAE2;
      --wa-muted: #667781;
      --wa-border: #E9EDEF;
      --wa-incoming: #FFFFFF;
      --wa-outgoing: #D9FDD3;
      --safe-bottom: env(safe-area-inset-bottom, 0px);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      height: 100%;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #111B21;
      color: #111B21;
      overflow: hidden;
    }

    .app {
      height: 100%;
      max-width: 1400px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr;
      background: var(--wa-panel);
      position: relative;
    }

    /* ===== SIDEBAR (chat list) ===== */
    .sidebar {
      display: flex;
      flex-direction: column;
      height: 100%;
      background: var(--wa-panel);
      min-width: 0;
    }
    .side-header {
      background: var(--wa-teal);
      color: #fff;
      padding: 12px 14px;
      display: flex;
      align-items: center;
      gap: 12px;
      min-height: 60px;
    }
    .side-header .avatar {
      width: 40px; height: 40px; border-radius: 50%;
      background: rgba(255,255,255,0.2);
      display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:0.95rem; flex-shrink:0;
    }
    .side-header .titles { flex:1; min-width:0; }
    .side-header h1 {
      font-size: 1.1rem; font-weight: 700; color:#fff; line-height:1.2;
    }
    .side-header .sub { font-size:0.78rem; opacity:0.85; }
    .icon-btn {
      width:40px; height:40px; border:none; border-radius:50%;
      background:transparent; color:#fff; font-size:1.2rem;
      cursor:pointer; display:flex; align-items:center; justify-content:center;
      text-decoration:none; flex-shrink:0;
    }
    .icon-btn:active { background: rgba(255,255,255,0.12); }

    .search-wrap {
      padding: 8px 12px;
      background: #fff;
      border-bottom: 1px solid var(--wa-border);
    }
    .search-wrap input {
      width:100%; border:none; background:#F0F2F5; border-radius:8px;
      padding:10px 14px; font:inherit; font-size:0.92rem; outline:none;
    }

    .conv-list {
      flex:1; overflow-y:auto; -webkit-overflow-scrolling: touch;
      background:#fff;
    }
    .conv-item {
      width:100%; border:0; background:transparent; text-align:left;
      display:flex; gap:12px; align-items:center;
      padding:12px 14px; cursor:pointer; font:inherit;
      border-bottom:1px solid #F0F2F5;
    }
    .conv-item:active, .conv-item.active { background:#F0F2F5; }
    .conv-avatar {
      width:52px; height:52px; border-radius:50%; flex-shrink:0;
      background: linear-gradient(135deg, #075E54, #25D366);
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:1.05rem;
    }
    .conv-body { flex:1; min-width:0; }
    .conv-top { display:flex; justify-content:space-between; gap:8px; margin-bottom:4px; }
    .conv-name { font-weight:600; font-size:1rem; color:#111B21; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .conv-time { font-size:0.75rem; color:var(--wa-muted); flex-shrink:0; }
    .conv-bottom { display:flex; justify-content:space-between; gap:8px; align-items:center; }
    .conv-preview { font-size:0.88rem; color:var(--wa-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1; }
    .unread-pill {
      background: var(--wa-green); color:#fff; font-size:0.72rem; font-weight:700;
      min-width:22px; height:22px; border-radius:999px;
      display:inline-flex; align-items:center; justify-content:center; padding:0 7px;
    }
    .empty-list {
      padding:48px 24px; text-align:center; color:var(--wa-muted);
    }
    .empty-list .big { font-size:2.5rem; margin-bottom:10px; }

    .start-panel {
      border-top:1px solid var(--wa-border);
      padding:12px;
      background:#F0F2F5;
    }
    .start-panel label { display:block; font-size:0.8rem; font-weight:600; color:#111B21; margin-bottom:6px; }
    .start-panel select, .start-panel button {
      width:100%; padding:11px 12px; border-radius:10px; font:inherit; font-size:0.92rem;
    }
    .start-panel select { border:1px solid #D1D7DB; background:#fff; margin-bottom:8px; }
    .start-panel button {
      border:none; background:var(--wa-teal); color:#fff; font-weight:700; cursor:pointer;
    }

    /* ===== CHAT PANEL ===== */
    .chat {
      display:none;
      flex-direction:column;
      height:100%;
      min-width:0;
      background: var(--wa-chat-bg);
      position:relative;
    }
    .chat.open { display:flex; }

    .chat-header {
      background: var(--wa-teal);
      color:#fff;
      padding:10px 12px;
      display:flex; align-items:center; gap:10px;
      min-height:60px;
      z-index:2;
    }
    .chat-header .back-btn {
      width:40px; height:40px; border:none; background:transparent; color:#fff;
      font-size:1.4rem; cursor:pointer; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      flex-shrink:0;
    }
    .chat-header .back-btn:active { background:rgba(255,255,255,0.12); }
    .chat-header .avatar {
      width:40px; height:40px; border-radius:50%;
      background:rgba(255,255,255,0.22);
      display:flex; align-items:center; justify-content:center;
      font-weight:700; flex-shrink:0;
    }
    .chat-header .info { flex:1; min-width:0; }
    .chat-header .info .name {
      font-weight:600; font-size:1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .chat-header .info .status {
      font-size:0.78rem; opacity:0.9;
    }

    .chat-body {
      flex:1;
      overflow-y:auto;
      -webkit-overflow-scrolling: touch;
      padding:12px 10px 8px;
      background-color: #EFEAE2;
      background-image:
        radial-gradient(circle at 20% 20%, rgba(7,94,84,0.04) 0, transparent 40%),
        radial-gradient(circle at 80% 60%, rgba(37,211,102,0.05) 0, transparent 35%);
      display:flex;
      flex-direction:column;
      gap:4px;
    }

    .day-sep {
      align-self:center;
      background: rgba(255,255,255,0.92);
      color:#54656F;
      font-size:0.75rem;
      font-weight:600;
      padding:5px 12px;
      border-radius:8px;
      margin:8px 0;
      box-shadow:0 1px 1px rgba(0,0,0,0.06);
    }

    .row {
      display:flex;
      width:100%;
      margin:2px 0;
    }
    .row.me { justify-content:flex-end; }
    .row.them { justify-content:flex-start; }

    .bubble {
      max-width: min(82%, 480px);
      padding:7px 10px 5px;
      border-radius:10px;
      font-size:0.95rem;
      line-height:1.4;
      position:relative;
      box-shadow: 0 1px 0.5px rgba(0,0,0,0.08);
      word-wrap:break-word;
      white-space:pre-wrap;
    }
    .row.them .bubble {
      background: var(--wa-incoming);
      border-top-left-radius: 0;
      color:#111B21;
    }
    .row.me .bubble {
      background: var(--wa-outgoing);
      border-top-right-radius: 0;
      color:#111B21;
    }
    .bubble .meta {
      display:flex;
      justify-content:flex-end;
      align-items:center;
      gap:5px;
      margin-top:3px;
      font-size:0.7rem;
      color:#667781;
      line-height:1;
    }
    .receipt { font-size:0.85rem; letter-spacing:-1px; }
    .receipt.sent { color:#667781; }
    .receipt.read { color:#53BDEB; }

    .chat-input-bar {
      display:flex;
      align-items:flex-end;
      gap:8px;
      padding:8px 10px calc(8px + var(--safe-bottom));
      background:#F0F2F5;
      border-top:1px solid #E9EDEF;
    }
    .chat-input-bar form {
      display:flex;
      flex:1;
      gap:8px;
      align-items:flex-end;
    }
    .chat-input-bar input[type="text"] {
      flex:1;
      border:none;
      background:#fff;
      border-radius:24px;
      padding:12px 16px;
      font:inherit;
      font-size:0.98rem;
      outline:none;
      max-height:120px;
    }
    .send-btn {
      width:48px; height:48px; border-radius:50%; border:none;
      background:var(--wa-teal); color:#fff; font-size:1.25rem;
      cursor:pointer; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
    }
    .send-btn:disabled { opacity:0.55; }
    .send-btn:active { transform:scale(0.96); }

    .placeholder {
      display:none;
      height:100%;
      align-items:center;
      justify-content:center;
      flex-direction:column;
      gap:10px;
      background:#F0F2F5;
      color:#667781;
      text-align:center;
      padding:24px;
    }
    .placeholder .icon { font-size:3rem; }
    .placeholder h2 { color:#111B21; font-size:1.2rem; }

    /* Desktop split */
    @media (min-width: 860px) {
      .app { grid-template-columns: 380px 1fr; }
      .sidebar { border-right:1px solid var(--wa-border); }
      .chat { display:none; }
      .chat.open { display:flex; }
      .placeholder.show { display:flex; }
      .chat-header .back-btn { display:none; }
    }

    /* Mobile: only one panel visible */
    @media (max-width: 859px) {
      .app.chat-mode .sidebar { display:none; }
      .app.chat-mode .chat { display:flex; }
      .app.list-mode .sidebar { display:flex; }
      .app.list-mode .chat { display:none; }
      .placeholder { display:none !important; }
    }
  </style>
</head>
<body>
<div class="app list-mode" id="app">
  <!-- LIST -->
  <aside class="sidebar" id="sidebar">
    <div class="side-header">
      <a class="icon-btn" href="<?= htmlspecialchars($backLink) ?>" title="Back to dashboard">←</a>
      <div class="avatar"><?= htmlspecialchars(strtoupper(substr(preg_replace('/\s+/', '', $userName), 0, 2))) ?></div>
      <div class="titles">
        <h1>Messages</h1>
        <div class="sub"><?= $role === 'patient' ? 'Your doctors & clinics' : 'Patient chats' ?></div>
      </div>
      <button class="icon-btn" type="button" onclick="location.reload()" title="Refresh">⟳</button>
    </div>

    <div class="search-wrap">
      <input type="search" id="searchInput" placeholder="Search chats..." autocomplete="off">
    </div>

    <div class="conv-list" id="convList">
      <div class="empty-list"><div class="big">💬</div>Loading chats...</div>
    </div>

    <?php if ($role === 'patient'): ?>
    <div class="start-panel">
      <label for="providerSelect">Start new chat</label>
      <select id="providerSelect"><option value="">Choose doctor / clinic...</option></select>
      <button type="button" id="startChatBtn">Start chat</button>
    </div>
    <?php endif; ?>
  </aside>

  <!-- EMPTY STATE (desktop) -->
  <div class="placeholder show" id="placeholder">
    <div class="icon">💚</div>
    <h2>Care Connect Messages</h2>
    <p>Select a chat to view your message history.<br>Works like WhatsApp — simple and clear.</p>
  </div>

  <!-- CHAT -->
  <section class="chat" id="chatPanel">
    <div class="chat-header">
      <button type="button" class="back-btn" id="backToList" aria-label="Back to chats">←</button>
      <div class="avatar" id="chatAvatar">?</div>
      <div class="info">
        <div class="name" id="chatName">Chat</div>
        <div class="status" id="chatStatus">tap to view history</div>
      </div>
      <a class="icon-btn" href="<?= htmlspecialchars($backLink) ?>" title="Dashboard">⌂</a>
    </div>

    <div class="chat-body" id="threadBody"></div>

    <div class="chat-input-bar">
      <form id="sendForm" autocomplete="off">
        <input type="text" id="messageInput" placeholder="Type a message" maxlength="2000" enterkeyhint="send">
        <button type="submit" class="send-btn" id="sendBtn" aria-label="Send">➤</button>
      </form>
    </div>
  </section>
</div>

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
  let allConvs = [];

  const app = document.getElementById('app');
  const convList = document.getElementById('convList');
  const threadBody = document.getElementById('threadBody');
  const chatPanel = document.getElementById('chatPanel');
  const placeholder = document.getElementById('placeholder');
  const chatName = document.getElementById('chatName');
  const chatStatus = document.getElementById('chatStatus');
  const chatAvatar = document.getElementById('chatAvatar');
  const sendForm = document.getElementById('sendForm');
  const messageInput = document.getElementById('messageInput');
  const sendBtn = document.getElementById('sendBtn');
  const searchInput = document.getElementById('searchInput');

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[c]);
  }

  function initials(name) {
    const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }

  function parseDate(ts) {
    if (!ts) return null;
    const d = new Date(String(ts).replace(' ', 'T'));
    return isNaN(d.getTime()) ? null : d;
  }

  function fmtTime(ts) {
    const d = parseDate(ts);
    if (!d) return '';
    return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
  }

  function fmtListTime(ts) {
    const d = parseDate(ts);
    if (!d) return '';
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return fmtTime(ts);
    const y = new Date(); y.setDate(now.getDate() - 1);
    if (d.toDateString() === y.toDateString()) return 'Yesterday';
    return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
  }

  function dayKey(ts) {
    const d = parseDate(ts);
    return d ? d.toDateString() : '';
  }

  function dayLabel(ts) {
    const d = parseDate(ts);
    if (!d) return '';
    const today = new Date();
    const yday = new Date(); yday.setDate(today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === yday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  }

  function receiptHtml(m, mine) {
    if (!mine) return '';
    const read = parseInt(m.is_read, 10) === 1;
    if (read) return '<span class="receipt read" title="Seen">✓✓</span>';
    return '<span class="receipt sent" title="Sent">✓</span>';
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

  function showList() {
    app.classList.remove('chat-mode');
    app.classList.add('list-mode');
    chatPanel.classList.remove('open');
    if (placeholder) placeholder.classList.add('show');
  }

  function showChat() {
    app.classList.remove('list-mode');
    app.classList.add('chat-mode');
    chatPanel.classList.add('open');
    if (placeholder) placeholder.classList.remove('show');
  }

  function renderConversations(list) {
    allConvs = list || [];
    const q = (searchInput.value || '').trim().toLowerCase();
    const filtered = q
      ? allConvs.filter(c => String(c.other_name || '').toLowerCase().includes(q) || String(c.last_message || '').toLowerCase().includes(q))
      : allConvs;

    if (!filtered.length) {
      convList.innerHTML = '<div class="empty-list"><div class="big">💬</div>' +
        (ROLE === 'patient'
          ? 'No chats yet. Start one below or message a doctor from Find Care.'
          : 'No patient messages yet. When a patient messages you, it shows here.') +
        '</div>';
      return;
    }

    convList.innerHTML = filtered.map(c => {
      const unread = parseInt(c.unread || 0, 10);
      const t = c.last_message_time || c.last_message_at || '';
      const name = c.other_name || 'User';
      return `<button type="button" class="conv-item ${activeId === parseInt(c.id,10) ? 'active' : ''}" data-id="${c.id}">
        <div class="conv-avatar">${esc(initials(name))}</div>
        <div class="conv-body">
          <div class="conv-top">
            <div class="conv-name">${esc(name)}</div>
            <div class="conv-time">${esc(fmtListTime(t))}</div>
          </div>
          <div class="conv-bottom">
            <div class="conv-preview">${esc(c.last_message || 'Tap to open chat')}</div>
            ${unread ? '<span class="unread-pill">' + unread + '</span>' : ''}
          </div>
        </div>
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
      const existing = threadBody.querySelector('.row[data-id="' + id + '"] .receipt');
      if (existing && parseInt(m.sender_id, 10) === ME) {
        const wrap = document.createElement('span');
        wrap.innerHTML = receiptHtml(m, true);
        if (wrap.firstChild) existing.replaceWith(wrap.firstChild);
      }
      return;
    }
    knownIds.add(id);
    lastMsgId = Math.max(lastMsgId, id);
    maybeDaySep(m.created_at);

    const mine = parseInt(m.sender_id, 10) === ME;
    const row = document.createElement('div');
    row.className = 'row ' + (mine ? 'me' : 'them');
    row.dataset.id = id;
    row.innerHTML = '<div class="bubble">' +
      esc(m.message) +
      '<div class="meta"><span>' + fmtTime(m.created_at) + '</span>' + receiptHtml(m, mine) + '</div>' +
      '</div>';
    threadBody.appendChild(row);
    threadBody.scrollTop = threadBody.scrollHeight;
  }

  function applyReceipts(receipts) {
    (receipts || []).forEach(r => {
      if (parseInt(r.is_read, 10) !== 1) return;
      const el = threadBody.querySelector('.row[data-id="' + r.id + '"] .receipt');
      if (!el) return;
      el.className = 'receipt read';
      el.textContent = '✓✓';
      el.title = 'Seen';
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
    threadBody.innerHTML = '';
    showChat();

    const data = await api('messages', { params: { conversation_id: id } });
    if (!data.ok) {
      threadBody.innerHTML = '<div class="day-sep">Could not load messages</div>';
      return;
    }

    const name = data.other_name || 'Chat';
    chatName.textContent = name;
    chatAvatar.textContent = initials(name);
    const roleLabel = String(data.other_role || '').replace(/^./, c => c.toUpperCase());
    chatStatus.textContent = roleLabel ? roleLabel + ' · Care Connect' : 'Care Connect chat';

    const msgs = data.messages || [];
    if (!msgs.length) {
      threadBody.innerHTML = '<div class="day-sep">No messages yet. Say hello 👋</div>';
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
    if (!pid || ROLE !== 'patient') return;
    const data = await api('start', { method: 'POST', body: { provider_id: pid } });
    if (data.ok) {
      await loadConversations();
      openConversation(parseInt(data.conversation_id, 10));
    } else {
      alert(data.error || 'Could not start chat');
    }
  }

  async function pollNew() {
    if (!activeId || document.hidden) return;
    try {
      const data = await api('messages', {
        params: { conversation_id: activeId, after_id: lastMsgId }
      });
      if (!data.ok) return;
      (data.messages || []).forEach(m => appendBubble(m, true));
      applyReceipts(data.receipts || []);
      if ((data.messages || []).length) loadConversations();
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

  document.getElementById('backToList').addEventListener('click', function () {
    activeId = 0;
    if (pollTimer) clearInterval(pollTimer);
    showList();
    loadConversations();
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, '', 'messages.php');
    }
  });

  searchInput.addEventListener('input', function () {
    renderConversations(allConvs);
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

  // Init
  showList();
  loadConversations().then(async () => {
    if (initialConv > 0) openConversation(initialConv);
    else if (startProvider > 0) await startWithProvider(startProvider);
  });

  setInterval(() => { if (!document.hidden) loadConversations(); }, 8000);
})();
</script>
</body>
</html>
