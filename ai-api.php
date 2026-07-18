<?php
// Secure OpenRouter backend for CareConnect AI
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($input['message'] ?? '');
$history = $input['history'] ?? [];

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Get API key from environment (set this in Render)
$apiKey = getenv('OPENROUTER_API_KEY');

if (!$apiKey) {
    // Fallback if env not set yet
    http_response_code(500);
    echo json_encode([
        'error' => 'OpenRouter API key not configured',
        'fallback' => true
    ]);
    exit;
}

$systemPrompt = "You are CareConnect AI, a friendly and practical health assistant for people in Sierra Leone.\n\nYour role:\n- Help users understand common symptoms and illnesses (malaria, fever, typhoid, diarrhea, cough, headache, stomach pain, etc.)\n- Give clear, simple advice in plain English\n- Encourage clinic/hospital visits when needed\n- Help with Care Connect referrals guidance\n- Be warm, respectful, and conversational\n\nImportant rules:\n- You are NOT a replacement for a doctor\n- For emergencies (difficulty breathing, severe bleeding, chest pain, seizures, unconsciousness), tell them to go to a hospital immediately\n- Prefer practical advice relevant to Sierra Leone\n- Keep answers clear and not too long\n- Ask a short follow-up question when useful\n- If unsure, say so and recommend professional care";

// Build messages array
$messages = [
    ['role' => 'system', 'content' => $systemPrompt]
];

// Add limited history (last 6 turns)
if (is_array($history)) {
    $history = array_slice($history, -6);
    foreach ($history as $item) {
        if (!empty($item['role']) && !empty($item['content'])) {
            $role = $item['role'] === 'ai' ? 'assistant' : $item['role'];
            if (in_array($role, ['user', 'assistant'])) {
                $messages[] = [
                    'role' => $role,
                    'content' => $item['content']
                ];
            }
        }
    }
}

$messages[] = [
    'role' => 'user',
    'content' => $userMessage
];

$payload = [
    'model' => 'openai/gpt-4o-mini', // affordable + smart
    'messages' => $messages,
    'temperature' => 0.6,
    'max_tokens' => 500
];

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'HTTP-Referer: https://care-connect-sl-1.onrender.com',
        'X-Title: Care Connect SL AI Assistant'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Connection failed',
        'details' => $curlError,
        'fallback' => true
    ]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || empty($data['choices'][0]['message']['content'])) {
    http_response_code(500);
    echo json_encode([
        'error' => 'AI response failed',
        'details' => $data['error']['message'] ?? 'Unknown error',
        'fallback' => true
    ]);
    exit;
}

$aiReply = trim($data['choices'][0]['message']['content']);

// Convert basic markdown-ish text to simple HTML for chat display
$aiReply = htmlspecialchars($aiReply, ENT_QUOTES, 'UTF-8');
$aiReply = nl2br($aiReply);
$aiReply = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $aiReply);

echo json_encode([
    'reply' => $aiReply,
    'source' => 'openrouter'
]);
