<?php
/**
 * Stripe Webhook Handler — Care Connect SL
 *
 * Endpoint: POST /api/stripe-webhook.php
 * Dashboard → Developers → Webhooks → Add endpoint
 * Events to enable:
 *   - checkout.session.completed
 *   - payment_intent.succeeded
 *   - payment_intent.payment_failed
 *   - charge.refunded
 *
 * Expected Checkout / PaymentIntent metadata:
 *   user_id       (required)  Care Connect user id
 *   type          wallet_topup | consultation
 *   amount_sll    amount in SLL to credit / charge internally
 *   provider_id   (consultation) doctor/hospital user id
 *   referral_id   (optional)
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Stripe sends POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../stripe_config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../payment_helper.php';

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === false || $payload === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

// --- Verify signature (no SDK required) ---
function stripeVerifySignature(string $payload, string $sigHeader, string $secret, int $tolerance = 300): bool
{
    if ($secret === '' || $sigHeader === '') {
        return false;
    }

    $parts = [];
    foreach (explode(',', $sigHeader) as $item) {
        $kv = explode('=', trim($item), 2);
        if (count($kv) === 2) {
            $parts[$kv[0]][] = $kv[1];
        }
    }

    $timestamp = $parts['t'][0] ?? null;
    $signatures = $parts['v1'] ?? [];
    if (!$timestamp || empty($signatures)) {
        return false;
    }

    if (abs(time() - (int)$timestamp) > $tolerance) {
        return false;
    }

    $signed = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed, $secret);

    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}

if (!stripeConfigured()) {
    // Allow local testing only when explicitly enabled
    $allowUnsigned = getenv('STRIPE_ALLOW_UNSIGNED_WEBHOOKS') === '1';
    if (!$allowUnsigned) {
        http_response_code(500);
        echo json_encode(['error' => 'Stripe webhook not configured']);
        exit;
    }
} elseif (!stripeVerifySignature($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['type']) || empty($event['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event JSON']);
    exit;
}

$eventId = (string)$event['id'];
$eventType = (string)$event['type'];
$object = $event['data']['object'] ?? [];

// --- Idempotency table ---
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS stripe_events (
        id VARCHAR(255) PRIMARY KEY,
        type VARCHAR(100) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'processed',
        payload_summary TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log('stripe_events table: ' . $e->getMessage());
}

// Already processed?
try {
    $chk = $conn->prepare('SELECT id FROM stripe_events WHERE id = ? LIMIT 1');
    $chk->execute([$eventId]);
    if ($chk->fetch()) {
        http_response_code(200);
        echo json_encode(['received' => true, 'duplicate' => true]);
        exit;
    }
} catch (Exception $e) {}

$payment = new PaymentSystem($conn);
$handled = false;
$summary = $eventType;

/**
 * Extract metadata from Checkout Session or PaymentIntent
 */
function stripeMeta(array $object): array
{
    $meta = $object['metadata'] ?? [];
    if (!is_array($meta)) {
        $meta = [];
    }
    // Nested payment_intent metadata sometimes on session
    return $meta;
}

function markEvent(PDO $conn, string $id, string $type, string $summary): void
{
    try {
        $conn->prepare('INSERT INTO stripe_events (id, type, status, payload_summary, created_at) VALUES (?, ?, ?, ?, NOW())')
             ->execute([$id, $type, 'processed', mb_substr($summary, 0, 500)]);
    } catch (Exception $e) {
        // unique conflict = concurrent delivery; ignore
    }
}

function notifyUser(PDO $conn, int $userId, string $title, string $message, string $link = 'wallet.php'): void
{
    try {
        $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                        VALUES (?, 'payment', ?, ?, ?, 0, NOW())")
             ->execute([$userId, $title, $message, $link]);
    } catch (Exception $e) {}
}

