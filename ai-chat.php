<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Health Assistant — Care Connect SL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .chat-container {
            max-width: 820px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 580px;
            border: 1px solid #e5e7eb;
        }

        .chat-header {
            background: linear-gradient(135deg, #0F1C3A, #1a2a4a);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-header .ai-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .chat-messages {
            flex: 1;
            padding: 16px 20px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message {
            max-width: 85%;
            padding: 11px 15px;
            border-radius: 14px;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        .message.user {
            align-self: flex-end;
            background: #1EB53A;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.ai {
            align-self: flex-start;
            background: white;
            border: 1px solid #e5e7eb;
            border-bottom-left-radius: 4px;
        }

        .chat-input-area {
            padding: 14px 20px;
            border-top: 1px solid #e5e7eb;
            background: white;
        }

        .chat-input {
            display: flex;
            gap: 10px;
        }

        .chat-input input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
        }

        .chat-input button {
            padding: 0 24px;
            background: #1EB53A;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
        }

        .chat-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px 20px;
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
        }

        .chat-suggestions button {
            padding: 6px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            background: white;
            font-size: 0.85rem;
            cursor: pointer;
        }

        /* Mobile Fixes */
        @media (max-width: 768px) {
            .chat-container {
                height: 520px;
                margin: 0 10px;
                border-radius: 12px;
            }
            .chat-messages {
                padding: 12px 16px;
            }
            .message {
                max-width: 92%;
                font-size: 0.92rem;
                padding: 10px 14px;
            }
            .chat-input {
                flex-direction: column;
            }
            .chat-input input {
                width: 100%;
            }
            .chat-input button {
                width: 100%;
                padding: 12px;
            }
            .chat-suggestions {
                padding: 8px 16px;
                gap: 6px;
            }
            .chat-header {
                padding: 12px 16px;
            }
        }

        @media (max-width: 480px) {
            .chat-container {
                height: 480px;
            }
            .message {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

<header>
  <div class="nav-inner">
    <a href="index.html" class="logo">Care<span class="accent">Connect</span> SL</a>
  </div>
</header>

<main style="max-width: 860px; margin: 20px auto; padding: 0 12px;">
    <div style="text-align: center; margin-bottom: 16px;">
        <h1 style="margin: 0 0 6px 0; font-size: 1.8rem;">🤖 CareConnect AI Assistant</h1>
        <p style="color: #64748B; margin: 0;">Ask about symptoms, malaria, fever, referrals, or health questions</p>
    </div>

    <div class="chat-container">
        <!-- Header -->
        <div class="chat-header">
            <div class="ai-avatar">🇸🇱</div>
            <div>
                <h2 style="margin:0; font-size:1.05rem;">CareConnect AI</h2>
                <p style="margin:0; font-size:0.8rem; opacity:0.85;">Sierra Leone Health Assistant</p>
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="message ai">
                👋 Hello! I'm your AI health assistant for Sierra Leone.<br><br>
                I can help with malaria, fever, cough, typhoid, diarrhea, and more.<br>
                How can I assist you today?
            </div>
        </div>

        <!-- Suggestions -->
        <div class="chat-suggestions">
            <button onclick="sendSuggestion('What are the symptoms of malaria?')">🦟 Malaria</button>
            <button onclick="sendSuggestion('I have fever and headache')">🌡️ Fever</button>
            <button onclick="sendSuggestion('I have cough and cold')">🤧 Cough</button>
            <button onclick="sendSuggestion('Symptoms of typhoid?')">🦠 Typhoid</button>
            <button onclick="sendSuggestion('I have diarrhea')">💧 Diarrhea</button>
        </div>

        <!-- Input -->
        <div class="chat-input-area">
            <div class="chat-input">
                <input type="text" id="chatInput" placeholder="Type your health question..." autocomplete="off">
                <button onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>
</main>

<script>
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');

let isProcessing = false;

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

    addMessage(message, 'user');
    chatInput.value = '';
    isProcessing = true;
    showTyping();

    // Simple local knowledge base for common conditions
    setTimeout(() => {
        hideTyping();
        const response = getLocalResponse(message);
        addMessage(response, 'ai');
        isProcessing = false;
    }, 800);
}

function getLocalResponse(query) {
    const q = query.toLowerCase();

    if (q.includes('malaria')) {
        return `🦟 <strong>Malaria</strong><br><br>Symptoms: High fever, chills, headache, sweating, body aches.<br><br><strong>What to do:</strong> Get tested at nearest health center. Start treatment immediately if positive. Rest and drink fluids.`;
    }

    if (q.includes('fever')) {
        return `🌡️ <strong>Fever</strong><br><br>Common causes: Malaria, flu, infection, typhoid.<br><br><strong>Home care:</strong> Rest, drink water, take paracetamol. See a doctor if fever lasts more than 2-3 days or is very high.`;
    }

    if (q.includes('cough') || q.includes('cold')) {
        return `🤧 <strong>Cough & Cold</strong><br><br>Rest, drink warm fluids, use steam inhalation. See a doctor if cough lasts more than 10 days or you have difficulty breathing.`;
    }

    if (q.includes('typhoid')) {
        return `🦠 <strong>Typhoid Fever</strong><br><br>Symptoms: High fever, headache, stomach pain, weakness, loss of appetite.<br><br><strong>Action:</strong> Get tested at a health center. Treatment usually involves antibiotics. Drink clean water and eat safe food.`;
    }

    if (q.includes('diarrhea')) {
        return `💧 <strong>Diarrhea</strong><br><br>Drink ORS (oral rehydration solution). Eat light food. Avoid dairy. See a doctor if it lasts more than 2 days or you see blood.`;
    }

    if (q.includes('headache')) {
        return `😫 <strong>Headache</strong><br><br>Rest in a quiet room, drink water, take paracetamol. See a doctor if severe or with fever/vomiting.`;
    }

    if (q.includes('referral')) {
        return `📋 You can submit a referral on our Referrals page. Our team will connect you with the right healthcare provider.`;
    }

    return `Thank you for your question. I can help with malaria, fever, cough, typhoid, diarrhea, headache, and referrals. Try asking about any of these topics!`;
}

function sendSuggestion(text) {
    chatInput.value = text;
    sendMessage();
}

// Enter key support
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