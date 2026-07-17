<?php
// Start session for user display
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 AI Health Assistant — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ============================================
           AI CHAT - SIERRA LEONE FLAG THEME
           ============================================ */
        .chat-container {
            max-width: 820px;
            margin: 0 auto;
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 620px;
            border: 1px solid rgba(30, 181, 58, 0.1);
        }

        /* Chat Header - Flag Theme */
        .chat-header {
            background: var(--flag-gradient);
            color: var(--white);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .chat-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .chat-header .ai-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            position: relative;
            z-index: 1;
            animation: float-green 3s infinite ease-in-out;
        }

        .chat-header .ai-info h2 {
            font-size: 1.1rem;
            color: var(--white);
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .chat-header .ai-info p {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .chat-header .ai-status {
            margin-left: auto;
            font-size: 0.75rem;
            color: #4ade80;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
        }

        .chat-header .ai-status .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #4ade80;
            animation: pulse-dot 2s infinite;
        }

        /* Flag Decoration */
        .flag-stripes {
            display: flex;
            height: 3px;
            flex-shrink: 0;
        }
        .flag-stripes span {
            flex: 1;
            height: 100%;
        }
        .flag-stripes span:nth-child(1) { background: #1EB53A; }
        .flag-stripes span:nth-child(2) { background: #FFFFFF; }
        .flag-stripes span:nth-child(3) { background: #0000CD; }

        /* Messages Area - Light Blue Background with Pattern */
        .chat-messages {
            flex: 1;
            padding: 20px 24px;
            overflow-y: auto;
            background: 
                radial-gradient(ellipse at 0% 0%, rgba(30, 181, 58, 0.02) 0%, transparent 50%),
                radial-gradient(ellipse at 100% 100%, rgba(0, 0, 205, 0.02) 0%, transparent 50%),
                #EFF6FF; /* Light blue base */
            background-blend-mode: overlay;
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 0;
            position: relative;
        }

        /* Subtle pattern overlay */
        .chat-messages::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(30, 181, 58, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(0, 0, 205, 0.03) 0%, transparent 50%),
                repeating-linear-gradient(45deg, 
                    rgba(30, 181, 58, 0.01) 0px, 
                    rgba(30, 181, 58, 0.01) 2px,
                    transparent 2px, 
                    transparent 8px
                );
            pointer-events: none;
            z-index: 0;
        }

        .chat-messages .message {
            max-width: 82%;
            padding: 12px 18px;
            border-radius: var(--radius-lg);
            animation: fadeIn 0.3s ease;
            line-height: 1.6;
            word-wrap: break-word;
            position: relative;
            z-index: 1;
        }

        /* USER MESSAGES - BRIGHT COLOR */
        .chat-messages .message.user {
            align-self: flex-end;
            background: linear-gradient(135deg, #1EB53A 0%, #34D399 100%);
            color: var(--white);
            border-bottom-right-radius: 4px;
            box-shadow: 0 2px 8px rgba(30, 181, 58, 0.2);
        }

        .chat-messages .message.ai {
            align-self: flex-start;
            background: var(--white);
            color: var(--dark-text);
            border: 1px solid var(--border);
            border-bottom-left-radius: 4px;
            box-shadow: var(--shadow-sm);
        }

        .chat-messages .message.ai .model-badge {
            display: inline-block;
            font-size: 0.6rem;
            padding: 2px 10px;
            border-radius: var(--radius-full);
            background: var(--flag-gradient);
            color: var(--white);
            margin-bottom: 6px;
        }

        .chat-messages .message .time {
            font-size: 0.7rem;
            opacity: 0.6;
            margin-top: 6px;
            display: block;
        }

        .chat-messages .message.user .time {
            color: rgba(255, 255, 255, 0.7);
        }

        .chat-messages .message.ai .time {
            color: var(--gray-light);
        }

        .chat-messages .message .disclaimer {
            display: block;
            margin-top: 8px;
            font-size: 0.75rem;
            color: var(--gray-light);
            border-top: 1px solid var(--border);
            padding-top: 8px;
            font-style: italic;
        }

        /* Typing Indicator */
        .typing-indicator {
            display: flex;
            gap: 5px;
            padding: 8px 0;
            align-self: flex-start;
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 10px 16px;
            border: 1px solid var(--border);
        }

        .typing-indicator span {
            width: 10px;
            height: 10px;
            background: var(--gray-light);
            border-radius: 50%;
            animation: typing-bounce 1.4s infinite both;
        }

        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing-bounce {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-12px); opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Suggestions */
        .chat-suggestions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px 24px;
            background: var(--white);
            flex-shrink: 0;
            border-top: 1px solid var(--border);
        }

        .chat-suggestions button {
            padding: 6px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-full);
            background: var(--white);
            font-size: 0.8rem;
            cursor: pointer;
            transition: all var(--transition);
            color: var(--muted);
            white-space: nowrap;
        }

        .chat-suggestions button:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(30, 181, 58, 0.05);
            transform: translateY(-2px);
        }

        /* Input Area - VISIBLE */
        .chat-input-area {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: var(--white);
            flex-shrink: 0;
        }

        .chat-input {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .chat-input input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid #E2E8F0;
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: all var(--transition);
            background: var(--white);
            color: var(--dark-text);
            min-height: 52px;
        }

        .chat-input input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(30, 181, 58, 0.1);
        }

        .chat-input input:disabled {
            background: #F1F5F9;
            cursor: not-allowed;
        }

        .chat-input input::placeholder {
            color: var(--gray-light);
        }

        /* Send Button - VISIBLE */
        .chat-input button {
            padding: 14px 32px;
            background: var(--flag-gradient);
            color: var(--white);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all var(--transition-bounce);
            white-space: nowrap;
            position: relative;
            overflow: hidden;
            min-height: 52px;
            min-width: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .chat-input button::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #0000CD, #3B82F6);
            opacity: 0;
            transition: opacity var(--transition);
            border-radius: var(--radius-md);
        }

        .chat-input button:hover:not(:disabled)::before {
            opacity: 1;
        }

        .chat-input button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 181, 58, 0.3);
        }

        .chat-input button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .chat-input button * {
            position: relative;
            z-index: 1;
        }

        .chat-input button .send-icon {
            font-size: 1.2rem;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 8px;
            padding-top: 12px;
            flex-wrap: wrap;
            border-top: 1px solid var(--border);
            margin-top: 12px;
        }

        .quick-actions a {
            padding: 6px 16px;
            font-size: 0.8rem;
            border-radius: var(--radius-full);
            background: var(--light);
            color: var(--dark-text);
            text-decoration: none;
            transition: all var(--transition);
        }

        .quick-actions a:hover {
            background: var(--flag-gradient);
            color: var(--white);
            text-decoration: none;
            transform: translateY(-2px);
        }

        /* Disclaimer */
        .chat-disclaimer {
            font-size: 0.75rem;
            color: var(--muted);
            text-align: center;
            padding: 10px 24px;
            background: #F8FAFC;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }

        .chat-disclaimer strong {
            color: var(--dark);
        }

        /* Scrollbar */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        .chat-messages::-webkit-scrollbar-track {
            background: #e2e8f0;
        }
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .chat-container {
                height: 520px;
                border-radius: var(--radius-lg);
            }
            .chat-messages {
                padding: 16px;
            }
            .chat-messages .message {
                max-width: 90%;
                font-size: 0.95rem;
            }
            .chat-input {
                flex-direction: column;
            }
            .chat-input input {
                width: 100%;
                min-height: 48px;
            }
            .chat-input button {
                width: 100%;
                min-height: 48px;
                min-width: unset;
            }
            .chat-suggestions {
                padding: 8px 16px;
            }
            .quick-actions {
                gap: 6px;
            }
            .quick-actions a {
                font-size: 0.75rem;
                padding: 4px 12px;
            }
            .chat-header {
                padding: 14px 16px;
            }
            .chat-header .ai-info h2 {
                font-size: 0.95rem;
            }
            .chat-input-area {
                padding: 12px 16px;
            }
        }

        @media (max-width: 480px) {
            .chat-container {
                height: 460px;
            }
            .chat-messages .message {
                max-width: 95%;
                font-size: 0.9rem;
                padding: 10px 14px;
            }
            .chat-input button {
                font-size: 0.9rem;
                padding: 12px 20px;
            }
        }
    </style>
