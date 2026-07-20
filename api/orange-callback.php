<?php
/**
 * Orange Money notification callback — Care Connect SL
 * Set as notif_url when creating a web payment.
 *
 * Orange typically POSTs JSON or form fields including:
 *   status / status_code, order_id, txnid, amount, notif_token, ...
 *
 * We credit the wallet only when status indicates SUCCESS and order is still pending.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Accept GET for browser return pings, but only process payment on POST
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw = file_get_contents('php://input');
$json = json_decode($raw ?: '', true);

$data = [];
if (is_array($json)) {
    $data = $json;
}
$data = array_merge($_GET, $_POST, $data);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../payment_helper.php';

// Ensure request log table
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS orange_callbacks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(64) NULL,
        status VARCHAR(40) NULL,
        payload TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$orderId = (string)($data['order_id'] ?? ($data['orderId'] ?? ($data['orderID'] ?? '')));
$status = strtoupper((string)($data['status'] ?? ($data['status_code'] ?? ($data['payment_status'] ?? ''))));
$txnId = (string)($data['txnid'] ?? ($data['txn_id'] ?? ($data['transaction_id'] ?? '')));
$amount = (float)($data['amount'] ?? 0);

try {
    $conn->prepare('INSERT INTO orange_callbacks (order_id, status, payload, created_at) VALUES (?, ?, ?, NOW())')
         ->execute([$orderId ?: null, $status ?: null, mb_substr($raw ?: json_encode($data), 0, 4000)]);
} catch (Exception $e) {}

// Success status values seen across Orange markets
$successStatuses = ['SUCCESS', 'SUCCESSFUL', 'SUCCESSFULL', 'PAID', '0', '200', 'OK'];
$isSuccess = in_array($status, $successStatuses, true)
    || (isset($data['status']) && (int)$data['status'] === 0);

if ($orderId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing order_id']);
    exit;
}

if (!$isSuccess) {
    // Acknowledge so Orange stops retrying for failed/cancelled
    try {
        $conn->prepare("UPDATE mobile_money_requests SET status = 'failed' WHERE reference = ? AND status = 'pending'")
             ->execute([$orderId]);
    } catch (Exception $e) {}
    http_response_code(200);
    echo json_encode(['ok' => true, 'credited' => false, 'status' => $status]);
    exit;
}

// Load pending mobile money request
try {
    $stmt = $conn->prepare("SELECT * FROM mobile_money_requests WHERE reference = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $req = false;
}

if (!$req) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'credited' => false, 'error' => 'Unknown order']);
    exit;
}

if (($req['status'] ?? '') === 'completed') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'credited' => false, 'duplicate' => true]);
    exit;
}

$userId = (int)$req['user_id'];
$creditAmount = $amount > 0 ? $amount : (float)$req['amount'];

$payment = new PaymentSystem($conn);
$ok = $payment->addFunds(
    $userId,
    $creditAmount,
    'Orange Money deposit' . ($txnId ? ' (' . $txnId . ')' : ''),
    $orderId
);

if ($ok) {
    try {
        $conn->prepare("UPDATE mobile_money_requests SET status = 'completed', completed_at = NOW() WHERE id = ?")
             ->execute([(int)$req['id']]);
    } catch (Exception $e) {
        try {
            $conn->prepare("UPDATE mobile_money_requests SET status = 'completed' WHERE id = ?")
                 ->execute([(int)$req['id']]);
        } catch (Exception $e2) {}
    }

    try {
        $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                        VALUES (?, 'payment', 'Orange Money received', ?, 'wallet.php', 0, NOW())")
             ->execute([
                 $userId,
                 'SLL ' . number_format($creditAmount, 0) . ' added to your wallet via Orange Money.',
             ]);
    } catch (Exception $e) {}

    http_response_code(200);
    echo json_encode(['ok' => true, 'credited' => true, 'amount' => $creditAmount]);
    exit;
}

http_response_code(500);
echo json_encode(['ok' => false, 'error' => 'Credit failed']);
