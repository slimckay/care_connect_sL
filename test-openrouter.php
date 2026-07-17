<?php
/**
 * Test OpenRouter API Connection - Gemma 4 31B
 */

// ⚠️ REPLACE WITH YOUR ACTUAL API KEY
$api_key = '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';

$url = 'https://openrouter.ai/api/v1/chat/completions';

// ✅ Using Google Gemma 4 31B (FREE - 256K context)
$model = 'google/gemma-4-31b-instruct';

$data = [
    'model' => $model,
    'messages' => [
        [
            'role' => 'user',
            'content' => 'Say hello and tell me one health tip in 10 words'
        ]
    ],
    'max_tokens' => 50,
    'temperature' => 0.7
];

// Convert to JSON
$jsonData = json_encode($data);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key,
    'HTTP-Referer: http://localhost',
    'X-Title: Care Connect SL'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "=== OPENROUTER API TEST ===<br><br>";
echo "HTTP Status Code: " . $http_code . "<br>";
echo "Model: " . $model . " (FREE - 256K context)<br><br>";

if ($http_code === 200) {
    echo "✅ API is working!<br><br>";
    $result = json_decode($response, true);
    echo "<strong>Response:</strong><br>";
    echo "<pre>" . htmlspecialchars($result['choices'][0]['message']['content'] ?? $response) . "</pre>";
} else {
    echo "❌ API is NOT working<br><br>";
    echo "Error: " . $curl_error . "<br>";
    echo "Response: " . htmlspecialchars($response) . "<br>";
    
    if ($http_code === 401 || $http_code === 403) {
        echo "<br>💡 Your API key is invalid.<br>";
        echo "Get a free key from: <a href='https://openrouter.ai/' target='_blank'>https://openrouter.ai/</a>";
    } elseif ($http_code === 402) {
        echo "<br>💡 Insufficient balance. Add credits or use a free model.";
    } elseif ($http_code === 404) {
        echo "<br>💡 Model not found. Try a different model.<br>";
        echo "Check available models: <a href='https://openrouter.ai/models' target='_blank'>https://openrouter.ai/models</a>";
    } elseif ($http_code === 400) {
        echo "<br>💡 Bad request. The API format might be incorrect.";
        echo "<br>Try using a different model name.";
    }
}
?>