<?php
/**
 * Admin support chat for a contact-form message + quick referral
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ../login.php');
        exit;
    }
}

require_once __DIR__ . '/../db.php';

$adminName = $_SESSION['user_name'] ?? ($_SESSION['admin_name'] ?? 'Admin');
$contactId = (int)($_GET['id'] ?? 0);
if ($contactId <= 0) {
    header('Location: manage-messages.php');
    exit;
}

$contact = null;
try {
    $s = $conn->prepare('SELECT * FROM contact_messages WHERE id = ? LIMIT 1');
    $s->execute([$contactId]);
    $contact = $s->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if (!$contact) {
    header('Location: manage-messages.php');
    exit;
}

// Matched user?
$matchedUser = null;
if (!empty($contact['email'])) {
    try {
        $u = $conn->prepare('SELECT id, name, email, role FROM users WHERE email = ? LIMIT 1');
        $u->execute([$contact['email']]);
        $matchedUser = $u->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {}
}

// Quick referral create
$refMsg = '';
$refErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_referral') {
    $pname = trim($_POST['patient_name'] ?? ($contact['name'] ?? ''));
    $phone = trim($_POST['phone'] ?? ($contact['phone'] ?? ''));
    $location = trim($_POST['location'] ?? 'Freetown');
    $condition = trim($_POST['condition'] ?? ($contact['message'] ?? ''));
    $assigned = (int)($_POST['assigned_to'] ?? 0);

    if ($pname === '' || $condition === '') {
        $refErr = 'Name and condition are required.';
    } else {
        try {
            $uid = $matchedUser ? (int)$matchedUser['id'] : null;
            $cols = 'patient_name, phone, location, status, created_at';
            $vals = '?, ?, ?, ?, NOW()';
            $params = [$pname, $phone, $location, $assigned > 0 ? 'in_progress' : 'pending'];

            // condition column variants
            try {
                $conn->query('SELECT `condition` FROM referrals LIMIT 1');
                $cols .= ', `condition`';
                $vals .= ', ?';
                $params[] = $condition;
            } catch (Exception $e) {
                try {
                    $conn->query('SELECT medical_condition FROM referrals LIMIT 1');
                    $cols .= ', medical_condition';
                    $vals .= ', ?';
                    $params[] = $condition;
                } catch (Exception $e2) {}
            }

            if ($uid) {
                $cols .= ', user_id';
                $vals .= ', ?';
                $params[] = $uid;
            }
            if ($assigned > 0) {
                $cols .= ', assigned_to';
                $vals .= ', ?';
                $params[] = $assigned;
            }
            if (!empty($contact['email'])) {
                try {
                    $conn->query('SELECT email FROM referrals LIMIT 1');
                    $cols .= ', email';
                    $vals .= ', ?';
                    $params[] = $contact['email'];
                } catch (Exception $e) {}
            }

            $conn->prepare("INSERT INTO referrals ($cols) VALUES ($vals)")->execute($params);
            $newId = (int)$conn->lastInsertId();
            $refMsg = 'Referral #' . $newId . ' created' . ($assigned ? ' and assigned.' : ' (pending pool).');

            try {
                $conn->prepare("UPDATE contact_messages SET status = 'replied', updated_at = NOW() WHERE id = ?")
                     ->execute([$contactId]);
            } catch (Exception $e) {}
        } catch (Exception $e) {
            $refErr = 'Could not create referral: ' . $e->getMessage();
        }
    }
}

// Doctors list for assign
$doctors = [];
try {
    $doctors = $conn->query("SELECT id, name FROM users WHERE role IN ('doctor','hospital') ORDER BY name LIMIT 80")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$active = 'messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Support chat — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="admin-styles.css">
  <style>
    .chat-layout { display:grid; grid-template-columns:1.2fr 1fr; gap:16px; }
    @media(max-width:960px){ .chat-layout{ grid-template-columns:1fr; } }
    .chat-box { background:#fff; border:1px solid #E5E7EB; border-radius:16px; display:flex; flex-direction:column; min-height:520px; overflow:hidden; }
    .chat-head { padding:14px 16px; background:#0F1C3A; color:#fff; }
    .chat-head h2 { margin:0; font-size:1.05rem; color:#fff !important; }
    .chat-head .meta { opacity:.85; font-size:.85rem; margin-top:4px; }
    .chat-body { flex:1; overflow-y:auto; padding:14px; background:#F0F2F5; display:flex; flex-direction:column; gap:8px; }
    .bubble { max-width:85%; padding:10px 12px; border-radius:12px; font-size:.92rem; line-height:1.45; white-space:pre-wrap; }
    .bubble.them { align-self:flex-start; background:#fff; border-top-left-radius:4px; }
    .bubble.me { align-self:flex-end; background:#D9FDD3; border-top-right-radius:4px; }
    .bubble .who { font-size:.72rem; font-weight:700; color:#64748B; margin-bottom:3px; }
    .bubble .when { font-size:.68rem; color:#94A3B8; margin-top:4px; text-align:right; }
    .chat-input { display:flex; gap:8px; padding:12px; background:#fff; border-top:1px solid #E5E7EB; }
    .chat-input textarea { flex:1; border:1.5px solid #E2E8F0; border-radius:12px; padding:10px 12px; font:inherit; resize:none; min-height:44px; }
    .chat-input button { border:none; background:#1EB53A; color:#fff; font-weight:700; border-radius:12px; padding:0 18px; cursor:pointer; }
    .side-card { background:#fff; border:1px solid #E5E7EB; border-radius:16px; padding:16px; margin-bottom:12px; }
    .side-card h3 { margin:0 0 10px; font-size:1rem; }
    .side-card label { display:block; font-weight:600; font-size:.85rem; margin:8px 0 4px; }
    .side-card input, .side-card select, .side-card textarea {
      width:100%; padding:10px; border:1.5px solid #E2E8F0; border-radius:10px; font:inherit;
    }
    .badge-ok { display:inline-block; background:#DCFCE7; color:#166534; padding:4px 10px; border-radius:999px; font-size:.78rem; font-weight:700; }
    .badge-warn { display:inline-block; background:#FEF3C7; color:#92400E; padding:4px 10px; border-radius:999px; font-size:.78rem; font-weight:700; }
  </style>
</head>
<body class="admin-body">
<div class="admin-wrapper">
  <?php include __DIR__ . '/_sidebar.php'; ?>
  <main class="admin-main">
    <div class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button">☰</button>
        <span class="page-title">Support chat</span>
      </div>
      <div class="admin-topbar-right">
        <a href="manage-messages.php" class="btn-admin" style="text-decoration:none;padding:8px 14px;border-radius:999px;">← Inbox</a>
        <span class="welcome"><?= htmlspecialchars($adminName) ?></span>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($refMsg): ?><div class="alert success">✅ <?= htmlspecialchars($refMsg) ?></div><?php endif; ?>
      <?php if ($refErr): ?><div class="alert error">⚠️ <?= htmlspecialchars($refErr) ?></div><?php endif; ?>

      <div class="chat-layout">
        <div class="chat-box">
          <div class="chat-head">
            <h2><?= htmlspecialchars($contact['name'] ?? 'Contact') ?></h2>
            <div class="meta">
              <?= htmlspecialchars($contact['email'] ?? '') ?>
              <?php if (!empty($contact['phone'])): ?> · <?= htmlspecialchars($contact['phone']) ?><?php endif; ?>
              <?php if ($matchedUser): ?>
                · <span class="badge-ok">Registered user</span>
              <?php else: ?>
                · <span class="badge-warn">Guest (not registered)</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="chat-body" id="threadBody"><div style="color:#64748B;text-align:center;padding:20px">Loading…</div></div>
          <form class="chat-input" id="sendForm">
            <textarea id="msgInput" placeholder="Type a reply… help them book care or ask for symptoms" rows="2"></textarea>
            <button type="submit" id="sendBtn">Send</button>
          </form>
        </div>

        <div>
          <div class="side-card">
            <h3>Original message</h3>
            <p style="color:#334155;line-height:1.5;white-space:pre-wrap;margin:0;font-size:.92rem"><?= htmlspecialchars($contact['message'] ?? '') ?></p>
            <?php if (!empty($contact['email'])): ?>
              <p style="margin-top:12px"><a href="mailto:<?= htmlspecialchars($contact['email']) ?>?subject=Care%20Connect%20SL%20Support">Reply by email →</a></p>
            <?php endif; ?>
          </div>

          <div class="side-card">
            <h3>Create referral for them</h3>
            <p style="color:#64748B;font-size:.85rem;margin:0 0 10px">Turns this contact into a care referral (pending or assigned).</p>
            <form method="POST">
              <input type="hidden" name="action" value="create_referral">
              <label>Patient name</label>
              <input name="patient_name" value="<?= htmlspecialchars($contact['name'] ?? '') ?>" required>
              <label>Phone</label>
              <input name="phone" value="<?= htmlspecialchars($contact['phone'] ?? '') ?>">
              <label>Location</label>
              <input name="location" value="Freetown" required>
              <label>Condition / need</label>
              <textarea name="condition" rows="3" required><?= htmlspecialchars($contact['message'] ?? '') ?></textarea>
              <label>Assign doctor (optional)</label>
              <select name="assigned_to">
                <option value="0">Open pool — any doctor</option>
                <?php foreach ($doctors as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn-primary" style="width:100%;margin-top:12px;border:none;padding:12px;border-radius:10px;font-weight:700;cursor:pointer">Create referral</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="../js/dark-mode.js"></script>
<script>
(function(){
  const CONTACT_ID = <?= (int)$contactId ?>;
  const API = '../api/support-chat-api.php';
  let threadId = 0, lastId = 0;
  const body = document.getElementById('threadBody');
  const form = document.getElementById('sendForm');
  const input = document.getElementById('msgInput');
  const btn = document.getElementById('sendBtn');

  function esc(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function fmt(ts){ if(!ts)return''; const d=new Date(String(ts).replace(' ','T')); return isNaN(d)?ts:d.toLocaleString(); }

  function addBubble(m){
    const id = parseInt(m.id,10);
    if (body.querySelector('[data-id="'+id+'"]')) return;
    lastId = Math.max(lastId, id);
    const mine = m.sender_role === 'admin';
    const div = document.createElement('div');
    div.className = 'bubble ' + (mine ? 'me' : 'them');
    div.dataset.id = id;
    div.innerHTML = '<div class="who">'+esc(m.sender_name||m.sender_role)+'</div>'
      + esc(m.message)
      + '<div class="when">'+esc(fmt(m.created_at))+'</div>';
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
  }

  async function ensureThread(){
    const r = await fetch(API+'?action=thread&contact_id='+CONTACT_ID, {credentials:'same-origin'});
    const d = await r.json();
    if (!d.ok) throw new Error(d.error||'thread fail');
    threadId = parseInt(d.thread.id,10);
  }

  async function loadMessages(incremental){
    if (!threadId) return;
    let url = API+'?action=messages&thread_id='+threadId;
    if (incremental && lastId) url += '&after_id='+lastId;
    const r = await fetch(url, {credentials:'same-origin'});
    const d = await r.json();
    if (!d.ok) return;
    if (!incremental) { body.innerHTML=''; lastId=0; }
    (d.messages||[]).forEach(addBubble);
    if (!(d.messages||[]).length && !incremental) {
      body.innerHTML = '<div style="color:#64748B;text-align:center;padding:20px">No messages yet. Say hello.</div>';
    }
  }

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const text = input.value.trim();
    if (!text || !threadId) return;
    btn.disabled = true;
    try {
      const r = await fetch(API, {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'send', thread_id:threadId, message:text})
      });
      const d = await r.json();
      if (d.ok && d.message) {
        if (body.querySelector('[style*="text-align:center"]')) body.innerHTML='';
        addBubble(d.message);
        input.value='';
      } else alert(d.error||'Send failed');
    } catch(err){ alert('Network error'); }
    finally { btn.disabled=false; input.focus(); }
  });

  ensureThread().then(()=>loadMessages(false)).then(()=>{
    setInterval(()=>loadMessages(true), 2500);
  }).catch(err=>{
    body.innerHTML = '<div style="color:#B91C1C;padding:20px">Could not open chat: '+esc(err.message)+'</div>';
  });

  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (sidebarToggle && sidebar) sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
})();
</script>
</body>
</html>