</head>
<body>

<!-- Preloader -->
<div id="preloader" role="status" aria-label="Loading">
    <div class="pulse-ring"></div>
    <svg class="heartbeat-svg" viewBox="0 0 300 80" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <polyline points="0,40 60,40 80,10 100,70 120,5 140,75 160,40 300,40" fill="none" stroke="#1EB53A" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <p class="preload-text">Care Connect SL</p>
    <div class="preloader-flags">
        <span></span><span></span><span></span>
    </div>
</div>

<!-- Header -->
<header role="banner">
    <div class="nav-inner">
        <a href="index.html" class="logo" aria-label="Care Connect SL Home">
            <span class="logo-icon" aria-hidden="true">❤️</span> Care<span class="accent">Connect</span> SL
        </a>
        <nav aria-label="Main navigation">
            <ul class="nav-links" role="menubar">
                <li><a href="index.html" role="menuitem">Home</a></li>
                <li><a href="pages/doctors.php" role="menuitem">Find Care</a></li>
                <li><a href="pages/hospitals.html" role="menuitem">Clinics</a></li>
                <li><a href="pages/referral.html" role="menuitem">Referrals</a></li>
                <li><a href="pages/about.html" role="menuitem">About</a></li>
                <li><a href="ai-chat.php" role="menuitem" style="color: var(--primary); font-weight: 600;">💬 AI Assistant</a></li>
            </ul>
        </nav>
        <div class="nav-actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span style="color: var(--muted); font-size: 0.9rem;">👋 <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                <a href="profile.php" class="btn-ghost">Profile</a>
                <a href="logout.php" class="btn-ghost">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn-ghost">Sign In</a>
                <a href="register.php" class="btn-primary">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="page-content" role="main" style="padding: 20px 0;">
    <section class="page-hero" aria-labelledby="chat-title" style="padding: 20px 0 16px;">
        <h1 id="chat-title">🤖 CareConnect AI Assistant</h1>
        <p>Your intelligent healthcare guide. Ask about symptoms, referrals, or health questions.</p>
    </section>

    <!-- Chat Container -->
    <div class="chat-container">
        <!-- Header -->
        <div class="chat-header">
            <div class="ai-avatar">🇸🇱</div>
            <div class="ai-info">
                <h2>CareConnect AI</h2>
                <p>Powered by OpenRouter • Sierra Leone</p>
            </div>
            <div class="ai-status">
                <span class="dot"></span>
                <span id="statusText">Online</span>
            </div>
        </div>

        <!-- Flag Stripes -->
        <div class="flag-stripes">
            <span></span><span></span><span></span>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="message ai">
                <span class="model-badge">🇸🇱 CareConnect AI</span>
                <strong>👋 Welcome to CareConnect AI!</strong><br><br>
                I'm your health assistant for Sierra Leone. I can help you with:<br>
                • 🩺 Understanding symptoms<br>
                • 🏥 Finding the right care<br>
                • 📋 Explaining referrals<br>
                • ❓ Answering health questions<br><br>
                <em style="color: var(--muted);">💡 Try asking about malaria, cough, fever, or finding a doctor.</em>
                <span class="time"><?php echo date('h:i A'); ?></span>
            </div>
        </div>

        <!-- Suggestions -->
        <div class="chat-suggestions">
            <button onclick="sendSuggestion('What are the symptoms of malaria?')">🦟 Malaria</button>
            <button onclick="sendSuggestion('I have a cough and cold')">🤧 Cough</button>
            <button onclick="sendSuggestion('I have a fever and headache')">🌡️ Fever</button>
            <button onclick="sendSuggestion('How do I get a referral?')">📋 Referrals</button>
            <button onclick="sendSuggestion('Where can I find a doctor?')">👨‍⚕️ Find Doctor</button>
        </div>

        <!-- Input Area -->
        <div class="chat-input-area">
            <div class="chat-input">
                <input type="text" id="chatInput" placeholder="Type your health question..." autocomplete="off">
                <button id="sendBtn" onclick="sendMessage()">
                    <span class="send-icon">✉️</span> Send
                </button>
            </div>
            <div class="quick-actions">
                <a href="pages/referral.html">📝 Make Referral</a>
                <a href="pages/doctors.php">👨‍⚕️ Find Doctor</a>
                <a href="pages/hospitals.html">🏥 Find Clinic</a>
                <a href="faq.html">❓ FAQ</a>
            </div>
        </div>

        <!-- Disclaimer -->
        <div class="chat-disclaimer">
            <strong>⚠️ Medical Disclaimer:</strong> This AI provides general health information only. 
            For medical advice, diagnosis, or treatment, please consult a qualified healthcare professional. 
            In emergencies, call <strong>999</strong> immediately.
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="site-footer" role="contentinfo">
    <div class="footer-grid container">
        <div>
            <a href="index.html" class="logo" aria-label="Care Connect SL Home">Care<span class="accent">Connect</span> SL</a>
            <p>Home-based care referrals and clinic coordination across Sierra Leone.</p>
        </div>
        <div>
            <h3>Quick links</h3>
            <ul class="footer-links">
                <li><a href="index.html">Home</a></li>
                <li><a href="pages/referral.html">Make Referral</a></li>
                <li><a href="ai-chat.php">💬 AI Assistant</a></li>
            </ul>
        </div>
        <div>
            <h3>Contact</h3>
            <p><a href="mailto:hello@careconnect.sl">hello@careconnect.sl</a></p>
            <p><a href="tel:+23276000000">+232 76 000 000</a></p>
        </div>
    </div>
    <div class="flag-divider">
        <span></span><span></span><span></span>
    </div>
    <p class="footer-note">&copy; 2026 Care Connect SL. All rights reserved. 🇸🇱 Made with ❤️ in Sierra Leone</p>
