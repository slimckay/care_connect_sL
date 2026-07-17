<?php
/**
 * AI Chat Handler - Care Connect SL (OpenRouter API)
 * Enhanced with Knowledge Base + FAQ + Medical Info
 * Reads API key from environment variables
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include Knowledge Base
require_once __DIR__ . '/knowledge_base.php';

$knowledgeBase = new KnowledgeBase();

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

// Get user message
$message = sanitizeInput($_POST['message'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

if (empty($message)) {
    echo json_encode(['error' => 'Please enter a message.']);
    exit;
}

// ============================================
// STEP 1: CHECK KNOWLEDGE BASE FIRST
// ============================================
$kb_response = $knowledgeBase->getResponse($message);

if ($kb_response !== null) {
    echo json_encode([
        'response' => $kb_response,
        'source' => 'knowledge_base',
        'type' => 'instant'
    ]);
    exit;
}

// ============================================
// STEP 2: CHECK FOR EMERGENCIES
// ============================================
$emergency_keywords = ['emergency', 'urgent', 'heart attack', 'stroke', 'seizure', 'unconscious', 'not breathing'];
foreach ($emergency_keywords as $keyword) {
    if (stripos($message, $keyword) !== false) {
        echo json_encode([
            'response' => "⚠️ **EMERGENCY SITUATION DETECTED**\n\n🚨 **CALL 999 IMMEDIATELY** 🚨\n\nGo to nearest hospital emergency room.\n\nCare Connect will coordinate with the hospital for your referral.\n\n**Do not wait!** Help is available.",
            'source' => 'emergency',
            'type' => 'urgent'
        ]);
        exit;
    }
}

// ============================================
// STEP 3: COMMON QUESTIONS (Skip API)
// ============================================
$message_lower = strtolower(trim($message));
$common_responses = [
    'what is care connect' => "Care Connect SL is Sierra Leone's home-based medical referral platform. We connect patients, caregivers, and healthcare providers across 15+ districts with 180+ community health workers and 65+ partner clinics.",
    'how to submit referral' => "To submit a referral:\n1️⃣ Go to 'Referrals' page\n2️⃣ Fill patient details and condition\n3️⃣ Choose preferred clinic (optional)\n4️⃣ Submit\nOur team will review within 24 hours.",
    'find doctor' => "Use our 'Find Care' page to search for doctors by name, specialty, or location. You can also request referrals directly.",
    'clinic near me' => "Visit our 'Clinics' page to find partner clinics across Sierra Leone. We have locations in Freetown, East End, Western Area, and more.",
    'cost' => "✅ Care Connect is FREE! You only pay for medical services directly to the provider. Some clinics offer free consultations.",
    'thank' => "You're welcome! 😊 Anything else I can help you with?",
    'bye' => "Take care! 🌟 Remember, Care Connect is always here for your healthcare needs."
];

foreach ($common_responses as $key => $response) {
    if (strpos($message_lower, $key) !== false) {
        echo json_encode([
            'response' => $response,
            'source' => 'faq',
            'type' => 'instant'
        ]);
        exit;
    }
}

// ============================================
// STEP 4: CALL OPENROUTER API
// ============================================

// ✅ READ API KEY FROM ENVIRONMENT VARIABLE (set in Render)
$api_key = getenv('OPENROUTER_API_KEY') ?: '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';

$url = 'https://openrouter.ai/api/v1/chat/completions';

// Free models available
$model = 'meta-llama/llama-3.3-70b-instruct:free'; // Good all-round free model

// Build system prompt
$system_prompt = "You are CareConnect AI, a helpful healthcare assistant for Care Connect SL in Sierra Leone. 

ABOUT CARE CONNECT SL:
- Sierra Leone's home-based medical referral platform
- Connects patients, caregivers, and healthcare providers
- 180+ community workers, 65+ partner clinics, 15+ districts covered

YOUR ROLE:
1. Help patients understand symptoms and guide to appropriate care
2. Explain how referrals work on the platform
3. Provide general health information (NOT medical diagnosis)
4. Be friendly, empathetic, and professional
5. Always recommend professional medical help for serious conditions

Current Date: " . date('Y-m-d');

// Prepare API request
$data = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $message]
    ],
    'temperature' => 0.7,
    'max_tokens' => 500,
    'top_p' => 0.95,
    'stream' => false
];

// Send request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key,
    'HTTP-Referer: https://care-connect-sl.onrender.com', // Update with your Render URL
    'X-Title: Care Connect SL'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Process response
if ($http_code === 200) {
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        $ai_response = $result['choices'][0]['message']['content'];
        
        // Add disclaimer if not present
        if (!strpos($ai_response, 'consult a licensed healthcare professional')) {
            $ai_response .= "\n\n---\n*Please consult a licensed healthcare professional for medical decisions.*";
        }
        
        echo json_encode(['response' => $ai_response, 'source' => 'api']);
        exit;
    } else {
        echo json_encode(['error' => 'Could not generate a response. Please try again.']);
        exit;
    }
} else {
    // Handle errors
    $error_message = 'AI service temporarily unavailable. Please try again later.';
    
    if ($http_code === 429) {
        $error_message = 'Too many requests. Please wait a moment and try again.';
    } elseif ($http_code === 401 || $http_code === 403) {
        $error_message = 'API key invalid. Please contact support.';
    } elseif ($http_code === 0) {
        $error_message = 'Network error. Please check your internet connection.';
    }
    
    error_log("OpenRouter API error: HTTP $http_code - $curl_error - " . substr($response, 0, 200));
    echo json_encode(['error' => $error_message]);
    exit;
}

// ============================================
// HELPER FUNCTION
// ============================================
function sanitizeInput($input) {
    if (is_null($input)) return '';
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}
?>
