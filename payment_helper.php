<?php
/**
 * Payment Helper - Care Connect SL
 * Fixed platform commission:
 *   - Doctors: 15%
 *   - Hospitals / clinics: 20%
 */

require_once __DIR__ . '/db.php';

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($value)
    {
        return trim(strip_tags((string)$value));
    }
}

/**
 * Fixed platform commission by provider role.
 */
function platformCommissionRate(string $role): float
{
    $role = strtolower(trim($role));
    if ($role === 'hospital') {
        return 20.0;
    }
    // doctor and any other clinical provider default
    return 15.0;
}

function platformCommissionAmount(float $amount, string $role): array
{
    $rate = platformCommissionRate($role);
    $platformFee = round($amount * ($rate / 100), 2);
    $providerEarnings = round(max(0, $amount - $platformFee), 2);
    return [
        'rate' => $rate,
        'platform_fee' => $platformFee,
        'provider_earnings' => $providerEarnings,
    ];
}

class PaymentSystem {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createWallet($user_id) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Create wallet error: " . $e->getMessage());
            return false;
        }
    }

    public function getBalance($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT balance FROM wallets WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            return $result ? (float)$result['balance'] : 0.00;
        } catch (PDOException $e) {
            error_log("Get balance error: " . $e->getMessage());
            return 0.00;
        }
    }

    public function addFunds($user_id, $amount, $description = 'Deposit', $reference = null) {
        if ($amount <= 0) return false;
        try {
            $this->conn->beginTransaction();
            $current = $this->getBalance($user_id);
            $new_balance = $current + $amount;
            $stmt = $this->conn->prepare("UPDATE wallets SET balance = ? WHERE user_id = ?");
            $stmt->execute([$new_balance, $user_id]);
            $ref = $reference ?? 'TX-' . strtoupper(uniqid());
            $stmt = $this->conn->prepare("
                INSERT INTO transactions
                (user_id, type, amount, balance_after, status, description, reference, created_at)
                VALUES (?, 'deposit', ?, ?, 'completed', ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $amount, $new_balance, $description, $ref]);
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Add funds error: " . $e->getMessage());
            return false;
        }
    }

    public function deductFunds($user_id, $amount, $description = 'Payment', $reference = null) {
        if ($amount <= 0) return false;
        $current = $this->getBalance($user_id);
        if ($current < $amount) return false;
        try {
            $this->conn->beginTransaction();
            $new_balance = $current - $amount;
            $stmt = $this->conn->prepare("UPDATE wallets SET balance = ? WHERE user_id = ?");
            $stmt->execute([$new_balance, $user_id]);
            $ref = $reference ?? 'TX-' . strtoupper(uniqid());
            $stmt = $this->conn->prepare("
                INSERT INTO transactions
                (user_id, type, amount, balance_after, status, description, reference, created_at)
                VALUES (?, 'payment', ?, ?, 'completed', ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $amount, $new_balance, $description, $ref]);
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Deduct funds error: " . $e->getMessage());
            return false;
        }
    }

    public function transfer($from_user, $to_user, $amount, $description = 'Consultation fee') {
        if ($amount <= 0) return ['success' => false, 'error' => 'Invalid amount'];
        if ($from_user == $to_user) return ['success' => false, 'error' => 'Cannot transfer to self'];
        $from_balance = $this->getBalance($from_user);
        if ($from_balance < $amount) {
            return ['success' => false, 'error' => 'Insufficient balance'];
        }
        try {
            $this->conn->beginTransaction();
            $new_from_balance = $from_balance - $amount;
            $stmt = $this->conn->prepare("UPDATE wallets SET balance = ? WHERE user_id = ?");
            $stmt->execute([$new_from_balance, $from_user]);

            $to_balance = $this->getBalance($to_user);
            $new_to_balance = $to_balance + $amount;
            $stmt = $this->conn->prepare("UPDATE wallets SET balance = ? WHERE user_id = ?");
            $stmt->execute([$new_to_balance, $to_user]);

            $ref = 'TX-' . strtoupper(uniqid());
            $stmt = $this->conn->prepare("
                INSERT INTO transactions
                (user_id, type, amount, balance_after, status, description, reference, created_at)
                VALUES (?, 'payment', ?, ?, 'completed', ?, ?, NOW())
            ");
            $stmt->execute([$from_user, $amount, $new_from_balance, $description . ' (sent)', $ref]);
            $stmt = $this->conn->prepare("
                INSERT INTO transactions
                (user_id, type, amount, balance_after, status, description, reference, created_at)
                VALUES (?, 'deposit', ?, ?, 'completed', ?, ?, NOW())
            ");
            $stmt->execute([$to_user, $amount, $new_to_balance, $description . ' (received)', $ref]);
            $this->conn->commit();
            return ['success' => true, 'reference' => $ref];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Transfer error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Transfer failed'];
        }
    }

    /**
     * Process payment for a referral with FIXED platform commission.
     * Doctors: 15% · Hospitals: 20%
     */
    public function processReferralPayment($referral_id, $patient_id, $provider_id, $amount, $provider_role = 'doctor') {
        try {
            $this->conn->beginTransaction();

            // Prefer role from DB if available
            try {
                $rs = $this->conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                $rs->execute([$provider_id]);
                $row = $rs->fetch();
                if ($row && !empty($row['role'])) {
                    $provider_role = $row['role'];
                }
            } catch (Exception $e) {}

            $split = platformCommissionAmount((float)$amount, (string)$provider_role);
            $commission_rate = $split['rate'];
            $platform_fee = $split['platform_fee'];
            $provider_earnings = $split['provider_earnings'];

            $stmt = $this->conn->prepare("
                INSERT INTO referral_payments
                (referral_id, patient_id, provider_id, amount, platform_fee, provider_earnings, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$referral_id, $patient_id, $provider_id, $amount, $platform_fee, $provider_earnings]);
            $payment_id = $this->conn->lastInsertId();

            $this->deductFunds($patient_id, $amount, 'Referral payment - ' . $referral_id);
            $this->addFunds($provider_id, $provider_earnings, 'Referral earnings - ' . $referral_id);

            $stmt = $this->conn->prepare("
                UPDATE referral_payments
                SET status = 'completed', payment_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$payment_id]);

            $this->conn->commit();
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'commission_rate' => $commission_rate,
                'platform_fee' => $platform_fee,
                'provider_earnings' => $provider_earnings,
            ];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Referral payment error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }

    public function getTransactionHistory($user_id, $limit = 20) {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, type, amount, balance_after, status, description, reference, created_at
                FROM transactions
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Transaction history error: " . $e->getMessage());
            return [];
        }
    }

    public function initiateMobileMoney($user_id, $amount, $phone_number, $provider = 'Orange') {
        try {
            $reference = 'MM-' . strtoupper(uniqid());
            $stmt = $this->conn->prepare("
                INSERT INTO mobile_money_requests
                (user_id, amount, phone_number, provider, status, reference, created_at)
                VALUES (?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([$user_id, $amount, $phone_number, $provider, $reference]);
            $request_id = $this->conn->lastInsertId();
            return [
                'success' => true,
                'request_id' => $request_id,
                'reference' => $reference,
                'message' => 'Mobile money request initiated. Please check your phone.'
            ];
        } catch (PDOException $e) {
            error_log("Mobile money error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to initiate payment'];
        }
    }

    public function simulateMobileMoney($request_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT user_id, amount FROM mobile_money_requests WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            if (!$request) return false;
            $this->addFunds($request['user_id'], $request['amount'], 'Mobile money deposit');
            $stmt = $this->conn->prepare("
                UPDATE mobile_money_requests
                SET status = 'completed', completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$request_id]);
            return true;
        } catch (PDOException $e) {
            error_log("Simulate mobile money error: " . $e->getMessage());
            return false;
        }
    }
}