</footer>

<script src="js/main.js"></script>

<script>
// ============================================
// AI CHAT - FULL FUNCTIONALITY
// ============================================

const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const sendBtn = document.getElementById('sendBtn');
const statusText = document.getElementById('statusText');

let isProcessing = false;

// ============================================
// ADD MESSAGE
// ============================================
function addMessage(text, sender, isError = false) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}${isError ? ' error' : ''}`;
    const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    let formattedText = text;
    formattedText = formattedText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    formattedText = formattedText.replace(/\*(.*?)\*/g, '<em>$1</em>');
    formattedText = formattedText.replace(/\n/g, '<br>');

    if (sender === 'ai' && !isError) {
        messageDiv.innerHTML = `
            <span class="model-badge">🇸🇱 CareConnect AI</span>
            ${formattedText}
            <span class="time">${time}</span>
        `;
    } else {
        messageDiv.innerHTML = `${formattedText}<span class="time">${time}</span>`;
    }

    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// ============================================
// TYPING INDICATOR
// ============================================
function showTyping() {
    const typingDiv = document.createElement('div');
    typingDiv.className = 'typing-indicator';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `<span></span><span></span><span></span>`;
    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function hideTyping() {
    const typing = document.getElementById('typingIndicator');
    if (typing) typing.remove();
}

// ============================================
// SET STATUS
// ============================================
function setStatus(text, isOnline = true) {
    statusText.textContent = text;
    const dot = document.querySelector('.chat-header .dot');
    if (dot) {
        dot.style.background = isOnline ? '#4ade80' : '#f59e0b';
    }
}

// ============================================
// DETECT GREETINGS (Frontend fallback)
// ============================================
function isGreeting(text) {
    const greetings = ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'how are you', "what's up", "how's it going", "nice to meet you"];
    const lower = text.toLowerCase().trim();
    for (let g of greetings) {
        if (lower.includes(g)) return true;
    }
    return false;
}

function getGreetingResponse() {
    const responses = [
        "👋 Hello! I'm CareConnect AI, your health assistant. How are you feeling today? I'm here to help with any health questions you have.",
        "👋 Hi there! How are you doing today? I hope you're well. Can I help you with any health concerns?",
        "👋 Hey! Nice to meet you. How's your day going? I'm here to answer your health questions.",
        "🌅 Good morning! How did you sleep? I'm here to help with any health questions today.",
        "☀️ Good afternoon! How are you feeling today? I hope you're having a great day.",
        "🌙 Good evening! How was your day? I'm here to help with any health concerns.",
        "😊 I'm doing well, thank you for asking! How about you? How are you feeling today?"
    ];
    return responses[Math.floor(Math.random() * responses.length)];
}

// ============================================
// SEND MESSAGE - WITH KNOWLEDGE BASE FALLBACK
// ============================================
async function sendMessage() {
    const message = chatInput.value.trim();
    if (!message || isProcessing) return;

    // Add user message
    addMessage(message, 'user');
    chatInput.value = '';
    chatInput.disabled = true;
    sendBtn.disabled = true;
    isProcessing = true;

    setStatus('Thinking...', true);
    showTyping();

    try {
        // First, check if it's a greeting locally
        if (isGreeting(message)) {
            hideTyping();
            addMessage(getGreetingResponse(), 'ai');
            setStatus('Online', true);
            chatInput.disabled = false;
            sendBtn.disabled = false;
            isProcessing = false;
            chatInput.focus();
            return;
        }

        // Call the backend
        const response = await fetch('ai-openrouter.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'message=' + encodeURIComponent(message)
        });

        const data = await response.json();

        if (data.error) {
            // If it's a rate limit error, use fallback
            if (data.error.includes('Too many requests') || data.error.includes('429')) {
                hideTyping();
                const fallbackMessage = getFallbackResponse(message);
                addMessage(fallbackMessage, 'ai');
                setStatus('Limited Mode', false);
            } else {
                hideTyping();
                addMessage('❌ ' + data.error, 'ai', true);
                setStatus('Error', false);
            }
        } else if (data.response) {
            hideTyping();
            addMessage(data.response, 'ai');
            setStatus('Online', true);
        } else {
            hideTyping();
            const fallbackMessage = getFallbackResponse(message);
            addMessage(fallbackMessage, 'ai');
            setStatus('Limited Mode', false);
        }

    } catch (error) {
        console.error('Chat error:', error);
        hideTyping();
        const fallbackMessage = getFallbackResponse(message);
        addMessage(fallbackMessage, 'ai');
        setStatus('Limited Mode', false);
    }

    // Reset state
    chatInput.disabled = false;
    sendBtn.disabled = false;
    isProcessing = false;
    chatInput.focus();
}

// ============================================
// SMART FALLBACK RESPONSES - EXPANDED
// ============================================
function getFallbackResponse(query) {
    const q = query.toLowerCase();
    
    // COUGH & COLD
    if (q.includes('cough') || q.includes('cold') || q.includes('runny nose') || q.includes('sneezing')) {
        return `🤧 **Cough and Cold Information:**

