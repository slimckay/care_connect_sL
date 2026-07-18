<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>AI Health Assistant — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .chat-page {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 16px;
        }

        .chat-page-title {
            text-align: center;
            margin-bottom: 20px;
        }

        .chat-page-title h1 {
            margin: 0 0 6px 0;
            font-size: 1.9rem;
            color: #0F1C3A !important;
        }

        .chat-page-title p {
            color: #64748B !important;
            margin: 0;
        }

        .chat-container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 75vh;
            min-height: 600px;
            border: 1px solid #e5e7eb;
        }

        .chat-header {
            background: linear-gradient(135deg, #0F1C3A, #1a2a4a);
            color: white;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }

        .chat-header .ai-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .chat-header h2 {
            margin: 0;
            font-size: 1.15rem;
            color: white !important;
        }

        .chat-header p {
            margin: 0;
            font-size: 0.85rem;
            opacity: 0.85;
            color: white !important;
        }

        .chat-messages {
            flex: 1;
            padding: 24px 28px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .message {
            max-width: 92%;
            padding: 16px 20px;
            border-radius: 16px;
            line-height: 1.65;
            font-size: 1.05rem;
            word-wrap: break-word;
        }

        .message.user {
            align-self: flex-end;
            background: #1EB53A;
            color: white !important;
            border-bottom-right-radius: 4px;
        }

        .message.ai {
            align-self: flex-start;
            background: white;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 4px;
            color: #1F2937 !important;
        }

        .chat-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px 24px;
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
            flex-shrink: 0;
        }

        .chat-suggestions button {
            padding: 8px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            background: white;
            font-size: 0.9rem;
            cursor: pointer;
            color: #1F2937;
            transition: all 0.2s;
        }

        .chat-suggestions button:hover {
            border-color: #1EB53A;
            color: #1EB53A;
            background: #f0fdf4;
        }

        .chat-input-area {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            background: white;
            flex-shrink: 0;
        }

        .chat-input {
            display: flex;
            gap: 12px;
        }

        .chat-input input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1.05rem;
            color: #1F2937;
        }

        .chat-input button {
            padding: 0 28px;
            background: #1EB53A;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
        }

        .chat-input button:hover {
            background: #15802A;
        }

        [data-theme="dark"] .chat-page-title h1 { color: #f8fafc !important; }
        [data-theme="dark"] .chat-page-title p { color: #94A3B8 !important; }
        [data-theme="dark"] .chat-container { background: #1e293b; border-color: #334155; }
        [data-theme="dark"] .chat-messages { background: #0f172a; }
        [data-theme="dark"] .message.ai {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0 !important;
        }
        [data-theme="dark"] .chat-suggestions,
        [data-theme="dark"] .chat-input-area {
            background: #1e293b;
            border-color: #334155;
        }
        [data-theme="dark"] .chat-suggestions button {
            background: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }
        [data-theme="dark"] .chat-input input {
            background: #0f172a;
            border-color: #334155;
            color: #e2e8f0;
        }

        @media (max-width: 768px) {
            .chat-page { margin: 12px auto; padding: 0 10px; }
            .chat-page-title { margin-bottom: 12px; }
            .chat-page-title h1 { font-size: 1.4rem; }
            .chat-page-title p { font-size: 0.9rem; }
            .chat-container {
                height: 78vh;
                min-height: 560px;
                border-radius: 14px;
            }
            .chat-messages { padding: 16px; }
            .message {
                max-width: 95%;
                font-size: 1rem;
                padding: 14px 16px;
            }
            .chat-input { flex-direction: column; }
            .chat-input input { width: 100%; }
            .chat-input button { width: 100%; padding: 14px; }
            .chat-suggestions { padding: 10px 16px; }
            .chat-header { padding: 12px 16px; }
            .chat-header .ai-avatar { width: 40px; height: 40px; font-size: 1.3rem; }
        }

        @media (max-width: 480px) {
            .chat-container {
                height: 80vh;
                min-height: 520px;
            }
            .chat-page-title h1 { font-size: 1.25rem; }
        }
    </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
    <nav>
      <ul class="nav-links">
        <li><a href="index.html">Home</a></li>
        <li><a href="pages/doctors.php">Find Care</a></li>
        <li><a href="pages/hospitals.html">Clinics</a></li>
        <li><a href="pages/referral.html">Referrals</a></li>
        <li><a href="pages/about.html">About</a></li>
        <li><a href="pages/contact.html">Contact</a></li>
        <li><a href="ai-chat.php" class="active" style="color:#1EB53A; font-weight:600;">💬 AI Assistant</a></li>
      </ul>
    </nav>
    <div class="nav-actions">
      <button onclick="toggleDarkMode()" class="dark-toggle">🌓</button>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="profile.php" class="btn-ghost">Profile</a>
        <a href="logout.php" class="btn-ghost">Logout</a>
      <?php else: ?>
        <a href="login.php" class="btn-ghost">Sign In</a>
        <a href="register.php" class="btn-primary">Get Started</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="chat-page">
    <div class="chat-page-title">
        <h1>🤖 CareConnect AI Assistant</h1>
        <p>Powered by OpenRouter — talk naturally about symptoms, illness, or referrals</p>
    </div>

    <div class="chat-container">
        <div class="chat-header">
            <div class="ai-avatar">🇸🇱</div>
            <div>
                <h2>CareConnect AI</h2>
                <p>Sierra Leone Health Assistant</p>
            </div>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="message ai">
                👋 Hello! I'm your CareConnect health assistant for Sierra Leone.<br><br>
                You can talk to me naturally about symptoms, malaria, fever, typhoid, diarrhea, cough, pregnancy care, or how to get a referral.<br><br>
                What would you like help with today?
            </div>
        </div>

        <div class="chat-suggestions">
            <button onclick="sendSuggestion('I think I have malaria')">🦟 Malaria</button>
            <button onclick="sendSuggestion('I have high fever')">🌡️ Fever</button>
            <button onclick="sendSuggestion('I have diarrhea')">💧 Diarrhea</button>
            <button onclick="sendSuggestion('Symptoms of typhoid')">🦠 Typhoid</button>
            <button onclick="sendSuggestion('How do I get a referral?')">📋 Referral</button>
        </div>

        <div class="chat-input-area">
            <div class="chat-input">
                <input type="text" id="chatInput" placeholder="Type how you feel or ask a question..." autocomplete="off">
                <button onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>
</main>

<script src="js/dark-mode.js"></script>
<script>
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');

let isProcessing = false;
let conversationHistory = [];

function addMessage(text, sender) {
    const div = document.createElement('div');
    div.className = `message ${sender}`;
    div.innerHTML = text;
    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function showTyping() {
    const div = document.createElement('div');
    div.className = 'message ai';
    div.id = 'typing';
    div.innerHTML = 'Thinking...';
    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function hideTyping() {
    const typing = document.getElementById('typing');
    if (typing) typing.remove();
}

async function sendMessage() {
    const message = chatInput.value.trim();
    if (!message || isProcessing) return;

    addMessage(escapeHtml(message), 'user');
    conversationHistory.push({ role: 'user', content: message });
    chatInput.value = '';
    isProcessing = true;
    showTyping();

    try {
        const response = await fetch('ai-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message: message,
                history: conversationHistory.slice(0, -1)
            })
        });

        const data = await response.json();
        hideTyping();

        if (data.reply) {
            addMessage(data.reply, 'ai');
            conversationHistory.push({ role: 'assistant', content: stripHtml(data.reply) });
        } else {
            // Fallback to local knowledge if OpenRouter fails
            const fallback = getLocalResponse(message);
            addMessage(fallback, 'ai');
            conversationHistory.push({ role: 'assistant', content: stripHtml(fallback) });
        }
    } catch (err) {
        hideTyping();
        const fallback = getLocalResponse(message);
        addMessage(fallback + '<br><br><em>(Using offline mode)</em>', 'ai');
        conversationHistory.push({ role: 'assistant', content: stripHtml(fallback) });
    }

    // Keep history short
    if (conversationHistory.length > 12) {
        conversationHistory = conversationHistory.slice(-12);
    }

    isProcessing = false;
}

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function stripHtml(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

// Local fallback if OpenRouter is unavailable
function getLocalResponse(query) {
    const q = query.toLowerCase();

    if (q.includes('malaria')) {
        return `🦟 <strong>Malaria</strong><br><br>Symptoms often include high fever, chills, headache, and body pain.<br><br>Please get tested quickly at a clinic. If positive, start treatment the same day, rest, and drink fluids.`;
    }
    if (q.includes('fever')) {
        return `🌡️ <strong>Fever</strong><br><br>Rest, drink clean water, and take paracetamol if available. See a clinic if fever lasts more than 2–3 days or becomes very high.`;
    }
    if (q.includes('typhoid')) {
        return `🦠 <strong>Typhoid</strong><br><br>Common signs: high fever, headache, stomach pain, weakness. Get tested and follow clinic treatment. Drink clean water and eat safe food.`;
    }
    if (q.includes('diarrhea') || q.includes('diarrhoea')) {
        return `💧 <strong>Diarrhea</strong><br><br>Drink ORS often. Seek care if there is blood, severe dehydration, or it lasts more than 2 days.`;
    }
    if (q.includes('referral')) {
        return `📋 You can submit a referral on the Referrals page. For emergencies, go directly to the nearest hospital.`;
    }

    return `I can help with malaria, fever, typhoid, diarrhea, cough, headache, and referrals. Please tell me your main symptom.`;
}

function sendSuggestion(text) {
    chatInput.value = text;
    sendMessage();
}

chatInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        sendMessage();
    }
});

window.onload = function() {
    chatInput.focus();
};
</script>

</body>
</html>