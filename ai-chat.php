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
        <p>Talk to me about symptoms, common illnesses in Sierra Leone, or how to get care</p>
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
                You can talk to me naturally. Tell me how you feel, ask about malaria, fever, typhoid, diarrhea, cough, pregnancy care, or how to get a referral.<br><br>
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
let conversationContext = [];

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
    conversationContext.push({ role: 'user', text: message });
    chatInput.value = '';
    isProcessing = true;
    showTyping();

    setTimeout(() => {
        hideTyping();
        const response = getConversationalResponse(message);
        addMessage(response, 'ai');
        conversationContext.push({ role: 'ai', text: response });
        isProcessing = false;
    }, 700);
}

function getConversationalResponse(query) {
    const q = query.toLowerCase();
    const recent = conversationContext.slice(-4).map(m => m.text.toLowerCase()).join(' ');

    // Greetings / small talk
    if (/^(hi|hello|hey|good morning|good afternoon|good evening)\b/.test(q)) {
        return `Hello! 😊 I'm here to help with health questions relevant to Sierra Leone.<br><br>You can tell me your symptoms, ask about common illnesses like malaria or typhoid, or ask how to get a referral. What's going on?`;
    }

    if (q.includes('thank') || q.includes('thanks')) {
        return `You're very welcome. 🙏 Take care of yourself. If anything gets worse or you're unsure, please visit a nearby clinic or hospital.<br><br>Is there anything else I can help you with?`;
    }

    if (q.includes('how are you')) {
        return `I'm doing well, thank you! Ready to help. How are <strong>you</strong> feeling today?`;
    }

    // Emergency flags
    if (q.includes('cannot breathe') || q.includes("can't breathe") || q.includes('chest pain') || q.includes('unconscious') || q.includes('severe bleeding') || q.includes('convulsion') || q.includes('seizure')) {
        return `🚨 <strong>This sounds urgent.</strong><br><br>Please go to the nearest hospital or call emergency help right away. Don't wait for online advice in emergency situations.<br><br>If you can, ask someone nearby to take you to care now.`;
    }

    // Malaria
    if (q.includes('malaria') || (q.includes('mosquito') && q.includes('fever')) || (q.includes('chills') && q.includes('fever'))) {
        return `🦟 <strong>Malaria is very common in Sierra Leone.</strong><br><br>
<strong>Typical symptoms:</strong><br>
• High fever<br>
• Chills and sweating<br>
• Headache and body pain<br>
• Weakness / tiredness<br>
• Sometimes vomiting<br><br>
<strong>What you should do:</strong><br>
1. Get a malaria test as soon as possible at a clinic or pharmacy that offers testing.<br>
2. If positive, start treatment the same day (don't delay).<br>
3. Rest and drink plenty of fluids.<br>
4. Use a mosquito net and keep the area clean to reduce reinfection.<br><br>
If fever is very high, the person is a child, pregnant, or getting worse quickly, go to a hospital today.<br><br>
Would you like me to explain what to do while waiting for a test?`;
    }

    // Fever
    if (q.includes('fever') || q.includes('temperature') || q.includes('hot body')) {
        return `🌡️ <strong>Fever</strong> means the body is fighting something.<br><br>
In Sierra Leone, common causes include malaria, infection, typhoid, or flu-like illness.<br><br>
<strong>Home care:</strong><br>
• Rest<br>
• Drink plenty of clean water / ORS if needed<br>
• Take paracetamol if available (follow correct dose)<br>
• Remove excess clothing and keep the room cool<br><br>
<strong>See a clinic if:</strong><br>
• Fever lasts more than 2–3 days<br>
• Very high fever<br>
• With severe headache, vomiting, stiff neck, or confusion<br>
• In a baby, pregnant woman, or elderly person<br><br>
Do you also have chills, headache, or stomach pain? That can help me guide you better.`;
    }

    // Typhoid
    if (q.includes('typhoid')) {
        return `🦠 <strong>Typhoid fever</strong> is spread through contaminated food or water.<br><br>
<strong>Common symptoms:</strong><br>
• High fever that may rise slowly<br>
• Headache<br>
• Stomach pain<br>
• Weakness and loss of appetite<br>
• Sometimes diarrhea or constipation<br><br>
<strong>What to do:</strong><br>
• Get tested at a health facility<br>
• Treatment usually needs antibiotics prescribed by a clinician<br>
• Drink clean water only and eat well-cooked food<br>
• Rest and avoid self-medication with incomplete antibiotics<br><br>
If symptoms are severe or the person is dehydrated, go to a hospital. Would you like prevention tips too?`;
    }

    // Diarrhea / cholera-like
    if (q.includes('diarrhea') || q.includes('diarrhoea') || q.includes('running stomach') || q.includes('cholera') || q.includes('loose stool')) {
        return `💧 <strong>Diarrhea</strong> can become dangerous mainly because of dehydration.<br><br>
<strong>Immediate care:</strong><br>
• Drink ORS (oral rehydration solution) often<br>
• Continue small amounts of food if possible<br>
• Avoid unclean water and street food for now<br>
• Wash hands with soap regularly<br><br>
<strong>Go to a clinic quickly if:</strong><br>
• Blood in stool<br>
• Very watery diarrhea that doesn't stop<br>
• Signs of dehydration (dry mouth, no urine, dizziness)<br>
• High fever with diarrhea<br>
• Baby or elderly person is affected<br><br>
I can also explain how to prepare simple ORS if you need that.`;
    }

    // Cough / cold / pneumonia signs
    if (q.includes('cough') || q.includes('cold') || q.includes('flu') || q.includes('catarrh') || q.includes('breathing')) {
        return `🤧 <strong>Cough and cold</strong> are common, but breathing problems need attention.<br><br>
<strong>Helpful care:</strong><br>
• Rest and drink warm fluids<br>
• Steam inhalation can ease blocked nose<br>
• Avoid smoke and dust where possible<br><br>
<strong>See a doctor if:</strong><br>
• Difficulty breathing<br>
• Chest pain<br>
• Cough lasting more than 10–14 days<br>
• High fever with cough<br>
• Coughing blood<br><br>
Is the cough dry, or is there phlegm? And do you have fever with it?`;
    }

    // Headache
    if (q.includes('headache') || q.includes('migraine')) {
        return `😫 <strong>Headache</strong> can come from stress, dehydration, malaria, sinus problems, or eye strain.<br><br>
<strong>Try this first:</strong><br>
• Rest in a quiet place<br>
• Drink water<br>
• Take paracetamol if appropriate<br>
• Reduce screen time and rest your eyes<br><br>
<strong>Seek care if headache is:</strong><br>
• Sudden and very severe<br>
• With fever, stiff neck, vomiting, or confusion<br>
• After a head injury<br>
• Getting worse over days<br><br>
Do you also have fever or neck stiffness?`;
    }

    // Stomach pain
    if (q.includes('stomach') || q.includes('belly') || q.includes('abdominal') || q.includes('ulcer')) {
        return `🤢 <strong>Stomach pain</strong> has many causes — food, infection, ulcer, or more serious issues.<br><br>
<strong>Helpful steps:</strong><br>
• Rest and avoid heavy / spicy food for a while<br>
• Drink clean water in small sips<br>
• Note if pain is mild, sharp, or with vomiting/diarrhea<br><br>
<strong>Go to a clinic urgently if:</strong><br>
• Severe pain that doesn't ease<br>
• Vomiting blood or black stool<br>
• Pain with high fever<br>
• Swollen hard abdomen<br><br>
Can you tell me if the pain is upper stomach, lower stomach, or all over?`;
    }

    // Pregnancy related basic guidance
    if (q.includes('pregnant') || q.includes('pregnancy') || q.includes('antenatal')) {
        return `🤰 <strong>Pregnancy care is very important.</strong><br><br>
Please visit an antenatal clinic regularly. In Sierra Leone, early and repeated checkups help protect mother and baby.<br><br>
<strong>General advice:</strong><br>
• Attend antenatal visits as scheduled<br>
• Sleep under a mosquito net (malaria is dangerous in pregnancy)<br>
• Take prescribed supplements if given by the clinic<br>
• Seek care quickly for bleeding, severe headache, swelling, or reduced baby movement<br><br>
I can give general guidance, but a trained midwife or doctor should manage pregnancy care. Would you like help finding how to request a referral?`;
    }

    // Children
    if (q.includes('baby') || q.includes('child') || q.includes('my son') || q.includes('my daughter') || q.includes('infant')) {
        return `👶 For children, it's safer to act early.<br><br>
<strong>Take a child to a clinic promptly if there is:</strong><br>
• High fever<br>
• Difficulty breathing<br>
• Refusal to eat/drink<br>
• Unusual sleepiness or irritability<br>
• Diarrhea with dehydration signs<br><br>
Please don't wait too long with babies and young children. If you share the main symptoms, I can help you know what to watch for.`;
    }

    // Referral help
    if (q.includes('referral') || q.includes('see a doctor') || q.includes('find doctor') || q.includes('clinic') || q.includes('hospital')) {
        return `📋 <strong>I can help you get connected to care.</strong><br><br>
On Care Connect SL you can:<br>
1. Go to the <strong>Referrals</strong> page and submit patient details<br>
2. Use <strong>Find Care</strong> to look for verified providers<br>
3. Contact the team if you need guidance<br><br>
If this is urgent, please go directly to the nearest clinic or hospital instead of waiting.<br><br>
Do you want the steps for submitting a referral?`;
    }

    // Prevention / general health
    if (q.includes('prevent') || q.includes('protection') || q.includes('stay healthy')) {
        return `🛡️ <strong>Simple prevention tips for Sierra Leone:</strong><br><br>
• Sleep under insecticide-treated mosquito nets<br>
• Drink clean / treated water<br>
• Wash hands with soap before eating and after toilet
• Cook food well and avoid unsafe leftovers<br>
• Keep surroundings clean to reduce mosquitoes and disease<br>
• Go for early testing when fever starts<br><br>
Prevention saves time, money, and lives. Want tips focused on malaria or on water-related illness?`;
    }

    // Follow-up style conversational fallback using context
    if (recent.includes('malaria') && (q.includes('yes') || q.includes('what next') || q.includes('while waiting'))) {
        return `While waiting for a malaria test:<br><br>
• Rest and stay hydrated<br>
• Take paracetamol for fever if available<br>
• Avoid self-starting strong medicines without guidance<br>
• Go urgently if breathing becomes difficult, confusion starts, or the person can't keep fluids down<br><br>
Please test as soon as you can. Would you like help with referral options?`;
    }

    // Default conversational response
    return `I hear you. 🙂<br><br>
I can help with common issues like <strong>malaria, fever, typhoid, diarrhea, cough, headache, stomach pain, pregnancy care basics, and referrals</strong>.<br><br>
Tell me a bit more — for example: "I have fever and chills since yesterday" or "My child has diarrhea". The more details you share, the better I can guide you.<br><br>
<strong>Note:</strong> I'm a support assistant, not a replacement for a doctor or hospital in emergencies.`;
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