**Common Cold Symptoms:**
• Runny or stuffy nose
• Sneezing
• Sore throat
• Mild cough
• Fatigue

✅ **Home Care:**
1️⃣ Rest and get plenty of sleep
2️⃣ Drink warm fluids (ginger tea, honey lemon)
3️⃣ Use steam inhalation for congestion
4️⃣ Salt water gargle for sore throat
5️⃣ Over-the-counter pain relievers (Paracetamol)

🚨 **See a doctor if:**
• Symptoms last more than 10 days
• High fever develops
• Difficulty breathing

📍 Care Connect can connect you with community health workers and nearby pharmacies.`;
    }

    // FEVER
    if (q.includes('fever') || q.includes('temperature') || q.includes('hot') || q.includes('sweating')) {
        return `🌡️ **Fever Information:**

**Common Causes:**
• Malaria
• Flu or cold
• Infection
• Typhoid

✅ **What to do:**
1️⃣ Rest and stay hydrated
2️⃣ Take Paracetamol for fever (use correct dose)
3️⃣ Use lukewarm sponging to cool down
4️⃣ Monitor temperature regularly
5️⃣ Get tested at nearest health center

🚨 **Seek immediate care if:**
• Fever over 39°C
• Fever lasting more than 3 days
• With severe headache or vomiting

