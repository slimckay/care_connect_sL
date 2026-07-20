<?php
/**
 * Create a Stripe Checkout Session — Care Connect SL
 * POST JSON: { amount_sll, type?, provider_id?, referral_id? }
 * Requires logged-in patient for wallet_topup / consultation.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Login required']);
    exit;
}

require_once __DIR__ . '/../stripe_config.php';

if (STRIPE_SECRET_KEY === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Stripe not configured. Set STRIPE_SECRET_KEY on the server.']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body)) {
    $body = $_POST;
}

$userId = (int)$_SESSION['user_id'];
$amountSll = (float)($body['amount_sll'] ?? 0);
$type = strtolower((string)($body['type'] ?? 'wallet_topup'));
$providerId = (int)($body['provider_id'] ?? 0);
$referralId = (int)($body['referral_id'] ?? 0);

if ($amountSll < 1000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Minimum amount is 1,000 SLL']);
    exit;
}

if ($type === 'consultation' && $providerId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'provider_id required for consultation']);
    exit;
}

$currency = STRIPE_CURRENCY;
$usdCents = max(50, (int)round(($amountSll / 22000) * 100));
if ($currency === 'sll') {
    $unitAmount = (int)round($amountSll);
} else {
    $unitAmount = $usdCents;
}

$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$successUrl = $origin . '/wallet.php?stripe=success';
$cancelUrl = $origin . '/wallet.php?stripe=cancel';
if ($type === 'consultation') {
    $successUrl = $origin . '/pages/pay.php?stripe=success';
    $cancelUrl = $origin . '/pages/pay.php?stripe=cancel';
}

$lineName = $type === 'consultation'
    ? 'Care Connect consultation'
    : 'Care Connect wallet top-up';

$metadata = [
    'user_id' => (string)$userId,
    'type' => $type,
    'amount_sll' => (string)(int)$amountSll,
];
if ($providerId > 0) {
    $metadata['provider_id'] = (string)$providerId;
}
if ($referralId > 0) {
    $metadata['referral_id'] = (string)$referralId;
}

$params = [
    'mode' => 'payment',
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
    'client_reference_id' => (string)$userId,
    'line_items' => [[
        'quantity' => 1,
        'price_data' => [
            'currency' => $currency,
            'unit_amount' => $unitAmount,
            'product_data' => [
                'name' => $lineName,
                'description' => 'SLL ' . number_format($amountSll, 0),
            ],
        ],
    ]],
    'metadata' => $metadata,
    'payment_intent_data' => [
        'metadata' => $metadata,
    ],
];

function stripeFlatten(array $data, string $prefix = ''): array
{
    $out = [];
    foreach ($data as $k => $v) {
        $key = $prefix === '' ? $k : $prefix . '[' . $k . ']';
        if (is_array($v)) {
            $isList = array_keys($v) === range(0, count($v) - 1);
            if ($isList) {
                foreach ($v as $i => $item) {
                    if (is_array($item)) {
                        $out += stripeFlatten($item, $key . '[' . $i . ']');
                    } else {
                        $out[$key . '[' . $i . ']'] = $item;
                    }
                }
            } else {
                $out += stripeFlatten($v, $key);
            }
        } else {
            $out[$key] = $v;
        }
    }
    return $out;
}

$flat = stripeFlatten($params);
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
    CURLOPT_POSTFIELDS => http_build_query($flat),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Stripe request failed: ' . $err]);
    exit;
}

$data = json_decode($response, true);
if ($httpCode >= 400 || !is_array($data)) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => is_array($data) ? ($data['error']['message'] ?? 'Stripe error') : 'Stripe error',
        'stripe_status' => $httpCode,
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'session_id' => $data['id'] ?? null,
    'url' => $data['url'] ?? null,
]);