try {
    switch ($eventType) {

        // ---- Successful Checkout (preferred for hosted pay) ----
        case 'checkout.session.completed': {
            $session = $object;
            $meta = stripeMeta($session);
            $userId = (int)($meta['user_id'] ?? 0);
            $type = strtolower((string)($meta['type'] ?? 'wallet_topup'));
            $amountSll = (float)($meta['amount_sll'] ?? 0);

            // Fallback: convert from session amount_total (cents)
            if ($amountSll <= 0 && !empty($session['amount_total'])) {
                // Treat Stripe amount as major units * 100 if currency is not SLL
                $amountSll = ((int)$session['amount_total']) / 100;
            }

            $paymentStatus = $session['payment_status'] ?? '';
            if ($paymentStatus !== 'paid' && ($session['status'] ?? '') !== 'complete') {
                $summary = 'checkout not paid yet';
                break;
            }

            if ($userId <= 0 || $amountSll <= 0) {
                $summary = 'missing user_id or amount';
                error_log("Stripe checkout.session.completed missing meta: " . json_encode($meta));
                break;
            }

            $stripeRef = (string)($session['id'] ?? $eventId);

            if ($type === 'consultation') {
                $providerId = (int)($meta['provider_id'] ?? 0);
                $referralId = (int)($meta['referral_id'] ?? 0);
                if ($providerId <= 0) {
                    $summary = 'consultation missing provider_id';
                    break;
                }
                // Credit patient wallet first, then run split payment
                $payment->addFunds($userId, $amountSll, 'Stripe top-up for consultation', $stripeRef);
                $result = $payment->processReferralPayment(
                    $referralId > 0 ? $referralId : null,
                    $userId,
                    $providerId,
                    $amountSll
                );
                if (!empty($result['success'])) {
                    notifyUser(
                        $conn,
                        $userId,
                        'Payment successful',
                        'You paid SLL ' . number_format($amountSll, 0) . ' via Stripe. Provider received their share.',
                        'pages/pay.php'
                    );
                    notifyUser(
                        $conn,
                        $providerId,
                        'Payment received',
                        'You received SLL ' . number_format($result['provider_earnings'], 0) .
                        ' (platform fee ' . (int)$result['commission_rate'] . '%).',
                        'wallet.php'
                    );
                    $summary = 'consultation paid user=' . $userId . ' provider=' . $providerId;
                    $handled = true;
                } else {
                    $summary = 'consultation process failed: ' . ($result['error'] ?? 'unknown');
                    error_log($summary);
                }
            } else {
                // Default: wallet top-up
                $ok = $payment->addFunds($userId, $amountSll, 'Stripe wallet deposit', $stripeRef);
                if ($ok) {
                    notifyUser(
                        $conn,
                        $userId,
                        'Wallet funded',
                        'SLL ' . number_format($amountSll, 0) . ' added via Stripe.',
                        'wallet.php'
                    );
                    $summary = 'wallet_topup user=' . $userId . ' amount=' . $amountSll;
                    $handled = true;
                } else {
                    $summary = 'wallet credit failed';
                }
            }
            break;
        }

        // ---- PaymentIntent succeeded (API / Elements flow) ----
        case 'payment_intent.succeeded': {
            $pi = $object;
            $meta = stripeMeta($pi);
            $userId = (int)($meta['user_id'] ?? 0);
            $type = strtolower((string)($meta['type'] ?? 'wallet_topup'));
            $amountSll = (float)($meta['amount_sll'] ?? 0);
            if ($amountSll <= 0 && !empty($pi['amount'])) {
                $amountSll = ((int)$pi['amount']) / 100;
            }
            if ($userId <= 0 || $amountSll <= 0) {
                $summary = 'pi missing meta';
                break;
            }

            $stripeRef = (string)($pi['id'] ?? $eventId);

            // Avoid double-credit if checkout.session.completed already handled same intent
            try {
                $dup = $conn->prepare("SELECT id FROM transactions WHERE reference = ? LIMIT 1");
                $dup->execute([$stripeRef]);
                if ($dup->fetch()) {
                    $summary = 'already credited for ' . $stripeRef;
                    $handled = true;
                    break;
                }
            } catch (Exception $e) {}

            if ($type === 'consultation') {
                $providerId = (int)($meta['provider_id'] ?? 0);
                $referralId = (int)($meta['referral_id'] ?? 0);
                if ($providerId <= 0) {
                    $summary = 'pi consultation missing provider';
                    break;
                }
                $payment->addFunds($userId, $amountSll, 'Stripe top-up for consultation', $stripeRef);
                $result = $payment->processReferralPayment(
                    $referralId > 0 ? $referralId : null,
                    $userId,
                    $providerId,
                    $amountSll
                );
                $handled = !empty($result['success']);
                $summary = $handled
                    ? 'pi consultation ok'
                    : ('pi consultation fail: ' . ($result['error'] ?? ''));
            } else {
                $handled = (bool)$payment->addFunds($userId, $amountSll, 'Stripe wallet deposit', $stripeRef);
                if ($handled) {
                    notifyUser($conn, $userId, 'Wallet funded', 'SLL ' . number_format($amountSll, 0) . ' added via Stripe.');
                }
                $summary = $handled ? 'pi wallet ok' : 'pi wallet fail';
            }
            break;
        }

        // ---- Failed payment ----
        case 'payment_intent.payment_failed': {
            $pi = $object;
            $meta = stripeMeta($pi);
            $userId = (int)($meta['user_id'] ?? 0);
            $msg = $pi['last_payment_error']['message'] ?? 'Payment failed';
            if ($userId > 0) {
                notifyUser($conn, $userId, 'Payment failed', $msg, 'wallet.php');
            }
            $summary = 'payment_failed user=' . $userId;
            $handled = true;
            break;
        }

        // ---- Refund ----
        case 'charge.refunded': {
            $charge = $object;
            $meta = stripeMeta($charge);
            $userId = (int)($meta['user_id'] ?? 0);
            $amountSll = (float)($meta['amount_sll'] ?? 0);
            if ($amountSll <= 0 && !empty($charge['amount_refunded'])) {
                $amountSll = ((int)$charge['amount_refunded']) / 100;
            }
            $stripeRef = 'REFUND-' . (string)($charge['id'] ?? $eventId);

            if ($userId > 0 && $amountSll > 0) {
                // Deduct refunded amount from wallet if funds still available
                $ok = $payment->deductFunds($userId, $amountSll, 'Stripe refund reversal', $stripeRef);
                if (!$ok) {
                    // Record negative intent even if balance insufficient
                    try {
                        $conn->prepare("INSERT INTO transactions (user_id, type, amount, status, description, reference, created_at)
                                        VALUES (?, 'refund', ?, 'pending', 'Stripe refund — balance short', ?, NOW())")
                             ->execute([$userId, $amountSll, $stripeRef]);
                    } catch (Exception $e) {}
                }
                notifyUser($conn, $userId, 'Refund processed', 'SLL ' . number_format($amountSll, 0) . ' refunded via Stripe.');
                $handled = true;
                $summary = 'refund user=' . $userId;
            } else {
                $summary = 'refund missing meta';
            }
            break;
        }

        default:
            // Acknowledge unhandled events so Stripe does not retry forever
            $summary = 'ignored:' . $eventType;
            $handled = true;
            break;
    }

    markEvent($conn, $eventId, $eventType, $summary);

    http_response_code(200);
    echo json_encode([
        'received' => true,
        'handled' => $handled,
        'type' => $eventType,
        'summary' => $summary,
    ]);
} catch (Throwable $e) {
    error_log('stripe-webhook: ' . $e->getMessage());
    // 500 → Stripe will retry
    http_response_code(500);
    echo json_encode(['error' => 'Handler error']);
}