📍 Care Connect can help you find testing and treatment near you.`;
    }

    // HEADACHE
    if (q.includes('headache') || q.includes('head pain') || q.includes('migraine')) {
        return `😫 **Headache Information:**

**Common Types:**
• Tension headache (mild to moderate)
• Migraine (severe, one side, with nausea)
• Dehydration headache
• Fever-related headache

✅ **Home Relief:**
1️⃣ Rest in a quiet, dark room
2️⃣ Drink plenty of water
3️⃣ Apply cold compress to forehead
4️⃣ Take Paracetamol or Ibuprofen

🚨 **Seek urgent care if:**
• Severe, sudden headache (worst ever)
• Headache with fever, stiff neck, or confusion
• Headache after head injury

📍 Care Connect can help determine if it's serious.`;
    }

    // MALARIA
    if (q.includes('malaria') || q.includes('fever') || q.includes('chills')) {
        return `🦟 **Malaria Information:**

Malaria is common in Sierra Leone. Key symptoms include:
• High fever (39°C+) that comes and goes
• Chills and sweating
• Severe headache
• Nausea and vomiting

✅ **What to do:**
1️⃣ Get tested at nearest health center (RDT test)
2️⃣ If positive, start ACT medication within 24 hours
3️⃣ Rest and stay hydrated
4️⃣ Complete the full course of medication

🚨 Seek emergency care if: confusion, seizures, difficulty breathing.

📍 Care Connect can help you find testing centers and treatment near you.`;
    }

    // REFERRAL
    if (q.includes('referral') || q.includes('how to')) {
        return `📋 **How to Submit a Referral:**

1️⃣ Go to the 'Referrals' page
2️⃣ Fill in patient's name and contact details
3️⃣ Describe the medical condition
4️⃣ Choose a preferred clinic (optional)
5️⃣ Click 'Send Referral'

⏱️ **Timeframes:**
• Urgent: 4-6 hours
• Moderate: 24 hours
• Routine: 48-72 hours

💡 Our team will review and connect you with a provider. It's 100% FREE!`;
    }

    // FIND DOCTOR
    if (q.includes('doctor') || q.includes('find') || q.includes('care') || q.includes('clinic')) {
        return `👨‍⚕️ **Finding Healthcare in Sierra Leone:**

Care Connect helps you find:
• 🏥 Partner clinics across 15+ districts
• 👩‍⚕️ Community health workers (180+)
• 🏨 Hospitals and health centers

**How to find care:**
1️⃣ Visit our 'Find Care' page
2️⃣ Search by location or specialty
3️⃣ View provider profiles and ratings
4️⃣ Submit a referral directly

💡 All partner providers are verified and licensed.`;
    }

    // Default fallback
    return `💚 **Thank you for reaching out to CareConnect AI!**

I can help you with health questions. Try asking about:

📋 **Common topics:**
• Malaria symptoms and treatment
• Cough, cold, and fever
• How to submit a referral
• Finding a doctor or clinic
• General health questions

📱 **Quick links:**
• Find Care: /pages/doctors.php
• Make Referral: /pages/referral.html
• Clinics: /pages/hospitals.html

💡 Our AI is here 24/7 to help with your health questions! 🇸🇱

*Please consult a licensed healthcare professional for medical decisions.*`;
}

// ============================================
// SEND SUGGESTION
// ============================================
function sendSuggestion(text) {
    chatInput.value = text;
    sendMessage();
}

// ============================================
// INIT
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    chatInput.focus();
    setStatus('Online', true);
    
    // Enter key to send
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            sendMessage();
        }
    });
});

// Limit input length
chatInput.addEventListener('input', function() {
    if (this.value.length > 500) {
        this.value = this.value.substring(0, 500);
    }
});
</script>

</body>
</html>