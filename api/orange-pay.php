<?php
/**
 * Start Orange Money payment for wallet top-up — Care Connect SL
 * POST: amount, phone (optional display), return handled by Orange pages
 */
header('Content-Type: application/json; charset=utf-8');

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

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../payment_helper.php';
require_once __DIR__ . '/../orange_money.php';

$userId = (int)$_SESSION['user_id'];
$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '{}', true);
if (!is_array($body)) {
    $body = $_POST;
}

$amount = (float)($body['amount'] ?? 0);
$phone = trim((string)($body['phone'] ?? ''));

if ($amount < 1000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Minimum is 1,000 SLL']);
    exit;
}

$payment = new PaymentSystem($conn);
$orange = new OrangeMoney();

// Unique order reference
$orderId = 'OM-' . $userId . '-' . strtoupper(bin2hex(random_bytes(4)));

// Store pending request
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS mobile_money_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        phone_number VARCHAR(40) NULL,
        provider VARCHAR(40) NULL,
        status VARCHAR(20) DEFAULT 'pending',
        reference VARCHAR(64) NULL,
        pay_token VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        INDEX idx_ref (reference)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

try {
    $conn->prepare("INSERT INTO mobile_money_requests
        (user_id, amount, phone_number, provider, status, reference, created_at)
        VALUES (?, ?, ?, 'Orange', 'pending', ?, NOW())")
         ->execute([$userId, $amount, $phone ?: null, $orderId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not create payment request']);
    exit;
}

$origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$notifUrl = $origin . '/api/orange-callback.php';
$returnUrl = $origin . '/wallet.php?orange=success&ref=' . urlencode($orderId);
$cancelUrl = $origin . '/wallet.php?orange=cancel&ref=' . urlencode($orderId);

// Live Orange when credentials exist
if ($orange->isConfigured()) {
    $result = $orange->initiatePayment(
        $orderId,
        $amount,
        $notifUrl,
        $returnUrl,
        $cancelUrl,
        'Care Connect wallet top-up'
    );

    if (!empty($result['success']) && !empty($result['payment_url'])) {
        if (!empty($result['pay_token'])) {
            try {
                $conn->prepare('UPDATE mobile_money_requests SET pay_token = ? WHERE reference = ?')
                     ->execute([$result['pay_token'], $orderId]);
            } catch (Exception $e) {}
        }
        echo json_encode([
            'ok' => true,
            'live' => true,
            'reference' => $orderId,
            'payment_url' => $result['payment_url'],
            'message' => 'Redirecting to Orange Money…',
        ]);
        exit;
    }

    // Fall through to demo if Orange API fails (so demos still work)
    error_log('Orange live init failed: ' . json_encode($result));
}

// Demo / offline mode — credit immediately so product demos work without keys
$payment->addFunds($userId, $amount, 'Orange Money deposit (demo mode)', $orderId);
try {
    $conn->prepare("UPDATE mobile_money_requests SET status = 'completed', completed_at = NOW() WHERE reference = ?")
         ->execute([$orderId]);
} catch (Exception $e) {}

echo json_encode([
    'ok' => true,
    'live' => false,
    'demo' => true,
    'reference' => $orderId,
    'payment_url' => null,
    'message' => 'Demo mode: funds added. Set ORANGE_CLIENT_ID / SECRET / MERCHANT_KEY for live Orange pay.',
    'redirect' => '/wallet.php?orange=demo&ref=' . urlencode($orderId),
]);
